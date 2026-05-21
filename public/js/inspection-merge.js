window.initInspectionMerge = function (form) {
    const trigger = form.querySelector('[data-build-merge]');
    const fileInput = form.querySelector('[data-merged-image-input]');
    const previewWrap = form.querySelector('[data-merge-preview-wrap]');
    const previewImg = form.querySelector('[data-merge-preview]');

    if (!trigger || !fileInput) {
        return;
    }

    async function loadImage(src) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = reject;
            img.src = src;
        });
    }

    trigger.addEventListener('click', async () => {
        const box = form.closest('section, div');
        const selected = [...box.querySelectorAll('[data-merge-select]:checked')];
        if (selected.length < 2) {
            if (window.showAlert) window.showAlert('حدد صورتين على الأقل لعملية الدمج.'); else alert('حدد صورتين على الأقل لعملية الدمج.');
            return;
        }

        try {
            trigger.disabled = true;
            trigger.textContent = 'جارٍ الدمج...';

            const images = [];
            for (const chk of selected) {
                const src = chk.getAttribute('data-merge-src');
                if (!src) continue;
                const img = await loadImage(src);
                images.push(img);
            }

            if (images.length < 2) {
                if (window.showAlert) window.showAlert('تعذر تحميل الصور المحددة.'); else alert('تعذر تحميل الصور المحددة.');
                return;
            }

            const targetH = 1200;
            const widths = images.map((im) => Math.round((im.naturalWidth / im.naturalHeight) * targetH));
            const gap = 10;
            const totalW = widths.reduce((a, b) => a + b, 0) + (images.length - 1) * gap;

            const canvas = document.createElement('canvas');
            canvas.width = totalW;
            canvas.height = targetH;
            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            let x = 0;
            images.forEach((img, i) => {
                const w = widths[i];
                ctx.drawImage(img, x, 0, w, targetH);
                x += w + gap;
            });

            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));
            if (!blob) return;

            const dt = new DataTransfer();
            dt.items.add(new File([blob], `merged-${Date.now()}.jpg`, { type: 'image/jpeg' }));
            fileInput.files = dt.files;

            if (previewWrap && previewImg) {
                previewImg.src = canvas.toDataURL('image/jpeg', 0.8);
                previewWrap.classList.remove('hidden');
            }

            form.submit();
        } finally {
            trigger.disabled = false;
            trigger.textContent = 'دمج الصور المحددة';
        }
    });
};
