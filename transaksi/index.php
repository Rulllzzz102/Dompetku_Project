<?php
require_once __DIR__ . "/../includes/app_helpers.php";
require_once __DIR__ . "/../config/auth_check.php";
require_once __DIR__ . "/../functions/transaksi.php";

$pageTitle = "Transaksi";
$activeMenu = "transactions";
$userId = (int) $_SESSION["user_id"];
$filterType = $_GET["type"] ?? "all";

if (!in_array($filterType, ["all", "income", "expense"], true)) {
	$filterType = "all";
}

$transactions = getUserTransactions($koneksi, $userId, $filterType);

require __DIR__ . "/../includes/header.php";
?>
<div class="page-heading">
	<div>
		<span class="subtitle">CATATAN KEUANGAN</span>
		<h1>Semua Transaksi</h1>
		<p>Kelola pemasukan dan pengeluaran Anda.</p>
	</div>
	<a class="btn-primary" href="tambah.php">+ Tambah Transaksi</a>
</div>

<?php if (isset($_GET["saved"])): ?>
	<p class="notice notice-success" role="status">Transaksi berhasil disimpan.</p>
<?php elseif (isset($_GET["deleted"])): ?>
	<p class="notice notice-success" role="status">Transaksi berhasil dihapus.</p>
<?php endif; ?>

<nav class="filter-pills" aria-label="Filter jenis transaksi">
	<a class="<?= $filterType === "all" ? "active" : "" ?>" href="index.php">Semua</a>
	<a class="<?= $filterType === "income" ? "active" : "" ?>" href="?type=income">Pemasukan</a>
	<a class="<?= $filterType === "expense" ? "active" : "" ?>" href="?type=expense">Pengeluaran</a>
</nav>

<div class="table-container transaction-table-wrap">
	<table>
		<thead>
			<tr>
				<th>Transaksi</th>
				<th>Kategori</th>
				<th>Akun</th>
				<th>Tanggal</th>
				<th>Jumlah</th>
				<th><span class="visually-hidden">Tindakan</span></th>
			</tr>
		</thead>
		<tbody id="transactionTable">
			<?php if (!$transactions): ?>
				<tr>
					<td colspan="6" class="empty-table-cell">Belum ada transaksi pada filter ini.</td>
				</tr>
			<?php else: ?>
				<?php foreach ($transactions as $transaction): ?>
					<?php $isIncome = $transaction["transaction_type"] === "income"; ?>
					<tr>
						<td><strong><?= appEscape($transaction["title"]) ?></strong></td>
						<td><?= appEscape($transaction["category_name"] ?? "Tanpa kategori") ?></td>
						<td><?= appEscape($transaction["account_name"] ?? "Belum dipilih") ?></td>
						<td><?= date("d M Y", strtotime($transaction["transaction_date"])) ?></td>
						<td class="amount <?= $isIncome ? "income-text" : "expense-text" ?>">
							<?= $isIncome ? "+" : "−" ?><?= formatRupiah($transaction["amount"]) ?>
						</td>
						<td class="transaction-actions">
							<a class="table-action" href="edit.php?id=<?= (int) $transaction["transaction_id"] ?>">Edit</a>
							<form method="post" action="hapus.php" data-confirm="Hapus transaksi ini?">
								<input type="hidden" name="csrf_token" value="<?= appEscape(csrfToken()) ?>">
								<input type="hidden" name="transaction_id" value="<?= (int) $transaction["transaction_id"] ?>">
								<button class="table-action delete-action" type="submit">Hapus</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
<p class="table-note">Menampilkan maksimal 200 transaksi terbaru.</p>
<?php require __DIR__ . "/../includes/footer.php"; ?>
