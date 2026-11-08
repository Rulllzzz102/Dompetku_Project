<?php
require_once __DIR__ . "/../includes/app_helpers.php";
require_once __DIR__ . "/../config/auth_check.php";
require_once __DIR__ . "/../functions/transaksi.php";

$pageTitle = "Tambah Transaksi";
$activeMenu = "transactions";
$userId = (int) $_SESSION["user_id"];
$type = $_POST["transaction_type"] ?? ($_GET["type"] ?? "expense");
$errors = [];
$title = trim($_POST["title"] ?? "");
$amount = trim($_POST["amount"] ?? "");
$transactionDate = $_POST["transaction_date"] ?? date("Y-m-d");
$categoryId = (int) ($_POST["category_id"] ?? 0);
$accountId = $_POST["account_id"] ?? "";
$notes = trim($_POST["notes"] ?? "");

if (!in_array($type, ["income", "expense"], true)) {
	$type = "expense";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	if (!isValidCsrfToken($_POST["csrf_token"] ?? null)) {
		$errors[] = "Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.";
	} elseif ($title === "" || strlen($title) > 150) {
		$errors[] = "Judul transaksi wajib diisi dan maksimal 150 karakter.";
	} elseif (!preg_match("/^\d+(\.\d{1,2})?$/", $amount) || (float) $amount <= 0) {
		$errors[] = "Masukkan nominal lebih besar dari nol.";
	} elseif (!$categoryId) {
		$errors[] = "Pilih kategori transaksi.";
	} elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $transactionDate)
		|| !checkdate((int) substr($transactionDate, 5, 2), (int) substr($transactionDate, 8, 2), (int) substr($transactionDate, 0, 4))) {
		$errors[] = "Tanggal transaksi tidak valid.";
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
		} elseif (createUserTransaction($koneksi, $userId, $accountValue, $categoryId, $type, $title, $amount, $transactionDate, $notes)) {
			header("Location: index.php?saved=1");
			exit;
		} else {
			$errors[] = "Transaksi belum dapat disimpan. Coba lagi.";
		}
	}
}

$categories = getTransactionCategories($koneksi, $userId, $type);
$accounts = getUserAccounts($koneksi, $userId);

require __DIR__ . "/../includes/header.php";
?>
<div class="page-heading">
	<div>
		<span class="subtitle">CATATAN BARU</span>
		<h1>Tambah Transaksi</h1>
		<p>Catat uang masuk atau keluar dari keuangan Anda.</p>
	</div>
	<a class="text-link" href="index.php">Kembali ke transaksi</a>
</div>

<?php foreach ($errors as $error): ?>
	<p class="notice notice-error" role="alert"><?= appEscape($error) ?></p>
<?php endforeach; ?>

<nav class="transaction-type-switch" aria-label="Jenis transaksi">
	<a class="<?= $type === "expense" ? "selected expense-selected" : "" ?>" href="?type=expense"><span aria-hidden="true">↓</span> Pengeluaran</a>
	<a class="<?= $type === "income" ? "selected income-selected" : "" ?>" href="?type=income"><span aria-hidden="true">↑</span> Pemasukan</a>
</nav>

<form class="transaction-form" method="post" action="">
	<input type="hidden" name="csrf_token" value="<?= appEscape(csrfToken()) ?>">
	<input type="hidden" name="transaction_type" value="<?= appEscape($type) ?>">

	<section class="transaction-panel amount-panel">
		<div class="form-section-title">
			<label for="amount">Nominal Transaksi</label>
			<span class="type-badge <?= $type === "income" ? "income-badge" : "expense-badge" ?>">
				<?= $type === "income" ? "Pemasukan" : "Pengeluaran" ?>
			</span>
		</div>
		<div class="amount-input-wrap">
			<span>Rp</span>
			<input id="amount" name="amount" type="number" min="0.01" step="0.01" value="<?= appEscape($amount) ?>" placeholder="0" required>
		</div>
		<div class="quick-amounts" aria-label="Nominal cepat">
			<button type="button" data-add-amount="50000">+50 rb</button>
			<button type="button" data-add-amount="100000">+100 rb</button>
			<button type="button" data-add-amount="500000">+500 rb</button>
			<button type="button" data-clear-amount aria-label="Kosongkan nominal">↻</button>
		</div>
	</section>

	<section class="transaction-panel transaction-fields">
		<div class="form-field">
			<label for="title">Judul Transaksi</label>
			<input id="title" name="title" type="text" maxlength="150" value="<?= appEscape($title) ?>" placeholder="Masukan Nama Pemasukan/Pengeluaran" required>
		</div>
		<div class="form-field form-two-columns">
			<div>
				<label for="transaction_date">Tanggal Transaksi</label>
				<input id="transaction_date" name="transaction_date" type="date" value="<?= appEscape($transactionDate) ?>" required>
			</div>
			<div>
				<label for="account_id">Sumber Dana <span class="optional-label">Opsional</span></label>
				<select id="account_id" name="account_id">
					<option value="">Pilih nanti</option>
					<?php foreach ($accounts as $account): ?>
						<option value="<?= (int) $account["account_id"] ?>" <?= (string) $accountId === (string) $account["account_id"] ? "selected" : "" ?>><?= appEscape($account["account_name"]) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
	</section>

	<section class="transaction-panel">
		<div class="form-section-title">
			<h2>Kategori</h2>
			<span class="optional-label"><?= count($categories) ?> tersedia</span>
		</div>
		<?php if ($categories): ?>
			<div class="category-grid">
				<?php foreach ($categories as $index => $category): ?>
					<label class="category-choice">
						<input type="radio" name="category_id" value="<?= (int) $category["category_id"] ?>" <?= $categoryId === (int) $category["category_id"] || (!$categoryId && $index === 0) ? "checked" : "" ?> required>
						<span class="category-choice-mark material-symbols-outlined" aria-hidden="true"><?= appEscape($category["icon"] ?: "category") ?></span>
						<span><?= appEscape($category["category_name"]) ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<p class="empty-inline">Kategori belum tersedia. Impor ulang skema database untuk menambahkan kategori bawaan.</p>
		<?php endif; ?>
	</section>

	<section class="transaction-panel">
		<div class="form-field">
			<label for="notes">Catatan Tambahan <span class="optional-label">Opsional</span></label>
			<textarea id="notes" name="notes" rows="3" placeholder="Tambahkan catatan jika diperlukan"><?= appEscape($notes) ?></textarea>
		</div>
	</section>

	<div class="transaction-form-actions">
		<button class="auth-submit" type="submit">Simpan Transaksi <span aria-hidden="true">✓</span></button>
		<a class="cancel-link" href="index.php">Batal</a>
	</div>
</form>
<?php require __DIR__ . "/../includes/footer.php"; ?>
