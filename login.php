<?php
require 'config.php';
if (current_user()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $st = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$username]);
    $row = $st->fetch();

    $ok = false;
    if ($row) {
        if (preg_match('/^\$(2[aby]|argon2)/', $row['password'])) {
            $ok = password_verify($password, $row['password']);
        } else {
            // Password lama masih teks biasa: cocokkan, lalu ubah jadi hash.
            $ok = hash_equals($row['password'], $password);
            if ($ok) {
                $pdo->prepare('UPDATE users SET password = ? WHERE id_users = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id_users']]);
            }
        }
    }

    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => $row['id_users'],
            'username' => $row['username'],
            'nama' => $row['nama'],
            'level' => $row['level'],
        ];
        redirect('index.php');
    }
    $error = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk - Konservasi Energi</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="wrap">
  <div class="login-kotak kartu">
    <h1>Konservasi Energi</h1>
    <?php if ($error): ?><div class="notif err"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit">Masuk</button>
    </form>
  </div>
</main>
</body>
</html>
