(function () {
    'use strict';

    const state = {
        activeTab: 'menu-epbt',
        activeModule: 'taksiran',
        activeReportId: 'detail-tgkn-terimaan-jalan',
        jlkod: '',
        akaun: '',
        status: 'all',
        page: 1,
        perPage: 25,
        totalPages: 1,
        sortBy: 'PEG_AKAUN',
        sortDir: 'ASC',
        reportId: (window.APP_BOOT && window.APP_BOOT.defaultReportId) || '',
        reportPage: 1,
        reportPerPage: 25,
        reportTotalPages: 1,
        reportSortBy: '',
        reportSortDir: 'ASC',
        epbtPage: 1,
        epbtPerPage: 25,
        epbtTotalPages: 1,
        epbtSortBy: '',
        epbtSortDir: 'ASC',
        forceRefresh: false,
        demoMode: Boolean(window.APP_BOOT && window.APP_BOOT.demoMode),
        loadedTabs: {}
    };

    const clientPageCache = new Map();
    let senaraiAbortController = null;
    let reportAbortController = null;

    function setClientCache(key, value) {
        if (clientPageCache.size >= 40) {
            const oldestKey = clientPageCache.keys().next().value;
            clientPageCache.delete(oldestKey);
        }
        clientPageCache.set(key, value);
    }

    function fmtDuration(ms) {
        const num = Number(ms);
        if (!Number.isFinite(num)) return '';
        return num >= 1000 ? `${(num / 1000).toFixed(2)}s` : `${Math.round(num)} ms`;
    }

    const TAB_TITLES = {
        'menu-epbt': 'Menu Laporan ePBT (SPHB)',
        portal: 'Laman Utama Modul (ePBT)',
        dashboard: 'Dashboard Utama',
        analisis: 'Analisis Tunggakan & Terimaan Jalan',
        senarai: 'Senarai Akaun Pegangan',
        laporan: 'Pelancar Laporan Modul (SQL)',
        pentadbiran: 'Pentadbiran & Diagnostik Oracle 10g',
        tetapan: 'Tetapan Sistem & Log Masuk Mock'
    };

    function $(id) {
        return document.getElementById(id);
    }

    function on(idOrEl, event, handler) {
        if (!idOrEl) return;
        const el = typeof idOrEl === 'string' ? $(idOrEl) : idOrEl;
        if (el) {
            el.addEventListener(event, handler);
        }
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtRM(val) {
        const num = Number(val) || 0;
        const abs = Math.abs(num).toLocaleString('en-MY', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        return (num < 0 ? '-RM ' : 'RM ') + abs;
    }

    function fmtInt(val) {
        return (Number(val) || 0).toLocaleString('en-MY');
    }

    function buildFilterQuery(extra = {}) {
        const params = new URLSearchParams({
            jlkod: state.jlkod,
            akaun: state.akaun,
            status: state.status,
            ...extra
        });
        return params.toString();
    }

    async function apiGet(action, extra = {}, signal = null) {
        const qs = buildFilterQuery({ action, ...extra });
        const fetchOpts = { headers: { Accept: 'application/json' } };
        if (signal) {
            fetchOpts.signal = signal;
        }
        const res = await fetch(`api.php?${qs}`, fetchOpts);
        const json = await res.json();
        if (!res.ok || !json.ok) {
            throw new Error(json.message || 'Ralat komunikasi pelayan.');
        }
        return json;
    }

    async function apiPost(action, body) {
        const res = await fetch(`api.php?action=${encodeURIComponent(action)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(body)
        });
        const json = await res.json();
        if (!res.ok || !json.ok) {
            throw new Error(json.message || 'Ralat permintaan.');
        }
        return json;
    }

    // ================= TEMA & SIDEBAR =================
    function applyTheme(theme) {
        const dark = theme === 'dark';
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        localStorage.setItem('app_theme', dark ? 'dark' : 'light');
        $('themeIcon').textContent = dark ? '☀️' : '🌙';
        $('themeLabel').textContent = dark ? 'Mod Cerah' : 'Mod Gelap';
    }

    function initTheme() {
        const saved = localStorage.getItem('app_theme') || 'light';
        applyTheme(saved);
    }

    function toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        applyTheme(current === 'dark' ? 'light' : 'dark');
    }

    function toggleSidebar() {
        if (window.innerWidth <= 900) {
            document.body.classList.toggle('sidebar-mobile-open');
            return;
        }
        document.body.classList.toggle('sidebar-expanded');
    }

    // ================= NAVIGASI TAB =================
    function switchTab(tab) {
        state.activeTab = tab;
        document.querySelectorAll('.nav-item').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });
        document.querySelectorAll('.epbt-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });
        document.querySelectorAll('.tab-panel').forEach(panel => {
            panel.classList.toggle('active', panel.id === `tab-${tab}`);
        });
        if ($('topbarTitle')) {
            $('topbarTitle').textContent = TAB_TITLES[tab] || 'Dashboard';
        }

        if ($('globalFilterBar')) {
            const showFilters = ['dashboard', 'analisis', 'senarai', 'laporan'].includes(tab);
            $('globalFilterBar').style.display = showFilters ? 'flex' : 'none';
        }
        if ($('statusFilterWrap')) {
            $('statusFilterWrap').style.display = (tab === 'dashboard' || tab === 'laporan') ? 'none' : 'flex';
        }

        document.body.classList.remove('sidebar-mobile-open');

        if (!state.loadedTabs[tab]) {
            refreshActiveTab();
        }
    }

    function refreshActiveTab() {
        if (state.activeTab === 'menu-epbt') {
            loadEpbtReportData();
        } else if (state.activeTab === 'dashboard') {
            loadDashboard();
        } else if (state.activeTab === 'analisis') {
            loadAnalisis();
        } else if (state.activeTab === 'senarai') {
            loadSenarai();
        } else if (state.activeTab === 'laporan') {
            loadDynamicReport();
        }
    }

    // ================= TAB 1: DASHBOARD UTAMA =================
    async function loadDashboard() {
        try {
            const extra = state.forceRefresh ? { refresh: '1' } : {};
            state.forceRefresh = false;
            const res = await apiGet('dashboard', extra);
            state.loadedTabs.dashboard = true;
            const d = res.data;
            const s = d.summary;

            $('dashTotalAkaun').textContent = fmtInt(s.total_akaun);
            $('dashAkaunBreakdown').textContent = `Bersih: ${fmtInt(s.akaun_bersih)} · Tertunggak: ${fmtInt(s.akaun_tertunggak)}`;
            $('dashBakiTgk').textContent = fmtRM(s.baki_tunggakan);
            $('dashBilTerimaTgk').textContent = `Bil: ${fmtRM(s.bil_tunggakan)} · Terima: ${fmtRM(s.terima_tunggakan)}`;
            $('dashBakiSem').textContent = fmtRM(s.baki_semasa);
            $('dashBilTerimaSem').textContent = `Bil: ${fmtRM(s.bil_semasa)} · Terima: ${fmtRM(s.terima_semasa)}`;
            $('dashKadarKutipan').textContent = `${Number(s.kadar_kutipan_keseluruhan || 0).toFixed(1)}%`;
            $('dashJumlahBaki').textContent = `Jumlah Baki Bersih: ${fmtRM(s.jumlah_baki)}`;

            // Kad Prestasi Tunggakan & Semasa (Gaya PDF Halaman 2)
            $('cardTgkBil').textContent = fmtRM(s.bil_tunggakan);
            $('cardTgkTerima').textContent = fmtRM(s.terima_tunggakan);
            $('cardTgkPct').textContent = `(${Number(s.kadar_kutipan_tunggakan).toFixed(1)}%)`;
            $('cardTgkBaki').textContent = fmtRM(s.baki_tunggakan);

            $('cardSemBil').textContent = fmtRM(s.bil_semasa);
            $('cardSemTerima').textContent = fmtRM(s.terima_semasa);
            $('cardSemPct').textContent = `(${Number(s.kadar_kutipan_semasa).toFixed(1)}%)`;
            $('cardSemBaki').textContent = fmtRM(s.baki_semasa);

            // Profil akaun jika carian PEG_AKAUN aktif
            const profileCard = $('accountProfileCard');
            if (state.akaun && d.account_profile) {
                const p = d.account_profile;
                profileCard.style.display = 'block';
                $('profileTitle').textContent = `${p.PEG_AKAUN} · ${p.PEG_JLKOD} - ${p.JLN_JNAMA}`;
                $('profBilTgk').textContent = fmtRM(p.BIL_TUNGGAKAN);
                $('profTerimaTgk').textContent = fmtRM(p.TERIMA_TUNGGAKAN);
                $('profBakiTgk').textContent = fmtRM(p.BAKI_TUNGGAKAN);
                $('profBilSem').textContent = fmtRM(p.BIL_SEMASA);
                $('profTerimaSem').textContent = fmtRM(p.TERIMA_SEMASA);
                $('profBakiSem').textContent = fmtRM(p.BAKI_SEMASA);

                const matchesEl = $('profileMatches');
                matchesEl.innerHTML = '';
                (d.matched_accounts || []).forEach(m => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'pill pill-indigo';
                    btn.style.border = 'none';
                    btn.style.cursor = 'pointer';
                    btn.textContent = m.PEG_AKAUN;
                    btn.addEventListener('click', () => {
                        $('filterAkaun').value = m.PEG_AKAUN;
                        state.akaun = m.PEG_AKAUN;
                        state.loadedTabs = {};
                        loadDashboard();
                    });
                    matchesEl.appendChild(btn);
                });
            } else {
                profileCard.style.display = 'none';
            }

            DashboardCharts.renderPrestasiLineChart($('chartPrestasiTunggakan'), {
                items: d.series.items || [],
                color: '#4f46e5',
                valueKey: 'baki_tunggakan',
                bilKey: 'bil_tunggakan',
                terimaKey: 'terima_tunggakan',
                bakiKey: 'baki_tunggakan',
                pctKey: 'kadar_tunggakan'
            });

            DashboardCharts.renderPrestasiLineChart($('chartPrestasiSemasa'), {
                items: d.series.items || [],
                color: '#ec4899',
                valueKey: 'terima_semasa',
                bilKey: 'bil_semasa',
                terimaKey: 'terima_semasa',
                bakiKey: 'baki_semasa',
                pctKey: 'kadar_semasa'
            });
        } catch (err) {
            $('chartPrestasiTunggakan').innerHTML = `<div class="empty-state">${escapeHtml(err.message)}</div>`;
            $('chartPrestasiSemasa').innerHTML = `<div class="empty-state">${escapeHtml(err.message)}</div>`;
        }
    }

    // ================= TAB 2: ANALISIS JALAN =================
    async function loadAnalisis() {
        try {
            const extra = state.forceRefresh ? { refresh: '1' } : {};
            state.forceRefresh = false;
            const res = await apiGet('analisis', extra);
            state.loadedTabs.analisis = true;
            const d = res.data;
            const s = d.summary;
            const total = Math.max(1, Number(s.total_akaun) || 0);

            const elTotal = $('eisTotalAkaun') || $('anaJumlahAkaun');
            if (elTotal) elTotal.textContent = fmtInt(s.total_akaun);

            const elScope = $('eisJalanLabel') || $('anaScopeLabel');
            if (elScope) elScope.textContent = state.jlkod ? `Kod Jalan: ${state.jlkod}` : 'Semua Jalan';

            const elKadar = $('eisKadarKutipan') || $('anaPurataKutipan');
            if (elKadar) elKadar.textContent = `${Number(s.kadar_kutipan_keseluruhan || 0).toFixed(1)}%`;

            const elTerimaSub = $('eisKadarSub') || $('anaJumlahTerimaSub');
            if (elTerimaSub) elTerimaSub.textContent = `Terima: ${fmtRM(s.jumlah_terima)}`;

            const elBersih = $('eisAkaunSelesai') || $('anaBersih');
            if (elBersih) elBersih.textContent = fmtInt(s.akaun_bersih);

            const elBersihPct = $('eisAkaunSelesaiPct') || $('anaBersihPct');
            if (elBersihPct) elBersihPct.textContent = `${((s.akaun_bersih / total) * 100).toFixed(1)}% daripada jumlah akaun`;

            const elTgk = $('eisAkaunTertunggak') || $('anaTertunggak');
            if (elTgk) elTgk.textContent = fmtInt(s.akaun_tertunggak);

            const elTgkPct = $('eisAkaunTertunggakPct') || $('anaTertunggakPct');
            if (elTgkPct) elTgkPct.textContent = `${((s.akaun_tertunggak / total) * 100).toFixed(1)}% memerlukan tindakan`;

            const barBox = $('chartKomponenBar') || $('chartAnalisisBar');
            if (barBox) DashboardCharts.renderBarChart(barBox, d.buckets || []);

            const donutBox = $('chartNisbahDonut') || $('chartAnalisisDonut');
            if (donutBox) DashboardCharts.renderDonutChart(donutBox, {
                cleanCount: s.akaun_bersih,
                arrearsCount: s.akaun_tertunggak,
                totalCount: s.total_akaun
            });

            if ($('donutLegend')) {
                $('donutLegend').innerHTML = `
                    <span class="legend-item"><span class="legend-swatch" style="background:#10b981;"></span>Selesai / Bersih (${fmtInt(s.akaun_bersih)})</span>
                    <span class="legend-item"><span class="legend-swatch" style="background:#ef4444;"></span>Tertunggak (${fmtInt(s.akaun_tertunggak)})</span>
                `;
            }

            const top5Tgk = $('top5TunggakanBody') || $('topTunggakanBody');
            if (top5Tgk) renderTopTable(top5Tgk, d.top_tunggakan || [], 'tunggakan');

            const top5Terima = $('top5TerimaBody') || $('topKutipanBody');
            if (top5Terima) renderTopTable(top5Terima, d.top_kutipan || [], 'kutipan');
        } catch (err) {
            const barBox = $('chartKomponenBar') || $('chartAnalisisBar');
            if (barBox) barBox.innerHTML = `<div class="empty-state">${escapeHtml(err.message)}</div>`;
        }
    }

    function renderTopTable(tbody, rows, kind) {
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Tiada rekod ditemui.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map((r, idx) => {
            if (kind === 'tunggakan') {
                return `
                    <tr>
                        <td><strong>${idx + 1}</strong></td>
                        <td><strong>${escapeHtml(r.PEG_AKAUN)}</strong></td>
                        <td><span class="pill pill-indigo">${escapeHtml(r.PEG_JLKOD)} · ${escapeHtml(r.JLN_JNAMA)}</span></td>
                        <td class="num val-danger">${fmtRM(r.BAKI_TUNGGAKAN)}</td>
                        <td class="num">${fmtRM(r.BAKI_SEMASA)}</td>
                        <td class="num val-danger">${fmtRM(r.JUMLAH_BAKI)}</td>
                    </tr>
                `;
            }
            const jumlahTerima = (Number(r.TERIMA_TUNGGAKAN) || 0) + (Number(r.TERIMA_SEMASA) || 0);
            return `
                <tr>
                    <td><strong>${idx + 1}</strong></td>
                    <td><strong>${escapeHtml(r.PEG_AKAUN)}</strong></td>
                    <td><span class="pill pill-indigo">${escapeHtml(r.PEG_JLKOD)} · ${escapeHtml(r.JLN_JNAMA)}</span></td>
                    <td class="num">${fmtRM(r.TERIMA_TUNGGAKAN)}</td>
                    <td class="num">${fmtRM(r.TERIMA_SEMASA)}</td>
                    <td class="num val-success">${fmtRM(jumlahTerima)}</td>
                </tr>
            `;
        }).join('');
    }

    function updateSortIndicators(tableId, activeCol, sortDir) {
        document.querySelectorAll(`#${tableId} thead th.sortable`).forEach(th => {
            const col = (th.dataset.sort || '').toUpperCase();
            let ind = th.querySelector('.sort-ind');
            if (!ind) {
                ind = document.createElement('span');
                ind.className = 'sort-ind';
                th.appendChild(ind);
            }
            const isActive = col !== '' && col === String(activeCol || '').toUpperCase();
            th.classList.toggle('sort-active', isActive);
            ind.textContent = isActive ? (sortDir === 'DESC' ? '▼' : '▲') : '⇅';
        });
    }

    // ================= TAB 3: SENARAI AKAUN =================
    function renderSenaraiData(d, speedNote) {
        state.loadedTabs.senarai = true;
        state.totalPages = Math.max(1, Number(d.total_pages) || 1);
        updateSortIndicators('senaraiTable', state.sortBy, state.sortDir);
        const rows = d.rows || [];
        const tbody = $('senaraiBody');

        const metaEl = $('senaraiSummaryText') || $('senaraiMeta');
        if (metaEl) metaEl.textContent = `Jumlah rekod sepadan: ${fmtInt(d.total)} akaun${speedNote}`;
        if ($('paginationInfo')) $('paginationInfo').textContent = `Halaman ${fmtInt(d.page)} daripada ${fmtInt(d.total_pages)} (${fmtInt(d.total)} rekod)`;
        if ($('firstPageBtn')) $('firstPageBtn').disabled = d.page <= 1;
        if ($('prevPageBtn')) $('prevPageBtn').disabled = d.page <= 1;
        if ($('nextPageBtn')) $('nextPageBtn').disabled = d.page >= d.total_pages;
        if ($('lastPageBtn')) $('lastPageBtn').disabled = d.page >= d.total_pages;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="12" class="empty-state">Tiada rekod sepadan dengan penapis yang dipilih.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(r => {
            const statusClass = r.STATUS === 'Tertunggak'
                ? 'pill-red'
                : (r.STATUS === 'Lebih Bayar' ? 'pill-cyan' : 'pill-green');
            const bakiClass = Number(r.JUMLAH_BAKI) > 0
                ? 'val-danger'
                : (Number(r.JUMLAH_BAKI) < 0 ? 'val-cyan' : 'val-success');

            return `
                <tr>
                    <td>${escapeHtml(r.ROW_NUM ?? '-')}</td>
                    <td><strong>${escapeHtml(r.PEG_AKAUN)}</strong></td>
                    <td>${escapeHtml(r.PEG_JLKOD)}</td>
                    <td><button type="button" class="drilldown-street-btn" data-jlkod="${escapeHtml(r.PEG_JLKOD)}" data-jlnama="${escapeHtml(r.JLN_JNAMA)}" title="Klik untuk zoom in perincian jalan ini">${escapeHtml(r.JLN_JNAMA)} <span class="drilldown-badge">🔍</span></button></td>
                    <td class="num">${fmtRM(r.BIL_TUNGGAKAN)}</td>
                    <td class="num">${fmtRM(r.TERIMA_TUNGGAKAN)}</td>
                    <td class="num ${Number(r.BAKI_TUNGGAKAN) > 0 ? 'val-danger' : ''}">${fmtRM(r.BAKI_TUNGGAKAN)}</td>
                    <td class="num">${fmtRM(r.BIL_SEMASA)}</td>
                    <td class="num">${fmtRM(r.TERIMA_SEMASA)}</td>
                    <td class="num ${Number(r.BAKI_SEMASA) > 0 ? 'val-danger' : ''}">${fmtRM(r.BAKI_SEMASA)}</td>
                    <td class="num ${bakiClass}">${fmtRM(r.JUMLAH_BAKI)}</td>
                    <td><span class="pill ${statusClass}">${escapeHtml(r.STATUS)}</span></td>
                </tr>
            `;
        }).join('');

        tbody.querySelectorAll('.drilldown-street-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const jlkod = btn.dataset.jlkod;
                const jlnama = btn.dataset.jlnama;
                if (jlkod) {
                    openStreetDrilldown(jlkod, jlnama);
                }
            });
        });
    }

    async function loadSenarai() {
        const tbody = $('senaraiBody');
        const tableWrap = $('senaraiTable').closest('.table-wrap');
        const cacheKey = JSON.stringify(['senarai', state.jlkod, state.akaun, state.status, state.page, state.perPage, state.sortBy, state.sortDir]);
        const doRefresh = state.forceRefresh;
        state.forceRefresh = false;

        if (!doRefresh && clientPageCache.has(cacheKey)) {
            if (tableWrap) tableWrap.classList.remove('is-loading');
            renderSenaraiData(clientPageCache.get(cacheKey), ' · 0 ms (Memori)');
            return;
        }

        if (senaraiAbortController) {
            senaraiAbortController.abort();
        }
        senaraiAbortController = new AbortController();

        const hasExistingRows = tbody.children.length > 0 && !tbody.querySelector('.empty-state');
        if (hasExistingRows && tableWrap) {
            tableWrap.classList.add('is-loading');
            const metaEl = $('senaraiSummaryText') || $('senaraiMeta');
            if (metaEl) metaEl.textContent = 'Mengisih / memuatkan halaman...';
        } else {
            tbody.innerHTML = '<tr><td colspan="12" class="empty-state">Memuatkan rekod &amp; membina indeks pantas...</td></tr>';
        }

        try {
            const params = {
                page: state.page,
                per_page: state.perPage,
                sort_by: state.sortBy,
                sort_dir: state.sortDir
            };
            if (doRefresh) params.refresh = '1';

            const res = await apiGet('senarai', params, senaraiAbortController.signal);
            const d = res.data;
            setClientCache(cacheKey, d);
            const speedLabel = d.elapsed_ms !== undefined
                ? ` · ${fmtDuration(d.elapsed_ms)} (${d.cache_hit ? 'Cache' : 'Oracle 10g'})`
                : '';
            renderSenaraiData(d, speedLabel);
        } catch (err) {
            if (err && err.name === 'AbortError') return;
            tbody.innerHTML = `<tr><td colspan="12" class="empty-state">${escapeHtml(err.message)}</td></tr>`;
        } finally {
            if (tableWrap) tableWrap.classList.remove('is-loading');
        }
    }

    // ================= TAB 3B: PELANCAR LAPORAN MODUL (SQL) =================
    function renderDynamicReportData(d, speedNote) {
        state.loadedTabs.laporan = true;
        state.reportTotalPages = Math.max(1, Number(d.total_pages) || 1);
        const thead = $('dynamicReportHead');
        const tbody = $('dynamicReportBody');
        const cols = (d.report && d.report.columns) || [];
        const rows = d.rows || [];
        const activeSortBy = (d.sort_by || state.reportSortBy || '').toUpperCase();
        const activeSortDir = (d.sort_dir || state.reportSortDir || 'ASC').toUpperCase();
        state.reportSortBy = activeSortBy;
        state.reportSortDir = activeSortDir;

        if ($('activeReportTitle') && d.report) $('activeReportTitle').textContent = d.report.title || '';
        if ($('activeReportMeta') && d.report) $('activeReportMeta').textContent = `${d.report.description || ''} · ${fmtInt(d.total)} rekod${speedNote}`;
        if ($('reportPaginationInfo')) $('reportPaginationInfo').textContent = `Halaman ${fmtInt(d.page)} daripada ${fmtInt(d.total_pages)} (${fmtInt(d.total)} rekod)`;
        if ($('reportFirstBtn')) $('reportFirstBtn').disabled = d.page <= 1;
        if ($('reportPrevBtn')) $('reportPrevBtn').disabled = d.page <= 1;
        if ($('reportNextBtn')) $('reportNextBtn').disabled = d.page >= d.total_pages;
        if ($('reportLastBtn')) $('reportLastBtn').disabled = d.page >= d.total_pages;

        if (!thead || !tbody) return;

        thead.innerHTML = `<tr><th>Row #</th>${cols.map(c => {
            const key = String(c.key).toUpperCase();
            const isNum = c.type === 'currency' || c.type === 'number' || c.type === 'integer' || c.type === 'percent';
            const isActive = key === activeSortBy;
            const arrow = isActive ? (activeSortDir === 'DESC' ? '▼' : '▲') : '⇅';
            return `<th class="sortable ${isNum ? 'num' : ''} ${isActive ? 'sort-active' : ''}" data-sort="${escapeHtml(key)}">${escapeHtml(c.label)}<span class="sort-ind">${arrow}</span></th>`;
        }).join('')}</tr>`;

        thead.querySelectorAll('th.sortable').forEach(th => {
            th.addEventListener('click', () => {
                const col = th.dataset.sort;
                if (state.reportSortBy === col) {
                    state.reportSortDir = state.reportSortDir === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    state.reportSortBy = col;
                    state.reportSortDir = 'ASC';
                }
                state.reportPage = 1;
                loadDynamicReport();
            });
        });

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="${cols.length + 1}" class="empty-state">Tiada rekod sepadan.</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(r => {
            // Kenal pasti baris JUMLAH BESAR (ROLLUP) — highlight berbeza
            const isTotal = String(r.KAWASAN ?? r.kawasan ?? '').toUpperCase().includes('JUMLAH') ||
                            String(r.ROW_TYPE ?? '').toUpperCase() === 'TOTAL';
            const rowClass = isTotal ? ' class="row-grand-total"' : '';
            const cells = cols.map(c => {
                const key = String(c.key).toUpperCase();
                const val = r[key] ?? '';
                if (!isTotal && (key === 'JALAN_TAMAN_KAMPUNG' || key === 'JLN_JNAMA' || key === 'KOD_JALAN' || key === 'PEG_JLKOD')) {
                    const kodJln = r.KOD_JALAN ?? r.PEG_JLKOD ?? '';
                    const nmJln = r.JALAN_TAMAN_KAMPUNG ?? r.JLN_JNAMA ?? val;
                    if (kodJln) {
                        return `<td><button type="button" class="drilldown-street-btn" data-jlkod="${escapeHtml(kodJln)}" data-jlnama="${escapeHtml(nmJln)}" title="Klik untuk zoom in &amp; papar maklumat terperinci akaun jalan ini">${escapeHtml(val)} <span class="drilldown-badge">🔍 Perincian</span></button></td>`;
                    }
                }
                if (c.type === 'currency') {
                    return `<td class="num">${fmtRM(val)}</td>`;
                }
                if (c.type === 'number' || c.type === 'integer') {
                    return `<td class="num">${fmtInt(val)}</td>`;
                }
                if (c.type === 'percent') {
                    const num = parseFloat(val);
                    const cls = num > 30 ? 'val-danger' : (num > 20 ? 'val-warn' : 'val-success');
                    return `<td class="num ${cls}">${Number.isFinite(num) ? num.toFixed(2) + '%' : escapeHtml(val)}</td>`;
                }
                return `<td>${escapeHtml(val)}</td>`;
            }).join('');
            return `<tr${rowClass}><td>${escapeHtml(r.ROW_NUM ?? '-')}</td>${cells}</tr>`;
        }).join('');

        tbody.querySelectorAll('.drilldown-street-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const jlkod = btn.dataset.jlkod;
                const jlnama = btn.dataset.jlnama;
                if (jlkod) {
                    openStreetDrilldown(jlkod, jlnama);
                }
            });
        });
    }

    async function loadDynamicReport() {
        const thead = $('dynamicReportHead');
        const tbody = $('dynamicReportBody');
        const tableWrap = $('dynamicReportTable') ? $('dynamicReportTable').closest('.table-wrap') : null;

        if (!thead || !tbody) return;

        if (!state.reportId) {
            thead.innerHTML = '';
            tbody.innerHTML = '<tr><td class="empty-state">Tiada laporan modul dipilih.</td></tr>';
            return;
        }

        document.querySelectorAll('.report-card-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.reportId === state.reportId);
        });

        const cacheKey = JSON.stringify(['report', state.reportId, state.jlkod, state.akaun, state.reportPage, state.reportPerPage, state.reportSortBy, state.reportSortDir]);
        const doRefresh = state.forceRefresh;
        state.forceRefresh = false;

        if (!doRefresh && clientPageCache.has(cacheKey)) {
            if (tableWrap) tableWrap.classList.remove('is-loading');
            renderDynamicReportData(clientPageCache.get(cacheKey), ' · 0 ms (Memori)');
            return;
        }

        if (reportAbortController) {
            reportAbortController.abort();
        }
        reportAbortController = new AbortController();

        const hasExistingRows = tbody.children.length > 0 && !tbody.querySelector('.empty-state');
        if (hasExistingRows && tableWrap) {
            tableWrap.classList.add('is-loading');
            $('activeReportMeta').textContent = 'Mengisih / memuatkan halaman laporan...';
        } else {
            tbody.innerHTML = '<tr><td colspan="12" class="empty-state">Menjana laporan modul &amp; membina indeks pantas...</td></tr>';
        }

        try {
            const params = {
                report_id: state.reportId,
                jlkod: state.jlkod,
                carian: state.akaun,
                page: state.reportPage,
                per_page: state.reportPerPage,
                sort_by: state.reportSortBy,
                sort_dir: state.reportSortDir
            };
            if (doRefresh) params.refresh = '1';

            const res = await apiGet('run_report', params, reportAbortController.signal);
            const d = res.data;
            setClientCache(cacheKey, d);
            const speedLabel = d.elapsed_ms !== undefined
                ? ` · ${fmtDuration(d.elapsed_ms)} (${d.cache_hit ? 'Cache' : 'Oracle 10g'})`
                : '';
            renderDynamicReportData(d, speedLabel);
        } catch (err) {
            if (err && err.name === 'AbortError') return;
            tbody.innerHTML = `<tr><td colspan="12" class="empty-state">${escapeHtml(err.message)}</td></tr>`;
        } finally {
            if (tableWrap) tableWrap.classList.remove('is-loading');
        }
    }

    function filterReportsByModule(moduleId) {
        $('reportModuleFilter').value = moduleId || '';
        let firstVisibleId = '';
        document.querySelectorAll('.report-card-btn').forEach(btn => {
            const matches = !moduleId || moduleId === 'epbt-report' || btn.dataset.moduleId === moduleId;
            btn.style.display = matches ? 'flex' : 'none';
            if (matches && !firstVisibleId) {
                firstVisibleId = btn.dataset.reportId;
            }
        });
        if (firstVisibleId) {
            state.reportId = firstVisibleId;
            state.reportPage = 1;
            state.reportSortBy = '';
            state.reportSortDir = 'ASC';
        }
    }

    // ================= MOD DEMO / SAMBUNGAN ORACLE =================
    async function setDemoMode(enable) {
        try {
            await apiPost('set_mode', { demo_mode: Boolean(enable) });
            window.location.reload();
        } catch (err) {
            alert(err.message);
        }
    }

    async function testOracleConnection() {
        const box = $('testConnResult') || $('connTestResult');
        if (!box) return;
        box.style.display = 'block';
        box.className = 'alert-banner';
        box.innerHTML = '<div class="alert-text">Sedang menguji sambungan langsung ke Oracle 10g (ORCLVM55)...</div>';

        try {
            const res = await apiGet('test_connection');
            const info = res.data;
            box.className = info.ok ? 'alert-banner' : 'alert-banner alert-danger';
            if (info.ok) {
                box.style.borderColor = 'var(--accent-green)';
                box.innerHTML = `
                    <div class="alert-text" style="color:var(--text-primary);">
                        <strong style="color:var(--accent-green);">⚡ Sambungan Oracle Berjaya!</strong> (${info.latency_ms} ms)<br>
                        Pemacu PHP: <code>${escapeHtml(info.driver)}</code> · Versi Pelayan: <code>${escapeHtml(info.server_version || '-')}</code>
                    </div>
                `;
            } else {
                box.innerHTML = `
                    <div class="alert-text">
                        <strong style="color:var(--accent-red);">Sambungan Gagal</strong> (Pemacu: <code>${escapeHtml(info.driver)}</code>, Dikonfigurasi: ${info.configured ? 'Ya' : 'Belum'})<br>
                        ${escapeHtml(info.message)}
                    </div>
                `;
            }
        } catch (err) {
            box.className = 'alert-banner alert-danger';
            box.innerHTML = `<div class="alert-text">${escapeHtml(err.message)}</div>`;
        }
    }

    async function testAllOracleProfiles() {
        const box = $('testConnResult') || $('connTestResult');
        const tbody = $('profilesTestBody');
        if (box) {
            box.style.display = 'block';
            box.className = 'alert-banner';
            box.innerHTML = '<div class="alert-text">Sedang menguji kesemua 8 skema Oracle 10g...</div>';
        }
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Sedang menguji kesemua 8 skema Oracle 10g...</td></tr>';
        }

        try {
            const res = await apiGet('test_profiles');
            const rows = res.data || [];
            if (tbody) {
                tbody.innerHTML = rows.map(r => {
                    const pill = r.ok
                        ? '<span class="pill pill-green">Berjaya</span>'
                        : '<span class="pill pill-red">Gagal</span>';
                    const detail = r.ok ? (r.server_version || r.message) : r.message;
                    return `
                        <tr>
                            <td><strong>${escapeHtml((r.profile || '').toUpperCase())}</strong></td>
                            <td>${escapeHtml(r.label || '-')}</td>
                            <td>${pill}</td>
                            <td>${escapeHtml(detail)}</td>
                            <td class="num">${escapeHtml(r.latency_ms ?? '-')}</td>
                        </tr>
                    `;
                }).join('');
            }
            if (box) {
                let html = '<div class="alert-text" style="margin-bottom:8px;"><strong>Hasil Ujian 8 Skema Oracle 10g:</strong></div>';
                html += '<table class="data-table" style="font-size:12px;margin-top:6px;"><thead><tr><th>Skema</th><th>Label</th><th>Status</th><th>Maklumat Pelayan</th><th>Masa (ms)</th></tr></thead><tbody>';
                rows.forEach(r => {
                    const pill = r.ok ? '<span class="pill pill-green">Berjaya</span>' : '<span class="pill pill-red">Gagal</span>';
                    const detail = r.ok ? (r.server_version || r.message) : r.message;
                    html += `<tr><td><strong>${escapeHtml((r.profile || '').toUpperCase())}</strong></td><td>${escapeHtml(r.label || '-')}</td><td>${pill}</td><td>${escapeHtml(detail)}</td><td class="num">${escapeHtml(r.latency_ms ?? '-')}</td></tr>`;
                });
                html += '</tbody></table>';
                box.innerHTML = html;
            }
        } catch (err) {
            if (tbody) tbody.innerHTML = `<tr><td colspan="5" class="empty-state">${escapeHtml(err.message)}</td></tr>`;
            if (box) box.innerHTML = `<div class="alert-text alert-danger">${escapeHtml(err.message)}</div>`;
        }
    }

    // ================= AUTH MOCK =================
    function updateAuthUI(auth) {
        $('authBadge').textContent = auth.logged_in ? `Log Masuk: ${auth.id}` : 'Mod Tetamu';
        const pill = $('authStatusPill');
        pill.className = `pill ${auth.logged_in ? 'pill-green' : 'pill-cyan'}`;
        pill.textContent = auth.logged_in ? `Aktif: ${auth.id}` : 'Belum Log Masuk';
    }

    // ================= MODAL DRILLDOWN (ZOOM IN JALAN) =================
    let activeDrilldownJlkod = '';
    let drilldownAbortController = null;

    async function openStreetDrilldown(jlkod, jlnama) {
        if (!jlkod) return;
        activeDrilldownJlkod = String(jlkod);

        const modal = $('drilldownModal');
        if (!modal) return;

        const title = $('drilldownJalanTitle') || $('drilldownTitle');
        const code = $('drilldownJalanCode') || $('drilldownSubtitle');
        const timeMeta = $('drilldownMetaTime');
        const tbody = $('drilldownBody');
        const footerCount = $('drilldownFooterCount');
        const totalEl = $('drilldownTotalAkaun') || $('drilldownKpiRumah');
        const tgkEl = $('drilldownBakiTgk') || $('drilldownKpiTgk');
        const semEl = $('drilldownBakiSem') || $('drilldownKpiSemasa');
        const kadarEl = $('drilldownKadar') || $('drilldownKpiJumlah');

        const displayName = jlnama ? `${jlkod} - ${jlnama}` : `Kod Jalan ${jlkod}`;
        if (title) title.textContent = `Pecahan Akaun: ${displayName}`;
        if (code) code.textContent = `JALAN ${jlkod} (${displayName})`;
        if (timeMeta) timeMeta.textContent = '';
        if (footerCount) footerCount.textContent = '';

        if (totalEl) totalEl.textContent = '...';
        if (tgkEl) tgkEl.textContent = '...';
        if (semEl) semEl.textContent = '...';
        if (kadarEl) kadarEl.textContent = '...';

        if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="empty-state">Memuatkan rekod terperinci bagi jalan ini...</td></tr>';

        modal.classList.add('is-active');
        modal.style.setProperty('display', 'flex', 'important');
        modal.setAttribute('aria-hidden', 'false');

        if (drilldownAbortController) {
            drilldownAbortController.abort();
        }
        drilldownAbortController = new AbortController();

        try {
            const started = performance.now();
            const res = await apiGet('senarai', {
                jlkod: jlkod,
                page: 1,
                per_page: 200,
                sort_by: 'PEG_AKAUN',
                sort_dir: 'ASC'
            }, drilldownAbortController.signal);

            const d = res.data || {};
            const rows = d.rows || [];
            const elapsed = Math.round(performance.now() - started);

            if (code) {
                code.textContent = `JALAN ${jlkod} - ${displayName} (${fmtInt(d.total || rows.length)} akaun • ${elapsed}ms)`;
            }
            if (timeMeta) {
                timeMeta.textContent = `Masa: ${elapsed} ms`;
            }

            // Kira KPI agregat
            let sumTgk = 0;
            let sumSemasa = 0;
            let sumJumlah = 0;
            let sumTerimaTgk = 0;
            let sumTerimaSem = 0;

            rows.forEach(r => {
                sumTgk += Number(r.BAKI_TUNGGAKAN || 0);
                sumSemasa += Number(r.BAKI_SEMASA || 0);
                sumJumlah += Number(r.JUMLAH_BAKI || 0);
                sumTerimaTgk += Number(r.TERIMA_TUNGGAKAN || 0);
                sumTerimaSem += Number(r.TERIMA_SEMASA || 0);
            });

            if (totalEl) totalEl.textContent = fmtInt(d.total || rows.length);
            if (tgkEl) tgkEl.textContent = fmtRM(sumTgk);
            if (semEl) semEl.textContent = fmtRM(sumSemasa);
            if (kadarEl) {
                const bilTotal = sumTgk + sumSemasa + sumTerimaTgk + sumTerimaSem;
                const terimaTotal = sumTerimaTgk + sumTerimaSem;
                const pct = bilTotal > 0 ? ((terimaTotal / bilTotal) * 100).toFixed(1) : '0.0';
                kadarEl.textContent = `${pct}%`;
            }

            if (footerCount) {
                if ((d.total || 0) > rows.length) {
                    footerCount.textContent = `Memaparkan ${rows.length} daripada ${fmtInt(d.total)} akaun. Untuk semakan penuh, klik "Buka Penuh di Senarai Akaun".`;
                } else {
                    footerCount.textContent = `Memaparkan semua ${fmtInt(rows.length)} akaun bagi jalan ini.`;
                }
            }

            if (tbody) {
                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="10" class="empty-state">Tiada rekod akaun ditemui untuk kod jalan ini.</td></tr>';
                    return;
                }

                tbody.innerHTML = rows.map((r, i) => {
                    const statusClass = r.STATUS === 'Tertunggak'
                        ? 'pill-red'
                        : (r.STATUS === 'Lebih Bayar' ? 'pill-cyan' : 'pill-green');
                    const bakiClass = Number(r.JUMLAH_BAKI) > 0
                        ? 'val-danger'
                        : (Number(r.JUMLAH_BAKI) < 0 ? 'val-cyan' : 'val-success');

                    return `
                        <tr>
                            <td>${i + 1}</td>
                            <td><strong>${escapeHtml(r.PEG_AKAUN)}</strong></td>
                            <td class="num">${fmtRM(r.BIL_TUNGGAKAN)}</td>
                            <td class="num">${fmtRM(r.TERIMA_TUNGGAKAN)}</td>
                            <td class="num ${Number(r.BAKI_TUNGGAKAN) > 0 ? 'val-danger' : ''}">${fmtRM(r.BAKI_TUNGGAKAN)}</td>
                            <td class="num">${fmtRM(r.BIL_SEMASA)}</td>
                            <td class="num">${fmtRM(r.TERIMA_SEMASA)}</td>
                            <td class="num ${Number(r.BAKI_SEMASA) > 0 ? 'val-danger' : ''}">${fmtRM(r.BAKI_SEMASA)}</td>
                            <td class="num ${bakiClass}">${fmtRM(r.JUMLAH_BAKI)}</td>
                            <td><span class="pill ${statusClass}">${escapeHtml(r.STATUS)}</span></td>
                        </tr>
                    `;
                }).join('');
            }
        } catch (err) {
            if (err.name === 'AbortError') return;
            if (code) code.textContent = 'Ralat memuatkan rekod jalan: ' + err.message;
            if (tbody) tbody.innerHTML = `<tr><td colspan="10" class="empty-state" style="color:var(--accent-red);">${escapeHtml(err.message)}</td></tr>`;
        }
    }

    function closeStreetDrilldown() {
        const modal = $('drilldownModal');
        if (modal) {
            modal.classList.remove('is-active');
            modal.style.setProperty('display', 'none', 'important');
            modal.setAttribute('aria-hidden', 'true');
        }
        if (drilldownAbortController) {
            drilldownAbortController.abort();
            drilldownAbortController = null;
        }
    }

    // ================= PENGENDALI MENU KLASIK ePBT (DREAMWEAVER SPHB) =================
    function initEpbtMenu() {
        const modules = window.APP_BOOT?.modules || [];
        const reports = window.APP_BOOT?.reportsMeta || [];

        // 10 Butang Modul Sebelah Kiri
        document.querySelectorAll('.epbt-btn-module').forEach(btn => {
            btn.addEventListener('click', () => {
                const modId = btn.dataset.moduleId;
                selectEpbtModule(modId);
            });
        });

        // Tab Navigasi Paparan
        document.querySelectorAll('.epbt-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                switchTab(tab);
            });
        });

        // Handler untuk butang borang parameter
        if ($('epbtResetBtn')) {
            $('epbtResetBtn').addEventListener('click', () => {
                if ($('epbtDariAkaun')) $('epbtDariAkaun').value = '';
                if ($('epbtHinggaAkaun')) $('epbtHinggaAkaun').value = '';
                if ($('epbtJalanSelect')) $('epbtJalanSelect').value = '';
                if ($('epbtStatusSelect')) $('epbtStatusSelect').value = 'all';
                state.epbtPage = 1;
                state.epbtSortBy = '';
                state.epbtSortDir = 'ASC';
                loadEpbtReportData(true);
            });
        }

        if ($('epbtCetakBtn')) {
            $('epbtCetakBtn').addEventListener('click', () => {
                window.print();
            });
        }

        if ($('epbtJanaBtn')) {
            $('epbtJanaBtn').addEventListener('click', () => {
                state.epbtPage = 1;
                loadEpbtReportData(false);
            });
        }

        if ($('epbtOpenDashboardBtn')) {
            $('epbtOpenDashboardBtn').addEventListener('click', () => {
                switchTab('dashboard');
            });
        }

        if ($('epbtExportCsvBtn')) {
            $('epbtExportCsvBtn').addEventListener('click', () => {
                const repId = state.activeReportId || 'detail-tgkn-terimaan-jalan';
                const jlkod = $('epbtJalanSelect')?.value || '';
                const carian = $('epbtDariAkaun')?.value || '';
                window.location.href = `api.php?action=export_report_csv&report_id=${encodeURIComponent(repId)}&jlkod=${encodeURIComponent(jlkod)}&carian=${encodeURIComponent(carian)}`;
            });
        }

        if ($('btnCariDariAkaun')) {
            $('btnCariDariAkaun').addEventListener('click', () => {
                state.epbtPage = 1;
                loadEpbtReportData(false);
            });
        }
        if ($('btnCariHinggaAkaun')) {
            $('btnCariHinggaAkaun').addEventListener('click', () => {
                state.epbtPage = 1;
                loadEpbtReportData(false);
            });
        }

        // Handler untuk perubahan jalan & status
        if ($('epbtJalanSelect')) {
            $('epbtJalanSelect').addEventListener('change', () => {
                state.epbtPage = 1;
                loadEpbtReportData(false);
            });
        }
        if ($('epbtStatusSelect')) {
            $('epbtStatusSelect').addEventListener('change', () => {
                state.epbtPage = 1;
                loadEpbtReportData(false);
            });
        }

        // Handler kawalan pagination laporan ePBT
        if ($('epbtFirstPageBtn')) {
            $('epbtFirstPageBtn').addEventListener('click', () => {
                if (state.epbtPage > 1) {
                    state.epbtPage = 1;
                    loadEpbtReportData(false);
                }
            });
        }
        if ($('epbtPrevPageBtn')) {
            $('epbtPrevPageBtn').addEventListener('click', () => {
                if (state.epbtPage > 1) {
                    state.epbtPage--;
                    loadEpbtReportData(false);
                }
            });
        }
        if ($('epbtNextPageBtn')) {
            $('epbtNextPageBtn').addEventListener('click', () => {
                if (state.epbtPage < state.epbtTotalPages) {
                    state.epbtPage++;
                    loadEpbtReportData(false);
                }
            });
        }
        if ($('epbtLastPageBtn')) {
            $('epbtLastPageBtn').addEventListener('click', () => {
                if (state.epbtPage < state.epbtTotalPages) {
                    state.epbtPage = state.epbtTotalPages;
                    loadEpbtReportData(false);
                }
            });
        }
        if ($('epbtPerPageSelect')) {
            $('epbtPerPageSelect').addEventListener('change', (e) => {
                state.epbtPerPage = Number(e.target.value) || 25;
                state.epbtPage = 1;
                loadEpbtReportData(false);
            });
        }

        // Bind untuk pautan submenu pramuat (pre-rendered)
        bindSubmenuLinks();

        // Mulakan pilihan modul lalai: taksiran
        selectEpbtModule('taksiran', true);
    }

    function bindSubmenuLinks() {
        document.querySelectorAll('#epbtSubmenuList a').forEach(a => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                const view = a.dataset.view;
                const repId = a.dataset.reportId;
                const ptr = a.dataset.pointer;
                const title = a.dataset.title || a.textContent.trim();
                if (view) {
                    switchTab(view);
                } else if (repId) {
                    selectEpbtReport(repId, title, ptr, false);
                }
            });
        });
    }

    function selectEpbtModule(moduleId, isInitial = false) {
        state.activeModule = moduleId;
        document.querySelectorAll('.epbt-btn-module').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.moduleId === moduleId);
        });

        const modules = window.APP_BOOT?.modules || [];
        const mod = modules.find(m => m.id === moduleId) || modules[0];
        if (!mod) return;

        if ($('activeModuleHeader')) {
            $('activeModuleHeader').textContent = (mod.code || mod.title || moduleId).toUpperCase();
        }

        if ($('epbtSubmenuHeading')) {
            $('epbtSubmenuHeading').textContent = `Cetakan / Laporan Modul ${mod.title || mod.code}`;
        }

        renderEpbtSubmenuList(mod);

        // Pilih item pertama yang sah
        const firstCategory = (mod.menu_categories && mod.menu_categories[0]) || null;
        const firstItem = firstCategory && firstCategory.items && firstCategory.items[0];
        if (firstItem && firstItem.report_id) {
            selectEpbtReport(firstItem.report_id, firstItem.title, firstItem.pointer, isInitial);
        }
    }

    function renderEpbtSubmenuList(mod) {
        const listEl = $('epbtSubmenuList');
        if (!listEl) return;
        listEl.innerHTML = '';

        const categories = mod.menu_categories || [];
        if (categories.length === 0) {
            listEl.innerHTML = '<li><span class="tulisankelabu">Tiada laporan berdaftar untuk modul ini.</span></li>';
            return;
        }

        categories.forEach(cat => {
            if (categories.length > 1) {
                const headerLi = document.createElement('li');
                headerLi.style.fontWeight = 'bold';
                headerLi.style.marginTop = '6px';
                headerLi.style.color = 'var(--text-primary)';
                headerLi.textContent = cat.title || '';
                listEl.appendChild(headerLi);
            }

            (cat.items || []).forEach(item => {
                const li = document.createElement('li');
                li.innerHTML = `
                    <span style="font-weight:bold;color:#2e2e2e;margin-right:4px;">+</span>
                    <a href="javascript:void(0)" class="tulisankelabu ${item.highlight ? 'highlight-new' : ''}">
                        ${escapeHtml(item.title)}
                    </a>
                `;

                li.querySelector('a').addEventListener('click', (e) => {
                    e.preventDefault();
                    if (item.view) {
                        switchTab(item.view);
                    } else if (item.report_id) {
                        selectEpbtReport(item.report_id, item.title, item.pointer, false);
                    }
                });

                listEl.appendChild(li);
            });
        });
    }

    function selectEpbtReport(reportId, title, pointer, isInitial = false) {
        state.activeReportId = reportId;
        state.reportId = reportId;
        state.epbtPage = 1;
        state.epbtSortBy = '';
        state.epbtSortDir = 'ASC';

        if ($('epbtParamHeading')) {
            $('epbtParamHeading').textContent = title || reportId;
        }

        const reports = window.APP_BOOT?.reportsMeta || [];
        const rep = reports.find(r => r.id === reportId);

        const pointerName = rep?.pointer_report || pointer || (reportId === 'detail-tgkn-terimaan-jalan' ? 'DETAIL_TGKN_TERIMAAN_IKUT_JALAN' : (reportId === 'biltahunakaun' ? 'Itd05b' : reportId));
        if ($('epbtPointerName')) {
            $('epbtPointerName').textContent = pointerName;
        }
        if ($('epbtHiddenField')) {
            $('epbtHiddenField').value = pointerName;
        }

        if ($('rowPilihJalan')) {
            $('rowPilihJalan').style.display = (rep && rep.supports_jlkod === false) ? 'none' : 'table-row';
        }

        loadEpbtReportData(isInitial);
    }

    async function loadEpbtReportData(isInitialAutoLoad = false) {
        const thead = $('epbtResultThead');
        const tbody = $('epbtResultTbody');
        const metaEl = $('epbtResultMeta');
        if (!thead || !tbody) return;

        const reportId = state.activeReportId || 'detail-tgkn-terimaan-jalan';
        const jlkod = $('epbtJalanSelect')?.value || '';
        const dariAkaun = $('epbtDariAkaun')?.value.trim() || '';
        const status = $('epbtStatusSelect')?.value || 'all';

        // Hanya jika masa mula buka laman & tiada jalan dipilih
        if (isInitialAutoLoad && !jlkod && !dariAkaun) {
            tbody.innerHTML = '<tr><td colspan="10" class="empty-state">Sila pilih pilihan jalan atau klik butang <strong>"Papar Jadual &amp; Data Live"</strong> untuk memuatkan rekod terus dari Oracle 10g.</td></tr>';
            if (metaEl) metaEl.textContent = 'Sedia (Pilih jalan atau klik Papar Jadual)';
            return;
        }

        const jalanLabel = jlkod ? `Kod Jalan ${jlkod}` : 'Semua Jalan (Serentak)';
        tbody.innerHTML = `<tr><td colspan="10" class="empty-state"><div style="padding:16px;">⚡ Menarik data langsung bagi <strong>${escapeHtml(jalanLabel)}</strong> daripada Oracle 10g (ORCLVM55)... Sila tunggu sebentar.</div></td></tr>`;
        if (metaEl) metaEl.textContent = `Menghubungi Oracle 10g (${jalanLabel})...`;

        try {
            const params = {
                report_id: reportId,
                jlkod: jlkod,
                carian: dariAkaun,
                status: status,
                page: state.epbtPage || 1,
                per_page: state.epbtPerPage || 25,
                sort_by: state.epbtSortBy || undefined,
                sort_dir: state.epbtSortDir || 'ASC'
            };
            const res = await apiGet('run_report', params);
            const d = res.data || {};
            const cols = (d.report && d.report.columns) || d.columns || [];
            let rows = d.rows || [];

            const curPage = Number(d.page || state.epbtPage) || 1;
            const totalPages = Math.max(1, Number(d.total_pages || state.epbtTotalPages) || 1);
            state.epbtPage = curPage;
            state.epbtTotalPages = totalPages;

            const activeSortBy = (d.sort_by || state.epbtSortBy || '').toUpperCase();
            const activeSortDir = (d.sort_dir || state.epbtSortDir || 'ASC').toUpperCase();
            state.epbtSortBy = activeSortBy;
            state.epbtSortDir = activeSortDir;

            // Tapis status jika dipilih dalam borang parameter
            if (status !== 'all' && rows.length > 0) {
                if (status === 'tertunggak') {
                    rows = rows.filter(r => (Number(r.BAKI_TUNGGAKAN) || Number(r.JUMLAH_TUNGGAKAN) || 0) > 0 || (Number(r.BAKI_SEMASA) || 0) > 0);
                } else if (status === 'selesai') {
                    rows = rows.filter(r => (Number(r.BAKI_TUNGGAKAN) || Number(r.JUMLAH_TUNGGAKAN) || 0) <= 0 && (Number(r.BAKI_SEMASA) || 0) <= 0);
                } else if (status === 'lebih_bayar') {
                    rows = rows.filter(r => (Number(r.BAKI_SEMASA) || 0) < 0 || (Number(r.BAKI_TUNGGAKAN) || 0) < 0);
                }
            }

            // Render Head jika columns dikembalikan dengan penunjuk isihan (sortable header)
            if (cols.length > 0) {
                thead.innerHTML = `<tr><th>#</th>` + cols.map(c => {
                    const key = String(c.key).toUpperCase();
                    const isNum = c.type === 'currency' || c.type === 'number' || c.type === 'integer' || c.type === 'percent';
                    const isActive = key === activeSortBy;
                    const arrow = isActive ? (activeSortDir === 'DESC' ? '▼' : '▲') : '⇅';
                    return `<th class="sortable ${isNum ? 'num' : ''} ${isActive ? 'sort-active' : ''}" data-sort="${escapeHtml(key)}" title="Klik untuk isih ${escapeHtml(c.label || c.key)}">${escapeHtml(c.label || c.key)}<span class="sort-ind">${arrow}</span></th>`;
                }).join('') + `</tr>`;

                thead.querySelectorAll('th.sortable').forEach(th => {
                    th.addEventListener('click', () => {
                        const col = th.dataset.sort;
                        if (state.epbtSortBy === col) {
                            state.epbtSortDir = state.epbtSortDir === 'ASC' ? 'DESC' : 'ASC';
                        } else {
                            state.epbtSortBy = col;
                            state.epbtSortDir = 'ASC';
                        }
                        state.epbtPage = 1;
                        loadEpbtReportData(false);
                    });
                });
            }

            // Render Body
            if (rows.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${(cols.length || 9) + 1}" class="empty-state">Tiada rekod sepadan ditemui untuk pilihan jalan atau parameter ini di dalam Oracle 10g.</td></tr>`;
            } else {
                tbody.innerHTML = rows.map((r, idx) => {
                    const rowNum = r.ROW_NUM ?? (((state.epbtPage - 1) * state.epbtPerPage) + idx + 1);
                    const isTotal = String(r.KAWASAN ?? r.kawasan ?? '').toUpperCase().includes('JUMLAH') || String(r.ROW_TYPE ?? '').toUpperCase() === 'TOTAL';
                    const rowClass = isTotal ? ' class="row-grand-total"' : '';
                    const cells = cols.map(c => {
                        const key = String(c.key).toUpperCase();
                        const val = r[key] ?? '';
                        if (!isTotal && (key === 'JALAN_TAMAN_KAMPUNG' || key === 'JLN_JNAMA' || key === 'KOD_JALAN' || key === 'PEG_JLKOD')) {
                            const kodJln = r.KOD_JALAN ?? r.PEG_JLKOD ?? '';
                            if (kodJln) {
                                return `<td><a href="javascript:void(0)" class="link-road" data-jlkod="${escapeHtml(kodJln)}" data-jnama="${escapeHtml(r.JALAN_TAMAN_KAMPUNG ?? r.JLN_JNAMA ?? '')}" style="color:var(--accent-indigo);font-weight:600;text-decoration:none;" title="Lihat Pecahan Akaun Jalan ${escapeHtml(kodJln)}">${escapeHtml(val)}</a></td>`;
                            }
                        }
                        if (c.type === 'currency') {
                            const num = Number(val || 0);
                            const valClass = num > 0 && (key.includes('BAKI') || key.includes('TUNGGAK')) ? 'val-danger' : (num < 0 ? 'val-cyan' : '');
                            return `<td class="num ${valClass}">${fmtRM(val)}</td>`;
                        }
                        if (c.type === 'number' || c.type === 'integer') {
                            return `<td class="num">${fmtInt(val)}</td>`;
                        }
                        if (c.type === 'percent') {
                            const num = parseFloat(val);
                            const cls = num > 30 ? 'val-danger' : (num > 20 ? 'val-warn' : 'val-success');
                            return `<td class="num ${cls}">${Number.isFinite(num) ? num.toFixed(2) + '%' : escapeHtml(val)}</td>`;
                        }
                        return `<td>${escapeHtml(val)}</td>`;
                    }).join('');
                    return `<tr${rowClass}><td>${isTotal ? '★' : rowNum}</td>${cells}</tr>`;
                }).join('');

                // Pasang pautan drilldown jalan jika ada
                tbody.querySelectorAll('.link-road').forEach(a => {
                    a.addEventListener('click', (e) => {
                        e.preventDefault();
                        const jlk = a.dataset.jlkod;
                        const jnm = a.dataset.jnama || a.textContent.trim();
                        if (jlk) openStreetDrilldown(jlk, jnm);
                    });
                });
            }

            // Kemas kini maklumat Pagination
            if ($('epbtPaginationInfo')) {
                const totalRows = Number(d.total) || rows.length;
                $('epbtPaginationInfo').textContent = `Halaman ${fmtInt(curPage)} daripada ${fmtInt(totalPages)} (${fmtInt(totalRows)} rekod)`;
            }
            if ($('epbtFirstPageBtn')) {
                $('epbtFirstPageBtn').disabled = curPage <= 1;
            }
            if ($('epbtPrevPageBtn')) {
                $('epbtPrevPageBtn').disabled = curPage <= 1;
            }
            if ($('epbtNextPageBtn')) {
                $('epbtNextPageBtn').disabled = curPage >= totalPages;
            }
            if ($('epbtLastPageBtn')) {
                $('epbtLastPageBtn').disabled = curPage >= totalPages;
            }
            if ($('epbtPerPageSelect')) {
                $('epbtPerPageSelect').value = String(state.epbtPerPage);
            }

            if (metaEl) {
                metaEl.textContent = `Dijumpai ${fmtInt(d.total || rows.length)} rekod (${d.elapsed_ms || 0} ms · ORACLE 10G) — ${jalanLabel}`;
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="10" class="empty-state" style="color:var(--accent-red);">Ralat: ${escapeHtml(err.message)}</td></tr>`;
            if (metaEl) metaEl.textContent = 'Ralat memuatkan data';
        }
    }

    // ================= EVENT LISTENERS =================
    function bindEvents() {
        try {
            initEpbtMenu();

            document.querySelectorAll('.nav-item').forEach(btn => {
                btn.addEventListener('click', () => switchTab(btn.dataset.tab));
            });

            on('sidebarToggleBtn', 'click', toggleSidebar);
            on('mobileMenuBtn', 'click', toggleSidebar);
            on('sidebarBackdrop', 'click', () => {
                document.body.classList.remove('sidebar-mobile-open');
            });

            on('themeToggleBtn', 'click', toggleTheme);
            on('settingsThemeBtn', 'click', toggleTheme);
            on('settingsSidebarBtn', 'click', () => {
                document.body.classList.toggle('sidebar-expanded');
            });

            const applyFilters = () => {
                if ($('filterJalan')) state.jlkod = $('filterJalan').value;
                if ($('filterAkaun')) state.akaun = $('filterAkaun').value.trim();
                if ($('filterStatus')) state.status = $('filterStatus').value;
                state.page = 1;
                state.reportPage = 1;
                state.loadedTabs = {};
                clientPageCache.clear();
                refreshActiveTab();
            };

            on('applyFilterBtn', 'click', applyFilters);
            on('filterJalan', 'change', applyFilters);
            on('filterStatus', 'change', applyFilters);

            let searchTimer = null;
            if ($('filterAkaun')) {
                $('filterAkaun').addEventListener('input', () => {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(applyFilters, 380);
                });
            }

            on('resetFilterBtn', 'click', () => {
                if ($('filterJalan')) $('filterJalan').value = '';
                if ($('filterAkaun')) $('filterAkaun').value = '';
                if ($('filterStatus')) $('filterStatus').value = 'all';
                applyFilters();
            });

            on('forceRefreshBtn', 'click', () => {
                state.forceRefresh = true;
                state.loadedTabs = {};
                clientPageCache.clear();
                refreshActiveTab();
            });

            document.querySelectorAll('#senaraiTable thead th.sortable').forEach(th => {
                th.addEventListener('click', () => {
                    const col = th.dataset.sort;
                    if (state.sortBy === col) {
                        state.sortDir = state.sortDir === 'ASC' ? 'DESC' : 'ASC';
                    } else {
                        state.sortBy = col;
                        state.sortDir = 'ASC';
                    }
                    state.page = 1;
                    loadSenarai();
                });
            });

            on('perPageSelect', 'change', (e) => {
                state.perPage = Number(e.target.value) || 25;
                state.page = 1;
                loadSenarai();
            });

            on('firstPageBtn', 'click', () => {
                state.page = 1;
                loadSenarai();
            });

            on('prevPageBtn', 'click', () => {
                state.page = Math.max(1, state.page - 1);
                loadSenarai();
            });

            on('nextPageBtn', 'click', () => {
                state.page++;
                loadSenarai();
            });

            on('lastPageBtn', 'click', () => {
                if (state.totalPages > 1) state.page = state.totalPages;
                loadSenarai();
            });

            on('exportCsvBtn', 'click', () => {
                const qs = buildFilterQuery({
                    action: 'export_csv',
                    sort_by: state.sortBy,
                    sort_dir: state.sortDir
                });
                window.location.href = `api.php?${qs}`;
            });

            on('printTableBtn', 'click', () => window.print());
            on('printSenaraiBtn', 'click', () => window.print());

            on('quickOpenDashboardBtn', 'click', () => switchTab('dashboard'));
            on('quickOpenReportsBtn', 'click', () => switchTab('laporan'));

            document.querySelectorAll('.module-tile').forEach(tile => {
                tile.addEventListener('click', () => {
                    const targetTab = tile.dataset.targetTab;
                    const moduleId = tile.dataset.moduleId;
                    const title = tile.dataset.moduleTitle;
                    const reportCount = Number(tile.dataset.reportCount || 0);
                    const notice = $('portalModuleNotice');

                    if (targetTab === 'laporan' || (!targetTab && reportCount > 0)) {
                        if (notice) notice.style.display = 'none';
                        filterReportsByModule(moduleId);
                        state.loadedTabs.laporan = false;
                        switchTab('laporan');
                        return;
                    }
                    if (targetTab) {
                        if (notice) notice.style.display = 'none';
                        switchTab(targetTab);
                        return;
                    }

                    if (notice) {
                        notice.style.display = 'flex';
                        notice.innerHTML = `
                            <div class="alert-text">
                                <strong>Modul ${escapeHtml(title)} sedia ditambah:</strong>
                                Salin <code>modules/reports/_template.example.php</code> dan tetapkan <code>'module_id' => '${escapeHtml(moduleId)}'</code> untuk mengaktifkan laporan SQL di bawah modul ini.
                            </div>
                            <button class="btn-secondary" type="button" onclick="document.getElementById('portalModuleNotice').style.display='none'">Tutup</button>
                        `;
                    }
                });
            });

            on('reportModuleFilter', 'change', (e) => {
                filterReportsByModule(e.target.value);
                loadDynamicReport();
            });

            document.querySelectorAll('.report-card-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    state.reportId = btn.dataset.reportId;
                    state.reportPage = 1;
                    state.reportSortBy = '';
                    state.reportSortDir = 'ASC';
                    loadDynamicReport();
                });
            });

            on('reportPerPageSelect', 'change', (e) => {
                state.reportPerPage = Number(e.target.value) || 25;
                state.reportPage = 1;
                loadDynamicReport();
            });

            on('reportFirstBtn', 'click', () => {
                state.reportPage = 1;
                loadDynamicReport();
            });

            on('reportPrevBtn', 'click', () => {
                state.reportPage = Math.max(1, state.reportPage - 1);
                loadDynamicReport();
            });

            on('reportNextBtn', 'click', () => {
                state.reportPage++;
                loadDynamicReport();
            });

            on('reportLastBtn', 'click', () => {
                if (state.reportTotalPages > 1) state.reportPage = state.reportTotalPages;
                loadDynamicReport();
            });

            on('exportReportCsvBtn', 'click', () => {
                if (!state.reportId) return;
                const qs = new URLSearchParams({
                    action: 'export_report_csv',
                    report_id: state.reportId,
                    jlkod: state.jlkod,
                    carian: state.akaun,
                    sort_by: state.reportSortBy,
                    sort_dir: state.reportSortDir
                }).toString();
                window.location.href = `api.php?${qs}`;
            });

            on('printReportBtn', 'click', () => window.print());

            on('switchDemoBtn', 'click', () => setDemoMode(true));
            on('switchLiveBtn', 'click', () => setDemoMode(false));
            on('settingsModeBtn', 'click', () => setDemoMode(!state.demoMode));

            on('testConnBtn', 'click', testOracleConnection);
            on('testProfilesBtn', 'click', testAllOracleProfiles);
            on('testAllProfilesBtn', 'click', testAllOracleProfiles);

            on('adminRefreshBtn', 'click', async () => {
                const box = $('testConnResult');
                if (box) {
                    box.style.display = 'block';
                    box.className = 'alert-banner';
                    box.innerHTML = '<div class="alert-text">Mengosongkan cache sistem...</div>';
                }
                try {
                    clientPageCache.clear();
                    state.loadedTabs = {};
                    state.forceRefresh = true;
                    await apiPost('clear_cache', {});
                    if (box) {
                        box.innerHTML = '<div class="alert-text" style="color:var(--accent-green);"><strong>⚡ Cache sistem berjaya dikosongkan.</strong> Sedia menarik data terkini dari Oracle 10g.</div>';
                    }
                } catch (e) {
                    if (box) box.innerHTML = `<div class="alert-text" style="color:var(--accent-red);">Ralat: ${escapeHtml(e.message)}</div>`;
                }
            });

            on('loginSubmitBtn', 'click', async () => {
                try {
                    const idVal = $('loginUserId')?.value || '';
                    const passVal = $('loginPassword')?.value || '';
                    const res = await apiPost('auth', { op: 'login', id: idVal, password: passVal });
                    updateAuthUI(res.auth);
                    alert(res.message || 'Berjaya log masuk.');
                } catch (err) {
                    alert(err.message);
                }
            });

            on('logoutSubmitBtn', 'click', async () => {
                try {
                    const res = await apiPost('auth', { op: 'logout' });
                    updateAuthUI(res.auth);
                    alert(res.message || 'Berjaya log keluar.');
                } catch (err) {
                    alert(err.message);
                }
            });

            on('mockLoginForm', 'submit', async (e) => {
                e.preventDefault();
                try {
                    const res = await apiPost('auth', {
                        op: 'login',
                        id: $('loginId').value,
                        password: $('loginPass').value
                    });
                    updateAuthUI(res.auth);
                    if ($('authFeedback')) $('authFeedback').textContent = res.message;
                } catch (err) {
                    if ($('authFeedback')) $('authFeedback').textContent = err.message;
                }
            });

            on('mockLogoutBtn', 'click', async () => {
                const res = await apiPost('auth', { op: 'logout' });
                updateAuthUI(res.auth);
                if ($('authFeedback')) $('authFeedback').textContent = res.message;
            });

            on('mockUpdateForm', 'submit', async (e) => {
                e.preventDefault();
                try {
                    const res = await apiPost('auth', {
                        op: 'update_mock',
                        new_id: $('newMockId').value,
                        new_password: $('newMockPass').value
                    });
                    if ($('loginId')) $('loginId').value = res.auth.id;
                    if ($('newMockPass')) $('newMockPass').value = '';
                    updateAuthUI(res.auth);
                    if ($('authFeedback')) $('authFeedback').textContent = res.message;
                } catch (err) {
                    if ($('authFeedback')) $('authFeedback').textContent = err.message;
                }
            });

            // Event Modal Drilldown Jalan
            on('drilldownCloseBtn', 'click', closeStreetDrilldown);
            on('drilldownCloseFooterBtn', 'click', closeStreetDrilldown);
            on('drilldownModal', 'click', (e) => {
                if (e.target === $('drilldownModal')) {
                    closeStreetDrilldown();
                }
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && $('drilldownModal') && $('drilldownModal').style.display !== 'none') {
                    closeStreetDrilldown();
                }
            });

            on('drilldownOpenSenaraiBtn', 'click', () => {
                if (!activeDrilldownJlkod) return;
                const targetJlkod = activeDrilldownJlkod;
                closeStreetDrilldown();
                if ($('filterJalan')) $('filterJalan').value = targetJlkod;
                state.jlkod = targetJlkod;
                state.page = 1;
                state.loadedTabs.senarai = false;
                clientPageCache.clear();
                switchTab('senarai');
                loadSenarai();
            });

            on('drilldownExportCsvBtn', 'click', () => {
                if (!activeDrilldownJlkod) return;
                window.location.href = `api.php?action=export_csv&jlkod=${encodeURIComponent(activeDrilldownJlkod)}`;
            });
        } catch (e) {
            console.error('Error in bindEvents:', e);
        }
    }

    function registerPwa() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js').catch(() => {});
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        bindEvents();
        registerPwa();
        const urlTab = new URLSearchParams(window.location.search).get('tab');
        const initialTab = urlTab && TAB_TITLES[urlTab] ? urlTab : 'menu-epbt';
        switchTab(initialTab);
    });
})();

