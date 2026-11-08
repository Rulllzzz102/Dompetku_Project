<?php
require_once __DIR__ . "/../includes/app_helpers.php";
require_once __DIR__ . "/../config/auth_check.php";

$pageTitle = "Grafik & Analisis";
$activeMenu = "charts";
$userId = (int) $_SESSION["user_id"];
$period = $_GET["period"] ?? "monthly";
if (!in_array($period, ["weekly", "monthly", "yearly"], true)) {
	$period = "monthly";
}

$today = new DateTimeImmutable("today");
if ($period === "weekly") {
	$start = $today->modify("-6 days");
	$end = $today;
	$dateFormat = "%Y-%m-%d";
	$periodLabel = "7 Hari Terakhir";
} elseif ($period === "yearly") {
	$start = $today->setDate((int) $today->format("Y"), 1, 1);
	$end = $today->setDate((int) $today->format("Y"), 12, 31);
	$dateFormat = "%Y-%m";
	$periodLabel = "Tahun " . $today->format("Y");
} else {
	$start = $today->modify("first day of this month")->modify("-5 months");
	$end = $today;
	$dateFormat = "%Y-%m";
	$periodLabel = "6 Bulan Terakhir";
}

$startDate = $start->format("Y-m-d");
$endDate = $end->format("Y-m-d");
$seriesQuery = mysqli_prepare(
	$koneksi,
	"SELECT DATE_FORMAT(transaction_date, '$dateFormat') AS period_key,
			transaction_type, SUM(amount) AS total
	 FROM transactions
	 WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
	 GROUP BY period_key, transaction_type"
);
mysqli_stmt_bind_param($seriesQuery, "iss", $userId, $startDate, $endDate);
mysqli_stmt_execute($seriesQuery);
$seriesResult = mysqli_stmt_get_result($seriesQuery);
$seriesRows = $seriesResult ? mysqli_fetch_all($seriesResult, MYSQLI_ASSOC) : [];
mysqli_stmt_close($seriesQuery);

$monthNames = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
$buckets = [];
if ($period === "weekly") {
	for ($day = $start; $day <= $end; $day = $day->modify("+1 day")) {
		$key = $day->format("Y-m-d");
		$buckets[$key] = ["label" => $day->format("d/m"), "income" => 0.0, "expense" => 0.0];
	}
} else {
	$firstMonth = $period === "yearly" ? $start : $start->modify("first day of this month");
	$lastMonth = $period === "yearly" ? $end->modify("first day of this month") : $today->modify("first day of this month");
	for ($month = $firstMonth; $month <= $lastMonth; $month = $month->modify("+1 month")) {
		$key = $month->format("Y-m");
		$buckets[$key] = ["label" => $monthNames[(int) $month->format("n") - 1], "income" => 0.0, "expense" => 0.0];
	}
}

$totalIncome = 0.0;
$totalExpense = 0.0;
foreach ($seriesRows as $row) {
	if (!isset($buckets[$row["period_key"]])) {
		continue;
	}
	$value = (float) $row["total"];
	$buckets[$row["period_key"]][$row["transaction_type"]] = $value;
	if ($row["transaction_type"] === "income") {
		$totalIncome += $value;
	} else {
		$totalExpense += $value;
	}
}

$maxPeriodTotal = 0.0;
foreach ($buckets as $bucket) {
	$maxPeriodTotal = max($maxPeriodTotal, $bucket["income"], $bucket["expense"]);
}

$categoryQuery = mysqli_prepare(
	$koneksi,
	"SELECT COALESCE(c.category_name, 'Lainnya') AS category_name, SUM(t.amount) AS total
	 FROM transactions AS t
	 LEFT JOIN categories AS c ON c.category_id = t.category_id
	 WHERE t.user_id = ? AND t.transaction_type = 'expense' AND t.transaction_date BETWEEN ? AND ?
	 GROUP BY c.category_id, c.category_name
	 ORDER BY total DESC LIMIT 6"
);
mysqli_stmt_bind_param($categoryQuery, "iss", $userId, $startDate, $endDate);
mysqli_stmt_execute($categoryQuery);
$categoryResult = mysqli_stmt_get_result($categoryQuery);
$categories = $categoryResult ? mysqli_fetch_all($categoryResult, MYSQLI_ASSOC) : [];
mysqli_stmt_close($categoryQuery);

$accountBalanceQuery = mysqli_prepare(
	$koneksi,
	"SELECT COALESCE(SUM(initial_balance), 0) AS total FROM accounts WHERE user_id = ? AND is_active = 1"
);
mysqli_stmt_bind_param($accountBalanceQuery, "i", $userId);
mysqli_stmt_execute($accountBalanceQuery);
$accountBalanceResult = mysqli_stmt_get_result($accountBalanceQuery);
$accountBalanceRow = $accountBalanceResult ? mysqli_fetch_assoc($accountBalanceResult) : ["total" => 0];
mysqli_stmt_close($accountBalanceQuery);

$allTimeQuery = mysqli_prepare(
	$koneksi,
	"SELECT COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE -amount END), 0) AS net
	 FROM transactions WHERE user_id = ?"
);
mysqli_stmt_bind_param($allTimeQuery, "i", $userId);
mysqli_stmt_execute($allTimeQuery);
$allTimeResult = mysqli_stmt_get_result($allTimeQuery);
$allTimeRow = $allTimeResult ? mysqli_fetch_assoc($allTimeResult) : ["net" => 0];
mysqli_stmt_close($allTimeQuery);

$projectedBalance = (float) $accountBalanceRow["total"] + (float) $allTimeRow["net"];
$dayCount = max(1, (int) $start->diff(min($today, $end))->days + 1);
$averageDailyExpense = $totalExpense / $dayCount;
$categoryColors = ["#006c49", "#39d7a4", "#9bc7f5", "#d28a97", "#8cc68f", "#f0c46d"];
$donutStops = [];
$donutPosition = 0.0;
foreach ($categories as $index => $category) {
	$share = $totalExpense > 0 ? ((float) $category["total"] / $totalExpense) * 100 : 0;
	$nextPosition = $donutPosition + $share;
	$color = $categoryColors[$index % count($categoryColors)];
	$donutStops[] = $color . " " . number_format($donutPosition, 2, ".", "") . "% " . number_format($nextPosition, 2, ".", "") . "%";
	$donutPosition = $nextPosition;
}
$donutStyle = $donutStops ? "conic-gradient(" . implode(", ", $donutStops) . ")" : "#eaf1ff";

require __DIR__ . "/../includes/header.php";
?>
<div class="page-heading charts-heading">
	<div><span class="subtitle">ANALISIS KEUANGAN</span><h1>Grafik &amp; Analisis</h1><p>Pantau tren arus kas dan distribusi pengeluaran.</p></div>
	<nav class="filter-pills chart-period-tabs" aria-label="Periode grafik">
		<a class="<?= $period === "weekly" ? "active" : "" ?>" href="?period=weekly">Mingguan</a>
		<a class="<?= $period === "monthly" ? "active" : "" ?>" href="?period=monthly">Bulanan</a>
		<a class="<?= $period === "yearly" ? "active" : "" ?>" href="?period=yearly">Tahunan</a>
	</nav>
</div>

<section class="analysis-metrics" aria-label="Ringkasan analisis">
	<article class="analysis-metric"><span>PEMASUKAN PERIODE</span><strong class="income-text"><?= formatRupiah($totalIncome) ?></strong><small><?= appEscape($periodLabel) ?></small></article>
	<article class="analysis-metric"><span>PENGELUARAN PERIODE</span><strong class="expense-text"><?= formatRupiah($totalExpense) ?></strong><small>Rata-rata <?= formatRupiah($averageDailyExpense) ?> per hari</small></article>
	<article class="analysis-metric"><span>ESTIMASI SALDO SAAT INI</span><strong><?= formatRupiah($projectedBalance) ?></strong><small>Saldo awal akun + seluruh mutasi</small></article>
</section>

<div class="analysis-grid">
	<section class="finance-panel analysis-chart-panel">
		<div class="panel-heading">
			<div><h2>Perbandingan Arus Kas</h2><p><?= appEscape($periodLabel) ?></p></div>
			<div class="chart-legend"><span><i class="legend-dot income-dot"></i>Masuk</span><span><i class="legend-dot expense-dot"></i>Keluar</span></div>
		</div>
		<?php if ($totalIncome > 0 || $totalExpense > 0): ?>
			<div class="analysis-bars" role="img" aria-label="Grafik perbandingan pemasukan dan pengeluaran">
				<?php foreach ($buckets as $bucket): ?>
					<?php
					$incomeHeight = $maxPeriodTotal > 0 ? ($bucket["income"] / $maxPeriodTotal) * 100 : 0;
					$expenseHeight = $maxPeriodTotal > 0 ? ($bucket["expense"] / $maxPeriodTotal) * 100 : 0;
					?>
					<div class="analysis-bar-group">
						<div class="analysis-bar-pair">
							<span class="analysis-bar income-bar" title="Masuk <?= appEscape(formatRupiah($bucket["income"])) ?>" style="height: <?= number_format($incomeHeight, 2, ".", "") ?>%"></span>
							<span class="analysis-bar expense-bar" title="Keluar <?= appEscape(formatRupiah($bucket["expense"])) ?>" style="height: <?= number_format($expenseHeight, 2, ".", "") ?>%"></span>
						</div>
						<span class="analysis-bar-label"><?= appEscape($bucket["label"]) ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="chart-empty" role="status"><strong>Belum ada data untuk grafik</strong><span>Grafik akan terisi setelah transaksi dicatat.</span></div>
		<?php endif; ?>
	</section>

	<section class="finance-panel analysis-category-panel">
		<div class="panel-heading"><div><h2>Distribusi Pengeluaran</h2><p><?= appEscape($periodLabel) ?></p></div></div>
		<?php if ($categories && $totalExpense > 0): ?>
			<div class="category-donut" style="background: <?= appEscape($donutStyle) ?>"><div><span>Total Keluar</span><strong><?= formatRupiah($totalExpense) ?></strong></div></div>
			<div class="analysis-category-list">
				<?php foreach ($categories as $index => $category): ?>
					<?php $share = $totalExpense > 0 ? ((float) $category["total"] / $totalExpense) * 100 : 0; ?>
					<div class="analysis-category-row"><span class="category-color" style="background: <?= $categoryColors[$index % count($categoryColors)] ?>"></span><strong><?= appEscape($category["category_name"]) ?></strong><span><?= number_format($share, 0) ?>%</span><b><?= formatRupiah($category["total"]) ?></b></div>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="budget-empty" role="status"><span aria-hidden="true">◴</span><strong>Belum ada data kategori</strong><p>Pengeluaran berdasarkan kategori akan tampil di sini.</p></div>
		<?php endif; ?>
	</section>
</div>

<section class="finance-panel analysis-insight">
	<span class="analysis-insight-mark" aria-hidden="true">↗</span>
	<div><h2>Ringkasan Periode</h2><p><?php if ($totalIncome === 0 && $totalExpense === 0): ?>Belum ada cukup transaksi untuk menyusun analisis periode.<?php elseif ($totalIncome >= $totalExpense): ?>Pemasukan pada periode ini lebih besar atau sama dengan pengeluaran.<?php else: ?>Pengeluaran pada periode ini melebihi pemasukan. Tinjau kategori pengeluaran untuk melihat rinciannya.<?php endif; ?></p></div>
</section>
<?php require __DIR__ . "/../includes/footer.php"; ?>
