<?php
require 'config.php';
require_login();

$jmlSiswa = (int)$pdo->query('SELECT COUNT(*) FROM siswa')->fetchColumn();
$jmlPerangkat = (int)$pdo->query('SELECT COUNT(*) FROM perangkat')->fetchColumn();
$jmlChecklist = (int)$pdo->query('SELECT COUNT(*) FROM checklist')->fetchColumn();
$jmlTemuan = (int)$pdo->query("SELECT COUNT(*) FROM detail_checklist WHERE status = 'tidak'")->fetchColumn();

$terbaru = $pdo->query(
    'SELECT c.id_checklist, c.tanggal, c.jam, s.nama, s.kelas,
            COUNT(d.id_detail) AS jml, COALESCE(SUM(d.status = \'tidak\'), 0) AS tidak
     FROM checklist c
     JOIN siswa s ON s.nis = c.nis
     LEFT JOIN detail_checklist d ON d.id_checklist = c.id_checklist
     GROUP BY c.id_checklist, c.tanggal, c.jam, s.nama, s.kelas
     ORDER BY c.tanggal DESC, c.jam DESC, c.id_checklist DESC
     LIMIT 5'
)->fetchAll();

$title = 'Beranda';
$active = 'index';
include 'partials/header.php';
?>
<h1>Halo, <?= e($user['nama']) ?></h1>

<div class="ringkasan">
  <div class="kartu"><div class="angka"><?= $jmlChecklist ?></div><div class="label">Checklist tercatat</div></div>
  <?php if ($user['level'] === 'admin'): ?>
  <div class="kartu"><div class="angka"><?= $jmlSiswa ?></div><div class="label">Siswa</div></div>
  <div class="kartu"><div class="angka"><?= $jmlPerangkat ?></div><div class="label">Perangkat</div></div>
  <?php endif; ?>
  <div class="kartu"><div class="angka"><?= $jmlTemuan ?></div><div class="label">Poin yang belum sesuai</div></div>
</div>

<div class="kartu">
  <div class="judul-baris">
    <h2>Checklist terbaru</h2>
    <a class="tombol" href="checklist_tambah.php">Isi checklist baru</a>
  </div>
  <?php if (!$terbaru): ?>
    <p class="kosong">Belum ada checklist. Klik "Isi checklist baru" untuk memulai.</p>
  <?php else: ?>
  <div class="tabel-scroll">
  <table>
    <tr><th>Tanggal</th><th>Jam</th><th>Siswa</th><th>Kelas</th><th>Poin sesuai</th><th></th></tr>
    <?php foreach ($terbaru as $r): ?>
    <tr>
      <td><?= tgl($r['tanggal']) ?></td>
      <td><?= e(substr($r['jam'], 0, 5)) ?></td>
      <td><?= e($r['nama']) ?></td>
      <td><?= e($r['kelas']) ?></td>
      <td><?= (int)$r['jml'] - (int)$r['tidak'] ?> dari <?= (int)$r['jml'] ?></td>
      <td><a href="checklist_detail.php?id=<?= (int)$r['id_checklist'] ?>">Lihat</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php include 'partials/footer.php'; ?>
