/**
 * تحديث جزئي لشبكة الصور — بدون reload للصفحة كلها.
 */
(function () {
    function panelMeta(areaRoot) {
        const block = areaRoot?.querySelector('[data-area-photos]');
        if (!block) return null;
        return {
            block,
            panelUrl: block.getAttribute('data-panel-url') || '',
            areaId: block.getAttribute('data-area-id') || '',
        };
    }

    function panelFetchUrl(basePanelUrl, areaId, params) {
        const url = new URL(basePanelUrl, window.location.origin);
        if (params) {
            Object.entries(params).forEach(([k, v]) => {
                if (v !== null && v !== undefined && v !== '') {
                    url.searchParams.set(k, String(v));
                }
            });
        }
        return url;
    }

    function replacePhotosBlock(areaRoot, html) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newBlock = doc.querySelector('[data-area-photos]');
        const oldBlock = areaRoot.querySelector('[data-area-photos]');
        if (!newBlock || !oldBlock) return false;

        oldBlock.replaceWith(newBlock.cloneNode(true));
        window.bindAreaPhotosPanel(areaRoot);
        return true;
    }

    async function fetchPanelHtml(panelUrl, params) {
        const url = typeof panelUrl === 'string'
            ? panelFetchUrl(panelUrl, null, params)
            : panelUrl;

        const res = await fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            credentials: 'same-origin',
        });

        if (!res.ok) {
            throw new Error('panel fetch failed: ' + res.status);
        }

        return res.text();
    }

    window.refreshAreaPhotosPanel = async function (areaWrapperOrForm, panelUrl, areaId, totalInArea, extraParams) {
        try {
            const areaWrapper = areaWrapperOrForm?.closest
                ? (areaWrapperOrForm.closest('[id^="area-"]') || areaWrapperOrForm)
                : areaWrapperOrForm;

            if (!areaWrapper?.querySelector) {
                window.location.reload();
                return;
            }

            const meta = panelMeta(areaWrapper);
            const baseUrl = panelUrl || meta?.panelUrl || '';
            areaId = areaId || meta?.areaId;

            const params = { ...(extraParams || {}) };

            if (areaId && totalInArea > 0) {
                const perPageSelect = areaWrapper.querySelector('[data-photos-per-page-select]');
                const perPage = perPageSelect ? parseInt(perPageSelect.value, 10) || 40 : 40;
                const lastPage = Math.max(1, Math.ceil(totalInArea / perPage));
                if (!params['area_' + areaId + '_page']) {
                    params['area_' + areaId + '_page'] = String(lastPage);
                }
                if (!params.photos_per_page) {
                    params.photos_per_page = String(perPage);
                }
            }

            const html = await fetchPanelHtml(baseUrl, params);
            if (!replacePhotosBlock(areaWrapper, html)) {
                window.location.reload();
                return;
            }

            if (areaId) {
                const pageUrl = new URL(window.location.href);
                const pageKey = 'area_' + areaId + '_page';
                if (params[pageKey]) pageUrl.searchParams.set(pageKey, params[pageKey]);
                if (params.photos_per_page) pageUrl.searchParams.set('photos_per_page', params.photos_per_page);
                pageUrl.hash = areaWrapper.id;
                history.replaceState(null, '', pageUrl.pathname + pageUrl.search + pageUrl.hash);
            }
        } catch (err) {
            console.error('Refresh failed:', err);
            window.location.reload();
        }
    };

    window.bindAreaPhotosPanel = function (areaRoot) {
        if (!areaRoot) return;

        areaRoot.querySelectorAll('[data-resort-form]').forEach((form) => {
            if (form.dataset.boundResort === '1') return;
            form.dataset.boundResort = '1';

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = form.querySelector('button[type="submit"]');
                if (!btn || btn.disabled) return;

                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = 'جاري الترتيب...';

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                        credentials: 'same-origin',
                    });
                    const data = await res.json();

                    if (data.success && data.changed) {
                        document.querySelectorAll('a[data-pdf-export]').forEach((anchor) => {
                            try {
                                const u = new URL(anchor.href, window.location.origin);
                                u.searchParams.set('refresh', '1');
                                u.searchParams.set('v', String(Date.now()));
                                anchor.href = u.toString();
                            } catch (_) { /* ignore */ }
                        });

                        const panelUrl = form.getAttribute('data-photos-panel-url')
                            || areaRoot.querySelector('[data-area-photos]')?.getAttribute('data-panel-url');
                        await window.refreshAreaPhotosPanel(areaRoot, panelUrl, data.area_id, data.photo_count || 0, {
                            ['area_' + data.area_id + '_page']: '1',
                        });
                        btn.innerHTML = 'تم الترتيب ✓';
                        setTimeout(() => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }, 1500);
                    } else if (data.success) {
                        btn.innerHTML = 'بالفعل مرتبين';
                        setTimeout(() => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }, 2000);
                    } else {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                } catch (err) {
                    console.error(err);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            });
        });

        areaRoot.querySelectorAll('[data-photo-move-form]').forEach((form) => {
            if (form.dataset.boundMove === '1') return;
            form.dataset.boundMove = '1';

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = form.querySelector('button[type="submit"]');
                if (btn) btn.disabled = true;

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                        credentials: 'same-origin',
                    });
                    const data = await res.json();
                    if (data.success) {
                        const meta = panelMeta(areaRoot);
                        const perPageSelect = areaRoot.querySelector('[data-photos-per-page-select]');
                        const perPage = perPageSelect ? parseInt(perPageSelect.value, 10) || 40 : 40;
                        const pageUrl = new URL(window.location.href);
                        const currentPage = parseInt(pageUrl.searchParams.get('area_' + data.area_id + '_page') || '1', 10) || 1;
                        await window.refreshAreaPhotosPanel(
                            areaRoot,
                            meta?.panelUrl,
                            data.area_id,
                            data.photo_count,
                            {
                                ['area_' + data.area_id + '_page']: String(currentPage),
                                photos_per_page: String(perPage),
                            }
                        );
                    }
                } catch (err) {
                    console.error(err);
                } finally {
                    if (btn) btn.disabled = false;
                }
            });
        });

        const photosBlock = areaRoot.querySelector('[data-area-photos]');
        if (photosBlock && photosBlock.dataset.boundPagination !== '1') {
            photosBlock.dataset.boundPagination = '1';
            photosBlock.addEventListener('click', async (e) => {
                const link = e.target.closest('nav a[href]');
                if (!link || !photosBlock.contains(link)) return;
                e.preventDefault();

                const meta = panelMeta(areaRoot);
                if (!meta?.panelUrl) {
                    window.location.href = link.href;
                    return;
                }

                try {
                    const linkUrl = new URL(link.href, window.location.origin);
                    const params = {};
                    linkUrl.searchParams.forEach((v, k) => {
                        params[k] = v;
                    });
                    replacePhotosBlock(areaRoot, await fetchPanelHtml(meta.panelUrl, params));
                    areaRoot.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } catch (err) {
                    window.location.href = link.href;
                }
            });
        }

        areaRoot.querySelectorAll('[data-photos-per-page-select]').forEach((select) => {
            if (select.dataset.boundPerPage === '1') return;
            select.dataset.boundPerPage = '1';

            select.addEventListener('change', async () => {
                const meta = panelMeta(areaRoot);
                if (!meta?.panelUrl) return;

                try {
                    replacePhotosBlock(areaRoot, await fetchPanelHtml(meta.panelUrl, {
                        photos_per_page: select.value,
                        ['area_' + meta.areaId + '_page']: '1',
                    }));

                    const pageUrl = new URL(window.location.href);
                    pageUrl.searchParams.set('photos_per_page', select.value);
                    pageUrl.searchParams.set('area_' + meta.areaId + '_page', '1');
                    history.replaceState(null, '', pageUrl.pathname + pageUrl.search + pageUrl.hash);
                } catch (err) {
                    select.closest('form')?.submit();
                }
            });
        });

        areaRoot.querySelectorAll('[data-merge-form]').forEach((f) => {
            if (window.initInspectionMerge) window.initInspectionMerge(f);
        });
    };

    function initAllAreaPanels() {
        document.querySelectorAll('[id^="area-"]').forEach((areaRoot) => {
            if (areaRoot.querySelector('[data-area-photos]')) {
                window.bindAreaPhotosPanel(areaRoot);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllAreaPanels);
    } else {
        initAllAreaPanels();
    }
})();
