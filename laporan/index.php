<?php
require_once __DIR__ . "/../includes/app_helpers.php";
require_once __DIR__ . "/../config/auth_check.php";

$pageTitle = "Laporan Keuangan";
$activeMenu = "reports";
$userId = (int) $_SESSION["user_id"];
$month = $_GET["month"] ?? date("Y-m");
$type = $_GET["type"] ?? "all";

if (!preg_match("/^\d{4}-(0[1-9]|1[0-2])$/", $month)) {
	$month = date("Y-m");
}
if (!in_array($type, ["all", "income", "expense"], true)) {
	$type = "all";
}

$startDate = $month . "-01";
$endDate = date("Y-m-t", strtotime($startDate));
$monthNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
$monthLabel = $monthNames[(int) substr($month, 5, 2) - 1] . " " . substr($month, 0, 4);

$summaryQuery = mysqli_prepare(
	$koneksi,
	"SELECT
		COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS income_total,
		COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS expense_total,
		COUNT(*) AS transaction_count
	 FROM transactions
	 WHERE user_id = ? AND transaction_date BETWEEN ? AND ?"
);
mysqli_stmt_bind_param($summaryQuery, "iss", $userId, $startDate, $endDate);
mysqli_stmt_execute($summaryQuery);
$summaryResult = mysqli_stmt_get_result($summaryQuery);
$summary = $summaryResult ? mysqli_fetch_assoc($summaryResult) : ["income_total" => 0, "expense_total" => 0, "transaction_count" => 0];
mysqli_stmt_close($summaryQuery);

$incomeTotal = (float) $summary["income_total"];
$expenseTotal = (float) $summary["expense_total"];
$netTotal = $incomeTotal - $expenseTotal;
$savingsRatio = $incomeTotal > 0 ? round(($netTotal / $incomeTotal) * 100, 1) : 0;

$categoryQuery = mysqli_prepare(
	$koneksi,
	"SELECT COALESCE(c.category_name, 'Lainnya') AS category_name, SUM(t.amount) AS category_total
	 FROM transactions AS t
	 LEFT JOIN categories AS c ON c.category_id = t.category_id
	 WHERE t.user_id = ? AND t.transaction_type = 'expense' AND t.transaction_date BETWEEN ? AND ?
	 GROUP BY c.category_id, c.category_name
	 ORDER BY category_total DESC LIMIT 8"
);
mysqli_stmt_bind_param($categoryQuery, "iss", $userId, $startDate, $endDate);
mysqli_stmt_execute($categoryQuery);
$categoryResult = mysqli_stmt_get_result($categoryQuery);
$categoryTotals = $categoryResult ? mysqli_fetch_all($categoryResult, MYSQLI_ASSOC) : [];
mysqli_stmt_close($categoryQuery);

$historySql = "SELECT t.title, t.amount, t.transaction_date, t.transaction_type,
					  COALESCE(c.category_name, 'Lainnya') AS category_name,
					  COALESCE(a.account_name, 'Belum dipilih') AS account_name
			   FROM transactions AS t
			   LEFT JOIN categories AS c ON c.category_id = t.category_id
			   LEFT JOIN accounts AS a ON a.account_id = t.account_id
			   WHERE t.user_id = ? AND t.transaction_date BETWEEN ? AND ?";
if ($type !== "all") {
	$historySql .= " AND t.transaction_type = ?";
}
$historySql .= " ORDER BY t.transaction_date DESC, t.transaction_id DESC LIMIT 100";
$historyQuery = mysqli_prepare($koneksi, $historySql);
if ($type === "all") {
	mysqli_stmt_bind_param($historyQuery, "iss", $userId, $startDate, $endDate);
} else {
	mysqli_stmt_bind_param($historyQuery, "isss", $userId, $startDate, $endDate, $type);
}
mysqli_stmt_execute($historyQuery);
$historyResult = mysqli_stmt_get_result($historyQuery);
$history = $historyResult ? mysqli_fetch_all($historyResult, MYSQLI_ASSOC) : [];
mysqli_stmt_close($historyQuery);

if (($_GET["export"] ?? "") === "csv") {
	header("Content-Type: text/csv; charset=utf-8");
	header("Content-Disposition: attachment; filename=laporan-dompetku-" . $month . ".csv");
	$output = fopen("php://output", "w");
	fwrite($output, "\xEF\xBB\xBF");
	fputcsv($output, ["Tanggal", "Judul", "Jenis", "Kategori", "Akun", "Jumlah"]);
	foreach ($history as $row) {
		fputcsv($output, [
			$row["transaction_date"],
			$row["title"],
			$row["transaction_type"] === "income" ? "Pemasukan" : "Pengeluaran",
			$row["category_name"],
			$row["account_name"],
			$row["amount"]
		]);
	}
	fclose($output);
	exit;
}

require __DIR__ . "/../includes/header.php";
?>
<div class="page-heading report-heading">
	<div><span class="subtitle">LAPORAN KEUANGAN</span><h1><?= appEscape($monthLabel) ?></h1><p>Ringkasan pemasukan, pengeluaran, dan aktivitas pada periode ini.</p></div>
	<div class="report-actions print-hide">
		<a class="secondary-button" href="?month=<?= appEscape($month) ?>&amp;type=<?= appEscape($type) ?>&amp;export=csv">Unduh CSV</a>
		<button class="secondary-button" type="button" data-print-page>Cetak</button>
	</div>
</div>

<form class="report-period-form print-hide" method="get" action="">
	<label for="reportMonth">Pilih Bulan</label>
	<input id="reportMonth" name="month" type="month" value="<?= appEscape($month) ?>">
	<input type="hidden" name="type" value="<?= appEscape($type) ?>">
	<button class="secondary-button" type="submit">Terapkan</button>
</form>

<nav class="filter-pills print-hide" aria-label="Filter jenis transaksi">
	<a class="<?= $type === "all" ? "active" : "" ?>" href="?month=<?= appEscape($month) ?>">Semua</a>
	<a class="<?= $type === "income" ? "active" : "" ?>" href="?month=<?= appEscape($month) ?>&amp;type=income">Pemasukan</a>
	<a class="<?= $type === "expense" ? "active" : "" ?>" href="?month=<?= appEscape($month) ?>&amp;type=expense">Pengeluaran</a>
</nav>

<section class="report-summary" aria-label="Ringkasan arus kas">
	<div class="report-net-card">
		<div class="report-card-top"><span>ARUS KAS BERSIH</span><span class="report-health"><?= $netTotal >= 0 ? "Positif" : "Perlu diperhatikan" ?></span></div>
		<strong><?= $netTotal < 0 ? "−" : "+" ?><?= formatRupiah(abs($netTotal)) ?></strong>
		<p>Rasio tabungan: <?= number_format($savingsRatio, 1, ",", ".") ?>%</p>
		<div class="report-mini-stats">
			<div><span>Total Masuk</span><strong><?= formatRupiah($incomeTotal) ?></strong></div>
			<div><span>Total Keluar</span><strong><?= formatRupiah($expenseTotal) ?></strong></div>
		</div>
	</div>
	<div class="report-stat-card"><span>Total Transaksi</span><strong><?= (int) $summary["transaction_count"] ?></strong><small>pada <?= appEscape($monthLabel) ?></small></div>
</section>

<section class="report-section">
	<div class="section-heading"><div><h2>Distribusi Pengeluaran</h2><p>Total belanja <?= formatRupiah($expenseTotal) ?></p></div></div>
	<?php if ($categoryTotals && $expenseTotal > 0): ?>
		<div class="report-category-list">
			<?php foreach ($categoryTotals as $category): ?>
				<?php $share = min(100, ((float) $category["category_total"] / $expenseTotal) * 100); ?>
				<div class="report-category-row">
					<div class="report-category-line"><strong><?= appEscape($category["category_name"]) ?></strong><span><?= formatRupiah($category["category_total"]) ?> · <?= number_format($share, 0) ?>%</span></div>
					<div class="report-progress"><span style="width: <?= number_format($share, 2, ".", "") ?>%"></span></div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else: ?>
		<p class="empty-inline">Belum ada pengeluaran pada periode ini.</p>
	<?php endif; ?>
</section>

<section class="report-section">
	<div class="section-heading"><div><h2>Rincian Mutasi</h2><p><?= count($history) ?> transaksi ditampilkan</p></div></div>
	<?php if ($history): ?>
		<div class="report-history">
			<?php foreach ($history as $row): ?>
				<?php $isIncome = $row["transaction_type"] === "income"; ?>
				<article class="report-history-row">
					<div class="report-history-icon <?= $isIncome ? "income-mark" : "expense-mark" ?>" aria-hidden="true"><?= $isIncome ? "↑" : "↓" ?></div>
					<div class="report-history-main"><strong><?= appEscape($row["title"]) ?></strong><span><?= appEscape($row["category_name"]) ?> · <?= appEscape($row["account_name"]) ?></span></div>
					<time datetime="<?= appEscape($row["transaction_date"]) ?>"><?= date("d-m-Y", strtotime($row["transaction_date"])) ?></time>
					<strong class="<?= $isIncome ? "income-text" : "expense-text" ?>"><?= $isIncome ? "+" : "−" ?><?= formatRupiah($row["amount"]) ?></strong>
				</article>
			<?php endforeach; ?>
		</div>
	<?php else: ?>
		<p class="empty-inline">Belum ada transaksi pada periode ini.</p>
	<?php endif; ?>
</section>
<?php require __DIR__ . "/../includes/footer.php"; ?>
