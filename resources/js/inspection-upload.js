/**
 * معالجة الصور قبل الرفع: تصغير الحجم وتحويل PNG إلى JPEG لضمان عمل PDF بدون مشاكل الشفافية.
 */
window.initInspectionUpload = function(form) {
    const input = form.querySelector('input[type="file"]');
    const submitBtn = form.querySelector('button[type="submit"]');
    const statusText = document.createElement('p');
    statusText.className = 'text-xs text-emerald-400 mt-2 hidden';
    form.appendChild(statusText);

    if (!input || !submitBtn) return;

    form.addEventListener('submit', async (e) => {
        if (form.dataset.processed === '1') return;
        e.preventDefault();

        const files = input.files;
        if (!files.length) return;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="inline-block animate-spin mr-2">⏳</span> جاري معالجة الصور...';
        statusText.classList.remove('hidden');

        const dt = new DataTransfer();
        const maxDim = 1600;

        for (let i = 0; i < files.length; i++) {
            statusText.innerText = `معالجة الصورة ${i + 1} من ${files.length}...`;
            try {
                const processed = await processImage(files[i], maxDim);
                dt.items.add(processed);
            } catch (err) {
                console.error('Error processing image:', err);
                dt.items.add(files[i]); // fallback to original
            }
        }

        input.files = dt.files;
        form.dataset.processed = '1';
        submitBtn.innerHTML = 'جاري الرفع...';
        form.submit();
    });

    async function processImage(file, maxDim) {
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
                        h = Math.round((h * maxDim) / w);
                        w = maxDim;
                    } else {
                        w = Math.round((w * maxDim) / h);
                        h = maxDim;
                    }
                }

                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                
                // Fill white background to avoid black background on PNG transparency
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);

                canvas.toBlob((blob) => {
                    const newName = file.name.replace(/\.[^/.]+$/, "") + ".jpg";
                    const newFile = new File([blob], newName, { type: 'image/jpeg' });
                    resolve(newFile);
                }, 'image/jpeg', 0.85);
            };
            img.onerror = reject;
        });
    }
};
