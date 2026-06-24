/**
 * معالجة الصور قبل الرفع: تصغير الحجم وتحويل PNG إلى JPEG.
 * رفع على دفعات — مناسب لاستضافة Namecheap Shared (بدون Terminal).
 */
function inspectionUploadConfig() {
    const c = window.INSPECTION_UPLOAD_CONFIG || {};
    return {
        maxDim: Number(c.maxDim) || 1400,
        jpegQuality: Number(c.jpegQuality) || 0.8,
        skipBelowBytes: Number(c.skipBelowBytes) || 400000,
        chunkSize: Number(c.chunkSize) || 20,
        parallelRequests: Number(c.parallelRequests) || 2,
        processConcurrency: Number(c.processConcurrency) || 10,
        maxRetries: Number(c.maxRetries) || 3,
        bulkThreshold: Number(c.bulkThreshold) || 40,
        bulkChunkSize: Number(c.bulkChunkSize) || 25,
        bulkParallelRequests: Number(c.bulkParallelRequests) || 3,
        bulkSkipClientProcess: c.bulkSkipClientProcess !== false,
    };
}

window.initInspectionUpload = function(form) {
    if (form.dataset.uploadBound === '1') return;
    form.dataset.uploadBound = '1';

    const input = form.querySelector('input[type="file"]');
    const submitBtn = form.querySelector('button[type="submit"]');
    const statusText = document.createElement('p');
    statusText.className = 'text-xs text-emerald-400 mt-2 hidden';
    form.appendChild(statusText);

    if (!input || !submitBtn) return;

    // Click anywhere on form to open file picker
    form.addEventListener('click', (e) => {
        const target = e.target;
        if (!(target instanceof Element)) return;
        if (target.closest('button, a, input, textarea, select, label')) return;
        input.click();
    });

    // Show file names preview when selected
    input.addEventListener('change', () => {
        const preview = form.querySelector('[data-file-preview]');
        if (!preview) return;
        preview.innerHTML = '';
        Array.from(input.files).slice(0, 5).forEach(f => {
            const span = document.createElement('span');
            span.className = 'text-xs text-slate-400 bg-slate-800 px-2 py-1 rounded';
            span.textContent = f.name;
            preview.appendChild(span);
        });
        if (input.files.length > 5) {
            const more = document.createElement('span');
            more.className = 'text-xs text-slate-500';
            more.textContent = `+${input.files.length - 5} أخرى`;
            preview.appendChild(more);
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (form.dataset.uploading === '1') return;
        form.dataset.uploading = '1';

        try {
            const files = Array.from(input.files);
            if (!files.length) return;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="inline-block animate-spin mr-2">⏳</span> جاري تجهيز الصور...';
            statusText.classList.remove('hidden');
            statusText.className = 'text-xs text-emerald-400 mt-2';

            const cfg = inspectionUploadConfig();
            const isBulk = files.length >= cfg.bulkThreshold;
            const skipProcess = isBulk && cfg.bulkSkipClientProcess;
            const uploadChunkSize = isBulk ? cfg.bulkChunkSize : cfg.chunkSize;
            const uploadConcurrency = isBulk ? cfg.bulkParallelRequests : cfg.parallelRequests;
            const maxRetries = cfg.maxRetries;

            const batchId = (window.crypto && window.crypto.randomUUID)
                ? window.crypto.randomUUID()
                : String(Date.now()) + '-' + Math.random().toString(16).slice(2);

            let uploaded = 0, skipped = 0, failed = 0;
            let lastAreaId = null;
            let lastTotalInArea = 0;
            let processedCount = 0;
            let finalizeSent = false;

            statusText.innerText = skipProcess
                ? `رفع سريع: ${files.length} صورة...`
                : 'بدء تجهيز ورفع الصور...';
            submitBtn.innerHTML = '<span class="inline-block animate-spin mr-2">⏳</span> جاري الرفع...';

            async function uploadChunk(chunk, isLastChunk, attempt = 1) {
                const fd = new FormData();
                fd.append('_token', form.querySelector('input[name="_token"]').value);
                fd.append('upload_batch_id', batchId);
                if (isLastChunk && !finalizeSent) {
                    fd.append('upload_finalize', '1');
                    finalizeSent = true;
                }
                chunk.forEach(item => {
                    fd.append('photos[]', item.file);
                    fd.append('upload_file_keys[]', item.key);
                });
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: fd,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) {
                    if (attempt < maxRetries) {
                        await new Promise(r => setTimeout(r, attempt * 600));
                        return uploadChunk(chunk, isLastChunk, attempt + 1);
                    }
                    throw new Error('Upload failed');
                }
                const payload = await res.json().catch(() => null);
                if (!payload || payload.success !== true) {
                    if (attempt < maxRetries) {
                        await new Promise(r => setTimeout(r, attempt * 600));
                        return uploadChunk(chunk, isLastChunk, attempt + 1);
                    }
                    throw new Error('Bad payload');
                }
                return {
                    created: Number(payload.count || 0),
                    skipped: Number(payload.skipped || 0),
                    failed: Number(payload.failed || 0),
                    totalInArea: Number(payload.total_in_area || 0),
                    areaId: payload.area_id,
                };
            }

            /** Pipeline: معالجة + رفع متوازيان */
            await new Promise((resolve, reject) => {
                let fileIndex = 0;
                let readyBuffer = [];
                let activeUploads = 0;
                let processingDone = false;
                let uploadError = null;

                function updateStatus() {
                    const saved = uploaded + skipped;
                    if (skipProcess) {
                        statusText.innerText = `تم حفظ ${saved} من ${files.length} صورة...`;
                    } else {
                        statusText.innerText = `معالجة ${processedCount}/${files.length} | حفظ ${saved}...`;
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
                        const r = await uploadChunk(chunk, isLast);
                        uploaded += r.created;
                        skipped += r.skipped;
                        failed += r.failed;
                        if (r.areaId) lastAreaId = r.areaId;
                        if (r.totalInArea) lastTotalInArea = r.totalInArea;
                        updateStatus();
                    } catch (err) {
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
                    const workers = Array.from({ length: Math.min(concurrency, files.length) }, async () => {
                        while (fileIndex < files.length && !uploadError) {
                            const idx = fileIndex++;
                            const raw = files[idx];
                            try {
                                const file = skipProcess
                                    ? raw
                                    : await processImage(raw, cfg.maxDim, cfg);
                                readyBuffer.push({ file, key: buildFileKey(raw, idx) });
                            } catch {
                                readyBuffer.push({ file: raw, key: buildFileKey(raw, idx) });
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

                processLoop().catch(maybeReject);
            });
            const savedTotal = uploaded + skipped;
            if (failed > 0) {
                statusText.className = 'text-xs text-amber-500 mt-2';
                statusText.innerText = `تم حفظ ${savedTotal}، فشل ${failed} صورة.`;
            } else {
                statusText.className = 'text-xs text-emerald-400 mt-2';
                statusText.innerText = `✓ تم حفظ ${savedTotal} صورة${skipped ? ` (${uploaded} جديد + ${skipped} مسبق)` : ''}.`;
            }

            submitBtn.innerHTML = '✓ تم الرفع';
            submitBtn.className = submitBtn.className.replace('bg-slate-800', 'bg-emerald-700');

            // ── 3. تحديث منطقة الصور (آخر صفحة لظهور الصور الجديدة) ──
            await refreshAreaPhotos(form, lastAreaId, lastTotalInArea);
            bustReportLinks(true);

            // reset form للرفع التالي
            setTimeout(() => {
                input.value = '';
                const preview = form.querySelector('[data-file-preview]');
                if (preview) preview.innerHTML = '';
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'حفظ الصور ورفعها';
                submitBtn.className = submitBtn.className.replace('bg-emerald-700', 'bg-slate-800');
                statusText.classList.add('hidden');
                form.dataset.uploading = '0';
            }, 2000);

        } catch (err) {
            console.error('Upload failed:', err);
            statusText.className = 'text-xs text-red-500 mt-2';
            statusText.innerText = 'حدث خطأ أثناء الرفع. أعد المحاولة.';
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'حفظ الصور ورفعها';
            form.dataset.uploading = '0';
        }
    });

    /**
     * تحديث منطقة الصور — يُعرّف في inspection-panel.js
     */
    async function refreshAreaPhotos(form, areaId, totalInArea) {
        const areaWrapper = form.closest('[id^="area-"]');
        const panelUrl = form.getAttribute('data-photos-panel-url');
        if (window.refreshAreaPhotosPanel) {
            await window.refreshAreaPhotosPanel(areaWrapper, panelUrl, areaId, totalInArea);
        }
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
                let w = img.width, h = img.height;
                if (w > maxDim || h > maxDim) {
                    if (w > h) { h = Math.round(h * maxDim / w); w = maxDim; }
                    else { w = Math.round(w * maxDim / h); h = maxDim; }
                }
                if (isJpeg && w === img.width && h === img.height && file.size <= skipBelow * 2) {
                    return resolve(file);
                }
                const canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);
                canvas.toBlob(blob => {
                    if (!blob) return reject(new Error('toBlob failed'));
                    resolve(new File([blob], file.name.replace(/\.[^/.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                }, 'image/jpeg', quality);
            };
            img.onerror = reject;
        });
    }

    function buildFileKey(file, index) {
        return [index, file.name, file.size, file.lastModified || 0].join('|');
    }

    function bustReportLinks(forceRefresh) {
        document.querySelectorAll('a[data-pdf-export], a[href*="report"]').forEach((anchor) => {
            try {
                const url = new URL(anchor.href, window.location.origin);
                url.searchParams.set('v', String(Date.now()));
                if (forceRefresh) {
                    url.searchParams.set('refresh', '1');
                } else {
                    url.searchParams.delete('refresh');
                }
                anchor.href = url.toString();
            } catch (_) { /* ignore */ }
        });
    }
};