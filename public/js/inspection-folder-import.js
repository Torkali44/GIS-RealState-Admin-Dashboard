/**
 * استيراد مجلد المنزل — واجهة متقدمة، استئناف، سجل، وتقرير فشل.
 */
(function () {
    const IMAGE_RE = /\.(jpe?g|png|webp|gif|heic|heif|bmp)$/i;
    const STORAGE_PREFIX = 'gis_folder_import_';

    function isImageFile(name, type) {
        if (type && type.startsWith('image/')) return true;
        return IMAGE_RE.test(name || '');
    }

    function storageKey(houseId) {
        return STORAGE_PREFIX + String(houseId || '0');
    }

    function loadState(houseId) {
        try {
            const raw = localStorage.getItem(storageKey(houseId));
            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    }

    function saveState(houseId, state) {
        try {
            localStorage.setItem(storageKey(houseId), JSON.stringify(state));
        } catch (e) {
            console.warn('Import state save failed', e);
        }
    }

    function clearState(houseId) {
        try {
            localStorage.removeItem(storageKey(houseId));
        } catch (_) { /* ignore */ }
    }

    function sanitizeAreaName(name) {
        let n = (name || '').trim();
        if (!n || n.startsWith('.') || n.startsWith('$')) return '';
        n = n.replace(/[\x00-\x1F\x7F]/g, '');
        n = n.replace(/[<>:"/\\|?*]/g, '');
        n = n.replace(/\s+/g, ' ').trim();
        if (!n) return '';
        return n.length > 255 ? n.slice(0, 255) : n;
    }

    function normalizeSections(sections) {
        const out = [];
        sections.forEach((s) => {
            const name = sanitizeAreaName(s.name);
            if (!name) return;
            out.push({ name, folderName: s.name, files: s.files });
        });
        if (!out.length) {
            throw new Error('لم يُعثر على أسماء أقسام صالحة داخل المجلد.');
        }
        return out;
    }

    async function collectImagesFromDirHandle(dirHandle) {
        const files = [];
        for await (const [, handle] of dirHandle.entries()) {
            if (handle.kind !== 'file') continue;
            const file = await handle.getFile();
            if (isImageFile(file.name, file.type)) {
                files.push(file);
            }
        }
        return files;
    }

    async function scanHouseFolderHandle(dirHandle, limits) {
        const sections = [];
        let totalPhotos = 0;

        const entries = [];
        for await (const [name, handle] of dirHandle.entries()) {
            if (handle.kind !== 'directory') continue;
            if (!name || name.startsWith('.') || name.startsWith('$')) continue;
            entries.push({ name, handle });
        }

        // Sort directories alphabetically (numeric collation matching OS order)
        entries.sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }));

        for (const entry of entries) {
            const files = await collectImagesFromDirHandle(entry.handle);
            if (files.length === 0) continue;

            // Sort files inside directory alphabetically (numeric collation matching OS order)
            files.sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }));

            if (files.length > limits.maxPhotosPerArea) {
                throw new Error(`القسم «${entry.name}» يحتوي ${files.length} صورة — الحد الأقصى ${limits.maxPhotosPerArea}.`);
            }

            totalPhotos += files.length;
            if (totalPhotos > limits.maxTotalPhotos) {
                throw new Error(`إجمالي الصور (${totalPhotos}) يتجاوز الحد ${limits.maxTotalPhotos}.`);
            }

            sections.push({ name: sanitizeAreaName(entry.name) || entry.name, files });
        }

        if (sections.length === 0) {
            throw new Error('لم يُعثر على فولدرات أقسام تحتوي صوراً داخل مجلد المنزل.');
        }
        if (sections.length > limits.maxAreas) {
            throw new Error(`عدد الأقسام (${sections.length}) يتجاوز الحد ${limits.maxAreas}.`);
        }

        return { sections: normalizeSections(sections), totalPhotos };
    }

    function parseWebkitDirectoryFiles(fileList) {
        const map = new Map();

        Array.from(fileList).forEach((file) => {
            const rel = file.webkitRelativePath || file.name;
            const parts = rel.split('/').filter(Boolean);
            if (parts.length < 2) return;

            const areaName = parts.length === 2 ? parts[0] : parts[1];
            if (!areaName || areaName.startsWith('.')) return;
            if (!isImageFile(file.name, file.type)) return;

            if (!map.has(areaName)) {
                map.set(areaName, []);
            }
            map.get(areaName).push(file);
        });

        // Sort section names alphabetically
        const sortedSectionNames = Array.from(map.keys()).sort((a, b) =>
            a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' })
        );

        return sortedSectionNames.map((name) => {
            const files = map.get(name) || [];
            // Sort files inside section alphabetically
            files.sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }));
            return { name: sanitizeAreaName(name) || name, files };
        }).filter((s) => s.files.length > 0);
    }

    function mergeCompletedKeys(local, server) {
        const merged = { ...(local || {}) };
        Object.entries(server || {}).forEach(([areaId, keys]) => {
            const prev = merged[areaId] || [];
            merged[areaId] = [...new Set([...prev, ...keys])];
        });
        return merged;
    }

    function sectionsMatchResume(resume, sections) {
        if (!resume?.sectionNames?.length || !sections?.length) return false;
        if (resume.sectionNames.length !== sections.length) return false;
        return resume.sectionNames.every((n, idx) => n === sections[idx]?.name);
    }

    function createImportUI(dialog) {
        const els = {
            title: dialog.querySelector('[data-folder-import-dialog-title]'),
            body: dialog.querySelector('[data-folder-import-dialog-body]'),
            badge: dialog.querySelector('[data-import-status-badge]'),
            bar: dialog.querySelector('[data-folder-import-dialog-bar]'),
            statSaved: dialog.querySelector('[data-import-stat-saved]'),
            statSkipped: dialog.querySelector('[data-import-stat-skipped]'),
            statFailed: dialog.querySelector('[data-import-stat-failed]'),
            statSections: dialog.querySelector('[data-import-stat-sections]'),
            network: dialog.querySelector('[data-import-network]'),
            log: dialog.querySelector('[data-import-log]'),
            failedWrap: dialog.querySelector('[data-import-failed-wrap]'),
            failedCount: dialog.querySelector('[data-import-failed-count]'),
            failedList: dialog.querySelector('[data-import-failed-list]'),
            confirmPanel: dialog.querySelector('[data-import-confirm]'),
            confirmList: dialog.querySelector('[data-import-confirm-list]'),
            pausedPanel: dialog.querySelector('[data-import-paused]'),
            pausedSections: dialog.querySelector('[data-import-paused-sections]'),
            noticePanel: dialog.querySelector('[data-import-notice]'),
            noticeTitle: dialog.querySelector('[data-import-notice-title]'),
            noticeBody: dialog.querySelector('[data-import-notice-body]'),
            progressPanel: dialog.querySelector('[data-import-progress]'),
            btnConfirm: dialog.querySelector('[data-import-btn-confirm]'),
            btnCancel: dialog.querySelector('[data-import-btn-cancel]'),
            btnPause: dialog.querySelector('[data-import-btn-pause]'),
            btnResume: dialog.querySelector('[data-import-btn-resume]'),
            btnClose: dialog.querySelector('[data-import-btn-close]'),
        };

        const logs = [];
        const failedReport = [];

        function setBadge(mode) {
            if (!els.badge) return;
            const map = {
                ready: ['جاهز', 'bg-slate-800 text-slate-400'],
                confirm: ['تأكيد', 'bg-sky-500/20 text-sky-300'],
                running: ['جاري الرفع', 'bg-emerald-500/20 text-emerald-300'],
                paused: ['متوقف', 'bg-amber-500/20 text-amber-300'],
                done: ['مكتمل', 'bg-emerald-500/20 text-emerald-300'],
                error: ['خطأ', 'bg-red-500/20 text-red-300'],
            };
            const [text, cls] = map[mode] || map.ready;
            els.badge.textContent = text;
            els.badge.className = 'rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ' + cls;
        }

        function hidePanels() {
            els.confirmPanel?.classList.add('hidden');
            els.pausedPanel?.classList.add('hidden');
            els.noticePanel?.classList.add('hidden');
            els.progressPanel?.classList.remove('hidden');
        }

        function resetButtons() {
            els.btnConfirm?.classList.add('hidden');
            els.btnPause?.classList.add('hidden');
            els.btnResume?.classList.add('hidden');
            els.btnClose?.classList.add('hidden');
            els.btnCancel?.classList.remove('hidden');
            if (els.btnCancel) els.btnCancel.textContent = 'إلغاء';
        }

        function reset() {
            logs.length = 0;
            failedReport.length = 0;
            if (els.log) els.log.innerHTML = '';
            if (els.failedWrap) els.failedWrap.classList.add('hidden');
            if (els.failedList) els.failedList.innerHTML = '';
            hidePanels();
            resetButtons();
            if (els.network) els.network.classList.add('hidden');
            setBadge('ready');
            updateStats({ saved: 0, skipped: 0, failed: 0, sections: 0, current: 0 });
        }

        function open() {
            if (!dialog.open) dialog.showModal();
        }

        function close() {
            if (dialog.open) dialog.close();
        }

        function setProgress(pct, title, body) {
            if (els.title) els.title.textContent = title || '';
            if (els.body) els.body.textContent = body || '';
            if (pct != null && els.bar) els.bar.style.width = `${Math.min(100, Math.max(0, pct || 0))}%`;
        }

        function updateStats(s) {
            if (els.statSaved) els.statSaved.textContent = String(s.saved ?? 0);
            if (els.statSkipped) els.statSkipped.textContent = String(s.skipped ?? 0);
            if (els.statFailed) els.statFailed.textContent = String(s.failed ?? 0);
            if (els.statSections) els.statSections.textContent = `${s.current ?? 0}/${s.sections ?? 0}`;
        }

        function addLog(line, tone) {
            const ts = new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            logs.push({ line, tone, ts });
            if (!els.log) return;
            const row = document.createElement('div');
            row.className = tone === 'error' ? 'text-red-400' : tone === 'warn' ? 'text-amber-400' : tone === 'ok' ? 'text-emerald-400' : 'text-slate-500';
            row.textContent = `[${ts}] ${line}`;
            els.log.appendChild(row);
            els.log.scrollTop = els.log.scrollHeight;
        }

        function setNetworkStatus(status) {
            if (!els.network) return;
            if (status === 'offline') {
                els.network.textContent = '⚠ انقطع الاتصال — في انتظار عودة الشبكة...';
                els.network.className = 'text-xs mt-3 rounded-lg bg-amber-500/10 px-3 py-2 text-amber-300';
                els.network.classList.remove('hidden');
                addLog('انقطاع الشبكة — إيقاف مؤقت', 'warn');
            } else if (status === 'online') {
                els.network.textContent = '✓ عاد الاتصال — متابعة الرفع...';
                els.network.className = 'text-xs mt-3 rounded-lg bg-emerald-500/10 px-3 py-2 text-emerald-300';
                addLog('عودة الشبكة — استئناف الرفع', 'ok');
                setTimeout(() => els.network.classList.add('hidden'), 2500);
            }
        }

        function addFailed(area, file, reason) {
            failedReport.push({ area, file, reason });
            if (!els.failedWrap || !els.failedList) return;
            els.failedWrap.classList.remove('hidden');
            if (els.failedCount) els.failedCount.textContent = String(failedReport.length);
            const li = document.createElement('li');
            li.className = 'text-red-300';
            li.textContent = `${area}: ${file} — ${reason}`;
            els.failedList.appendChild(li);
        }

        function setRunningMode() {
            hidePanels();
            resetButtons();
            els.btnPause?.classList.remove('hidden');
            els.btnCancel?.classList.add('hidden');
            setBadge('running');
        }

        function showConfirm(sections, totalPhotos, onConfirm) {
            reset();
            els.confirmPanel?.classList.remove('hidden');
            els.progressPanel?.classList.add('hidden');
            if (els.confirmList) {
                els.confirmList.innerHTML = sections.map((s) => (
                    `<li class="text-slate-300">• ${s.name} <span class="text-slate-500">(${s.files.length} صورة)</span></li>`
                )).join('');
            }
            setProgress(0, 'تأكيد الاستيراد', `سيتم إنشاء ${sections.length} قسم ورفع ${totalPhotos} صورة.`);
            els.btnConfirm?.classList.remove('hidden');
            setBadge('confirm');
            const onConfirmClick = () => {
                els.btnConfirm?.removeEventListener('click', onConfirmClick);
                setRunningMode();
                onConfirm();
            };
            els.btnConfirm?.addEventListener('click', onConfirmClick);
            open();
        }

        function showPaused(savedCount, sectionNames) {
            hidePanels();
            els.pausedPanel?.classList.remove('hidden');
            els.progressPanel?.classList.remove('hidden');
            resetButtons();
            els.btnResume?.classList.remove('hidden');
            els.btnClose?.classList.remove('hidden');
            setBadge('paused');
            setProgress(
                null,
                'إيقاف مؤقت',
                `${savedCount} صورة محفوظة. اضغط «استئناف» واختر نفس مجلد المنزل من جهازك.`
            );
            if (els.pausedSections && sectionNames?.length) {
                els.pausedSections.textContent = 'الأقسام: ' + sectionNames.join(' · ');
            }
            addLog('إيقاف مؤقت — جاهز للاستئناف', 'warn');
            open();
        }

        function showComplete(msg, hasFailed) {
            hidePanels();
            els.noticePanel?.classList.remove('hidden');
            els.progressPanel?.classList.add('hidden');
            if (els.noticePanel) {
                els.noticePanel.className = 'rounded-xl border p-4 text-center ' + (
                    hasFailed ? 'border-amber-500/30 bg-amber-500/5' : 'border-emerald-500/30 bg-emerald-500/5'
                );
            }
            if (els.noticeTitle) {
                els.noticeTitle.textContent = hasFailed ? 'اكتمل مع ملاحظات' : 'تم بنجاح';
                els.noticeTitle.className = 'text-base font-bold ' + (hasFailed ? 'text-amber-300' : 'text-emerald-300');
            }
            if (els.noticeBody) els.noticeBody.textContent = msg;
            resetButtons();
            els.btnClose?.classList.remove('hidden');
            setBadge(hasFailed ? 'error' : 'done');
            setProgress(100, hasFailed ? 'اكتمل مع ملاحظات' : 'اكتمل بنجاح', msg);
            addLog(msg, hasFailed ? 'warn' : 'ok');
            open();
        }

        function showNotice(title, body, tone) {
            hidePanels();
            els.noticePanel?.classList.remove('hidden');
            els.progressPanel?.classList.add('hidden');
            const border = tone === 'error' ? 'border-red-500/30 bg-red-500/5' : tone === 'warn' ? 'border-amber-500/30 bg-amber-500/5' : 'border-sky-500/30 bg-sky-500/5';
            if (els.noticePanel) els.noticePanel.className = 'rounded-xl border p-4 text-center ' + border;
            if (els.noticeTitle) {
                els.noticeTitle.textContent = title;
                els.noticeTitle.className = 'text-base font-bold ' + (tone === 'error' ? 'text-red-300' : tone === 'warn' ? 'text-amber-300' : 'text-sky-300');
            }
            if (els.noticeBody) els.noticeBody.textContent = body;
            resetButtons();
            els.btnClose?.classList.remove('hidden');
            setBadge(tone === 'error' ? 'error' : 'ready');
            open();
        }

        return {
            els, reset, open, close, setProgress, updateStats, addLog, setNetworkStatus,
            addFailed, showConfirm, showComplete, showPaused, showNotice, setRunningMode,
            getFailedReport: () => failedReport.slice(),
            getLogs: () => logs.slice(),
        };
    }

    window.initHouseFolderImport = function (root) {
        if (!root || root.dataset.folderImportBound === '1') return;
        root.dataset.folderImportBound = '1';

        const cfg = window.INSPECTION_FOLDER_IMPORT || {};
        const houseId = Number(cfg.houseId) || 0;
        const limits = {
            maxAreas: Number(cfg.maxAreas) || 30,
            maxPhotosPerArea: Number(cfg.maxPhotosPerArea) || 500,
            maxTotalPhotos: Number(cfg.maxTotalPhotos) || 5000,
        };

        const pickBtn = root.querySelector('[data-folder-import-pick]');
        const resumeBtn = root.querySelector('[data-folder-import-resume]');
        const fallbackInput = root.querySelector('[data-folder-import-fallback]');
        const statusEl = root.querySelector('[data-folder-import-status]');
        const dialog = document.getElementById('folder-import-dialog');
        const ui = dialog ? createImportUI(dialog) : null;

        let importing = false;
        let cancelRequested = false;

        function releaseImportLock() {
            importing = false;
            window.removeEventListener('beforeunload', beforeUnload);
            if (pickBtn) pickBtn.disabled = false;
        }

        function reloadAfterImport(areas) {
            releaseImportLock();
            const url = new URL(window.location.href);
            url.searchParams.set('imported', '1');
            url.searchParams.set('v', String(Date.now()));
            const firstArea = (areas || []).find((a) => a && a.id);
            url.hash = firstArea ? `area-${firstArea.id}` : '';
            window.location.replace(url.toString());
        }

        function setStatus(text, tone) {
            if (!statusEl) return;
            statusEl.textContent = text || '';
            statusEl.className = 'text-xs mt-2 ' + (tone === 'error' ? 'text-red-400' : tone === 'ok' ? 'text-emerald-400' : 'text-slate-400');
        }

        function beforeUnload(e) {
            e.preventDefault();
            e.returnValue = '';
        }

        ui?.els.btnCancel?.addEventListener('click', () => {
            if (ui.els.confirmPanel && !ui.els.confirmPanel.classList.contains('hidden')) {
                ui.close();
                return;
            }
            if (importing) {
                cancelRequested = true;
                ui.addLog('إيقاف مؤقت بعد إنهاء الدفعة الحالية...', 'warn');
            } else {
                ui.close();
            }
        });

        ui?.els.btnPause?.addEventListener('click', () => {
            if (!importing) return;
            cancelRequested = true;
            ui.addLog('طلب إيقاف مؤقت...', 'warn');
        });

        ui?.els.btnClose?.addEventListener('click', () => ui.close());

        async function fetchServerCompletedKeys(batchId) {
            if (!cfg.batchStatusUrl || !batchId) return {};
            try {
                const res = await fetch(`${cfg.batchStatusUrl}?batch_id=${encodeURIComponent(batchId)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    return data.completed_keys || {};
                }
            } catch (e) {
                console.warn('batch status fetch failed', e);
            }
            return {};
        }

        async function createAreas(sectionNames) {
            const res = await fetch(cfg.bulkAreasUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': cfg.csrfToken,
                },
                body: JSON.stringify({ sections: sectionNames }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'تعذر إنشاء الأقسام.');
            }
            return data.areas || [];
        }

        async function runImport(sections, resumeState) {
            if (!window.runChunkedPhotoUpload) throw new Error('محرك الرفع غير محمّل.');

            sections = normalizeSections(sections);

            importing = true;
            cancelRequested = false;
            window.addEventListener('beforeunload', beforeUnload);
            pickBtn.disabled = true;
            if (resumeBtn) resumeBtn.classList.add('hidden');

            const batchId = resumeState?.batchId || ((window.crypto && window.crypto.randomUUID)
                ? window.crypto.randomUUID()
                : String(Date.now()));

            const state = {
                houseId,
                batchId,
                sectionNames: sections.map((s) => s.name),
                completedSectionIndex: resumeState?.completedSectionIndex ?? -1,
                completedKeys: resumeState?.completedKeys || {},
                failed: [],
                status: 'in_progress',
                startedAt: resumeState?.startedAt || Date.now(),
            };

            saveState(houseId, state);

            const sectionNames = sections.map((s) => s.name);
            ui.setProgress(2, 'جاري التحضير...', resumeState ? 'استئناف الاستيراد...' : 'إنشاء الأقسام...');
            ui.open();
            ui.setRunningMode();

            const serverKeys = await fetchServerCompletedKeys(batchId);
            state.completedKeys = mergeCompletedKeys(state.completedKeys, serverKeys);
            if (Object.keys(serverKeys).length) {
                ui.addLog(`مزامنة السيرفر: ${Object.values(serverKeys).flat().length} صورة محفوظة مسبقاً`, 'ok');
            }
            saveState(houseId, state);

            ui.addLog('إنشاء/تحديث الأقسام على السيرفر...', '');
            const areas = await createAreas(sectionNames);
            state.areas = areas;
            saveState(houseId, state);
            ui.addLog(`تم تجهيز ${areas.filter((a) => a.upload_url).length} قسم`, 'ok');

            let totalUploaded = 0;
            let totalSkipped = 0;
            let totalFailed = 0;
            let photosDone = 0;
            const totalPhotos = sections.reduce((n, s) => n + s.files.length, 0);
            const startSection = Math.max(0, state.completedSectionIndex + 1);

            ui.updateStats({ saved: 0, skipped: 0, failed: 0, sections: sections.length, current: startSection });

            for (let i = startSection; i < sections.length; i++) {
                if (cancelRequested) {
                    state.status = 'paused';
                    state.completedSectionIndex = Math.max(-1, i - 1);
                    saveState(houseId, state);
                    const savedNow = totalUploaded + totalSkipped;
                    releaseImportLock();
                    showResumeButtonIfNeeded();
                    ui.showPaused(savedNow, state.sectionNames);
                    setStatus(`متوقف مؤقتاً — ${savedNow} صورة محفوظة`, '');
                    return;
                }

                const section = sections[i];
                const area = areas[i] || areas.find((a) => a.name === section.name);
                if (!area || !area.upload_url) {
                    totalFailed += section.files.length;
                    ui.addLog(`تعذر ربط القسم «${section.name}» — تحقق من اسم المجلد`, 'error');
                    ui.addFailed(section.name, '(القسم)', 'تعذر ربط القسم بالسيرفر');
                    continue;
                }

                const isLastArea = i === sections.length - 1;
                const areaSkipKeys = state.completedKeys[String(area.id)] || [];

                ui.setProgress(
                    5 + Math.round((photosDone / totalPhotos) * 90),
                    `القسم ${i + 1} من ${sections.length}`,
                    `«${section.name}» — رفع ${section.files.length} صورة...`
                );
                ui.addLog(`بدء قسم «${section.name}» (${section.files.length} صورة)`, '');

                const result = await window.runChunkedPhotoUpload({
                    uploadUrl: area.upload_url,
                    csrfToken: cfg.csrfToken,
                    files: section.files,
                    batchId,
                    preserveOrder: true,
                    skipKeys: areaSkipKeys,
                    finalizeOnComplete: isLastArea,
                    onNetworkStatus: (s) => ui.setNetworkStatus(s),
                    onLog: (msg, tone) => ui.addLog(msg, tone),
                    onProgress: (p) => {
                        const within = p.total > 0 ? (p.saved / p.total) : 0;
                        const overall = totalPhotos > 0
                            ? ((photosDone + Math.round(within * section.files.length)) / totalPhotos) * 90 + 5
                            : 5;
                        ui.setProgress(
                            overall,
                            `القسم ${i + 1}/${sections.length}: ${section.name}`,
                            `حفظ ${photosDone + p.saved} من ${totalPhotos} — ذاكرة تقديرية ~${p.memoryMb} MB`
                        );
                        ui.updateStats({
                            saved: totalUploaded + totalSkipped + p.saved,
                            skipped: totalSkipped + p.skipped,
                            failed: totalFailed + p.failed,
                            sections: sections.length,
                            current: i + 1,
                        });
                    },
                });

                totalUploaded += result.uploaded;
                totalSkipped += result.skipped;
                totalFailed += result.failed;
                photosDone += section.files.length;

                if (section.files.length > 0 && result.saved === 0) {
                    ui.addLog(`تحذير: لم تُحفظ صور في «${section.name}» — راجع السجل`, 'warn');
                }

                state.completedKeys[String(area.id)] = mergeCompletedKeys(
                    { [String(area.id)]: state.completedKeys[String(area.id)] || [] },
                    { [String(area.id)]: result.completedKeys || [] }
                )[String(area.id)];
                state.completedSectionIndex = i;
                state.failed = (state.failed || []).concat(
                    (result.failedItems || []).map((f) => ({
                        area: section.name,
                        file: f.file,
                        key: f.key,
                        reason: f.reason,
                    }))
                );
                saveState(houseId, state);

                (result.failedItems || []).forEach((f) => ui.addFailed(section.name, f.file, f.reason));
                ui.addLog(`انتهى قسم «${section.name}»: ${result.saved} محفوظ`, result.failed ? 'warn' : 'ok');
            }

            const totalSaved = totalUploaded + totalSkipped;
            if (totalSaved === 0 && totalPhotos > 0) {
                clearState(houseId);
                releaseImportLock();
                throw new Error('لم يُرفع أي صورة. جرّب «استيراد جديد» أو اضغط استئناف بعد اختيار نفس المجلد. إن استمرت المشكلة حدّث الصفحة.');
            }

            clearState(houseId);

            const msg = totalFailed === 0
                ? `تم رفع جميع الأقسام (${sections.length}) وجميع الصور (${totalSaved} صورة) بنجاح.`
                : `تم رفع ${totalSaved} صورة — فشل ${totalFailed} (راجع التقرير أدناه).`;

            ui.showComplete(msg, totalFailed > 0);
            setStatus(msg, totalFailed ? 'error' : 'ok');

            if (totalFailed === 0) {
                ui.addLog('تحديث الصفحة لعرض الأقسام والصور...', 'ok');
                setTimeout(() => reloadAfterImport(areas), 600);
            } else {
                releaseImportLock();
            }
        }

        function startWithSections(sections, resumeState) {
            const totalPhotos = sections.reduce((n, s) => n + s.files.length, 0);
            if (sections.length > limits.maxAreas) {
                throw new Error(`عدد الأقسام (${sections.length}) يتجاوز ${limits.maxAreas}.`);
            }
            if (totalPhotos > limits.maxTotalPhotos) {
                throw new Error(`إجمالي الصور (${totalPhotos}) يتجاوز ${limits.maxTotalPhotos}.`);
            }
            for (const s of sections) {
                if (s.files.length > limits.maxPhotosPerArea) {
                    throw new Error(`«${s.name}»: ${s.files.length} صورة — الحد ${limits.maxPhotosPerArea}.`);
                }
            }

            if (resumeState) {
                ui?.open();
                ui?.setRunningMode();
                return runImport(sections, resumeState).catch(handleImportError);
            }

            if (!ui) {
                if (!window.confirm(`سيتم رفع ${totalPhotos} صورة في ${sections.length} قسم. متابعة؟`)) return;
                return runImport(sections, null).catch(handleImportError);
            }

            ui.showConfirm(sections, totalPhotos, () => {
                runImport(sections, null).catch(handleImportError);
            });
        }

        function handleImportError(err) {
            console.error(err);
            const msg = err.message || 'فشل الاستيراد.';
            setStatus(msg, 'error');
            releaseImportLock();
            if (ui) {
                ui.showNotice('فشل الاستيراد', msg, 'error');
                ui.addLog(msg, 'error');
            }
            showResumeButtonIfNeeded();
        }

        function showResumeButtonIfNeeded() {
            const st = loadState(houseId);
            const can = st && (st.status === 'paused' || st.status === 'in_progress') && st.sectionNames?.length;
            if (resumeBtn) {
                if (can) {
                    resumeBtn.classList.remove('hidden');
                    const done = st.completedSectionIndex + 1;
                    resumeBtn.textContent = `استئناف (${done}/${st.sectionNames.length} قسم)`;
                } else {
                    resumeBtn.classList.add('hidden');
                }
            }
            if (can && ui?.els.btnResume) {
                ui.els.btnResume.classList.remove('hidden');
            }
        }

        async function pickProjectFolder() {
            if (window.showDirectoryPicker) {
                const dirHandle = await window.showDirectoryPicker({ mode: 'read' });
                const { sections } = await scanHouseFolderHandle(dirHandle, limits);
                return normalizeSections(sections);
            }
            return new Promise((resolve, reject) => {
                const onChange = async () => {
                    fallbackInput.removeEventListener('change', onChange);
                    if (!fallbackInput.files?.length) {
                        reject(Object.assign(new Error('لم يُختر مجلد'), { name: 'AbortError' }));
                        return;
                    }
                    try {
                        const sections = parseWebkitDirectoryFiles(fallbackInput.files);
                        resolve(normalizeSections(sections));
                    } catch (e) {
                        reject(e);
                    } finally {
                        fallbackInput.value = '';
                    }
                };
                fallbackInput.addEventListener('change', onChange);
                fallbackInput.click();
            });
        }

        async function beginResume() {
            const resume = loadState(houseId);
            if (!resume || (resume.status !== 'paused' && resume.status !== 'in_progress')) {
                ui?.showNotice('لا يوجد استيراد', 'لا يوجد استيراد متوقف لهذا المنزل.', 'warn');
                return;
            }
            try {
                ui?.showNotice('اختر المجلد', 'اختر نفس مجلد المنزل (فولدر المشروع) من جهازك...', '');
                const sections = await pickProjectFolder();
                if (!sectionsMatchResume(resume, sections)) {
                    clearState(houseId);
                    showResumeButtonIfNeeded();
                    throw new Error('المجلد لا يطابق الاستيراد السابق. اختر نفس مجلد المنزل بالضبط، أو ابدأ استيراداً جديداً.');
                }
                await startWithSections(sections, resume);
            } catch (err) {
                if (err && err.name === 'AbortError') return;
                handleImportError(err);
            }
        }

        ui?.els.btnResume?.addEventListener('click', () => beginResume());
        resumeBtn?.addEventListener('click', () => beginResume());

        pickBtn?.addEventListener('click', async () => {
            if (importing) return;
            clearState(houseId);
            showResumeButtonIfNeeded();
            try {
                if (window.showDirectoryPicker) {
                    const dirHandle = await window.showDirectoryPicker({ mode: 'read' });
                    setStatus('جاري فحص المجلد...', '');
                    const { sections } = await scanHouseFolderHandle(dirHandle, limits);
                    startWithSections(sections, null);
                    return;
                }
            } catch (err) {
                if (err && err.name === 'AbortError') return;
                console.warn('Directory picker failed, trying fallback', err);
            }
            fallbackInput?.click();
        });

        fallbackInput?.addEventListener('change', async () => {
            if (importing || !fallbackInput.files?.length) return;
            try {
                clearState(houseId);
                const sections = parseWebkitDirectoryFiles(fallbackInput.files);
                if (!sections.length) {
                    throw new Error('اختر مجلد المنزل الذي يحتوي فولدرات الأقسام وبداخلها الصور.');
                }
                await startWithSections(normalizeSections(sections), null);
            } catch (err) {
                handleImportError(err);
            } finally {
                fallbackInput.value = '';
            }
        });

        showResumeButtonIfNeeded();
        const pending = loadState(houseId);
        if (pending && (pending.status === 'paused' || pending.status === 'in_progress') && ui) {
            ui.showPaused(
                Object.values(pending.completedKeys || {}).flat().length,
                pending.sectionNames || []
            );
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-house-folder-import]').forEach((el) => {
            window.initHouseFolderImport(el);
        });

        const cfg = window.INSPECTION_FOLDER_IMPORT || {};
        const houseId = Number(cfg.houseId) || 0;
        if (houseId > 0) {
            try {
                const raw = localStorage.getItem('gis_folder_import_' + houseId);
                if (raw) {
                    const st = JSON.parse(raw);
                    if (st?.startedAt && Date.now() - st.startedAt > 86400000) {
                        localStorage.removeItem('gis_folder_import_' + houseId);
                    }
                }
            } catch (_) { /* ignore */ }
        }

        const params = new URLSearchParams(window.location.search);
        if (params.get('imported') === '1') {
            const dlg = document.getElementById('folder-import-dialog');
            if (dlg) {
                const ui = createImportUI(dlg);
                ui.showComplete('تم رفع جميع الأقسام وجميع الصور بنجاح.', false);
            }
            params.delete('imported');
            const qs = params.toString();
            const next = window.location.pathname + (qs ? `?${qs}` : '') + window.location.hash;
            window.history.replaceState(null, '', next);
        }
    });
})();
