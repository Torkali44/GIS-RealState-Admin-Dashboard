window.initInspectionMerge = function (form) {
    const trigger = form.querySelector('[data-build-merge]');
    const fileInput = form.querySelector('[data-merged-image-input]');

    if (!trigger || !fileInput) {
        return;
    }

    const cfg = window.INSPECTION_MERGE_CFG || {};
    const targetH = parseInt(form.dataset.mergeMaxHeight || cfg.maxHeight || 900, 10) || 900;
    const jpegQuality = parseFloat(form.dataset.mergeQuality || cfg.jpegQuality || 0.85) || 0.85;

    function loadImage(src) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = reject;
            img.src = src;
        });
    }

    function resetMergeButton() {
        trigger.disabled = false;
        trigger.textContent = 'دمج';
        delete form.dataset.mergeSubmitting;
    }

    function getCsrfToken() {
        const input = form.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }

    trigger.addEventListener('click', async () => {
        const areaRoot = form.closest('[id^="area-"]') || form.closest('section, div');
        const selected = [...areaRoot.querySelectorAll('[data-merge-select]:checked')];
        if (selected.length < 2) {
            if (window.showAlert) window.showAlert('حدد صورتين على الأقل لعملية الدمج.');
            else alert('حدد صورتين على الأقل لعملية الدمج.');
            return;
        }

        if (form.dataset.mergeSubmitting === '1') {
            return;
        }

        try {
            trigger.disabled = true;
            trigger.textContent = 'جارٍ الدمج...';
            form.dataset.mergeSubmitting = '1';

            const loaded = (await Promise.all(selected.map(async (chk) => {
                const src = chk.getAttribute('data-merge-src');
                if (!src) return null;
                try {
                    const img = await loadImage(src);
                    return { img, chk };
                } catch {
                    return null;
                }
            }))).filter(Boolean);

            if (loaded.length < 2) {
                if (window.showAlert) window.showAlert('تعذر تحميل صورتين على الأقل للدمج.');
                else alert('تعذر تحميل صورتين على الأقل للدمج.');
                resetMergeButton();
                return;
            }

            const images = loaded.map((item) => item.img);
            const mergedCheckboxes = loaded.map((item) => item.chk);

            const widths = images.map((im) => Math.round((im.naturalWidth / im.naturalHeight) * targetH));
            const gap = 8;
            const totalW = widths.reduce((a, b) => a + b, 0) + (images.length - 1) * gap;

            const canvas = document.createElement('canvas');
            canvas.width = totalW;
            canvas.height = targetH;
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                resetMergeButton();
                return;
            }

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            let x = 0;
            images.forEach((img, i) => {
                const w = widths[i];
                ctx.drawImage(img, x, 0, w, targetH);
                x += w + gap;
            });

            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', jpegQuality));
            if (!blob) {
                resetMergeButton();
                return;
            }

            const fd = new FormData();
            fd.append('_token', getCsrfToken());
            fd.append('merged_image', new File([blob], `merged-${Date.now()}.jpg`, { type: 'image/jpeg' }));

            const desc = form.querySelector('textarea[name="description"]');
            if (desc && desc.value.trim()) {
                fd.append('description', desc.value.trim());
            }

            mergedCheckboxes.forEach((chk) => {
                const id = chk.getAttribute('data-photo-id');
                if (id) fd.append('source_photo_ids[]', id);
            });

            const res = await fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok || !data.success) {
                const msg = data.message || (data.errors && Object.values(data.errors).flat().join('\n')) || 'تعذر دمج الصور.';
                if (window.showAlert) window.showAlert(msg);
                else alert(msg);
                resetMergeButton();
                return;
            }

            const areaId = data.area_id || (areaRoot.id ? areaRoot.id.replace(/^area-/, '') : null);
            const panelUrl = form.getAttribute('data-photos-panel-url')
                || areaRoot.querySelector('[data-photos-panel-url]')?.getAttribute('data-photos-panel-url');

            if (window.refreshAreaPhotosPanel && areaRoot && panelUrl) {
                const total = parseInt(data.photo_count, 10) || 0;
                await window.refreshAreaPhotosPanel(areaRoot, panelUrl, areaId, total);
            } else {
                window.location.href = window.location.pathname + window.location.search + '#area-' + areaId;
                window.location.reload();
                return;
            }

            if (desc) desc.value = '';
            areaRoot.querySelectorAll('[data-merge-select]:checked').forEach((el) => { el.checked = false; });

            if (window.showAlert) window.showAlert(data.message || 'تم الدمج بنجاح.');
            resetMergeButton();
        } catch (err) {
            console.error('merge failed', err);
            if (window.showAlert) {
                window.showAlert('تعذر دمج الصور. حاول مرة أخرى.');
            } else {
                alert('تعذر دمج الصور. حاول مرة أخرى.');
            }
            resetMergeButton();
        }
    });
};
