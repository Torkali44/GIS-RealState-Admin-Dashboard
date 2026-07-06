/**
 * محرك رفع الصور على دفعات — مشترك بين الرفع اليدوي واستيراد المجلد.
 */
(function () {
    function inspectionUploadConfig() {
        const c = window.INSPECTION_UPLOAD_CONFIG || {};
        return {
            maxDim: Number(c.maxDim) || 1400,
            jpegQuality: Number(c.jpegQuality) || 0.8,
            skipBelowBytes: Number(c.skipBelowBytes) || 400000,
            chunkSize: Number(c.chunkSize) || 20,
            parallelRequests: Number(c.parallelRequests) || 2,
            processConcurrency: Number(c.processConcurrency) || 10,
            maxRetries: Number(c.maxRetries) || 4,
            bulkThreshold: Number(c.bulkThreshold) || 40,
            bulkChunkSize: Number(c.bulkChunkSize) || 30,
            bulkParallelRequests: Number(c.bulkParallelRequests) || 4,
            bulkSkipClientProcess: c.bulkSkipClientProcess !== false,
            maxFileBytes: Number(c.maxFileBytes) || 20 * 1024 * 1024,
        };
    }

    /** مفتاح ثابت للاستئناف — لا يعتمد على ترتيب المصفوفة */
    function buildFileKey(file) {
        const rel = file.webkitRelativePath || file.name || '';
        return [rel, file.size, file.lastModified || 0].join('|');
    }

    function isValidClientImage(file, cfg) {
        if (!file) return false;
        if (file.size <= 0 || file.size > cfg.maxFileBytes) return false;
        const type = (file.type || '').toLowerCase();
        if (type.startsWith('image/')) return true;
        return /\.(jpe?g|png|webp|gif|heic|heif|bmp)$/i.test(file.name || '');
    }

    async function waitForOnline(onStatus) {
        if (typeof navigator === 'undefined' || navigator.onLine !== false) return;
        if (typeof onStatus === 'function') onStatus('offline');
        await new Promise((resolve) => {
            const handler = () => {
                window.removeEventListener('online', handler);
                if (typeof onStatus === 'function') onStatus('online');
                resolve();
            };
            window.addEventListener('online', handler);
        });
    }

    async function processImage(file, maxDim, cfg) {
        const quality = cfg?.jpegQuality ?? 0.8;
        const skipBelow = cfg?.skipBelowBytes ?? 400000;
        const isJpeg = file.type === 'image/jpeg' || file.type === 'image/jpg';
        if (isJpeg && file.size > 0 && file.size <= skipBelow) {
            return file;
        }

        return new Promise((resolve, reject) => {
            if (!file.type.startsWith('image/')) return resolve(file);
            const img = new Image();
            img.src = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(img.src);
                let w = img.width;
                let h = img.height;
                if (w > maxDim || h > maxDim) {
                    if (w > h) {
                        h = Math.round(h * maxDim / w);
                        w = maxDim;
                    } else {
                        w = Math.round(w * maxDim / h);
                        h = maxDim;
                    }
                }
                if (isJpeg && w === img.width && h === img.height && file.size <= skipBelow * 2) {
                    return resolve(file);
                }
                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);
                canvas.toBlob((blob) => {
                    if (!blob) return reject(new Error('toBlob failed'));
                    resolve(new File([blob], file.name.replace(/\.[^/.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                }, 'image/jpeg', quality);
            };
            img.onerror = reject;
        });
    }

    function classifyFetchError(res, err) {
        if (err && err.name === 'TypeError') return 'network';
        if (!res) return 'network';
        if (res.status >= 500) return 'server';
        if (res.status === 419) return 'session';
        if (res.status === 413) return 'too_large';
        if (res.status >= 400) return 'client';
        return 'unknown';
    }

    /**
     * @param {object} opts
     * @param {string} opts.uploadUrl
     * @param {string} opts.csrfToken
     * @param {File[]} opts.files
     * @param {string} [opts.batchId]
     * @param {boolean} [opts.finalizeOnComplete]
     * @param {boolean} [opts.preserveOrder]
     * @param {Set<string>|string[]} [opts.skipKeys]
     * @param {function} [opts.onProgress]
     * @param {function} [opts.onLog]
     * @param {function} [opts.onNetworkStatus]
     */
    window.runChunkedPhotoUpload = async function runChunkedPhotoUpload(opts) {
        const cfg = inspectionUploadConfig();
        const skipKeySet = new Set(Array.isArray(opts.skipKeys) ? opts.skipKeys : []);
        const rawFiles = Array.from(opts.files || []);
        const files = [];
        const failedItems = [];
        const invalidItems = [];

        rawFiles.forEach((file, sortIndex) => {
            const key = buildFileKey(file);
            if (skipKeySet.has(key)) return;
            if (!isValidClientImage(file, cfg)) {
                invalidItems.push({ file, key, sortIndex, reason: 'صيغة أو حجم غير مدعوم' });
                return;
            }
            files.push({ file, key, sortIndex });
        });

        const isBulk = files.length >= cfg.bulkThreshold;
        const skipProcess = isBulk && cfg.bulkSkipClientProcess;
        const uploadChunkSize = isBulk ? cfg.bulkChunkSize : cfg.chunkSize;
        const uploadConcurrency = isBulk ? cfg.bulkParallelRequests : cfg.parallelRequests;
        const maxRetries = cfg.maxRetries;
        const batchId = opts.batchId || ((window.crypto && window.crypto.randomUUID)
            ? window.crypto.randomUUID()
            : String(Date.now()) + '-' + Math.random().toString(16).slice(2));

        let uploaded = 0;
        let skipped = 0;
        let failed = invalidItems.length;
        let processedCount = 0;
        let finalizeSent = false;
        let lastAreaId = null;
        let lastTotalInArea = 0;
        const completedKeys = [];

        function log(msg, tone) {
            if (typeof opts.onLog === 'function') opts.onLog(msg, tone);
        }

        async function uploadChunk(chunk, isLastChunk, attempt = 1) {
            await waitForOnline(opts.onNetworkStatus);

            const fd = new FormData();
            fd.append('_token', opts.csrfToken);
            fd.append('upload_batch_id', batchId);
            if (opts.finalizeOnComplete && isLastChunk && !finalizeSent) {
                fd.append('upload_finalize', '1');
                finalizeSent = true;
            }
            chunk.forEach((item) => {
                fd.append('photos[]', item.file);
                fd.append('upload_file_keys[]', item.key);
                if (opts.preserveOrder) {
                    fd.append('upload_sort_indexes[]', String(item.sortIndex));
                }
            });

            let res;
            try {
                res = await fetch(opts.uploadUrl, {
                    method: 'POST',
                    body: fd,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
            } catch (err) {
                const kind = classifyFetchError(null, err);
                if (attempt < maxRetries) {
                    log(`إعادة محاولة (${attempt}/${maxRetries}) — ${kind === 'network' ? 'انقطاع شبكة' : 'خطأ اتصال'}`, 'warn');
                    await new Promise((r) => setTimeout(r, attempt * 800));
                    return uploadChunk(chunk, isLastChunk, attempt + 1);
                }
                throw Object.assign(new Error('Upload failed'), { kind, chunk });
            }

            if (!res.ok) {
                const kind = classifyFetchError(res);
                if (attempt < maxRetries && (kind === 'network' || kind === 'server')) {
                    log(`إعادة محاولة (${attempt}/${maxRetries}) — خطأ سيرفر ${res.status}`, 'warn');
                    await new Promise((r) => setTimeout(r, attempt * 800));
                    return uploadChunk(chunk, isLastChunk, attempt + 1);
                }
                throw Object.assign(new Error('Upload failed'), { kind, status: res.status, chunk });
            }

            const payload = await res.json().catch(() => null);
            if (!payload || payload.success !== true) {
                const preview = typeof payload?.message === 'string' ? payload.message : '';
                if (attempt < maxRetries) {
                    log(`إعادة محاولة (${attempt}/${maxRetries})${preview ? ': ' + preview : ''}`, 'warn');
                    await new Promise((r) => setTimeout(r, attempt * 800));
                    return uploadChunk(chunk, isLastChunk, attempt + 1);
                }
                throw Object.assign(new Error(payload?.message || 'فشل رفع الدفعة — حدّث الصفحة وحاول مجدداً'), { chunk });
            }

            if (chunk.length > 0 && (Number(payload.count || 0) + Number(payload.skipped || 0)) === 0) {
                log(`تحذير: الدفعة (${chunk.length} ملف) لم تُحفظ — تحقق من السيرفر أو حجم الملفات`, 'warn');
            }

            if (Array.isArray(payload.errors)) {
                payload.errors.forEach((e) => log(`✗ ${e.file}: ${e.reason}`, 'error'));
            }

            return {
                created: Number(payload.count || 0),
                skipped: Number(payload.skipped || 0),
                failed: Number(payload.failed || 0),
                totalInArea: Number(payload.total_in_area || 0),
                areaId: payload.area_id,
            };
        }

        async function uploadChunkSafe(chunk, isLastChunk) {
            try {
                return await uploadChunk(chunk, isLastChunk);
            } catch (err) {
                if (chunk.length <= 1) {
                    const item = chunk[0];
                    failedItems.push({
                        file: item.file.name,
                        key: item.key,
                        reason: err.kind === 'network' ? 'انقطاع الشبكة' : (err.kind === 'server' ? 'خطأ السيرفر' : 'فشل الرفع'),
                    });
                    failed++;
                    return null;
                }
                log(`تقسيم دفعة فاشلة (${chunk.length} ملف) لإعادة المحاولة فردياً...`, 'warn');
                let lastResult = null;
                for (let i = 0; i < chunk.length; i++) {
                    const single = chunk[i];
                    const isLast = isLastChunk && i === chunk.length - 1;
                    const r = await uploadChunkSafe([single], isLast);
                    if (r) lastResult = r;
                }
                return lastResult;
            }
        }

        await new Promise((resolve, reject) => {
            let fileIndex = 0;
            let readyBuffer = [];
            let activeUploads = 0;
            let processingDone = false;
            let uploadError = null;

            function updateStatus() {
                if (typeof opts.onProgress === 'function') {
                    opts.onProgress({
                        phase: processingDone ? 'upload' : (skipProcess ? 'upload' : 'process'),
                        processed: processedCount,
                        total: files.length,
                        saved: uploaded + skipped,
                        uploaded,
                        skipped,
                        failed,
                        invalid: invalidItems.length,
                        memoryMb: estimateMemoryMb(rawFiles),
                    });
                }
            }

            function maybeReject(err) {
                if (!uploadError) {
                    uploadError = err;
                    reject(err);
                }
            }

            async function startUpload(chunk, isLast) {
                activeUploads++;
                try {
                    const r = await uploadChunkSafe(chunk, isLast);
                    if (r) {
                        uploaded += r.created;
                        skipped += r.skipped;
                        failed += r.failed;
                        if (r.areaId) lastAreaId = r.areaId;
                        if (r.totalInArea) lastTotalInArea = r.totalInArea;
                        if (r.failed === 0) {
                            chunk.forEach((item) => completedKeys.push(item.key));
                        }
                    }
                    updateStatus();
                } catch (err) {
                    chunk.forEach((item) => {
                        failedItems.push({ file: item.file.name, key: item.key, reason: 'فشل غير متوقع' });
                    });
                    failed += chunk.length;
                    maybeReject(err);
                } finally {
                    activeUploads--;
                    pump();
                }
            }

            function pump() {
                if (uploadError) return;

                while (activeUploads < uploadConcurrency) {
                    if (readyBuffer.length >= uploadChunkSize) {
                        const chunk = readyBuffer.splice(0, uploadChunkSize);
                        const isLast = processingDone && readyBuffer.length === 0 && fileIndex >= files.length;
                        startUpload(chunk, isLast);
                        continue;
                    }
                    if (processingDone && readyBuffer.length > 0) {
                        const chunk = readyBuffer.splice(0, readyBuffer.length);
                        startUpload(chunk, true);
                        continue;
                    }
                    if (processingDone && readyBuffer.length === 0 && activeUploads === 0) {
                        resolve();
                        return;
                    }
                    break;
                }
            }

            async function processLoop() {
                const concurrency = skipProcess ? 1 : cfg.processConcurrency;
                const workers = Array.from({ length: Math.min(concurrency, Math.max(1, files.length)) }, async () => {
                    while (fileIndex < files.length && !uploadError) {
                        const idx = fileIndex++;
                        const entry = files[idx];
                        try {
                            const file = skipProcess
                                ? entry.file
                                : await processImage(entry.file, cfg.maxDim, cfg);
                            readyBuffer.push({ file, key: entry.key, sortIndex: entry.sortIndex });
                        } catch {
                            readyBuffer.push({ file: entry.file, key: entry.key, sortIndex: entry.sortIndex });
                        }
                        processedCount++;
                        updateStatus();
                        pump();
                    }
                });
                await Promise.all(workers);
                processingDone = true;
                pump();
            }

            if (files.length === 0) {
                invalidItems.forEach((item) => {
                    failedItems.push({ file: item.file.name, key: item.key, reason: item.reason });
                });
                resolve();
                return;
            }

            processLoop().catch(maybeReject);
        });

        return {
            uploaded,
            skipped,
            failed,
            saved: uploaded + skipped,
            areaId: lastAreaId,
            totalInArea: lastTotalInArea,
            batchId,
            completedKeys,
            failedItems: failedItems.concat(invalidItems.map((i) => ({
                file: i.file.name,
                key: i.key,
                reason: i.reason,
            }))),
        };
    };

    function estimateMemoryMb(files) {
        if (!files || !files.length) return 0;
        const bytes = files.reduce((sum, f) => sum + (f.size || 0), 0);
        return Math.round((bytes / (1024 * 1024)) * 10) / 10;
    }
})();
