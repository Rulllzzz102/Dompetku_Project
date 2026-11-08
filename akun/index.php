<?php
require_once __DIR__ . "/../includes/app_helpers.php";
require_once __DIR__ . "/../config/auth_check.php";

$pageTitle = "Akun Keuangan";
$activeMenu = "accounts";
$userId = (int) $_SESSION["user_id"];
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isValidCsrfToken($_POST["csrf_token"] ?? null)) {
        $errors[] = "Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.";
    } elseif (($_POST["action"] ?? "") === "archive") {
        $accountId = (int) ($_POST["account_id"] ?? 0);
        $archive = mysqli_prepare(
            $koneksi,
            "UPDATE accounts SET is_active = 0 WHERE account_id = ? AND user_id = ? AND is_active = 1"
        );
        mysqli_stmt_bind_param($archive, "ii", $accountId, $userId);
        $archived = mysqli_stmt_execute($archive) && mysqli_stmt_affected_rows($archive) > 0;
        mysqli_stmt_close($archive);
        if ($archived) {
            header("Location: ?archived=1");
            exit;
        }
        $errors[] = "Akun tidak ditemukan atau sudah diarsipkan.";
    } else {
        $accountName = trim($_POST["account_name"] ?? "");
        $accountType = $_POST["account_type"] ?? "cash";
        $initialBalance = trim($_POST["initial_balance"] ?? "0");
        $allowedAccountTypes = ["cash", "bank", "ewallet", "other"];

        if ($accountName === "" || strlen($accountName) > 100) {
            $errors[] = "Nama akun wajib diisi dan maksimal 100 karakter.";
        } elseif (!in_array($accountType, $allowedAccountTypes, true)) {
            $errors[] = "Jenis akun tidak valid.";
        } elseif (!preg_match("/^\d+(\.\d{1,2})?$/", $initialBalance)) {
            $errors[] = "Saldo awal harus berupa angka nol atau lebih.";
        } else {
            $insert = mysqli_prepare(
                $koneksi,
                "INSERT INTO accounts (user_id, account_name, account_type, initial_balance) VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($insert, "isss", $userId, $accountName, $accountType, $initialBalance);
            if (mysqli_stmt_execute($insert)) {
                mysqli_stmt_close($insert);
                header("Location: ?saved=1");
                exit;
            }
            $duplicate = mysqli_stmt_errno($insert) === 1062;
            mysqli_stmt_close($insert);
            $errors[] = $duplicate ? "Nama akun tersebut sudah digunakan." : "Akun belum dapat disimpan.";
        }
    }
}

$accountQuery = mysqli_prepare(
    $koneksi,
    "SELECT a.account_id, a.account_name, a.account_type, a.initial_balance,
            a.initial_balance + COALESCE(SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE -t.amount END), 0) AS current_balance
     FROM accounts AS a
     LEFT JOIN transactions AS t ON t.account_id = a.account_id AND t.user_id = a.user_id
     WHERE a.user_id = ? AND a.is_active = 1
     GROUP BY a.account_id, a.account_name, a.account_type, a.initial_balance
     ORDER BY a.account_name"
);
mysqli_stmt_bind_param($accountQuery, "i", $userId);
mysqli_stmt_execute($accountQuery);
$accountResult = mysqli_stmt_get_result($accountQuery);
$accounts = $accountResult ? mysqli_fetch_all($accountResult, MYSQLI_ASSOC) : [];
mysqli_stmt_close($accountQuery);

$totalBalance = array_sum(array_map("floatval", array_column($accounts, "current_balance")));
$accountTypeLabels = ["cash" => "Tunai", "bank" => "Bank", "ewallet" => "E-wallet", "other" => "Lainnya"];

require __DIR__ . "/../includes/header.php";
?>
<div class="page-heading">
    <div><span class="subtitle">SUMBER DANA</span><h1>Akun Keuangan</h1><p>Kelola saldo tunai, rekening, dan e-wallet Anda.</p></div>
    <div class="budget-total"><span>Total saldo akun</span><strong><?= formatRupiah($totalBalance) ?></strong></div>
</div>

<?php if (isset($_GET["saved"])): ?><p class="notice notice-success" role="status">Akun berhasil ditambahkan.</p><?php endif; ?>
<?php if (isset($_GET["archived"])): ?><p class="notice notice-success" role="status">Akun berhasil diarsipkan.</p><?php endif; ?>
<?php foreach ($errors as $error): ?><p class="notice notice-error" role="alert"><?= appEscape($error) ?></p><?php endforeach; ?>

<section class="transaction-panel account-entry-panel">
    <div class="form-section-title"><h2>Tambah Akun</h2><span class="optional-label">Saldo awal dapat diubah saat menambah akun baru</span></div>
    <form class="account-entry-form" method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= appEscape(csrfToken()) ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-field"><label for="accountName">Nama akun</label><input id="accountName" name="account_name" type="text" maxlength="100" placeholder="Contoh: Dompet Tunai" required></div>
        <div class="form-field"><label for="accountType">Jenis akun</label><select id="accountType" name="account_type"><option value="cash">Tunai</option><option value="bank">Bank</option><option value="ewallet">E-wallet</option><option value="other">Lainnya</option></select></div>
        <div class="form-field"><label for="initialBalance">Saldo awal</label><input id="initialBalance" name="initial_balance" type="number" min="0" step="0.01" value="0" required></div>
        <button class="auth-submit" type="submit">Tambah Akun <span aria-hidden="true">+</span></button>
    </form>
</section>

<section class="report-section">
    <div class="section-heading"><div><h2>Akun Aktif</h2><p><?= count($accounts) ?> akun</p></div></div>
    <?php if ($accounts): ?>
        <div class="account-management-list">
            <?php foreach ($accounts as $account): ?>
                <article class="account-management-card">
                    <span class="account-type-mark" aria-hidden="true">▣</span>
                    <div class="account-management-main"><strong><?= appEscape($account["account_name"]) ?></strong><span><?= appEscape($accountTypeLabels[$account["account_type"]] ?? "Lainnya") ?> · Saldo awal <?= formatRupiah($account["initial_balance"]) ?></span></div>
                    <strong class="account-management-balance"><?= formatRupiah($account["current_balance"]) ?></strong>
                    <form method="post" action="" data-confirm="Arsipkan akun ini? Transaksi sebelumnya tetap disimpan.">
                        <input type="hidden" name="csrf_token" value="<?= appEscape(csrfToken()) ?>">
                        <input type="hidden" name="action" value="archive">
                        <input type="hidden" name="account_id" value="<?= (int) $account["account_id"] ?>">
                        <button class="table-action delete-action" type="submit">Arsipkan</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="empty-inline">Belum ada akun tambahan. Anda tetap bisa mencatat transaksi tanpa memilih akun.</p>
    <?php endif; ?>
</section>
<?php require __DIR__ . "/../includes/footer.php"; ?>