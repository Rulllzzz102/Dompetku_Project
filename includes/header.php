
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? "DompetKu";
$activeMenu = $activeMenu ?? "";
$appBase = rtrim(dirname(dirname($_SERVER["SCRIPT_NAME"])), "/");
$displayName = $_SESSION["display_name"] ?? "Pengguna DompetKu";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Dompetku</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, "UTF-8") ?>/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div>
            <a class="brand" href="<?= $appBase ?>/dashboard/index.php">
                <img class="brand-logo" src="<?= $appBase ?>/assets/logo-dompetku.svg" alt="">
                <span class="brand-text">
                    <h2>DompetKu</h2>
                    <small>PERSONAL FINANCE</small>
                </span>
            </a>
            <nav class="menu" aria-label="Menu utama">
                <a href="<?= $appBase ?>/dashboard/index.php" class="<?= $activeMenu === "dashboard" ? "active" : "" ?>"><span class="material-symbols-outlined">home</span><span class="menu-text">Home</span></a>
                <a href="<?= $appBase ?>/laporan/index.php" class="<?= $activeMenu === "reports" ? "active" : "" ?>"><span class="material-symbols-outlined">receipt_long</span><span class="menu-text">Reports</span></a>
                <a href="<?= $appBase ?>/grafik/index.php" class="<?= $activeMenu === "charts" ? "active" : "" ?>"><span class="material-symbols-outlined">query_stats</span><span class="menu-text">Charts</span></a>
                <a href="<?= $appBase ?>/akun/index.php" class="<?= $activeMenu === "accounts" ? "active" : "" ?>"><span class="material-symbols-outlined">account_balance_wallet</span><span class="menu-text">Akun Keuangan</span></a>
            </nav>
        </div>
        <div class="sidebar-bottom">
            <div class="account-status">
                <strong>✓ Status Akun</strong>
                <p>Data keuangan tersimpan pada akun Anda.</p>
            </div>
            <a href="<?= $appBase ?>/auth/logout.php" class="logout"><span class="material-symbols-outlined" aria-hidden="true">logout</span><span>Keluar</span></a>
            <small class="version">DompetKu</small>
        </div>
    </aside>

    <main class="main-content" id="mainContent">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="Buka atau tutup menu navigasi" aria-controls="sidebar" aria-expanded="false">☰</button>
                <input type="search" id="searchInput" placeholder="Cari transaksi..." aria-label="Cari transaksi">
            </div>
            <a href="<?= $appBase ?>/transaksi/tambah.php" class="topbar-add"><span aria-hidden="true">+</span><span>Tambah Transaksi</span></a>
            <div class="profile">
                <div class="profile-info">
                    <strong><?= htmlspecialchars($displayName, ENT_QUOTES, "UTF-8") ?></strong>
                    <small>Akun Personal</small>
                </div>
                <div class="avatar" aria-hidden="true">👤</div>
            </div>
        </header>
        <section class="content">
