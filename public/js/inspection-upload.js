/**
 * معالجة الصور قبل الرفع: تصغير الحجم وتحويل PNG إلى JPEG.
 * الرفع بالتوازي بين الأقسام — كل قسم يرفع مستقلاً بدون reload.
 */
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

            // ── 1. معالجة الصور (تصغير + تحويل) ──
            const maxDim = 1600;
            const concurrency = 8;
            let completed = 0;
            const batchId = (window.crypto && window.crypto.randomUUID)
                ? window.crypto.randomUUID()
                : String(Date.now()) + '-' + Math.random().toString(16).slice(2);
            const processedItems = new Array(files.length);

            for (let i = 0; i < files.length; i += concurrency) {
                const chunk = files.slice(i, i + concurrency);
                await Promise.all(chunk.map(async (file, offset) => {
                    const idx = i + offset;
                    try {
                        processedItems[idx] = {
                            file: await processImage(file, maxDim),
                            key: buildFileKey(file, idx),
                        };
                    } catch {
                        processedItems[idx] = { file, key: buildFileKey(file, idx) };
                    }
                    completed++;
                    statusText.innerText = `تمت معالجة ${completed} من ${files.length} صورة...`;
                }));
            }

            // ── 2. رفع الصور بالدفعات ──
            const ready = processedItems.filter(Boolean);
            const uploadChunkSize = 20;
            const uploadConcurrency = 4;
            const maxRetries = 3;
            let uploaded = 0, skipped = 0, failed = 0;

            statusText.innerText = 'بدء رفع الصور...';
            submitBtn.innerHTML = '<span class="inline-block animate-spin mr-2">⏳</span> جاري الرفع...';

            const chunks = [];
            for (let i = 0; i < ready.length; i += uploadChunkSize) {
                chunks.push(ready.slice(i, i + uploadChunkSize));
            }

            async function uploadChunk(chunk, attempt = 1) {
                const fd = new FormData();
                fd.append('_token', form.querySelector('input[name="_token"]').value);
                fd.append('upload_batch_id', batchId);
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
                        await new Promise(r => setTimeout(r, attempt * 800));
                        return uploadChunk(chunk, attempt + 1);
                    }
                    throw new Error('Upload failed');
                }
                const payload = await res.json().catch(() => null);
                if (!payload || payload.success !== true) {
                    if (attempt < maxRetries) {
                        await new Promise(r => setTimeout(r, attempt * 800));
                        return uploadChunk(chunk, attempt + 1);
                    }
                    throw new Error('Bad payload');
                }
                return { created: Number(payload.count || 0), skipped: Number(payload.skipped || 0) };
            }

            let qIdx = 0;
            async function worker() {
                while (qIdx < chunks.length) {
                    const ci = qIdx++;
                    try {
                        const r = await uploadChunk(chunks[ci]);
                        uploaded += r.created;
                        skipped += r.skipped;
                    } catch {
                        failed += chunks[ci].length;
                    }
                    statusText.innerText = `تم حفظ ${uploaded} من ${ready.length} صورة...`;
                }
            }
            await Promise.all(Array.from({ length: Math.min(uploadConcurrency, chunks.length) }, worker));

            // ── 3. تحديث الصور بدون reload ──
            if (failed > 0) {
                statusText.className = 'text-xs text-amber-500 mt-2';
                statusText.innerText = `تم حفظ ${uploaded}، تجاهل ${skipped}، فشل ${failed} صورة.`;
            } else {
                statusText.className = 'text-xs text-emerald-400 mt-2';
                statusText.innerText = `✓ تم حفظ ${uploaded} صورة${skipped ? `، تجاهل ${skipped} مكرر` : ''}.`;
            }

            submitBtn.innerHTML = '✓ تم الرفع';
            submitBtn.className = submitBtn.className.replace('bg-slate-800', 'bg-emerald-700');

            // تحديث منطقة الصور لهذا القسم فقط — بدون reload
            await refreshAreaPhotos(form);
            bustReportLinks();

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
     * تحديث منطقة الصور لهذا القسم بدون reload للصفحة كلها
     */
    async function refreshAreaPhotos(form) {
        try {
            // جلب الصفحة الحالية بـ fetch
            const res = await fetch(window.location.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
            });
            if (!res.ok) {
                // fallback: reload بعد تأخير
                setTimeout(() => window.location.reload(), 1500);
                return;
            }
            const html = await res.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // إيجاد الـ area wrapper الخاص بهذا الفورم
            const areaWrapper = form.closest('[id^="area-"]');
            if (!areaWrapper) {
                setTimeout(() => window.location.reload(), 1000);
                return;
            }

            const areaId = areaWrapper.id; // e.g. "area-5"
            const newAreaWrapper = doc.getElementById(areaId);

            if (!newAreaWrapper) {
                setTimeout(() => window.location.reload(), 1000);
                return;
            }

            // استبدال محتوى منطقة الصور فقط (الـ photo grid)
            const photoGrid = areaWrapper.querySelector('[data-photo-grid]');
            const newPhotoGrid = newAreaWrapper.querySelector('[data-photo-grid]');

            if (photoGrid && newPhotoGrid) {
                photoGrid.innerHTML = newPhotoGrid.innerHTML;
                // إعادة تشغيل Alpine.js إن وجد
                if (window.Alpine) window.Alpine.initTree(photoGrid);
            } else {
                // استبدال الـ card كاملة
                const card = areaWrapper.querySelector('.rounded-2xl');
                const newCard = newAreaWrapper.querySelector('.rounded-2xl');
                if (card && newCard) {
                    card.innerHTML = newCard.innerHTML;
                    // إعادة تهيئة upload forms الجديدة
                    card.querySelectorAll('[data-upload-form]').forEach(f => {
                        if (window.initInspectionUpload) window.initInspectionUpload(f);
                    });
                    card.querySelectorAll('[data-merge-form]').forEach(f => {
                        if (window.initInspectionMerge) window.initInspectionMerge(f);
                    });
                } else {
                    setTimeout(() => window.location.reload(), 1000);
                }
            }

            syncReportLinksFromDocument(doc);
        } catch (err) {
            console.error('Refresh failed:', err);
            setTimeout(() => window.location.reload(), 1000);
        }
    }

    async function processImage(file, maxDim) {
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
                const isJpeg = file.type === 'image/jpeg' || file.type === 'image/jpg';
                if (isJpeg && w === img.width && h === img.height) return resolve(file);
                const canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);
                canvas.toBlob(blob => {
                    resolve(new File([blob], file.name.replace(/\.[^/.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                }, 'image/jpeg', 0.85);
            };
            img.onerror = reject;
        });
    }

    function buildFileKey(file, index) {
        return [index, file.name, file.size, file.lastModified || 0].join('|');
    }

    /** تحديث روابط التقرير بعد رفع صور (بدون reload كامل) */
    function bustReportLinks() {
        document.querySelectorAll('a[href*="report"]').forEach((anchor) => {
            try {
                const url = new URL(anchor.href, window.location.origin);
                url.searchParams.set('v', String(Date.now()));
                url.searchParams.delete('refresh');
                anchor.href = url.toString();
            } catch (_) { /* ignore */ }
        });
    }

    function syncReportLinksFromDocument(doc) {
        const current = document.querySelectorAll('a[href*="report.pdf"], a[href*="inline"]');
        const fresh = doc.querySelectorAll('a[href*="report.pdf"], a[href*="inline"]');
        fresh.forEach((freshLink, i) => {
            if (current[i]) {
                current[i].href = freshLink.href;
            }
        });
    }
};