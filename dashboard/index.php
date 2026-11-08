<?php
require_once __DIR__ . "/../includes/app_helpers.php";
require_once __DIR__ . "/../config/auth_check.php";
require_once __DIR__ . "/../functions/transaksi.php";

$pageTitle = "Dashboard";
$activeMenu = "dashboard";
$userId = (int) $_SESSION["user_id"];
$displayName = $_SESSION["display_name"] ?? "Pengguna DompetKu";
$currentMonth = (int) date("n");
$currentYear = (int) date("Y");
$monthStart = date("Y-m-01");
$monthEnd = date("Y-m-t");
$dashboardMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	$action = $_POST["dashboard_action"] ?? "";

	if (!isValidCsrfToken($_POST["csrf_token"] ?? null)) {
		$dashboardMessage = "Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.";
	} elseif ($action === "add_account") {
		$accountName = trim($_POST["account_name"] ?? "");
		$accountType = $_POST["account_type"] ?? "cash";
		$initialBalance = trim($_POST["initial_balance"] ?? "0");
		$allowedAccountTypes = ["cash", "bank", "ewallet", "other"];

		if ($accountName === "" || strlen($accountName) > 100) {
			$dashboardMessage = "Nama akun wajib diisi dan maksimal 100 karakter.";
		} elseif (!in_array($accountType, $allowedAccountTypes, true)) {
			$dashboardMessage = "Jenis akun tidak valid.";
		} elseif (!preg_match("/^\d+(\.\d{1,2})?$/", $initialBalance)) {
			$dashboardMessage = "Saldo awal harus berupa angka nol atau lebih.";
		} else {
			$insertAccount = mysqli_prepare(
				$koneksi,
				"INSERT INTO accounts (user_id, account_name, account_type, initial_balance) VALUES (?, ?, ?, ?)"
			);
			mysqli_stmt_bind_param($insertAccount, "isss", $userId, $accountName, $accountType, $initialBalance);
			if (mysqli_stmt_execute($insertAccount)) {
				mysqli_stmt_close($insertAccount);
				header("Location: index.php?saved=account");
				exit;
			}
			$duplicateAccount = mysqli_stmt_errno($insertAccount) === 1062;
			mysqli_stmt_close($insertAccount);
			$dashboardMessage = $duplicateAccount ? "Nama akun tersebut sudah digunakan." : "Akun belum dapat disimpan.";
		}
	} elseif ($action === "save_budget") {
		$budgetCategoryId = (int) ($_POST["category_id"] ?? 0);
		$budgetLimit = trim($_POST["limit_amount"] ?? "");

		if (!$budgetCategoryId || !userOwnsTransactionCategory($koneksi, $budgetCategoryId, $userId, "expense")) {
			$dashboardMessage = "Pilih kategori pengeluaran yang valid.";
		} elseif (!preg_match("/^\d+(\.\d{1,2})?$/", $budgetLimit) || (float) $budgetLimit <= 0) {
			$dashboardMessage = "Batas anggaran harus lebih besar dari nol.";
		} else {
			$saveBudget = mysqli_prepare(
				$koneksi,
				"INSERT INTO budgets (user_id, category_id, period_month, period_year, limit_amount)
				 VALUES (?, ?, ?, ?, ?)
				 ON DUPLICATE KEY UPDATE limit_amount = VALUES(limit_amount)"
			);
			mysqli_stmt_bind_param($saveBudget, "iiiis", $userId, $budgetCategoryId, $currentMonth, $currentYear, $budgetLimit);
			if (mysqli_stmt_execute($saveBudget)) {
				mysqli_stmt_close($saveBudget);
				header("Location: index.php?saved=budget");
				exit;
			}
			mysqli_stmt_close($saveBudget);
			$dashboardMessage = "Anggaran belum dapat disimpan.";
		}
	}
}

$totalsQuery = mysqli_prepare(
	$koneksi,
	"SELECT
		COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS income_total,
		COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS expense_total
	 FROM transactions WHERE user_id = ?"
);
mysqli_stmt_bind_param($totalsQuery, "i", $userId);
mysqli_stmt_execute($totalsQuery);
$totalsResult = mysqli_stmt_get_result($totalsQuery);
$totals = $totalsResult ? mysqli_fetch_assoc($totalsResult) : ["income_total" => 0, "expense_total" => 0];
mysqli_stmt_close($totalsQuery);

$initialBalanceQuery = mysqli_prepare(
	$koneksi,
	"SELECT COALESCE(SUM(initial_balance), 0) AS total FROM accounts WHERE user_id = ? AND is_active = 1"
);
mysqli_stmt_bind_param($initialBalanceQuery, "i", $userId);
mysqli_stmt_execute($initialBalanceQuery);
$initialBalanceResult = mysqli_stmt_get_result($initialBalanceQuery);
$initialBalance = $initialBalanceResult ? mysqli_fetch_assoc($initialBalanceResult) : ["total" => 0];
mysqli_stmt_close($initialBalanceQuery);

$balance = (float) $initialBalance["total"] + (float) $totals["income_total"] - (float) $totals["expense_total"];

$monthlyQuery = mysqli_prepare(
	$koneksi,
	"SELECT
		COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) AS income_total,
		COALESCE(SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END), 0) AS expense_total
	 FROM transactions WHERE user_id = ? AND transaction_date BETWEEN ? AND ?"
);
mysqli_stmt_bind_param($monthlyQuery, "iss", $userId, $monthStart, $monthEnd);
mysqli_stmt_execute($monthlyQuery);
$monthlyResult = mysqli_stmt_get_result($monthlyQuery);
$monthlyTotals = $monthlyResult ? mysqli_fetch_assoc($monthlyResult) : ["income_total" => 0, "expense_total" => 0];
mysqli_stmt_close($monthlyQuery);

$trendStart = date("Y-m-01", strtotime("-5 months"));
$trendQuery = mysqli_prepare(
	$koneksi,
	"SELECT DATE_FORMAT(transaction_date, '%Y-%m') AS period_key, transaction_type, SUM(amount) AS total
	 FROM transactions WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
	 GROUP BY period_key, transaction_type ORDER BY period_key"
);
mysqli_stmt_bind_param($trendQuery, "iss", $userId, $trendStart, $monthEnd);
mysqli_stmt_execute($trendQuery);
$trendResult = mysqli_stmt_get_result($trendQuery);
$trendRows = $trendResult ? mysqli_fetch_all($trendResult, MYSQLI_ASSOC) : [];
mysqli_stmt_close($trendQuery);

$monthNames = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
$trend = [];
$trendMonth = new DateTimeImmutable($trendStart);
for ($index = 0; $index < 6; $index++) {
	$key = $trendMonth->format("Y-m");
	$trend[$key] = ["label" => $monthNames[(int) $trendMonth->format("n") - 1], "income" => 0.0, "expense" => 0.0];
	$trendMonth = $trendMonth->modify("+1 month");
}
$maxTrend = 0.0;
foreach ($trendRows as $row) {
	if (isset($trend[$row["period_key"]])) {
		$trend[$row["period_key"]][$row["transaction_type"]] = (float) $row["total"];
	}
}
foreach ($trend as $point) {
	$maxTrend = max($maxTrend, $point["income"], $point["expense"]);
}
$hasTrend = (float) $monthlyTotals["income_total"] > 0 || (float) $monthlyTotals["expense_total"] > 0;
foreach ($trend as $point) {
	if ($point["income"] > 0 || $point["expense"] > 0) {
		$hasTrend = true;
		break;
	}
}

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
	 ORDER BY b.limit_amount DESC"
);
mysqli_stmt_bind_param($budgetQuery, "ssiii", $monthStart, $monthEnd, $userId, $currentMonth, $currentYear);
mysqli_stmt_execute($budgetQuery);
$budgetResult = mysqli_stmt_get_result($budgetQuery);
$budgets = $budgetResult ? mysqli_fetch_all($budgetResult, MYSQLI_ASSOC) : [];
mysqli_stmt_close($budgetQuery);
$budgetCategories = getTransactionCategories($koneksi, $userId, "expense");

$accountQuery = mysqli_prepare(
	$koneksi,
	"SELECT a.account_id, a.account_name, a.account_type,
			a.initial_balance + COALESCE(SUM(CASE WHEN t.transaction_type = 'income' THEN t.amount ELSE -t.amount END), 0) AS current_balance
	 FROM accounts AS a
	 LEFT JOIN transactions AS t ON t.account_id = a.account_id AND t.user_id = a.user_id
	 WHERE a.user_id = ? AND a.is_active = 1
	 GROUP BY a.account_id, a.account_name, a.account_type, a.initial_balance
	 ORDER BY a.account_name LIMIT 6"
);
mysqli_stmt_bind_param($accountQuery, "i", $userId);
mysqli_stmt_execute($accountQuery);
$accountResult = mysqli_stmt_get_result($accountQuery);
$accounts = $accountResult ? mysqli_fetch_all($accountResult, MYSQLI_ASSOC) : [];
mysqli_stmt_close($accountQuery);

$recentQuery = mysqli_prepare(
	$koneksi,
	"SELECT t.transaction_id, t.title, t.amount, t.transaction_date, t.transaction_type,
			COALESCE(c.category_name, 'Lainnya') AS category_name
	 FROM transactions AS t
	 LEFT JOIN categories AS c ON c.category_id = t.category_id
	 WHERE t.user_id = ? ORDER BY t.transaction_date DESC, t.transaction_id DESC LIMIT 8"
);
mysqli_stmt_bind_param($recentQuery, "i", $userId);
mysqli_stmt_execute($recentQuery);
$recentResult = mysqli_stmt_get_result($recentQuery);
$recentTransactions = $recentResult ? mysqli_fetch_all($recentResult, MYSQLI_ASSOC) : [];
mysqli_stmt_close($recentQuery);

require __DIR__ . "/../includes/header.php";
?>
<section class="welcome dashboard-welcome">
	<div><span class="subtitle">RINGKASAN EKSEKUTIF · <?= date("d/m/Y") ?></span><h1>Selamat datang, <?= appEscape($displayName) ?></h1><p>Pantau kondisi keuangan Anda dalam satu tempat.</p></div>
	<div class="welcome-actions"><a class="secondary-button" href="../laporan/index.php">Lihat Laporan</a><a class="btn-primary" href="../transaksi/tambah.php">+ Tambah Transaksi</a></div>
</section>

<?php if (isset($_GET["saved"])): ?>
	<p class="notice notice-success" role="status"><?= $_GET["saved"] === "account" ? "Akun berhasil ditambahkan." : "Anggaran bulan ini berhasil disimpan." ?></p>
<?php elseif ($dashboardMessage !== ""): ?>
	<p class="notice notice-error" role="alert"><?= appEscape($dashboardMessage) ?></p>
<?php endif; ?>

<section class="summary dashboard-summary" aria-label="Ringkasan saldo">
	<article class="summary-card"><div class="card-icon balance-icon"><img class="balance-logo" src="../assets/logo-dompetku.svg" alt=""></div><span>Total Saldo</span><h2><?= formatRupiah($balance) ?></h2><small>Saldo awal akun + semua transaksi</small></article>
	<article class="summary-card income"><div class="card-icon income-icon">↗</div><span>Total Pemasukan</span><h2><?= formatRupiah($totals["income_total"]) ?></h2><small>Akumulasi seluruh catatan</small></article>
	<article class="summary-card expense"><div class="card-icon expense-icon">↘</div><span>Total Pengeluaran</span><h2><?= formatRupiah($totals["expense_total"]) ?></h2><small>Akumulasi seluruh catatan</small></article>
	<article class="summary-card saving"><div class="card-icon saving-icon">◴</div><span>Anggaran Bulan Ini</span><h2><?= count($budgets) ?> kategori</h2><small>Target tabungan belum diatur</small></article>
</section>

<div class="dashboard-widgets" hidden>
	<section class="finance-panel cashflow-panel">
		<div class="panel-heading"><div><h2>Tren Arus Kas</h2><p>Enam bulan terakhir</p></div><div class="chart-legend"><span><i class="legend-dot income-dot"></i>Pemasukan</span><span><i class="legend-dot expense-dot"></i>Pengeluaran</span></div></div>
		<?php if ($hasTrend): ?>
			<div class="analysis-bars dashboard-bars" role="img" aria-label="Tren pemasukan dan pengeluaran enam bulan terakhir">
				<?php foreach ($trend as $point): ?>
					<?php $incomeHeight = $maxTrend > 0 ? ($point["income"] / $maxTrend) * 100 : 0; $expenseHeight = $maxTrend > 0 ? ($point["expense"] / $maxTrend) * 100 : 0; ?>
					<div class="analysis-bar-group"><div class="analysis-bar-pair"><span class="analysis-bar income-bar" title="Masuk <?= appEscape(formatRupiah($point["income"])) ?>" style="height: <?= number_format($incomeHeight, 2, ".", "") ?>%"></span><span class="analysis-bar expense-bar" title="Keluar <?= appEscape(formatRupiah($point["expense"])) ?>" style="height: <?= number_format($expenseHeight, 2, ".", "") ?>%"></span></div><span class="analysis-bar-label"><?= appEscape($point["label"]) ?></span></div>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="chart-empty" role="status"><strong>Belum ada data arus kas</strong><span>Grafik akan terisi setelah transaksi dicatat.</span></div>
		<?php endif; ?>
	</section>

	<section class="finance-panel budget-panel" id="budgets">
		<div class="panel-heading"><div><h2>Alokasi Anggaran</h2><p>Penggunaan anggaran bulan ini</p></div><details class="panel-manager"><summary>+ Atur</summary><form method="post" action=""><input type="hidden" name="csrf_token" value="<?= appEscape(csrfToken()) ?>"><input type="hidden" name="dashboard_action" value="save_budget"><label for="budgetCategory">Kategori pengeluaran</label><select id="budgetCategory" name="category_id" required><?php foreach ($budgetCategories as $category): ?><option value="<?= (int) $category["category_id"] ?>"><?= appEscape($category["category_name"]) ?></option><?php endforeach; ?></select><label for="budgetLimit">Batas per bulan</label><input id="budgetLimit" name="limit_amount" type="number" min="0.01" step="0.01" required><button class="auth-submit" type="submit">Simpan Anggaran</button></form></details></div>
		<?php if ($budgets): ?>
			<div class="dashboard-budget-list">
				<?php foreach ($budgets as $budget): ?>
					<?php $percent = (float) $budget["limit_amount"] > 0 ? min(100, ((float) $budget["spent"] / (float) $budget["limit_amount"]) * 100) : 0; ?>
					<div class="dashboard-budget-row"><div><strong><?= appEscape($budget["category_name"]) ?></strong><span><?= number_format($percent, 0) ?>%</span></div><div class="report-progress"><span style="width: <?= number_format($percent, 2, ".", "") ?>%"></span></div><small><?= formatRupiah($budget["spent"]) ?> dari <?= formatRupiah($budget["limit_amount"]) ?></small></div>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="budget-empty" role="status"><span aria-hidden="true">◴</span><strong>Belum ada anggaran</strong><p>Anggaran kategori akan tampil di sini.</p></div>
		<?php endif; ?>
	</section>

	<section class="finance-panel accounts-panel" id="accounts">
		<div class="panel-heading"><div><h2>Akun Keuangan</h2><p>Saldo setiap akun aktif</p></div><details class="panel-manager"><summary>+ Tambah Akun</summary><form method="post" action=""><input type="hidden" name="csrf_token" value="<?= appEscape(csrfToken()) ?>"><input type="hidden" name="dashboard_action" value="add_account"><label for="accountName">Nama akun</label><input id="accountName" name="account_name" type="text" maxlength="100" required><label for="accountType">Jenis akun</label><select id="accountType" name="account_type"><option value="cash">Tunai</option><option value="bank">Bank</option><option value="ewallet">E-wallet</option><option value="other">Lainnya</option></select><label for="initialBalance">Saldo awal</label><input id="initialBalance" name="initial_balance" type="number" min="0" step="0.01" value="0" required><button class="auth-submit" type="submit">Simpan Akun</button></form></details></div>
		<?php if ($accounts): ?>
			<div class="dashboard-account-list">
				<?php foreach ($accounts as $account): ?>
					<div class="dashboard-account-row"><span class="account-type-mark" aria-hidden="true">▣</span><div><strong><?= appEscape($account["account_name"]) ?></strong><small><?= appEscape(ucfirst($account["account_type"])) ?></small></div><b><?= formatRupiah($account["current_balance"]) ?></b></div>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="account-empty" role="status"><span aria-hidden="true">▣</span><div><strong>Belum ada akun tambahan</strong><p>Transaksi tetap dapat dicatat tanpa memilih akun.</p></div></div>
		<?php endif; ?>
	</section>
</div>

<div class="section-heading"><div><h2>Transaksi Terbaru</h2><p>Aktivitas keuangan Anda</p></div><a href="../transaksi/index.php">Lihat Semua</a></div>
<div class="table-container"><table><thead><tr><th>Transaksi</th><th>Kategori</th><th>Tanggal</th><th>Jenis</th><th>Jumlah</th></tr></thead><tbody>
<?php if ($recentTransactions): ?>
	<?php foreach ($recentTransactions as $transaction): ?>
		<?php $isIncome = $transaction["transaction_type"] === "income"; ?>
		<tr><td><strong><?= appEscape($transaction["title"]) ?></strong></td><td><?= appEscape($transaction["category_name"]) ?></td><td><?= date("d-m-Y", strtotime($transaction["transaction_date"])) ?></td><td><span class="badge <?= $isIncome ? "income-badge" : "expense-badge" ?>"><?= $isIncome ? "Pemasukan" : "Pengeluaran" ?></span></td><td class="amount <?= $isIncome ? "income-text" : "expense-text" ?>"><?= $isIncome ? "+" : "−" ?><?= formatRupiah($transaction["amount"]) ?></td></tr>
	<?php endforeach; ?>
<?php else: ?>
	<tr><td colspan="5" class="empty-table-cell">Belum ada transaksi. Tambahkan transaksi pertama Anda.</td></tr>
<?php endif; ?>
</tbody></table></div>
<?php require __DIR__ . "/../includes/footer.php"; ?>
