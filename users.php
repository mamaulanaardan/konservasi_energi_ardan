<?php
require 'config.php';
require_role(['admin']);
$me = current_user();
$levels = ['admin', 'guru', 'siswa'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $level = $_POST['level'] ?? '';
    $password = $_POST['password'] ?? '';
    try {
        if ($aksi === 'hapus') {
            if ($id === (int)$me['id']) {
                flash('Akun yang sedang dipakai tidak bisa dihapus.', 'err');
            } else {
                $pdo->prepare('DELETE FROM users WHERE id_users = ?')->execute([$id]);
                flash('Pengguna dihapus.');
            }
        } elseif ($username === '' || $nama === '' || !in_array($level, $levels, true) || strlen($username) > 50) {
            flash('Username, nama, dan level wajib diisi dengan benar.', 'err');
        } elseif ($aksi === 'tambah') {
            if ($password === '') {
                flash('Password wajib diisi untuk pengguna baru.', 'err');
            } else {
                $pdo->prepare('INSERT INTO users (username, password, nama, level) VALUES (?, ?, ?, ?)')
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $nama, $level]);
                flash('Pengguna ditambahkan.');
            }
        } elseif ($aksi === 'ubah') {
            if ($id === (int)$me['id'] && $level !== 'admin') {
                flash('Level akun yang sedang dipakai tidak bisa diturunkan.', 'err');
            } else {
                $pdo->prepare('UPDATE users SET username = ?, nama = ?, level = ? WHERE id_users = ?')
                    ->execute([$username, $nama, $level, $id]);
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET password = ? WHERE id_users = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                }
                flash('Data pengguna disimpan.');
            }
        }
    } catch (PDOException $ex) {
        flash('Gagal menyimpan. Username mungkin sudah dipakai.', 'err');
    }
    redirect('users.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT id_users, username, nama, level FROM users WHERE id_users = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$daftar = $pdo->query('SELECT id_users, username, nama, level FROM users ORDER BY FIELD(level, \'admin\', \'guru\', \'siswa\'), nama')->fetchAll();

$title = 'Pengguna';
$active = 'users';
include 'partials/header.php';
?>
<div class="judul-baris"><h1>Pengguna</h1></div>

<div class="kartu">
  <h2><?= $edit ? 'Ubah pengguna' : 'Tambah pengguna' ?></h2>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="<?= $edit ? 'ubah' : 'tambah' ?>">
    <input type="hidden" name="id" value="<?= (int)($edit['id_users'] ?? 0) ?>">
    <div class="grid-form">
      <div><label for="username">Username</label>
        <input type="text" id="username" name="username" maxlength="50" value="<?= e($edit['username'] ?? '') ?>" required></div>
      <div><label for="nama">Nama</label>
        <input type="text" id="nama" name="nama" maxlength="100" value="<?= e($edit['nama'] ?? '') ?>" required></div>
      <div><label for="level">Level</label>
        <select id="level" name="level">
          <?php foreach ($levels as $lv): ?>
            <option value="<?= $lv ?>" <?= ($edit['level'] ?? 'siswa') === $lv ? 'selected' : '' ?>><?= $lv ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="password">Password<?= $edit ? ' (kosongkan jika tidak diubah)' : '' ?></label>
        <input type="password" id="password" name="password" <?= $edit ? '' : 'required' ?>></div>
    </div>
    <div class="baris-tombol">
      <button type="submit">Simpan</button>
      <?php if ($edit): ?><a class="tombol pucat" href="users.php">Batal</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="kartu">
  <div class="tabel-scroll">
  <table>
    <tr><th>Username</th><th>Nama</th><th>Level</th><th></th></tr>
    <?php foreach ($daftar as $r): ?>
    <tr>
      <td><?= e($r['username']) ?></td><td><?= e($r['nama']) ?></td>
      <td><span class="lencana level"><?= e($r['level']) ?></span></td>
      <td>
        <a class="tombol pucat kecil" href="users.php?edit=<?= (int)$r['id_users'] ?>">Ubah</a>
        <?php if ((int)$r['id_users'] !== (int)$me['id']): ?>
        <form method="post" class="inline" onsubmit="return confirm('Hapus pengguna ini?')">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="hapus">
          <input type="hidden" name="id" value="<?= (int)$r['id_users'] ?>">
          <button type="submit" class="tombol bahaya kecil">Hapus</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>
<?php include 'partials/footer.php'; ?>
