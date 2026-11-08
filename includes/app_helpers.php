<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../functions/format_rupiah.php";

function appEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function csrfToken()
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function isValidCsrfToken($token)
{
    return isset($_SESSION["csrf_token"])
        && is_string($token)
        && hash_equals($_SESSION["csrf_token"], $token);
}