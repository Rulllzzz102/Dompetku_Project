<?php
require_once __DIR__ . "/../includes/app_helpers.php";
require_once __DIR__ . "/../config/auth_check.php";
require_once __DIR__ . "/../functions/transaksi.php";

$pageTitle = "Edit Transaksi";
$activeMenu = "transactions";
$userId = (int) $_SESSION["user_id"];
$transactionId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT) ?: 0;
$errors = [];

$transaction = getUserTransaction($koneksi, $transactionId, $userId);

if (!$transaction) {
	http_response_code(404);
	$pageTitle = "Transaksi Tidak Ditemukan";
	require __DIR__ . "/../includes/header.php";
	echo '<div class="page-heading"><div><h1>Transaksi tidak ditemukan</h1><p>Transaksi ini tidak tersedia pada akun Anda.</p></div><a class="text-link" href="index.php">Kembali</a></div>';
	require __DIR__ . "/../includes/footer.php";
	exit;
}

$type = $_POST["transaction_type"] ?? ($_GET["type"] ?? $transaction["transaction_type"]);
if (!in_array($type, ["income", "expense"], true)) {
	$type = $transaction["transaction_type"];
}
$title = $transaction["title"];
$amount = (string) $transaction["amount"];
$transactionDate = $transaction["transaction_date"];
$categoryId = (int) $transaction["category_id"];
$accountId = $transaction["account_id"] === null ? "" : (string) $transaction["account_id"];
$notes = $transaction["notes"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	$title = trim($_POST["title"] ?? "");
	$amount = trim($_POST["amount"] ?? "");
	$transactionDate = $_POST["transaction_date"] ?? "";
	$categoryId = (int) ($_POST["category_id"] ?? 0);
	$accountId = $_POST["account_id"] ?? "";
	$notes = trim($_POST["notes"] ?? "");

	if (!isValidCsrfToken($_POST["csrf_token"] ?? null)) {
		$errors[] = "Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.";
	} elseif ($title === "" || strlen($title) > 150) {
		$errors[] = "Judul transaksi wajib diisi dan maksimal 150 karakter.";
	} elseif (!preg_match("/^\d+(\.\d{1,2})?$/", $amount) || (float) $amount <= 0) {
		$errors[] = "Masukkan nominal lebih besar dari nol.";
	} elseif (!checkdate((int) substr($transactionDate, 5, 2), (int) substr($transactionDate, 8, 2), (int) substr($transactionDate, 0, 4))) {
		$errors[] = "Tanggal transaksi tidak valid.";
	} elseif (!$categoryId) {
		$errors[] = "Pilih kategori transaksi.";
	} else {
		$categoryValid = userOwnsTransactionCategory($koneksi, $categoryId, $userId, $type);
		$accountValue = $accountId === "" ? null : (int) $accountId;
		$accountValid = $accountValue === null;
		if ($accountValue !== null) {
			$accountValid = userOwnsActiveAccount($koneksi, $accountValue, $userId);
		}

		if (!$categoryValid) {
			$errors[] = "Kategori tidak sesuai dengan jenis transaksi.";
		} elseif (!$accountValid) {
			$errors[] = "Akun yang dipilih tidak valid.";
		} elseif (updateUserTransaction($koneksi, $transactionId, $userId, $accountValue, $categoryId, $type, $title, $amount, $transactionDate, $notes)) {
			header("Location: index.php?saved=1");
			exit;
		} else {
			$errors[] = "Perubahan transaksi belum dapat disimpan.";
		}
	}
}

$categories = getTransactionCategories($koneksi, $userId, $type);
$accounts = getUserAccounts($koneksi, $userId);

require __DIR__ . "/../includes/header.php";
?>
<div class="page-heading">
	<div><span class="subtitle">PERBARUI CATATAN</span><h1>Edit Transaksi</h1><p>Ubah detail transaksi yang dipilih.</p></div>
	<a class="text-link" href="index.php">Kembali ke transaksi</a>
</div>
<?php foreach ($errors as $error): ?>
	<p class="notice notice-error" role="alert"><?= appEscape($error) ?></p>
<?php endforeach; ?>
<nav class="transaction-type-switch" aria-label="Jenis transaksi">
	<a class="<?= $type === "expense" ? "selected expense-selected" : "" ?>" href="?id=<?= $transactionId ?>&amp;type=expense"><span aria-hidden="true">↓</span> Pengeluaran</a>
	<a class="<?= $type === "income" ? "selected income-selected" : "" ?>" href="?id=<?= $transactionId ?>&amp;type=income"><span aria-hidden="true">↑</span> Pemasukan</a>
</nav>
<form class="transaction-form" method="post" action="?id=<?= $transactionId ?>">
	<input type="hidden" name="csrf_token" value="<?= appEscape(csrfToken()) ?>">
	<input type="hidden" name="transaction_type" value="<?= appEscape($type) ?>">
	<section class="transaction-panel amount-panel">
		<div class="form-section-title"><label for="amount">Nominal Transaksi</label><span class="type-badge <?= $type === "income" ? "income-badge" : "expense-badge" ?>"><?= $type === "income" ? "Pemasukan" : "Pengeluaran" ?></span></div>
		<div class="amount-input-wrap"><span>Rp</span><input id="amount" name="amount" type="number" min="0.01" step="0.01" value="<?= appEscape($amount) ?>" required></div>
		<div class="quick-amounts" aria-label="Nominal cepat"><button type="button" data-add-amount="50000">+50 rb</button><button type="button" data-add-amount="100000">+100 rb</button><button type="button" data-add-amount="500000">+500 rb</button><button type="button" data-clear-amount aria-label="Kosongkan nominal">↻</button></div>
	</section>
	<section class="transaction-panel">
		<div class="form-field"><label for="title">Judul Transaksi</label><input id="title" name="title" type="text" maxlength="150" value="<?= appEscape($title) ?>" required></div>
		<div class="form-field form-two-columns">
			<div><label for="transaction_date">Tanggal Transaksi</label><input id="transaction_date" name="transaction_date" type="date" value="<?= appEscape($transactionDate) ?>" required></div>
			<div><label for="account_id">Sumber Dana <span class="optional-label">Opsional</span></label><select id="account_id" name="account_id"><option value="">Pilih nanti</option><?php foreach ($accounts as $account): ?><option value="<?= (int) $account["account_id"] ?>" <?= (string) $accountId === (string) $account["account_id"] ? "selected" : "" ?>><?= appEscape($account["account_name"]) ?></option><?php endforeach; ?></select></div>
		</div>
	</section>
	<section class="transaction-panel">
		<div class="form-section-title"><h2>Kategori</h2><span class="optional-label"><?= count($categories) ?> tersedia</span></div>
		<?php if ($categories): ?>
			<div class="category-grid">
				<?php foreach ($categories as $category): ?>
					<label class="category-choice"><input type="radio" name="category_id" value="<?= (int) $category["category_id"] ?>" <?= $categoryId === (int) $category["category_id"] ? "checked" : "" ?> required><span class="category-choice-mark material-symbols-outlined" aria-hidden="true"><?= appEscape($category["icon"] ?: "category") ?></span><span><?= appEscape($category["category_name"]) ?></span></label>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<p class="empty-inline">Kategori belum tersedia.</p>
		<?php endif; ?>
	</section>
	<section class="transaction-panel"><div class="form-field"><label for="notes">Catatan Tambahan <span class="optional-label">Opsional</span></label><textarea id="notes" name="notes" rows="3"><?= appEscape($notes) ?></textarea></div></section>
	<div class="transaction-form-actions"><button class="auth-submit" type="submit">Simpan Perubahan <span aria-hidden="true">✓</span></button><a class="cancel-link" href="index.php">Batal</a></div>
</form>
<?php require __DIR__ . "/../includes/footer.php"; ?>
