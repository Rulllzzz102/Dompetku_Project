<?php
require_once __DIR__ . "/../includes/app_helpers.php";
require_once __DIR__ . "/../config/auth_check.php";
require_once __DIR__ . "/../functions/transaksi.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isValidCsrfToken($_POST["csrf_token"] ?? null)) {
	http_response_code(400);
	exit("Permintaan tidak valid.");
}

$transactionId = filter_var($_POST["transaction_id"] ?? null, FILTER_VALIDATE_INT);
if (!$transactionId || $transactionId < 1) {
	header("Location: index.php");
	exit;
}

$userId = (int) $_SESSION["user_id"];
$deleted = deleteUserTransaction($koneksi, (int) $transactionId, $userId);

header("Location: index.php" . ($deleted ? "?deleted=1" : ""));
exit;
