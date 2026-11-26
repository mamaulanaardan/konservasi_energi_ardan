<?php
require 'config.php';
require_role(['admin']);
$isAdmin = current_user()['level'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $aksi = $_POST['aksi'] ?? '';
    $nis = trim($_POST['nis'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $kelas = trim($_POST['kelas'] ?? '');
    try {
        if ($aksi === 'hapus') {
            $pdo->prepare('DELETE FROM siswa WHERE nis = ?')->execute([$nis]);
            flash('Data siswa dihapus beserta checklist miliknya.');
        } elseif ($nis === '' || $nama === '' || $kelas === '' || strlen($nis) > 10 || strlen($kelas) > 10) {
            flash('NIS, nama, dan kelas wajib diisi. NIS dan kelas maksimal 10 karakter.', 'err');
        } elseif ($aksi === 'tambah') {
            $pdo->prepare('INSERT INTO siswa (nis, nama, kelas) VALUES (?, ?, ?)')->execute([$nis, $nama, $kelas]);
            flash('Siswa ditambahkan.');
        } elseif ($aksi === 'ubah') {
            $pdo->prepare('UPDATE siswa SET nama = ?, kelas = ? WHERE nis = ?')->execute([$nama, $kelas, $nis]);
            flash('Data siswa disimpan.');
        }
    } catch (PDOException $ex) {
        flash('Gagal menyimpan. NIS ini mungkin sudah terdaftar.', 'err');
    }
    redirect('siswa.php');
}

$edit = null;
if ($isAdmin && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM siswa WHERE nis = ?');
    $st->execute([$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}

$q = trim($_GET['q'] ?? '');
$st = $pdo->prepare('SELECT * FROM siswa WHERE nis LIKE ? OR nama LIKE ? OR kelas LIKE ? ORDER BY kelas, nama');
$like = '%' . $q . '%';
$st->execute([$like, $like, $like]);
$daftar = $st->fetchAll();

$title = 'Siswa';
$active = 'siswa';
include 'partials/header.php';
?>
<div class="judul-baris"><h1>Data siswa</h1></div>

<?php if ($isAdmin): ?>
<div class="kartu">
  <h2><?= $edit ? 'Ubah siswa' : 'Tambah siswa' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="<?= $edit ? 'ubah' : 'tambah' ?>">
    <div class="grid-form">
      <div><label for="nis">NIS</label>
        <input type="text" id="nis" name="nis" maxlength="10" value="<?= e($edit['nis'] ?? '') ?>" <?= $edit ? 'readonly' : 'required' ?>></div>
      <div><label for="nama">Nama</label>
        <input type="text" id="nama" name="nama" maxlength="100" value="<?= e($edit['nama'] ?? '') ?>" required></div>
      <div><label for="kelas">Kelas</label>
        <input type="text" id="kelas" name="kelas" maxlength="10" value="<?= e($edit['kelas'] ?? '') ?>" required></div>
    </div>
    <div class="baris-tombol">
      <button type="submit">Simpan</button>
      <?php if ($edit): ?><a class="tombol pucat" href="siswa.php">Batal</a><?php endif; ?>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="kartu">
  <form method="get" class="grid-form" style="margin-bottom:14px">
    <div><label for="q">Cari siswa</label>
      <input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="NIS, nama, atau kelas"></div>
    <div><button type="submit">Cari</button></div>
  </form>
  <?php if (!$daftar): ?>
    <p class="kosong">Tidak ada siswa yang cocok.</p>
  <?php else: ?>
  <div class="tabel-scroll">
  <table>
    <tr><th>NIS</th><th>Nama</th><th>Kelas</th><?php if ($isAdmin): ?><th></th><?php endif; ?></tr>
    <?php foreach ($daftar as $r): ?>
    <tr>
      <td><?= e($r['nis']) ?></td><td><?= e($r['nama']) ?></td><td><?= e($r['kelas']) ?></td>
      <?php if ($isAdmin): ?>
      <td>
        <a class="tombol pucat kecil" href="siswa.php?edit=<?= urlencode($r['nis']) ?>">Ubah</a>
        <form method="post" class="inline" onsubmit="return confirm('Hapus siswa ini? Checklist miliknya ikut terhapus.')">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="hapus">
          <input type="hidden" name="nis" value="<?= e($r['nis']) ?>">
          <button type="submit" class="tombol bahaya kecil">Hapus</button>
        </form>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php include 'partials/footer.php'; ?>
