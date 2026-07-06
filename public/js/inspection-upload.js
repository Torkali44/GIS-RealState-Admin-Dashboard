/**
 * معالجة الصور قبل الرفع: تصغير الحجم وتحويل PNG إلى JPEG.
 * رفع على دفعات — مناسب لاستضافة Namecheap Shared (بدون Terminal).
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

    form.addEventListener('click', (e) => {
        const target = e.target;
        if (!(target instanceof Element)) return;
        if (target.closest('button, a, input, textarea, select, label')) return;
        input.click();
    });

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

            if (!window.runChunkedPhotoUpload) {
                throw new Error('محرك الرفع غير محمّل');
            }

            const result = await window.runChunkedPhotoUpload({
                uploadUrl: form.action,
                csrfToken: form.querySelector('input[name="_token"]').value,
                files,
                preserveOrder: true,
                finalizeOnComplete: true,
                onProgress: (p) => {
                    statusText.innerText = p.phase === 'process'
                        ? `معالجة ${p.processed}/${p.total} | حفظ ${p.saved}...`
                        : `تم حفظ ${p.saved} من ${p.total} صورة...`;
                },
            });

            const savedTotal = result.saved;
            if (result.failed > 0) {
                statusText.className = 'text-xs text-amber-500 mt-2';
                statusText.innerText = `تم حفظ ${savedTotal}، فشل ${result.failed} صورة.`;
            } else {
                statusText.className = 'text-xs text-emerald-400 mt-2';
                statusText.innerText = `✓ تم حفظ ${savedTotal} صورة${result.skipped ? ` (${result.uploaded} جديد + ${result.skipped} مسبق)` : ''}.`;
            }

            submitBtn.innerHTML = '✓ تم الرفع';
            submitBtn.className = submitBtn.className.replace('bg-slate-800', 'bg-emerald-700');

            const areaWrapper = form.closest('[id^="area-"]');
            const panelUrl = form.getAttribute('data-photos-panel-url');
            if (window.refreshAreaPhotosPanel && areaWrapper && panelUrl) {
                await window.refreshAreaPhotosPanel(areaWrapper, panelUrl, result.areaId, result.totalInArea);
            }

            bustReportLinks(true);

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
