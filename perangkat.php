<?php
require 'config.php';
require_role(['admin']);
$isAdmin = current_user()['level'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $aksi = $_POST['aksi'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama_perangkat'] ?? '');
    $jenis = trim($_POST['jenis'] ?? '');
    $ket = trim($_POST['keterangan'] ?? '');
    try {
        if ($aksi === 'hapus') {
            $pdo->prepare('DELETE FROM perangkat WHERE id_perangkat = ?')->execute([$id]);
            flash('Perangkat dihapus beserta catatan checklist yang memakainya.');
        } elseif ($nama === '' || $jenis === '' || strlen($nama) > 100 || strlen($jenis) > 50) {
            flash('Nama dan jenis perangkat wajib diisi (nama maks. 100, jenis maks. 50 karakter).', 'err');
        } elseif ($aksi === 'tambah') {
            $pdo->prepare('INSERT INTO perangkat (nama_perangkat, jenis, keterangan) VALUES (?, ?, ?)')->execute([$nama, $jenis, $ket]);
            flash('Perangkat ditambahkan.');
        } elseif ($aksi === 'ubah') {
            $pdo->prepare('UPDATE perangkat SET nama_perangkat = ?, jenis = ?, keterangan = ? WHERE id_perangkat = ?')->execute([$nama, $jenis, $ket, $id]);
            flash('Perangkat disimpan.');
        }
    } catch (PDOException $ex) {
        flash('Gagal menyimpan data perangkat.', 'err');
    }
    redirect('perangkat.php');
}

$edit = null;
if ($isAdmin && isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM perangkat WHERE id_perangkat = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$daftar = $pdo->query('SELECT * FROM perangkat ORDER BY jenis, nama_perangkat')->fetchAll();

$title = 'Perangkat';
$active = 'perangkat';
include 'partials/header.php';
?>
<div class="judul-baris"><h1>Data perangkat</h1></div>

<?php if ($isAdmin): ?>
<div class="kartu">
  <h2><?= $edit ? 'Ubah perangkat' : 'Tambah perangkat' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="<?= $edit ? 'ubah' : 'tambah' ?>">
    <input type="hidden" name="id" value="<?= (int)($edit['id_perangkat'] ?? 0) ?>">
    <div class="grid-form">
      <div><label for="nama_perangkat">Nama perangkat</label>
        <input type="text" id="nama_perangkat" name="nama_perangkat" maxlength="100" value="<?= e($edit['nama_perangkat'] ?? '') ?>" required></div>
      <div><label for="jenis">Jenis</label>
        <input type="text" id="jenis" name="jenis" maxlength="50" value="<?= e($edit['jenis'] ?? '') ?>" required></div>
    </div>
    <div style="margin-top:14px"><label for="keterangan">Keterangan</label>
      <textarea id="keterangan" name="keterangan" rows="2"><?= e($edit['keterangan'] ?? '') ?></textarea></div>
    <div class="baris-tombol">
      <button type="submit">Simpan</button>
      <?php if ($edit): ?><a class="tombol pucat" href="perangkat.php">Batal</a><?php endif; ?>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="kartu">
  <?php if (!$daftar): ?>
    <p class="kosong">Belum ada perangkat.</p>
  <?php else: ?>
  <div class="tabel-scroll">
  <table>
    <tr><th>Nama</th><th>Jenis</th><th>Keterangan</th><?php if ($isAdmin): ?><th></th><?php endif; ?></tr>
    <?php foreach ($daftar as $r): ?>
    <tr>
      <td><?= e($r['nama_perangkat']) ?></td><td><?= e($r['jenis']) ?></td><td><?= e($r['keterangan']) ?></td>
      <?php if ($isAdmin): ?>
      <td>
        <a class="tombol pucat kecil" href="perangkat.php?edit=<?= (int)$r['id_perangkat'] ?>">Ubah</a>
        <form method="post" class="inline" onsubmit="return confirm('Hapus perangkat ini? Catatan checklist yang memakainya ikut terhapus.')">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="hapus">
          <input type="hidden" name="id" value="<?= (int)$r['id_perangkat'] ?>">
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
