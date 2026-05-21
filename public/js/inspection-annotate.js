/**
 * تعليم الصور: أسهم بنسب ثابتة (مطابقة للتصدير)، تكبير داخل الإطار، مقابض صغيرة.
 */
const NEW_ARROW_SIZE = 0.35;
const NEW_ARROW_X = 0.28;
const NEW_ARROW_Y = 0.22;
const MIN_ARROW_SIZE = 0.35;
const MAX_ARROW_SIZE = 2.5;
const ZOOM_MIN = 1;
const ZOOM_MAX = 2.2;

function arrowSizeFactor(a) {
    if (a == null || a.size === undefined || a.size === null || a.size === '') {
        return 1;
    }
    const n = Number(a.size);
    return Number.isFinite(n) ? n : 1;
}

function drawCursorArrow(ctx, tipX, tipY, canvasW, canvasH, angleDeg, sizeFactor) {
    const ref = Math.min(canvasW, canvasH);
    const size = Math.max(34, ref * 0.085 * sizeFactor);
    const angle = (angleDeg * Math.PI) / 180;

    ctx.save();
    ctx.translate(tipX, tipY);
    ctx.rotate(angle);

    ctx.shadowColor = 'rgba(0, 0, 0, 0.55)';
    ctx.shadowBlur = 10;
    ctx.shadowOffsetX = 2;
    ctx.shadowOffsetY = 2;

    ctx.fillStyle = '#ef4444';
    ctx.strokeStyle = '#ffffff';
    ctx.lineWidth = Math.max(2.2, ref * 0.0055);
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';

    const headW = size * 0.55;
    const headH = size * 0.55;
    const tailW = size * 0.22;
    const tailL = size * 1.85;

    ctx.beginPath();
    ctx.moveTo(0, 0);
    ctx.lineTo(headW * 0.95, headH * 0.85);
    ctx.lineTo(tailW, headH * 0.55);
    ctx.lineTo(tailW, headH * 0.55 + tailL);
    ctx.lineTo(-tailW, headH * 0.55 + tailL);
    ctx.lineTo(-tailW, headH * 0.55);
    ctx.lineTo(-headW * 0.95, headH * 0.85);
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
    const canvasCol = root.querySelector('[data-annotate-canvas-col]');
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
    const zoomRange = root.querySelector('[data-zoom-range]');
    const zoomValueEl = root.querySelector('[data-zoom-value]');
    const zoomResetBtn = root.querySelector('[data-zoom-reset]');
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
    let handleDragMode = null;
    let moved = false;
    let dragStart = null;

    let baseViewW = 0;
    let baseViewH = 0;
    let zoom = 1;
    let panX = 0;
    let panY = 0;
    let isPanning = false;
    let panDrag = null;

    function clamp(n, lo, hi) {
        return Math.min(hi, Math.max(lo, n));
    }

    function clampPan() {
        if (zoom <= 1) {
            panX = 0;
            panY = 0;
            return;
        }
        const maxX = (baseViewW * (zoom - 1)) / 2;
        const maxY = (baseViewH * (zoom - 1)) / 2;
        panX = clamp(panX, -maxX, maxX);
        panY = clamp(panY, -maxY, maxY);
    }

    function applyZoom() {
        if (!baseViewW || !baseViewH) {
            return;
        }
        zoom = clamp(zoom, ZOOM_MIN, ZOOM_MAX);
        if (zoom <= 1) {
            panX = 0;
            panY = 0;
        } else {
            clampPan();
        }
        wrap.style.width = `${baseViewW}px`;
        wrap.style.height = `${baseViewH}px`;
        imgEl.style.width = `${baseViewW}px`;
        imgEl.style.height = `${baseViewH}px`;
        wrap.style.transform = `translate(${panX}px, ${panY}px) scale(${zoom})`;
        wrap.style.transformOrigin = 'center center';

        viewport.style.width = `${baseViewW}px`;
        viewport.style.height = `${baseViewH}px`;
        const panCursor = zoom > 1 ? (isPanning ? 'grabbing' : 'grab') : '';
        viewport.style.cursor = panCursor;
        imgEl.style.cursor = zoom > 1 ? panCursor : '';

        if (zoomRange) zoomRange.value = String(Math.round(zoom * 100) / 100);
        if (zoomValueEl) zoomValueEl.textContent = `${Math.round(zoom * 100)}%`;
        renderArrows();
    }

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
            arrowInfo.textContent = `عدد الأسهم: ${arrows.length}${selectedArrow >= 0 ? ` — المحدد: ${selectedArrow + 1}` : ''} · دبل كليك للحذف · تكبير ثم اسحب الصورة لتحريكها · عجلة الماوس للتكبير`;
        }
        if (arrowCountInput) arrowCountInput.value = String(arrows.length);
    }

    /** إحداثيات على الصورة (0–1) من إحداثيات الشاشة — يتضمن التكبير والإزاحة تلقائياً */
    function viewportToNorm(clientX, clientY) {
        const r = imgEl.getBoundingClientRect();
        if (r.width <= 0 || r.height <= 0) {
            return { nx: 0, ny: 0 };
        }
        return {
            nx: clamp((clientX - r.left) / r.width, 0, 1),
            ny: clamp((clientY - r.top) / r.height, 0, 1),
        };
    }

    function toViewportPoint(nx, ny) {
        return { x: nx * baseViewW, y: ny * baseViewH };
    }

    function arrowPathD(a) {
        const pt = toViewportPoint(a.x, a.y);
        const tx = (pt.x / baseViewW) * 1000;
        const ty = (pt.y / baseViewH) * 1000;
        const sz = arrowSizeFactor(a);
        const size = 120 * sz;
        const headW = size * 0.55;
        const headH = size * 0.55;
        const tailW = size * 0.22;
        const tailL = size * 1.85;
        const baseY = ty + headH * 0.55;
        return {
            d: [
                `M ${tx} ${ty}`,
                `L ${tx + headW * 0.95} ${ty + headH * 0.85}`,
                `L ${tx + tailW} ${baseY}`,
                `L ${tx + tailW} ${baseY + tailL}`,
                `L ${tx - tailW} ${baseY + tailL}`,
                `L ${tx - tailW} ${baseY}`,
                `L ${tx - headW * 0.95} ${ty + headH * 0.85}`,
                'Z',
            ].join(' '),
            tx,
            ty,
            tailLen: headH * 0.55 + tailL,
        };
    }

    function renderArrows() {
        svgEl.querySelectorAll('[data-dyn-arrow], [data-arrow-handle]').forEach((n) => n.remove());

        if (!arrows.length) {
            svgEl.classList.add('hidden');
            handle.classList.add('hidden');
            syncHidden();
            return;
        }

        arrows.forEach((a, i) => {
            const { d, tx, ty } = arrowPathD(a);
            const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            p.setAttribute('data-dyn-arrow', '1');
            p.setAttribute('d', d);
            p.setAttribute('fill', '#ef4444');
            p.setAttribute('stroke', '#ffffff');
            p.setAttribute('stroke-width', i === selectedArrow ? '4' : '3');
            p.setAttribute('transform', `rotate(${a.angle || -45} ${tx} ${ty})`);
            p.style.cursor = 'pointer';
            p.style.pointerEvents = 'auto';
            p.addEventListener('click', (ev) => {
                ev.stopPropagation();
                selectedArrow = i;
                if (arrowSizeInput) arrowSizeInput.value = String(arrowSizeFactor(a));
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

        if (selectedArrow >= 0 && arrows[selectedArrow]) {
            const a = arrows[selectedArrow];
            const { tx, ty, tailLen } = arrowPathD(a);
            const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            g.setAttribute('data-arrow-handle', '1');
            g.setAttribute('transform', `translate(${tx} ${ty}) rotate(${a.angle || -45})`);
            g.style.pointerEvents = 'none';

            const scaleC = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            scaleC.setAttribute('cx', '0');
            scaleC.setAttribute('cy', String(tailLen * 0.9));
            scaleC.setAttribute('r', '11');
            scaleC.setAttribute('fill', '#22c55e');
            scaleC.setAttribute('stroke', '#fff');
            scaleC.setAttribute('stroke-width', '2.5');
            scaleC.setAttribute('data-handle-kind', 'scale');
            scaleC.style.cursor = 'ns-resize';
            scaleC.style.pointerEvents = 'auto';

            const rotC = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            rotC.setAttribute('cx', '-72');
            rotC.setAttribute('cy', '0');
            rotC.setAttribute('r', '11');
            rotC.setAttribute('fill', '#38bdf8');
            rotC.setAttribute('stroke', '#fff');
            rotC.setAttribute('stroke-width', '2.5');
            rotC.setAttribute('data-handle-kind', 'rotate');
            rotC.style.cursor = 'grab';
            rotC.style.pointerEvents = 'auto';

            g.appendChild(scaleC);
            g.appendChild(rotC);
            svgEl.appendChild(g);
        }

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
        if (!imgEl.naturalWidth || !imgEl.naturalHeight || !baseViewW || !baseViewH) return;

        const outW = imgEl.naturalWidth;
        const outH = imgEl.naturalHeight;

        const canvas = document.createElement('canvas');
        canvas.width = outW;
        canvas.height = outH;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        ctx.drawImage(imgEl, 0, 0, outW, outH);

        arrows.forEach((a) => {
            drawCursorArrow(ctx, a.x * outW, a.y * outH, outW, outH, a.angle || -45, arrowSizeFactor(a));
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
        arrows[selectedArrow].x = clamp(n.nx, 0, 1);
        arrows[selectedArrow].y = clamp(n.ny, 0, 1);
        renderArrows();
        assignCompositeFromCanvas();
    }

    function setupViewport() {
        const colW = canvasCol ? canvasCol.clientWidth : root.clientWidth;
        const maxW = Math.max(220, Math.min(colW - 8, 920));
        const imgRatio = imgEl.naturalWidth / imgEl.naturalHeight;
        const maxH = Math.max(240, Math.min(window.innerHeight * 0.62, 620));

        let vw = maxW;
        let vh = vw / imgRatio;
        if (vh > maxH) {
            vh = maxH;
            vw = vh * imgRatio;
        }

        baseViewW = Math.round(vw);
        baseViewH = Math.round(vh);

        applyZoom();
        assignCompositeFromCanvas();
    }

    function onPointerMove(ev) {
        if (isPanning && panDrag) {
            panX = panDrag.pan0x + (ev.clientX - panDrag.x0);
            panY = panDrag.pan0y + (ev.clientY - panDrag.y0);
            clampPan();
            wrap.style.transform = `translate(${panX}px, ${panY}px) scale(${zoom})`;
            moved = true;
            return;
        }

        if (handleDragMode === 'tip' && draggingArrow) {
            setArrowPositionFromClient(ev.clientX, ev.clientY);
            moved = true;
            return;
        }

        if (!handleDragMode || selectedArrow < 0 || !arrows[selectedArrow] || !dragStart) return;
        const a = arrows[selectedArrow];
        const n = viewportToNorm(ev.clientX, ev.clientY);

        if (handleDragMode === 'scale') {
            const dx = n.nx - a.x;
            const dy = n.ny - a.y;
            const d1 = Math.hypot(dx, dy);
            const ratio = clamp(d1 / dragStart.dist0, 0.35, 2.8);
            a.size = clamp(dragStart.size0 * ratio, MIN_ARROW_SIZE, MAX_ARROW_SIZE);
            if (arrowSizeInput) arrowSizeInput.value = String(Math.round(a.size * 10) / 10);
            renderArrows();
            assignCompositeFromCanvas();
            moved = true;
        }

        if (handleDragMode === 'rotate') {
            const ang = (Math.atan2(n.ny - a.y, n.nx - a.x) * 180) / Math.PI;
            a.angle = ang + dragStart.angleOffset;
            if (arrowAngleInput) arrowAngleInput.value = String(Math.round(a.angle));
            renderArrows();
            assignCompositeFromCanvas();
            moved = true;
        }
    }

    function endPointerDrag(ev) {
        if (isPanning) {
            try {
                if (ev && typeof ev.pointerId === 'number') {
                    viewport.releasePointerCapture(ev.pointerId);
                }
            } catch {
                /* ignore */
            }
            isPanning = false;
            panDrag = null;
            clampPan();
            applyZoom();
        }
        draggingArrow = false;
        handleDragMode = null;
        dragStart = null;
        window.removeEventListener('pointermove', onPointerMove);
        window.removeEventListener('pointerup', endPointerDrag);
        window.removeEventListener('pointercancel', endPointerDrag);
        if (moved) {
            setTimeout(() => {
                moved = false;
            }, 0);
        }
    }

    viewport.addEventListener('pointerdown', (e) => {
        const h = e.target && typeof e.target.closest === 'function' ? e.target.closest('[data-handle-kind]') : null;
        if (h) {
            const kind = h.getAttribute('data-handle-kind');
            if (!kind || selectedArrow < 0 || !arrows[selectedArrow]) return;
            e.preventDefault();
            e.stopPropagation();
            const a = arrows[selectedArrow];
            const n = viewportToNorm(e.clientX, e.clientY);
            const dx = n.nx - a.x;
            const dy = n.ny - a.y;
            const dist0 = Math.hypot(dx, dy) || 0.0001;
            const angMouse = (Math.atan2(n.ny - a.y, n.nx - a.x) * 180) / Math.PI;
            handleDragMode = kind;
            dragStart = {
                size0: arrowSizeFactor(a),
                dist0,
                angleOffset: (a.angle || -45) - angMouse,
            };
            window.addEventListener('pointermove', onPointerMove);
            window.addEventListener('pointerup', endPointerDrag);
            window.addEventListener('pointercancel', endPointerDrag);
            return;
        }

        const panBlocked =
            (e.target &&
                typeof e.target.closest === 'function' &&
                (e.target.closest('[data-dyn-arrow]') ||
                    e.target.closest('[data-drag-handle]') ||
                    e.target.closest('[data-handle-kind]'))) ||
            false;
        if (zoom > 1 && e.button === 0 && !panBlocked && viewport.contains(e.target)) {
            e.preventDefault();
            isPanning = true;
            panDrag = { x0: e.clientX, y0: e.clientY, pan0x: panX, pan0y: panY };
            try {
                viewport.setPointerCapture(e.pointerId);
            } catch {
                /* ignore */
            }
            moved = false;
            applyZoom();
            window.addEventListener('pointermove', onPointerMove);
            window.addEventListener('pointerup', endPointerDrag);
            window.addEventListener('pointercancel', endPointerDrag);
            return;
        }

        moved = false;
        if (e.target === handle || handle.contains(e.target)) {
            draggingArrow = true;
            handleDragMode = 'tip';
            window.addEventListener('pointermove', onPointerMove);
            window.addEventListener('pointerup', endPointerDrag);
            window.addEventListener('pointercancel', endPointerDrag);
        }
    });

    viewport.addEventListener(
        'wheel',
        (e) => {
            if (!viewport.contains(e.target)) return;
            e.preventDefault();
            const step = e.deltaY > 0 ? -0.06 : 0.06;
            zoom = clamp(zoom + step, ZOOM_MIN, ZOOM_MAX);
            clampPan();
            applyZoom();
            assignCompositeFromCanvas();
        },
        { passive: false },
    );

    zoomRange?.addEventListener('input', () => {
        const v = Number(zoomRange.value);
        if (!Number.isFinite(v)) return;
        zoom = clamp(v, ZOOM_MIN, ZOOM_MAX);
        clampPan();
        applyZoom();
        assignCompositeFromCanvas();
    });

    zoomResetBtn?.addEventListener('click', () => {
        zoom = 1;
        panX = 0;
        panY = 0;
        applyZoom();
        assignCompositeFromCanvas();
    });

    addArrowBtn?.addEventListener('click', () => {
        const s = clamp(Number(arrowSizeInput?.value) || NEW_ARROW_SIZE, MIN_ARROW_SIZE, MAX_ARROW_SIZE);
        arrows.push({
            x: NEW_ARROW_X,
            y: NEW_ARROW_Y,
            size: s,
            angle: Number(arrowAngleInput?.value || -45),
        });
        selectedArrow = arrows.length - 1;
        if (clearField) clearField.value = '0';
        if (arrowSizeInput) arrowSizeInput.value = String(s);
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
        const s = clamp(Number(arrowSizeInput?.value) || NEW_ARROW_SIZE, MIN_ARROW_SIZE, MAX_ARROW_SIZE);
        const ang = Number(arrowAngleInput?.value || -45);
        while (arrows.length < target) {
            arrows.push({
                x: clamp(NEW_ARROW_X + (arrows.length % 4) * 0.05, 0.08, 0.92),
                y: clamp(NEW_ARROW_Y + (arrows.length % 3) * 0.05, 0.08, 0.92),
                size: s,
                angle: ang,
            });
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
            arrows[selectedArrow].size = clamp(Number(arrowSizeInput.value) || 1, MIN_ARROW_SIZE, MAX_ARROW_SIZE);
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
            if (arrowSizeInput) arrowSizeInput.value = String(arrowSizeFactor(arrows[selectedArrow]));
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
