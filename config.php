<?php
session_start();

const DB_HOST = 'localhost';
const DB_NAME = 'konservasi_energi_ardan';
const DB_USER = 'root';
const DB_PASS = '';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Koneksi database gagal. Periksa pengaturan di config.php.');
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect($url) { header('Location: ' . $url); exit; }
function tgl($d) { return date('d-m-Y', strtotime($d)); }

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
            http_response_code(400);
            die('Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.');
        }
    }
}

function flash($msg = null, $type = 'ok') {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function current_user() { return $_SESSION['user'] ?? null; }

function require_login() {
    if (!current_user()) redirect('login.php');
    csrf_check();
}
function require_role(array $roles) {
    require_login();
    if (!in_array(current_user()['level'], $roles, true)) {
        http_response_code(403);
        die('Akses ditolak untuk akun Anda.');
    }
}
