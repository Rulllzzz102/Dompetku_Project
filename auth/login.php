<?php
require_once __DIR__ . "/../includes/app_helpers.php";

if (isset($_SESSION["user_id"])) {
	header("Location: ../dashboard/index.php");
	exit;
}

$loginMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
	if (!isValidCsrfToken($_POST["csrf_token"] ?? null)) {
		$loginMessage = "Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.";
	} else {
		$identity = trim($_POST["username"] ?? "");
		$password = $_POST["password"] ?? "";
		$username = $identity;
		$email = $identity;

		$statement = mysqli_prepare(
			$koneksi,
			"SELECT user_id, display_name, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1"
		);

		if ($statement) {
			mysqli_stmt_bind_param($statement, "ss", $username, $email);
			mysqli_stmt_execute($statement);
			mysqli_stmt_bind_result($statement, $userId, $displayName, $passwordHash);
			$userFound = mysqli_stmt_fetch($statement);
			mysqli_stmt_close($statement);

			if ($userFound && password_verify($password, $passwordHash)) {
				session_regenerate_id(true);
				$_SESSION["user_id"] = $userId;
				$_SESSION["display_name"] = $displayName;
				header("Location: ../dashboard/index.php");
				exit;
			}
		}

		$loginMessage = "Username/email atau kata sandi tidak cocok.";
	}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Masuk | DompetKu</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
	<header class="auth-header">
		<a class="auth-brand" href="../index.html">
			<img src="../assets/logo-dompetku.svg" alt="">
			<span>
				<strong>DompetKu</strong>
				<small>PERSONAL FINANCE</small>
			</span>
		</a>
		
	</header>

	<main class="auth-main">
		<section class="auth-card" aria-labelledby="loginTitle">
			<img class="auth-card-logo" src="../assets/logo-dompetku.svg" alt="">
			<h1 id="loginTitle">Masuk ke Akun Anda</h1>
			<p class="auth-intro">Kelola catatan keuangan, pantau pengeluaran, dan atur saldo kas Anda.</p>

			<?php if ($loginMessage !== ""): ?>
				<p class="auth-message" role="alert"><?= appEscape($loginMessage) ?></p>
			<?php endif; ?>

			<form class="auth-form" method="post" action="">
				<input type="hidden" name="csrf_token" value="<?= appEscape(csrfToken()) ?>">
				<div class="auth-field">
					<label for="username">Username atau Email</label>
					<input id="username" name="username" type="text" placeholder="Masukkan username atau email" autocomplete="username" required>
				</div>

				<div class="auth-field">
					<div class="auth-label-row">
						<label for="password">Kata Sandi</label>
						<a href="lupa_password.php">Lupa Password?</a>
					</div>
					<div class="auth-password-field">
						<input id="password" name="password" type="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required>
						<button type="button" class="auth-password-toggle" data-password-toggle aria-pressed="false">Tampilkan</button>
					</div>
				</div>

				<div class="auth-options">
					<label class="auth-remember">
						<input type="checkbox" name="remember">
						<span>Ingat Saya</span>
					</label>
					<span class="auth-protected"><span aria-hidden="true">◆</span> Akun Terlindungi</span>
				</div>

				<button class="auth-submit" type="submit">Masuk Sekarang <span aria-hidden="true">→</span></button>
			</form>

			<p class="auth-register">Belum memiliki akun? <a href="registrasi.php">Daftar Sekarang</a></p>
		</section>
	</main>

	<script src="../assets/css/js/app.js"></script>
</body>
</html>
