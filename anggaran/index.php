<?php
require_once __DIR__ . "/../includes/app_helpers.php";
require_once __DIR__ . "/../config/auth_check.php";
require_once __DIR__ . "/../functions/transaksi.php";

$pageTitle = "Anggaran";
$activeMenu = "budgets";
$userId = (int) $_SESSION["user_id"];
$month = $_GET["month"] ?? date("Y-m");
$errors = [];

if (!preg_match("/^\d{4}-(0[1-9]|1[0-2])$/", $month)) {
    $month = date("Y-m");
}
$monthNumber = (int) substr($month, 5, 2);
$yearNumber = (int) substr($month, 0, 4);
$startDate = $month . "-01";
$endDate = date("Y-m-t", strtotime($startDate));
$monthNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
$monthLabel = $monthNames[$monthNumber - 1] . " " . $yearNumber;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isValidCsrfToken($_POST["csrf_token"] ?? null)) {
        $errors[] = "Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.";
    } elseif (($_POST["action"] ?? "") === "delete") {
        $budgetId = (int) ($_POST["budget_id"] ?? 0);
        $delete = mysqli_prepare(
            $koneksi,
            "DELETE FROM budgets WHERE budget_id = ? AND user_id = ? AND period_month = ? AND period_year = ?"
        );
        mysqli_stmt_bind_param($delete, "iiii", $budgetId, $userId, $monthNumber, $yearNumber);
        $deleted = mysqli_stmt_execute($delete);
        mysqli_stmt_close($delete);
        if ($deleted) {
            header("Location: ?month=" . rawurlencode($month) . "&deleted=1");
            exit;
        }
        $errors[] = "Anggaran belum dapat dihapus.";
    } else {
        $categoryId = (int) ($_POST["category_id"] ?? 0);
        $limitAmount = trim($_POST["limit_amount"] ?? "");

        if (!$categoryId || !userOwnsTransactionCategory($koneksi, $categoryId, $userId, "expense")) {
            $errors[] = "Pilih kategori pengeluaran yang valid.";
        } elseif (!preg_match("/^\d+(\.\d{1,2})?$/", $limitAmount) || (float) $limitAmount <= 0) {
            $errors[] = "Batas anggaran harus lebih besar dari nol.";
        } else {
            $save = mysqli_prepare(
                $koneksi,
                "INSERT INTO budgets (user_id, category_id, period_month, period_year, limit_amount)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE limit_amount = VALUES(limit_amount)"
            );
            mysqli_stmt_bind_param($save, "iiiis", $userId, $categoryId, $monthNumber, $yearNumber, $limitAmount);
            if (mysqli_stmt_execute($save)) {
                mysqli_stmt_close($save);
                header("Location: ?month=" . rawurlencode($month) . "&saved=1");
                exit;
            }
            mysqli_stmt_close($save);
            $errors[] = "Anggaran belum dapat disimpan.";
        }
    }
}

$categories = getTransactionCategories($koneksi, $userId, "expense");
$budgetQuery = mysqli_prepare(
    $koneksi,
    "SELECT b.budget_id, b.limit_amount, c.category_name,
            COALESCE(SUM(t.amount), 0) AS spent
     FROM budgets AS b
     INNER JOIN categories AS c ON c.category_id = b.category_id
     LEFT JOIN transactions AS t ON t.user_id = b.user_id
        AND t.category_id = b.category_id AND t.transaction_type = 'expense'
        AND t.transaction_date BETWEEN ? AND ?
     WHERE b.user_id = ? AND b.period_month = ? AND b.period_year = ?
     GROUP BY b.budget_id, b.limit_amount, c.category_name
     ORDER BY c.category_name"
);
mysqli_stmt_bind_param($budgetQuery, "ssiii", $startDate, $endDate, $userId, $monthNumber, $yearNumber);
mysqli_stmt_execute($budgetQuery);
$budgetResult = mysqli_stmt_get_result($budgetQuery);
$budgets = $budgetResult ? mysqli_fetch_all($budgetResult, MYSQLI_ASSOC) : [];
mysqli_stmt_close($budgetQuery);

$spentTotal = array_sum(array_map("floatval", array_column($budgets, "spent")));
$limitTotal = array_sum(array_map("floatval", array_column($budgets, "limit_amount")));

require __DIR__ . "/../includes/header.php";
?>
<div class="page-heading">
    <div><span class="subtitle">PERENCANAAN KEUANGAN</span><h1>Anggaran</h1><p>Atur batas pengeluaran per kategori.</p></div>
    <div class="budget-total"><span>Terpakai dari batas</span><strong><?= formatRupiah($spentTotal) ?> / <?= formatRupiah($limitTotal) ?></strong></div>
</div>

<?php if (isset($_GET["saved"])): ?><p class="notice notice-success" role="status">Anggaran berhasil disimpan.</p><?php endif; ?>
<?php if (isset($_GET["deleted"])): ?><p class="notice notice-success" role="status">Anggaran berhasil dihapus.</p><?php endif; ?>
<?php foreach ($errors as $error): ?><p class="notice notice-error" role="alert"><?= appEscape($error) ?></p><?php endforeach; ?>

<form class="report-period-form" method="get" action="">
    <label for="budgetMonth">Periode</label>
    <input id="budgetMonth" name="month" type="month" value="<?= appEscape($month) ?>">
    <button class="secondary-button" type="submit">Tampilkan</button>
</form>

<section class="transaction-panel budget-entry-panel">
    <div class="form-section-title"><h2>Atur Anggaran Bulanan</h2><span class="optional-label"><?= appEscape($monthLabel) ?></span></div>
    <?php if ($categories): ?>
        <form class="budget-entry-form" method="post" action="?month=<?= appEscape($month) ?>">
            <input type="hidden" name="csrf_token" value="<?= appEscape(csrfToken()) ?>">
            <div class="form-field"><label for="budgetCategory">Kategori</label><select id="budgetCategory" name="category_id" required><?php foreach ($categories as $category): ?><option value="<?= (int) $category["category_id"] ?>"><?= appEscape($category["category_name"]) ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="limitAmount">Batas pengeluaran</label><input id="limitAmount" name="limit_amount" type="number" min="0.01" step="0.01" placeholder="0" required></div>
            <button class="auth-submit" type="submit">Simpan Anggaran</button>
        </form>
    <?php else: ?>
        <p class="empty-inline">Kategori pengeluaran belum tersedia.</p>
    <?php endif; ?>
</section>

<section class="report-section">
    <div class="section-heading"><div><h2>Penggunaan Anggaran</h2><p><?= appEscape($monthLabel) ?> · <?= count($budgets) ?> kategori</p></div></div>
    <?php if ($budgets): ?>
        <div class="budget-card-list">
            <?php foreach ($budgets as $budget): ?>
                <?php $percent = (float) $budget["limit_amount"] > 0 ? min(100, ((float) $budget["spent"] / (float) $budget["limit_amount"]) * 100) : 0; ?>
                <article class="budget-card">
                    <div class="budget-card-heading"><strong><?= appEscape($budget["category_name"]) ?></strong><span><?= number_format($percent, 0) ?>%</span></div>
                    <div class="report-progress"><span style="width: <?= number_format($percent, 2, ".", "") ?>%"></span></div>
                    <div class="budget-card-foot"><span><?= formatRupiah($budget["spent"]) ?> terpakai</span><span>Batas <?= formatRupiah($budget["limit_amount"]) ?></span></div>
                    <form method="post" action="?month=<?= appEscape($month) ?>" data-confirm="Hapus anggaran kategori ini?">
                        <input type="hidden" name="csrf_token" value="<?= appEscape(csrfToken()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="budget_id" value="<?= (int) $budget["budget_id"] ?>">
                        <button class="table-action delete-action" type="submit">Hapus Anggaran</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="empty-inline">Belum ada batas anggaran untuk <?= appEscape($monthLabel) ?>.</p>
    <?php endif; ?>
</section>
<?php require __DIR__ . "/../includes/footer.php"; ?>