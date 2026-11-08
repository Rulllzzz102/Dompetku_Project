<?php
require_once __DIR__ . "/../includes/app_helpers.php";

if (isset($_SESSION["user_id"])) {
	header("Location: ../dashboard/index.php");
	exit;
}

$errors = [];
$displayName = "";
$username = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	$displayName = trim($_POST["display_name"] ?? "");
	$username = trim($_POST["username"] ?? "");
	$email = strtolower(trim($_POST["email"] ?? ""));
	$password = $_POST["password"] ?? "";
	$passwordConfirmation = $_POST["password_confirmation"] ?? "";

	if (!isValidCsrfToken($_POST["csrf_token"] ?? null)) {
		$errors[] = "Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.";
	} elseif ($displayName === "" || strlen($displayName) > 100) {
		$errors[] = "Nama harus diisi dan maksimal 100 karakter.";
	} elseif (!preg_match("/^[A-Za-z0-9._-]{3,50}$/", $username)) {
		$errors[] = "Username harus 3-50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.";
	} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
		$errors[] = "Masukkan alamat email yang valid.";
	} elseif (strlen($password) < 8) {
		$errors[] = "Kata sandi harus memiliki minimal 8 karakter.";
	} elseif ($password !== $passwordConfirmation) {
		$errors[] = "Konfirmasi kata sandi tidak cocok.";
	} else {
		$check = mysqli_prepare(
			$koneksi,
			"SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1"
		);

		if ($check) {
			mysqli_stmt_bind_param($check, "ss", $username, $email);
			mysqli_stmt_execute($check);
			mysqli_stmt_store_result($check);
			$alreadyExists = mysqli_stmt_num_rows($check) > 0;
			mysqli_stmt_close($check);

			if ($alreadyExists) {
				$errors[] = "Username atau email sudah digunakan.";
			} else {
				$passwordHash = password_hash($password, PASSWORD_DEFAULT);
				$insert = mysqli_prepare(
					$koneksi,
					"INSERT INTO users (username, email, password_hash, display_name) VALUES (?, ?, ?, ?)"
				);

				if ($insert) {
					mysqli_stmt_bind_param($insert, "ssss", $username, $email, $passwordHash, $displayName);
					$created = mysqli_stmt_execute($insert);
					$newUserId = mysqli_insert_id($koneksi);
					mysqli_stmt_close($insert);

					if ($created) {
						session_regenerate_id(true);
						$_SESSION["user_id"] = $newUserId;
						$_SESSION["display_name"] = $displayName;
						header("Location: ../dashboard/index.php");
						exit;
					}
				}

				$errors[] = "Akun belum dapat dibuat. Periksa koneksi database lalu coba lagi.";
			}
		} else {
			$errors[] = "Akun belum dapat dibuat. Periksa koneksi database lalu coba lagi.";
		}
	}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Daftar | DompetKu</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
	<header class="auth-header">
		<a class="auth-brand" href="../index.html">
			<img src="../assets/logo-dompetku.svg" alt="">
			<span><strong>DompetKu</strong><small>PERSONAL FINANCE</small></span>
		</a>
	</header>

	<main class="auth-main">
		<section class="auth-card" aria-labelledby="registerTitle">
			<img class="auth-card-logo" src="../assets/logo-dompetku.svg" alt="">
			<h1 id="registerTitle">Buat Akun DompetKu</h1>
			<p class="auth-intro">Mulai catat pemasukan dan pengeluaran Anda.</p>

			<?php foreach ($errors as $error): ?>
				<p class="auth-message" role="alert"><?= appEscape($error) ?></p>
			<?php endforeach; ?>

			<form class="auth-form" method="post" action="">
				<input type="hidden" name="csrf_token" value="<?= appEscape(csrfToken()) ?>">
				<div class="auth-field">
					<label for="display_name">Nama Lengkap</label>
					<input id="display_name" name="display_name" type="text" value="<?= appEscape($displayName) ?>" autocomplete="name" maxlength="100" required>
				</div>
				<div class="auth-field">
					<label for="username">Username</label>
					<input id="username" name="username" type="text" value="<?= appEscape($username) ?>" autocomplete="username" minlength="3" maxlength="50" required>
				</div>
				<div class="auth-field">
					<label for="email">Email</label>
					<input id="email" name="email" type="email" value="<?= appEscape($email) ?>" autocomplete="email" maxlength="191" required>
				</div>
				<div class="auth-field">
					<label for="password">Kata Sandi</label>
					<div class="auth-password-field">
						<input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
						<button type="button" class="auth-password-toggle" data-password-toggle aria-pressed="false">Tampilkan</button>
					</div>
				</div>
				<div class="auth-field">
					<label for="password_confirmation">Konfirmasi Kata Sandi</label>
					<input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
				</div>
				<button class="auth-submit" type="submit">Daftar Sekarang <span aria-hidden="true">→</span></button>
			</form>

			<p class="auth-register">Sudah memiliki akun? <a href="login.php">Masuk</a></p>
		</section>
	</main>
	<script src="../assets/css/js/app.js"></script>
</body>
</html>
