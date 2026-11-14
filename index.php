<?php
declare(strict_types=1);

require_once __DIR__ . '/src/Bootstrap.php';

$ctx = Bootstrap::init();
$config = $ctx['config'];
/** @var OracleClient $client */
$client = $ctx['client'];
/** @var TunggakanRepository $repo */
$repo = $ctx['repo'];
/** @var ReportEngine $reportEngine */
$reportEngine = $ctx['report_engine'];
$demoMode = (bool) $ctx['demo_mode'];
$auth = Bootstrap::getPublicAuthState();
$sqlRef = Bootstrap::getSqlReference();
$modules = $reportEngine->getModules();
$reportsMeta = $reportEngine->getReportsMeta();

$initError = null;
$jalanList = [];
try {
    $jalanList = $repo->getJalanList();
} catch (Throwable $e) {
    $initError = $e->getMessage();
}

$driverName = $client->getAvailableDriver() ?? 'tiada';
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ms" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#181e29">
    <meta name="description" content="Sistem Dashboard Tunggakan & Terimaan Ikut Jalan berasaskan PHP dan Oracle 10g - Portal Laporan MBAS.">
    <title><?= $h($config['app']['name']) ?> | MBAS & Oracle 10g</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="assets/images/mbas_crest.svg">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
</head>
<body>

<!-- ================= 1. HEADER RASMI MAJLIS BANDARAYA ALOR SETAR ================= -->
<header class="mbas-portal-header">
    <div class="mbas-landmarks-banner" title="Pemandangan Mercu Tanda Bandaraya Alor Setar">
        <img src="assets/images/alor_setar_landmarks.svg" alt="Mercu Tanda Alor Setar (Masjid Zahir & Menara Alor Setar)">
    </div>

    <div class="mbas-header-center">
        <h1 class="mbas-main-title">MAJLIS BANDARAYA ALOR SETAR</h1>
        <div class="mbas-main-subtitle">Jln Kolam Air, 05675 Alor Setar, Kedah Darul Aman</div>
    </div>

    <div class="mbas-header-right">
        <?php if (file_exists(__DIR__ . '/assets/images/logo_kedah.jpg')): ?>
            <img src="assets/images/logo_kedah.jpg" alt="Jata Negeri Kedah" class="mbas-crest-img" style="max-height:56px;">
        <?php endif; ?>
        <img src="assets/images/mbas_crest.svg" alt="Jata Rasmi MBAS" class="mbas-crest-img" title="Majlis Bandaraya Alor Setar">
    </div>
</header>

<!-- ================= 2. MENUBAR UTAMA & STATUS PELAYAN (ORCLVM55) ================= -->
<div class="epbt-top-menubar">
    <div class="epbt-menu-title-left">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        <span>MENU UTAMA</span>
    </div>

    <div class="epbt-menu-title-center">
        LAPORAN - LAPORAN <span id="activeModuleHeader">TAKSIRAN</span>
    </div>

    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <span class="badge <?= $demoMode ? 'badge-demo' : ($initError ? 'badge-error' : 'badge-live') ?>" id="sourceBadge" style="font-size:11.5px;padding:3px 10px;">
            <span class="dot"></span>
            <span id="sourceBadgeText">
                <?php if ($demoMode): ?>
                    Mod Demo (Data Contoh)
                <?php elseif ($initError): ?>
                    Oracle Belum Bersambung
                <?php else: ?>
                    Oracle 10g (<?= $h($config['oracle']['tns_alias']) ?>: <?= $h($config['oracle']['host']) ?>)
                <?php endif; ?>
            </span>
        </span>

        <span class="badge badge-info" id="authBadge" style="font-size:11px;padding:3px 8px;">
            <?= $auth['logged_in'] ? 'User: ' . $h($auth['id']) : 'Mod Tetamu' ?>
        </span>

        <button class="icon-btn" id="themeToggleBtn" type="button" title="Tukar Tema Light / Dark" style="height:28px;padding:0 8px;font-size:11px;">
            <span id="themeIcon">🌙</span>
            <span id="themeLabel">Gelap</span>
        </button>
    </div>
</div>

<!-- ================= 3. SUSUN ATUR UTAMA (10 MODUL SEBELAH KIRI + KANDUNGAN KANAN) ================= -->
<div class="epbt-layout-container">

    <!-- SEBELAH KIRI: 10 BUTANG MODUL ePBT (DIAMOND ICON EMBLEM) -->
    <aside class="epbt-sidebar-column" aria-label="Senarai Modul ePBT">
        <div class="epbt-sidebar-heading">Sistem Laporan</div>
        <nav class="epbt-menu-nav">
            <?php foreach ($modules as $mod): ?>
                <?php
                $modId = (string) ($mod['id'] ?? '');
                $isDefaultActive = ($modId === 'taksiran');
                ?>
                <button
                    type="button"
                    class="epbt-btn-module <?= $isDefaultActive ? 'active' : '' ?>"
                    data-module-id="<?= $h($modId) ?>"
                    data-module-title="<?= $h((string) ($mod['title'] ?? '')) ?>"
                    data-module-code="<?= $h((string) ($mod['code'] ?? '')) ?>"
                >
                    <img src="assets/images/epbt_diamond.svg" class="epbt-diamond-icon" alt="ePBT Diamond">
                    <span><?= $h((string) ($mod['code'] ?? $mod['title'] ?? '')) ?></span>
                </button>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- SEBELAH KANAN: KANDUNGAN & PAPARAN SISTEM -->
    <main class="epbt-content-column">

        <!-- TAB NAVIGASI PAPARAN -->
        <div class="epbt-view-nav-tabs no-print">
            <button class="epbt-tab-btn active" data-tab="menu-epbt">📑 Menu Laporan ePBT</button>
            <button class="epbt-tab-btn" data-tab="dashboard">📊 Dashboard Utama</button>
            <button class="epbt-tab-btn" data-tab="analisis">📈 Analisis Jalan EIS</button>
            <button class="epbt-tab-btn" data-tab="senarai">📋 Senarai Akaun Pegangan</button>
            <button class="epbt-tab-btn" data-tab="pentadbiran">⚙️ Pentadbiran &amp; Oracle</button>
            <button class="epbt-tab-btn" data-tab="tetapan">🔑 Tetapan Sistem</button>
        </div>

        <!-- BANNER STATUS / DIAGNOSTIK -->
        <?php if ($demoMode): ?>
            <div class="alert-banner alert-warning" id="modeBanner" style="margin-bottom:6px;">
                <div class="alert-text">
                    <strong>Mod Demo Aktif:</strong> Data yang dipaparkan ialah <strong>data contoh</strong> berasaskan sampel Jalan 142 (bukan tarikan langsung pelayan <code><?= $h($config['oracle']['host']) ?></code>).
                </div>
                <button class="btn-secondary" type="button" id="switchLiveBtn">Cuba Sambung Oracle Langsung</button>
            </div>
        <?php elseif ($initError !== null): ?>
            <div class="alert-banner alert-danger" id="modeBanner" style="margin-bottom:6px;">
                <div class="alert-text">
                    <strong>Sambungan Oracle 10g (<?= $h($config['oracle']['tns_alias']) ?>) belum aktif:</strong>
                    <span><?= $h($initError) ?></span><br>
                    <small>Pastikan pemacu <code>pdo_oci</code> atau <code>oci8</code> aktif dalam <code>php.ini</code>. Klik butang untuk mod demo atau uji di tab Pentadbiran.</small>
                </div>
                <button class="btn-primary" type="button" id="switchDemoBtn">Aktifkan Mod Demo (Data Contoh)</button>
            </div>
        <?php endif; ?>

        <!-- ================= PANEL 1: MENU KLASIK ePBT & BORANG PARAMETER ================= -->
        <section class="tab-panel active" id="tab-menu-epbt">

            <!-- KOTAK PILIHAN SUB-MENU (Gaya menubillesentahun.php - Tutorial Page 5) -->
            <div class="epbt-box-card">
                <div class="epbt-card-header-bar" id="epbtSubmenuHeading">
                    Cetakan / Laporan Modul TAKSIRAN (Oracle 10g)
                </div>
                <div class="epbt-card-inner-box">
                    <div class="epbt-prompt-text" id="epbtSubmenuPrompt">
                        Sila Pilih Bil / Laporan Yang Dikehendaki :-
                    </div>
                    <ul class="epbt-menu-list" id="epbtSubmenuList">
                        <?php
                        $activeMod = null;
                        foreach ($modules as $m) {
                            if (($m['id'] ?? '') === 'taksiran') { $activeMod = $m; break; }
                        }
                        $cats = $activeMod['menu_categories'] ?? [];
                        foreach ($cats as $cat):
                            if (count($cats) > 1): ?>
                                <li style="font-weight:bold;margin-top:6px;color:var(--text-primary);"><?= $h($cat['title'] ?? '') ?></li>
                            <?php endif;
                            foreach (($cat['items'] ?? []) as $it): ?>
                                <li>
                                    <span style="font-weight:bold;color:#2e2e2e;margin-right:4px;">+</span>
                                    <a href="javascript:void(0)" class="tulisankelabu <?= !empty($it['default']) ? 'highlight-new' : '' ?>"
                                       data-report-id="<?= $h($it['report_id'] ?? '') ?>"
                                       data-view="<?= $h($it['view'] ?? '') ?>"
                                       data-pointer="<?= $h($it['pointer'] ?? '') ?>"
                                       data-title="<?= $h($it['title'] ?? '') ?>">
                                        <?= $h($it['title'] ?? '') ?>
                                    </a>
                                </li>
                            <?php endforeach;
                        endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- KOTAK BORANG PARAMETER (Gaya biltahunakaun.php - Tutorial Page 3 & 4) -->
            <div class="epbt-box-card" id="epbtParamCard" style="margin-top:14px;">
                <div class="epbt-card-header-bar" id="epbtParamHeading">
                    Detail Tunggakan &amp; Terimaan Ikut Jalan (Live Oracle 10g)
                </div>
                <div class="epbt-card-inner-box">
                    <form id="epbtParamForm" onsubmit="return false;">
                        <input type="hidden" id="epbtHiddenField" value="DETAIL_TGKN_TERIMAAN_IKUT_JALAN">

                        <div class="epbt-param-panel">
                            <table class="epbt-param-table">
                                <tr id="rowDariAkaun">
                                    <td class="epbt-param-label">Dari Akaun :</td>
                                    <td>
                                        <input type="text" id="epbtDariAkaun" class="epbt-input-field" placeholder="Cth: 17017 atau 1001">
                                    </td>
                                    <td>
                                        <button type="button" class="epbt-btn-carian" id="btnCariDariAkaun">Carian</button>
                                    </td>
                                </tr>
                                <tr id="rowHinggaAkaun">
                                    <td class="epbt-param-label">Hingga Akaun :</td>
                                    <td>
                                        <input type="text" id="epbtHinggaAkaun" class="epbt-input-field" placeholder="Cth: 17017999">
                                    </td>
                                    <td>
                                        <button type="button" class="epbt-btn-carian" id="btnCariHinggaAkaun">Carian</button>
                                    </td>
                                </tr>
                                <tr id="rowPilihJalan">
                                    <td class="epbt-param-label">Pilihan Jalan :</td>
                                    <td colspan="2">
                                        <select id="epbtJalanSelect" class="epbt-input-field" style="min-width:280px;max-width:480px;">
                                            <option value="">Semua Jalan (Serentak)</option>
                                            <?php foreach ($jalanList as $j): ?>
                                                <option value="<?= $h($j['PEG_JLKOD']) ?>">
                                                    <?= $h($j['PEG_JLKOD'] . ' - ' . $j['JLN_JNAMA']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="rowStatusBaki">
                                    <td class="epbt-param-label">Status Baki :</td>
                                    <td colspan="2">
                                        <select id="epbtStatusSelect" class="epbt-input-field" style="min-width:200px;">
                                            <option value="all">Semua Status</option>
                                            <option value="tertunggak">Ada Tunggakan (&gt; RM0)</option>
                                            <option value="selesai">Selesai (RM0)</option>
                                            <option value="lebih_bayar">Lebih Bayar (&lt; RM0)</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="epbt-param-label">Rujukan Pointer :</td>
                                    <td colspan="2">
                                        <span class="epbt-pointer-badge">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L2 22h20L12 2zm0 4l6.5 13H5.5L12 6z"/></svg>
                                            <span id="epbtPointerName">DETAIL_TGKN_TERIMAAN_IKUT_JALAN</span>
                                        </span>
                                    </td>
                                </tr>
                            </table>

                            <div class="epbt-btn-group">
                                <button type="button" class="epbt-btn" id="epbtResetBtn">Reset</button>
                                <button type="button" class="epbt-btn" id="epbtCetakBtn">Cetak</button>
                                <button type="button" class="epbt-btn epbt-btn-primary" id="epbtJanaBtn">Papar Jadual &amp; Data Live</button>
                                <button type="button" class="epbt-btn" id="epbtOpenDashboardBtn">Buka Dashboard Penuh &rarr;</button>
                                <button type="button" class="epbt-btn" id="epbtExportCsvBtn">Eksport CSV</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- HASIL KEPUTUSAN JADUAL BERSAMA RINGKASAN DATA -->
            <div class="epbt-box-card" id="epbtResultCard" style="margin-top:14px;">
                <div class="epbt-card-header-bar" style="display:flex;justify-content:space-between;align-items:center;">
                    <span id="epbtResultHeading">Hasil Laporan &amp; Paparan Data</span>
                    <span style="font-size:11px;font-weight:normal;" id="epbtResultMeta">-</span>
                </div>
                <div class="epbt-card-inner-box">
                    <div class="table-wrap" style="max-height:480px;overflow-y:auto;">
                        <table class="data-table" id="epbtResultTable">
                            <thead id="epbtResultThead">
                                <tr>
                                    <th>#</th>
                                    <th>PEG_AKAUN</th>
                                    <th>PEG_JLKOD</th>
                                    <th>JLN_JNAMA</th>
                                    <th class="num">BIL_TUNGGAKAN</th>
                                    <th class="num">TERIMA_TUNGGAKAN</th>
                                    <th class="num">BAKI_TUNGGAKAN</th>
                                    <th class="num">BIL_SEMASA</th>
                                    <th class="num">TERIMA_SEMASA</th>
                                    <th class="num">BAKI_SEMASA</th>
                                </tr>
                            </thead>
                            <tbody id="epbtResultTbody">
                                <tr><td colspan="10" class="empty-state">Sila pilih jalan di atas atau klik butang <strong>"Papar Jadual &amp; Data Live"</strong> untuk memuatkan rekod terus dari Oracle 10g.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination-bar no-print" id="epbtPaginationBar" style="margin-top:10px;display:flex;justify-content:space-between;align-items:center;padding:8px 4px;flex-wrap:wrap;gap:8px;">
                        <div class="pagination-info" id="epbtPaginationInfo">Memaparkan rekod</div>
                        <div class="pagination-controls" style="display:flex;gap:6px;align-items:center;">
                            <button class="btn-page" id="epbtFirstPageBtn" type="button" title="Pergi ke Halaman Pertama">&laquo; Pertama</button>
                            <button class="btn-page" id="epbtPrevPageBtn" type="button" title="Halaman Sebelum">&larr; Sebelum</button>
                            <button class="btn-page" id="epbtNextPageBtn" type="button" title="Halaman Seterusnya">Seterusnya &rarr;</button>
                            <button class="btn-page" id="epbtLastPageBtn" type="button" title="Pergi ke Halaman Akhir">Akhir &raquo;</button>
                            <select class="control" id="epbtPerPageSelect" style="width:auto;">
                                <option value="25">25 baris</option>
                                <option value="50">50 baris</option>
                                <option value="100">100 baris</option>
                                <option value="200">200 baris</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

        </section>

        <!-- ================= PANEL 2: DASHBOARD UTAMA (KPI & PRESTASI GARIS) ================= -->
        <section class="tab-panel" id="tab-dashboard">
            <!-- PENAPIS CEPAT DI ATAS DASHBOARD -->
            <div class="filter-bar no-print" style="margin-bottom:14px;">
                <div class="field-group">
                    <label class="field-label" for="filterJalan">Pilihan Jalan (PEG_JLKOD)</label>
                    <select class="control" id="filterJalan">
                        <option value="">Semua Jalan (Serentak)</option>
                        <?php foreach ($jalanList as $j): ?>
                            <option value="<?= $h($j['PEG_JLKOD']) ?>">
                                <?= $h($j['PEG_JLKOD'] . ' - ' . $j['JLN_JNAMA']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-group compact">
                    <label class="field-label" for="filterAkaun">No. Akaun (PEG_AKAUN)</label>
                    <input class="control" type="text" id="filterAkaun" placeholder="Cth: 17017">
                </div>

                <button class="btn-primary" type="button" id="applyFilterBtn">Tapis Data</button>
                <button class="btn-secondary" type="button" id="resetFilterBtn">Set Semula</button>
                <button class="btn-secondary" type="button" id="forceRefreshBtn">Segarkan Data Live</button>
            </div>

            <!-- KAD KPI UTAMA -->
            <div class="kpi-grid-4">
                <div class="kpi-card accent-blue">
                    <div class="kpi-title">Jumlah Akaun Pegangan</div>
                    <div class="kpi-value" id="dashTotalAkaun">-</div>
                    <div class="kpi-sub" id="dashAkaunBreakdown">Menunggu data...</div>
                </div>
                <div class="kpi-card accent-indigo">
                    <div class="kpi-title">Baki Tunggakan (RM)</div>
                    <div class="kpi-value" id="dashBakiTgk">-</div>
                    <div class="kpi-sub" id="dashBilTerimaTgk">Bil: - · Terima: -</div>
                </div>
                <div class="kpi-card accent-pink">
                    <div class="kpi-title">Baki Semasa (RM)</div>
                    <div class="kpi-value" id="dashBakiSem">-</div>
                    <div class="kpi-sub" id="dashBilTerimaSem">Bil: - · Terima: -</div>
                </div>
                <div class="kpi-card accent-green">
                    <div class="kpi-title">Kadar Kutipan Keseluruhan</div>
                    <div class="kpi-value" id="dashKadarKutipan">-</div>
                    <div class="kpi-sub" id="dashJumlahBaki">Jumlah Baki: -</div>
                </div>
            </div>

            <!-- PROFIL HASIL CARIAN PEG_AKAUN -->
            <div class="account-profile-card" id="accountProfileCard" style="display:none;margin-top:14px;">
                <div class="profile-header">
                    <div>
                        <div class="prestasi-label">Hasil Carian Akaun Pegangan</div>
                        <h3 class="card-title" id="profileTitle">-</h3>
                    </div>
                    <div id="profileMatches" style="display:flex;gap:6px;flex-wrap:wrap;"></div>
                </div>
                <div class="profile-grid">
                    <div class="profile-cell">
                        <div class="profile-cell-lbl">Bil Tunggakan</div>
                        <div class="profile-cell-val" id="profBilTgk">-</div>
                    </div>
                    <div class="profile-cell">
                        <div class="profile-cell-lbl">Terima Tunggakan</div>
                        <div class="profile-cell-val val-success" id="profTerimaTgk">-</div>
                    </div>
                    <div class="profile-cell">
                        <div class="profile-cell-lbl">Baki Tunggakan</div>
                        <div class="profile-cell-val" id="profBakiTgk">-</div>
                    </div>
                    <div class="profile-cell">
                        <div class="profile-cell-lbl">Bil Semasa</div>
                        <div class="profile-cell-val" id="profBilSem">-</div>
                    </div>
                    <div class="profile-cell">
                        <div class="profile-cell-lbl">Terima Semasa</div>
                        <div class="profile-cell-val val-success" id="profTerimaSem">-</div>
                    </div>
                    <div class="profile-cell">
                        <div class="profile-cell-lbl">Baki Semasa</div>
                        <div class="profile-cell-val" id="profBakiSem">-</div>
                    </div>
                </div>
            </div>

            <!-- DUA KAD CARTA PRESTASI BERSEBELAHAN -->
            <div class="grid-2" style="margin-top:14px;">
                <article class="card">
                    <div class="prestasi-header">
                        <div class="prestasi-title-wrap">
                            <div class="prestasi-icon indigo">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 15l4-4 4 3 5-6"/></svg>
                            </div>
                            <div>
                                <div class="prestasi-label">Prestasi</div>
                                <div class="prestasi-name" style="color:var(--accent-indigo);">TUNGGAKAN</div>
                            </div>
                        </div>
                        <div class="prestasi-metrics">
                            <div class="metric-mini">
                                <div class="metric-mini-lbl">Bil</div>
                                <div class="metric-mini-val" id="cardTgkBil">-</div>
                                <div class="metric-mini-sub">12010+11090</div>
                            </div>
                            <div class="metric-mini">
                                <div class="metric-mini-lbl">Terima</div>
                                <div class="metric-mini-val" id="cardTgkTerima">-</div>
                                <div class="metric-mini-sub" id="cardTgkPct">(-%)</div>
                            </div>
                            <div class="metric-mini">
                                <div class="metric-mini-lbl">Baki</div>
                                <div class="metric-mini-val" id="cardTgkBaki">-</div>
                                <div class="metric-mini-sub">Tunggakan</div>
                            </div>
                        </div>
                    </div>
                    <div class="chart-box" id="chartPrestasiTunggakan"></div>
                </article>

                <article class="card">
                    <div class="prestasi-header">
                        <div class="prestasi-title-wrap">
                            <div class="prestasi-icon pink">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 11l4 4 4-6 5 4"/></svg>
                            </div>
                            <div>
                                <div class="prestasi-label">Prestasi</div>
                                <div class="prestasi-name" style="color:var(--accent-pink);">SEMASA</div>
                            </div>
                        </div>
                        <div class="prestasi-metrics">
                            <div class="metric-mini">
                                <div class="metric-mini-lbl">Bil</div>
                                <div class="metric-mini-val" id="cardSemBil">-</div>
                                <div class="metric-mini-sub">11010+11090</div>
                            </div>
                            <div class="metric-mini">
                                <div class="metric-mini-lbl">Terima</div>
                                <div class="metric-mini-val" id="cardSemTerima">-</div>
                                <div class="metric-mini-sub" id="cardSemPct">(-%)</div>
                            </div>
                            <div class="metric-mini">
                                <div class="metric-mini-lbl">Baki</div>
                                <div class="metric-mini-val" id="cardSemBaki">-</div>
                                <div class="metric-mini-sub">Semasa</div>
                            </div>
                        </div>
                    </div>
                    <div class="chart-box" id="chartPrestasiSemasa"></div>
                </article>
            </div>
        </section>

        <!-- ================= PANEL 3: ANALISIS JALAN EIS (CARTA & TOP 5) ================= -->
        <section class="tab-panel" id="tab-analisis">
            <div class="kpi-grid-4">
                <div class="kpi-card accent-blue">
                    <div class="kpi-title">Akaun Tertapis</div>
                    <div class="kpi-value" id="eisTotalAkaun">-</div>
                    <div class="kpi-sub" id="eisJalanLabel">Semua Jalan</div>
                </div>
                <div class="kpi-card accent-green">
                    <div class="kpi-title">Purata Kadar Kutipan</div>
                    <div class="kpi-value" id="eisKadarKutipan">-</div>
                    <div class="kpi-sub" id="eisKadarSub">Tunggakan &amp; Semasa</div>
                </div>
                <div class="kpi-card accent-pink">
                    <div class="kpi-title">Akaun Selesai (RM0)</div>
                    <div class="kpi-value" id="eisAkaunSelesai">-</div>
                    <div class="kpi-sub" id="eisAkaunSelesaiPct">0% daripada jumlah</div>
                </div>
                <div class="kpi-card accent-red">
                    <div class="kpi-title">Akaun Tertunggak</div>
                    <div class="kpi-value" id="eisAkaunTertunggak">-</div>
                    <div class="kpi-sub" id="eisAkaunTertunggakPct">0% daripada jumlah</div>
                </div>
            </div>

            <div class="grid-2" style="margin-top:14px;">
                <article class="card">
                    <h3 class="card-title">Perbandingan Komponen Kewangan (RM)</h3>
                    <div class="chart-box" id="chartKomponenBar"></div>
                </article>
                <article class="card">
                    <h3 class="card-title">Nisbah Akaun Selesai vs Tertunggak</h3>
                    <div class="chart-box" id="chartNisbahDonut"></div>
                </article>
            </div>

            <div class="grid-2" style="margin-top:14px;">
                <article class="card">
                    <h3 class="card-title">Top 5 Akaun Tunggakan Tertinggi</h3>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr><th>#</th><th>Akaun</th><th>Jalan</th><th class="num">Baki Tunggakan</th><th class="num">Baki Semasa</th><th class="num">Jumlah Baki</th></tr>
                            </thead>
                            <tbody id="top5TunggakanBody">
                                <tr><td colspan="6" class="empty-state">Memuatkan data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="card">
                    <h3 class="card-title">Top 5 Akaun Terimaan Terbaik</h3>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr><th>#</th><th>Akaun</th><th>Jalan</th><th class="num">Terima Tunggakan</th><th class="num">Terima Semasa</th><th class="num">Jumlah Terima</th></tr>
                            </thead>
                            <tbody id="top5TerimaBody">
                                <tr><td colspan="6" class="empty-state">Memuatkan data...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </section>

        <!-- ================= PANEL 4: SENARAI AKAUN PEGANGAN ================= -->
        <section class="tab-panel" id="tab-senarai">
            <div class="card">
                <div class="card-header" style="justify-content:space-between;flex-wrap:wrap;gap:10px;">
                    <div>
                        <h3 class="card-title">Senarai Terperinci Tunggakan &amp; Terimaan</h3>
                        <div class="card-sub" id="senaraiSummaryText"><span id="senaraiMeta">Memuatkan rekod...</span></div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="btn-secondary" type="button" id="exportCsvBtn">📥 Eksport CSV (Excel)</button>
                        <button class="btn-secondary" type="button" id="printSenaraiBtn">🖨️ Cetak Senarai</button>
                    </div>
                </div>

                <div class="table-wrap" style="max-height:600px;overflow-y:auto;">
                    <table class="data-table" id="senaraiTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th class="sortable" data-sort="PEG_AKAUN">PEG_AKAUN</th>
                                <th class="sortable" data-sort="PEG_JLKOD">PEG_JLKOD</th>
                                <th>JLN_JNAMA</th>
                                <th class="sortable num" data-sort="BIL_TUNGGAKAN">BIL_TUNGGAKAN</th>
                                <th class="sortable num" data-sort="TERIMA_TUNGGAKAN">TERIMA_TUNGGAKAN</th>
                                <th class="sortable num" data-sort="BAKI_TUNGGAKAN">BAKI_TUNGGAKAN</th>
                                <th class="sortable num" data-sort="BIL_SEMASA">BIL_SEMASA</th>
                                <th class="sortable num" data-sort="TERIMA_SEMASA">TERIMA_SEMASA</th>
                                <th class="sortable num" data-sort="BAKI_SEMASA">BAKI_SEMASA</th>
                                <th>STATUS</th>
                            </tr>
                        </thead>
                        <tbody id="senaraiBody">
                            <tr><td colspan="11" class="empty-state">Memuatkan data akaun...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="pagination-bar no-print">
                    <div class="pagination-info" id="paginationInfo">Halaman 1 / 1</div>
                    <div class="pagination-controls">
                        <button class="btn-page" id="firstPageBtn" type="button" title="Pergi ke Halaman Pertama">&laquo; Pertama</button>
                        <button class="btn-page" id="prevPageBtn">&larr; Sebelum</button>
                        <button class="btn-page" id="nextPageBtn">Seterusnya &rarr;</button>
                        <button class="btn-page" id="lastPageBtn" type="button" title="Pergi ke Halaman Akhir">Akhir &raquo;</button>
                        <select class="control" id="perPageSelect" style="width:auto;">
                            <option value="25">25 baris</option>
                            <option value="50">50 baris</option>
                            <option value="100">100 baris</option>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= PANEL 5: PENTADBIRAN & DIAGNOSTIK ORACLE 10g ================= -->
        <section class="tab-panel" id="tab-pentadbiran">
            <div class="grid-2">
                <article class="card">
                    <h3 class="card-title">Status Sambungan Pelayan ORCLVM55</h3>
                    <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px;">
                        <div><strong>Hos Pelayan:</strong> <code><?= $h($config['oracle']['host']) ?>:<?= (int) $config['oracle']['port'] ?></code></div>
                        <div><strong>Service Name:</strong> <code><?= $h($config['oracle']['service_name']) ?></code></div>
                        <div><strong>TNS Alias:</strong> <code><?= $h($config['oracle']['tns_alias']) ?></code></div>
                        <div><strong>Pemacu PHP Aktif:</strong> <span class="pill pill-indigo"><?= strtoupper($driverName) ?></span></div>
                        <div><strong>Mod Operasi:</strong> <span class="pill <?= $demoMode ? 'pill-warning' : 'pill-green' ?>"><?= $demoMode ? 'Mod Demo (Data Contoh)' : 'Sambungan Langsung Oracle 10g' ?></span></div>
                    </div>

                    <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="btn-primary" type="button" id="testConnBtn">⚡ Uji Sambungan Oracle</button>
                        <button class="btn-secondary" type="button" id="testAllProfilesBtn">⚡ Uji Semua 8 Skema ePBT</button>
                        <button class="btn-secondary" type="button" id="adminRefreshBtn">Kosongkan Cache</button>
                    </div>

                    <div id="testConnResult" style="margin-top:14px;display:none;"></div>
                </article>

                <article class="card">
                    <h3 class="card-title">Skrip SQL Teras Rujukan (Read-Only)</h3>
                    <pre style="background:var(--bg-subtle);padding:12px;border-radius:6px;font-size:11.5px;max-height:300px;overflow-y:auto;"><?= $h($sqlRef) ?></pre>
                </article>
            </div>
        </section>

        <!-- ================= PANEL 6: TETAPAN SISTEM ================= -->
        <section class="tab-panel" id="tab-tetapan">
            <div class="card" style="max-width:600px;">
                <h3 class="card-title">Tetapan &amp; Log Masuk Pengguna</h3>
                <div style="margin-top:14px;display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label class="field-label">ID Pengguna</label>
                        <input class="control" type="text" id="loginUserId" value="<?= $h($auth['id'] ?: 'admin') ?>">
                    </div>
                    <div>
                        <label class="field-label">Kata Laluan</label>
                        <input class="control" type="password" id="loginPassword" value="admin123">
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="btn-primary" type="button" id="loginSubmitBtn">Log Masuk</button>
                        <button class="btn-secondary" type="button" id="logoutSubmitBtn">Log Keluar</button>
                    </div>
                </div>
            </div>
        </section>

    </main>
</div>

<!-- ================= MODAL DRILL-DOWN JALAN ================= -->
<div class="modal-backdrop" id="drilldownModal" style="display:none;" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <div class="prestasi-label" id="drilldownJalanCode">JALAN -</div>
                <h3 class="modal-title" id="drilldownJalanTitle">Pecahan Akaun Jalan</h3>
            </div>
            <button class="modal-close-btn" id="drilldownCloseBtn" type="button">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-kpi-row">
                <div class="modal-kpi-card">
                    <div class="kpi-label">Jumlah Akaun</div>
                    <div class="kpi-value" id="drilldownTotalAkaun">-</div>
                </div>
                <div class="modal-kpi-card">
                    <div class="kpi-label">Baki Tunggakan</div>
                    <div class="kpi-value" style="color:var(--accent-indigo);" id="drilldownBakiTgk">-</div>
                </div>
                <div class="modal-kpi-card">
                    <div class="kpi-label">Baki Semasa</div>
                    <div class="kpi-value" style="color:var(--accent-pink);" id="drilldownBakiSem">-</div>
                </div>
                <div class="modal-kpi-card">
                    <div class="kpi-label">Kadar Kutipan</div>
                    <div class="kpi-value" style="color:var(--accent-green);" id="drilldownKadar">-</div>
                </div>
            </div>
            <div class="table-wrap" style="max-height:360px;overflow-y:auto;margin-top:12px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>No. Akaun</th>
                            <th class="num">Bil Tgk</th>
                            <th class="num">Terima Tgk</th>
                            <th class="num">Baki Tgk</th>
                            <th class="num">Bil Semasa</th>
                            <th class="num">Terima Semasa</th>
                            <th class="num">Baki Semasa</th>
                            <th class="num">Jumlah Baki</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="drilldownBody">
                        <tr><td colspan="10" class="empty-state">Memuatkan rekod...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" id="drilldownCloseFooterBtn">Tutup</button>
        </div>
    </div>
</div>

<script>
window.APP_BOOT = <?= json_encode([
    'demoMode' => $demoMode,
    'auth' => $auth,
    'defaultReportId' => $reportsMeta[0]['id'] ?? 'detail-tgkn-terimaan-jalan',
    'modules' => $modules,
    'reportsMeta' => $reportsMeta,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="assets/js/charts.js?v=<?= @filemtime(__DIR__ . '/assets/js/charts.js') ?: time() ?>"></script>
<script src="assets/js/app.js?v=<?= @filemtime(__DIR__ . '/assets/js/app.js') ?: time() ?>"></script>
</body>
</html>
