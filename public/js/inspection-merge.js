window.initInspectionMerge = function (form) {
    const trigger = form.querySelector('[data-build-merge]');
    const fileInput = form.querySelector('[data-merged-image-input]');

    if (!trigger) {
        return;
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

            const fd = new FormData();
            fd.append('_token', getCsrfToken());

            const desc = form.querySelector('textarea[name="description"]');
            if (desc && desc.value.trim()) {
                fd.append('description', desc.value.trim());
            }

            selected.forEach((chk) => {
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

            let data = {};
            try {
                data = await res.json();
            } catch (_) {
                data = {};
            }

            if (!res.ok || !data.success) {
                const msg = data.message
                    || (data.errors && Object.values(data.errors).flat().join('\n'))
                    || (data.errors && data.errors.merge && data.errors.merge[0])
                    || 'تعذر دمج الصور.';
                if (window.showAlert) window.showAlert(msg);
                else alert(msg);
                resetMergeButton();
                return;
            }

            const areaId = data.area_id || (areaRoot.id ? areaRoot.id.replace(/^area-/, '') : null);
            const panelUrl = form.getAttribute('data-photos-panel-url')
                || areaRoot.querySelector('[data-area-photos]')?.getAttribute('data-panel-url');

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
