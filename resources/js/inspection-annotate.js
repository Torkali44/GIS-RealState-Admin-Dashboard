/**
 * محرر مبسط: بدون قص + أسهم متعددة مع حفظ الناتج للتقرير.
 */
function drawCursorArrow(ctx, tipX, tipY, canvasW, canvasH, angleDeg, sizeFactor) {
    const ref = Math.min(canvasW, canvasH);
    const size = Math.max(30, ref * 0.08 * sizeFactor);
    const angle = (angleDeg * Math.PI) / 180;

    ctx.save();
    ctx.translate(tipX, tipY);
    ctx.rotate(angle);

    ctx.shadowColor = 'rgba(0, 0, 0, 0.5)';
    ctx.shadowBlur = 10;
    ctx.shadowOffsetX = 2;
    ctx.shadowOffsetY = 2;

    ctx.fillStyle = '#ef4444';
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = Math.max(2, ref * 0.005);
    ctx.lineJoin = 'round';

    ctx.beginPath();
    ctx.moveTo(0, 0);
    ctx.lineTo(0, size);
    ctx.lineTo(size * 0.3, size * 0.75);
    ctx.lineTo(size * 0.6, size * 1.2);
    ctx.lineTo(size * 0.75, size * 1.1);
    ctx.lineTo(size * 0.45, size * 0.65);
    ctx.lineTo(size * 0.9, size * 0.65);
    ctx.closePath();

    ctx.fill();
    ctx.stroke();
    ctx.restore();
}

function dataUrlToBlob(dataUrl) {
    const parts = dataUrl.split(',');
    const mime = parts[0].match(/:(.*?);/)?.[1] ?? 'image/jpeg';
    const binary = atob(parts[1] ?? '');
    const len = binary.length;
    const u8 = new Uint8Array(len);
    for (let i = 0; i < len; i++) u8[i] = binary.charCodeAt(i);
    return new Blob([u8], { type: mime });
}

window.initInspectionAnnotate = function (root) {
    const viewport = root.querySelector('[data-editor-viewport]');
    const wrap = root.querySelector('[data-annotate-wrap]');
    const imgEl = root.querySelector('[data-annotate-image]');
    const svgEl = root.querySelector('[data-arrow-svg]');
    const handle = root.querySelector('[data-drag-handle]');

    const tipXInput = root.querySelector('[data-input-tip-x]');
    const tipYInput = root.querySelector('[data-input-tip-y]');
    const annotationsInput = root.querySelector('[data-input-annotations]');
    const compositeInput = root.querySelector('[data-composite-input]');
    const form = root.querySelector('[data-annotate-form]');
    const clearField = root.querySelector('[data-clear-flag]');

    const arrowSizeInput = root.querySelector('[data-arrow-size]');
    const arrowAngleInput = root.querySelector('[data-arrow-angle]');
    const addArrowBtn = root.querySelector('[data-add-arrow]');
    const removeArrowBtn = root.querySelector('[data-remove-arrow]');
    const arrowInfo = root.querySelector('[data-arrow-info]');
    const arrowCountInput = root.querySelector('[data-arrow-count]');
    const clearBtn = root.querySelector('[data-clear-arrow]');

    if (!viewport || !wrap || !imgEl || !svgEl || !handle || !annotationsInput || !compositeInput || !form) return;

    let arrows = [];
    try {
        const parsed = JSON.parse(annotationsInput.value || '[]');
        if (Array.isArray(parsed)) arrows = parsed;
    } catch {
        arrows = [];
    }
    let selectedArrow = arrows.length ? 0 : -1;

    let draggingArrow = false;
    let moved = false;
    let startX = 0;
    let startY = 0;

    let viewW = 0;
    let viewH = 0;

    function syncHidden() {
        annotationsInput.value = JSON.stringify(arrows);
        if (arrows[0]) {
            tipXInput.value = String(arrows[0].x ?? '');
            tipYInput.value = String(arrows[0].y ?? '');
        } else {
            tipXInput.value = '';
            tipYInput.value = '';
        }
        if (arrowInfo) {
            arrowInfo.textContent = `عدد الأسهم: ${arrows.length}${selectedArrow >= 0 ? ` — السهم المحدد: ${selectedArrow + 1}` : ''} (دبل كليك على السهم لحذفه)`;
        }
        if (arrowCountInput) arrowCountInput.value = String(arrows.length);
    }

    function toViewportPoint(nx, ny) {
        return { x: nx * viewW, y: ny * viewH };
    }

    function viewportToNorm(clientX, clientY) {
        const rect = viewport.getBoundingClientRect();
        const vx = clientX - rect.left;
        const vy = clientY - rect.top;
        return { nx: vx / viewW, ny: vy / viewH };
    }

    function renderArrows() {
        svgEl.querySelectorAll('[data-dyn-arrow]').forEach((n) => n.remove());

        if (!arrows.length) {
            svgEl.classList.add('hidden');
            handle.classList.add('hidden');
            syncHidden();
            return;
        }

        arrows.forEach((a, i) => {
            const pt = toViewportPoint(a.x, a.y);
            const tx = (pt.x / viewW) * 1000;
            const ty = (pt.y / viewH) * 1000;
            const size = 120 * (a.size || 1);

            const d = `M ${tx},${ty} L ${tx},${ty + size} L ${tx + size * 0.3},${ty + size * 0.75} L ${tx + size * 0.6},${ty + size * 1.2} L ${tx + size * 0.75},${ty + size * 1.1} L ${tx + size * 0.45},${ty + size * 0.65} L ${tx + size * 0.9},${ty + size * 0.65} Z`;
            const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            p.setAttribute('data-dyn-arrow', '1');
            p.setAttribute('d', d);
            p.setAttribute('fill', '#ef4444');
            p.setAttribute('stroke', '#ffffff');
            p.setAttribute('stroke-width', i === selectedArrow ? '5' : '3');
            p.setAttribute('transform', `rotate(${a.angle || -45} ${tx} ${ty})`);
            p.style.cursor = 'pointer';
            p.style.pointerEvents = 'auto';
            p.addEventListener('click', (ev) => {
                ev.stopPropagation();
                selectedArrow = i;
                if (arrowSizeInput) arrowSizeInput.value = String(a.size || 1);
                if (arrowAngleInput) arrowAngleInput.value = String(a.angle || -45);
                renderArrows();
            });
            p.addEventListener('dblclick', (ev) => {
                ev.stopPropagation();
                arrows.splice(i, 1);
                selectedArrow = arrows.length ? Math.min(i, arrows.length - 1) : -1;
                renderArrows();
                assignCompositeFromCanvas();
            });
            svgEl.appendChild(p);
        });

        svgEl.classList.remove('hidden');

        if (selectedArrow >= 0 && arrows[selectedArrow]) {
            const s = toViewportPoint(arrows[selectedArrow].x, arrows[selectedArrow].y);
            handle.classList.remove('hidden');
            handle.style.left = `${s.x}px`;
            handle.style.top = `${s.y}px`;
        } else {
            handle.classList.add('hidden');
        }

        syncHidden();
    }

    function assignCompositeFromCanvas() {
        if (!imgEl.naturalWidth || !imgEl.naturalHeight || !viewW || !viewH) return;

        const outW = imgEl.naturalWidth;
        const outH = imgEl.naturalHeight;

        const canvas = document.createElement('canvas');
        canvas.width = outW;
        canvas.height = outH;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        ctx.drawImage(imgEl, 0, 0, outW, outH);

        arrows.forEach((a) => {
            drawCursorArrow(ctx, a.x * outW, a.y * outH, outW, outH, a.angle || -45, a.size || 1);
        });

        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
        const blob = dataUrlToBlob(dataUrl);
        const file = new File([blob], 'annotated.jpg', { type: 'image/jpeg' });
        const dt = new DataTransfer();
        dt.items.add(file);
        compositeInput.files = dt.files;
    }

    function setArrowPositionFromClient(clientX, clientY) {
        if (selectedArrow < 0 || !arrows[selectedArrow]) return;
        const rect = viewport.getBoundingClientRect();
        if (clientX < rect.left || clientX > rect.right || clientY < rect.top || clientY > rect.bottom) return;

        const n = viewportToNorm(clientX, clientY);
        arrows[selectedArrow].x = Math.min(1, Math.max(0, n.nx));
        arrows[selectedArrow].y = Math.min(1, Math.max(0, n.ny));
        renderArrows();
        assignCompositeFromCanvas();
    }

    function setupViewport() {
        const maxW = Math.min(root.clientWidth - 16, 1000);
        const imgRatio = imgEl.naturalWidth / imgEl.naturalHeight;
        const maxH = Math.max(260, window.innerHeight - 300);

        let vw = maxW;
        let vh = vw / imgRatio;
        if (vh > maxH) {
            vh = maxH;
            vw = vh * imgRatio;
        }

        viewW = Math.round(vw);
        viewH = Math.round(vh);

        viewport.style.width = `${viewW}px`;
        viewport.style.height = `${viewH}px`;
        wrap.style.width = `${viewW}px`;
        wrap.style.height = `${viewH}px`;
        imgEl.style.width = `${viewW}px`;
        imgEl.style.height = `${viewH}px`;

        renderArrows();
    }

    function onPointerMove(ev) {
        if (draggingArrow) {
            setArrowPositionFromClient(ev.clientX, ev.clientY);
            moved = true;
        }
    }

    function endDrag() {
        draggingArrow = false;
        window.removeEventListener('mousemove', onPointerMove);
        window.removeEventListener('mouseup', endDrag);
        if (moved) {
            setTimeout(() => { moved = false; }, 0);
        }
    }

    viewport.addEventListener('mousedown', (e) => {
        moved = false;
        if (e.target === handle || handle.contains(e.target)) {
            draggingArrow = true;
            startX = e.clientX;
            startY = e.clientY;
            window.addEventListener('mousemove', onPointerMove);
            window.addEventListener('mouseup', endDrag);
        }
    });

    addArrowBtn?.addEventListener('click', () => {
        arrows.push({ x: 0.5, y: 0.5, size: Number(arrowSizeInput?.value || 1), angle: Number(arrowAngleInput?.value || -45) });
        selectedArrow = arrows.length - 1;
        if (clearField) clearField.value = '0';
        renderArrows();
        assignCompositeFromCanvas();
    });

    removeArrowBtn?.addEventListener('click', () => {
        if (selectedArrow < 0 || !arrows[selectedArrow]) return;
        arrows.splice(selectedArrow, 1);
        selectedArrow = arrows.length ? Math.min(selectedArrow, arrows.length - 1) : -1;
        renderArrows();
        assignCompositeFromCanvas();
    });

    arrowCountInput?.addEventListener('change', () => {
        let target = Number(arrowCountInput.value || arrows.length);
        if (!Number.isFinite(target)) return;
        target = Math.max(0, Math.min(20, Math.round(target)));
        while (arrows.length < target) {
            arrows.push({ x: 0.5, y: 0.5, size: Number(arrowSizeInput?.value || 1), angle: Number(arrowAngleInput?.value || -45) });
        }
        while (arrows.length > target) arrows.pop();
        selectedArrow = arrows.length ? Math.min(selectedArrow < 0 ? 0 : selectedArrow, arrows.length - 1) : -1;
        renderArrows();
        assignCompositeFromCanvas();
    });

    clearBtn?.addEventListener('click', () => {
        arrows = [];
        selectedArrow = -1;
        if (clearField) clearField.value = '1';
        renderArrows();
        assignCompositeFromCanvas();
    });

    arrowSizeInput?.addEventListener('input', () => {
        if (selectedArrow >= 0 && arrows[selectedArrow]) {
            arrows[selectedArrow].size = Number(arrowSizeInput.value || 1);
            renderArrows();
            assignCompositeFromCanvas();
        }
    });

    arrowAngleInput?.addEventListener('input', () => {
        if (selectedArrow >= 0 && arrows[selectedArrow]) {
            arrows[selectedArrow].angle = Number(arrowAngleInput.value || -45);
            renderArrows();
            assignCompositeFromCanvas();
        }
    });

    form.addEventListener('submit', () => {
        if (clearField && clearField.value === '1') arrows = [];
        assignCompositeFromCanvas();
        syncHidden();
    });

    imgEl.addEventListener('load', () => {
        setupViewport();
        if (selectedArrow < 0 && arrows.length) selectedArrow = 0;
        if (selectedArrow >= 0 && arrows[selectedArrow]) {
            if (arrowSizeInput) arrowSizeInput.value = String(arrows[selectedArrow].size || 1);
            if (arrowAngleInput) arrowAngleInput.value = String(arrows[selectedArrow].angle || -45);
        }
        renderArrows();
        assignCompositeFromCanvas();
    });

    if (imgEl.complete && imgEl.naturalWidth) {
        setupViewport();
        renderArrows();
        assignCompositeFromCanvas();
    }

    window.addEventListener('resize', () => {
        setupViewport();
        renderArrows();
        assignCompositeFromCanvas();
    });

    syncHidden();
};
