<?php
require_once __DIR__ . "/../includes/app_helpers.php";

$message = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = strtolower(trim($_POST["email"] ?? ""));

    if (!isValidCsrfToken($_POST["csrf_token"] ?? null)) {
        $message = "Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Masukkan alamat email yang valid.";
    } else {
        $message = "Pengiriman tautan reset melalui email belum dikonfigurasi.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Kata Sandi | DompetKu</title>
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
        <section class="auth-card" aria-labelledby="resetTitle">
            <img class="auth-card-logo" src="../assets/logo-dompetku.svg" alt="">
            <h1 id="resetTitle">Lupa Kata Sandi?</h1>
            <p class="auth-intro">Masukkan email akun Anda untuk memulai proses pemulihan akses.</p>

            <?php if ($message !== ""): ?>
                <p class="auth-message" role="status"><?= appEscape($message) ?></p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= appEscape(csrfToken()) ?>">
                <div class="auth-field">
                    <label for="email">Alamat Email</label>
                    <input id="email" name="email" type="email" value="<?= appEscape($email) ?>" placeholder="nama@email.com" autocomplete="email" required>
                </div>
                <button class="auth-submit" type="submit">Kirim Instruksi Reset <span aria-hidden="true">→</span></button>
            </form>

            <p class="auth-register"><a href="login.php">Kembali ke halaman masuk</a></p>
        </section>
    </main>
</body>
</html>