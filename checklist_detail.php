<?php
require 'config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT c.*, s.nama, s.kelas FROM checklist c JOIN siswa s ON s.nis = c.nis WHERE c.id_checklist = ?');
$st->execute([$id]);
$c = $st->fetch();
if (!$c) {
    flash('Checklist tidak ditemukan.', 'err');
    redirect('checklist.php');
}

$st = $pdo->prepare(
    'SELECT d.*, p.nama_perangkat FROM detail_checklist d
     JOIN perangkat p ON p.id_perangkat = d.id_perangkat
     WHERE d.id_checklist = ?
     ORDER BY FIELD(d.tahapan, \'SEBELUM\', \'SELAMA\', \'SESUDAH\', \'5R\'), d.id_detail'
);
$st->execute([$id]);
$detail = $st->fetchAll();

$title = 'Detail checklist';
$active = 'checklist';
include 'partials/header.php';
?>
<div class="judul-baris">
  <h1>Detail checklist</h1>
  <a class="tombol pucat" href="checklist.php">Kembali ke daftar</a>
</div>

<div class="kartu">
  <div class="info-baris">
    <div><div class="label">Siswa</div><?= e($c['nama']) ?> (<?= e($c['nis']) ?>)</div>
    <div><div class="label">Kelas</div><?= e($c['kelas']) ?></div>
    <div><div class="label">Tanggal</div><?= tgl($c['tanggal']) ?></div>
    <div><div class="label">Jam</div><?= e(substr($c['jam'], 0, 5)) ?></div>
  </div>
</div>

<div class="kartu">
  <?php if (!$detail): ?>
    <p class="kosong">Checklist ini belum punya rincian pemeriksaan.</p>
  <?php else: ?>
  <div class="tabel-scroll">
  <table>
    <tr><th>Tahapan</th><th>Perangkat</th><th>Kondisi</th><th>Sesuai</th><th>Keterangan</th></tr>
    <?php foreach ($detail as $d): ?>
    <tr>
      <td><?= e($d['tahapan']) ?></td>
      <td><?= e($d['nama_perangkat']) ?></td>
      <td><?= e($d['kondisi']) ?></td>
      <td><span class="lencana <?= $d['status'] === 'ya' ? 'ya' : 'tidak' ?>"><?= $d['status'] === 'ya' ? 'Ya' : 'Tidak' ?></span></td>
      <td><?= e($d['keterangan']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php include 'partials/footer.php'; ?>
