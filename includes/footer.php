
        </section>
    </main>
    <nav class="mobile-nav" aria-label="Navigasi utama">
        <a class="<?= $activeMenu === "dashboard" ? "active" : "" ?>" href="<?= $appBase ?>/dashboard/index.php"><span class="material-symbols-outlined" aria-hidden="true">home</span>Home</a>
        <a class="<?= $activeMenu === "reports" ? "active" : "" ?>" href="<?= $appBase ?>/laporan/index.php"><span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>Reports</a>
        <a href="<?= $appBase ?>/transaksi/tambah.php" class="mobile-add" aria-label="Tambah transaksi">+</a>
        <a class="<?= $activeMenu === "charts" ? "active" : "" ?>" href="<?= $appBase ?>/grafik/index.php"><span class="material-symbols-outlined" aria-hidden="true">query_stats</span>Charts</a>
        <a class="<?= $activeMenu === "accounts" ? "active" : "" ?>" href="<?= $appBase ?>/akun/index.php"><span class="material-symbols-outlined" aria-hidden="true">account_balance_wallet</span>Akun</a>
    </nav>
    <button type="button" class="sidebar-backdrop" id="sidebarBackdrop" aria-label="Tutup menu"></button>
    <script src="<?= htmlspecialchars($appBase, ENT_QUOTES, "UTF-8") ?>/assets/css/js/app.js"></script>
</body>
</html>
