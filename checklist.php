<?php
require 'config.php';
require_login();
$canDelete = in_array(current_user()['level'], ['admin', 'guru'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canDelete && ($_POST['aksi'] ?? '') === 'hapus') {
    $pdo->prepare('DELETE FROM checklist WHERE id_checklist = ?')->execute([(int)($_POST['id'] ?? 0)]);
    flash('Checklist dihapus.');
    redirect('checklist.php');
}

$tanggal = trim($_GET['tanggal'] ?? '');
$q = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';

$st = $pdo->prepare(
    'SELECT c.id_checklist, c.tanggal, c.jam, s.nis, s.nama, s.kelas,
            COUNT(d.id_detail) AS jml, COALESCE(SUM(d.status = \'tidak\'), 0) AS tidak
     FROM checklist c
     JOIN siswa s ON s.nis = c.nis
     LEFT JOIN detail_checklist d ON d.id_checklist = c.id_checklist
     WHERE (? = \'\' OR c.tanggal = ?)
       AND (s.nama LIKE ? OR s.nis LIKE ? OR s.kelas LIKE ?)
     GROUP BY c.id_checklist, c.tanggal, c.jam, s.nis, s.nama, s.kelas
     ORDER BY c.tanggal DESC, c.jam DESC, c.id_checklist DESC'
);
$st->execute([$tanggal, $tanggal, $like, $like, $like]);
$daftar = $st->fetchAll();

$title = 'Checklist';
$active = 'checklist';
include 'partials/header.php';
?>
<div class="judul-baris">
  <h1>Checklist konservasi energi</h1>
  <a class="tombol" href="checklist_tambah.php">Isi checklist baru</a>
</div>

<div class="kartu">
  <form method="get" class="grid-form" style="margin-bottom:14px">
    <div><label for="tanggal">Tanggal</label>
      <input type="date" id="tanggal" name="tanggal" value="<?= e($tanggal) ?>"></div>
    <div><label for="q">Cari siswa</label>
      <input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="NIS, nama, atau kelas"></div>
    <div class="baris-tombol" style="margin:0">
      <button type="submit">Tampilkan</button>
      <a class="tombol pucat" href="checklist.php">Atur ulang</a>
    </div>
  </form>

  <?php if (!$daftar): ?>
    <p class="kosong">Tidak ada checklist yang cocok dengan pencarian ini.</p>
  <?php else: ?>
  <div class="tabel-scroll">
  <table>
    <tr><th>Tanggal</th><th>Jam</th><th>Siswa</th><th>Kelas</th><th>Poin sesuai</th><th></th></tr>
    <?php foreach ($daftar as $r): ?>
    <tr>
      <td><?= tgl($r['tanggal']) ?></td>
      <td><?= e(substr($r['jam'], 0, 5)) ?></td>
      <td><?= e($r['nama']) ?> <small>(<?= e($r['nis']) ?>)</small></td>
      <td><?= e($r['kelas']) ?></td>
      <td>
        <?= (int)$r['jml'] - (int)$r['tidak'] ?> dari <?= (int)$r['jml'] ?>
        <?php if ((int)$r['tidak'] > 0): ?><span class="lencana tidak"><?= (int)$r['tidak'] ?> belum sesuai</span><?php endif; ?>
      </td>
      <td>
        <a class="tombol pucat kecil" href="checklist_detail.php?id=<?= (int)$r['id_checklist'] ?>">Lihat</a>
        <?php if ($canDelete): ?>
        <form method="post" class="inline" onsubmit="return confirm('Hapus checklist ini beserta detailnya?')">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="hapus">
          <input type="hidden" name="id" value="<?= (int)$r['id_checklist'] ?>">
          <button type="submit" class="tombol bahaya kecil">Hapus</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php include 'partials/footer.php'; ?>
