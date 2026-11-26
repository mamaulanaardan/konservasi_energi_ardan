<?php
require 'config.php';
require_role(['admin']);

function tgl_valid($s) {
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
}

// Periode bawaan: dari checklist paling awal sampai hari ini (atau checklist terakhir),
// supaya data lama tetap muncul saat halaman dibuka pertama kali.
$rentang = $pdo->query('SELECT MIN(tanggal) AS awal, MAX(tanggal) AS akhir FROM checklist')->fetch();
$awalData = $rentang['awal'] ?: date('Y-m-01');
$akhirData = max($rentang['akhir'] ?: date('Y-m-d'), date('Y-m-d'));

$dari = $_GET['dari'] ?? $awalData;
$sampai = $_GET['sampai'] ?? $akhirData;
if (!tgl_valid($dari)) $dari = $awalData;
if (!tgl_valid($sampai)) $sampai = $akhirData;
if ($dari > $sampai) { [$dari, $sampai] = [$sampai, $dari]; }
$kelas = trim($_GET['kelas'] ?? '');
$idPerangkat = (int)($_GET['perangkat'] ?? 0);

$where = 'c.tanggal BETWEEN ? AND ?';
$params = [$dari, $sampai];
if ($kelas !== '') { $where .= ' AND s.kelas = ?'; $params[] = $kelas; }
if ($idPerangkat > 0) { $where .= ' AND d.id_perangkat = ?'; $params[] = $idPerangkat; }

$dasar = 'FROM detail_checklist d
          JOIN checklist c ON c.id_checklist = d.id_checklist
          JOIN siswa s ON s.nis = c.nis
          JOIN perangkat p ON p.id_perangkat = d.id_perangkat
          WHERE ' . $where;

// Unduh CSV
if (($_GET['format'] ?? '') === 'csv') {
    $st = $pdo->prepare(
        'SELECT c.tanggal, c.jam, s.nis, s.nama, s.kelas, p.nama_perangkat, d.tahapan, d.kondisi, d.status, d.keterangan '
        . $dasar . ' ORDER BY c.tanggal, c.jam, c.id_checklist, FIELD(d.tahapan, \'SEBELUM\', \'SELAMA\', \'SESUDAH\', \'5R\')'
    );
    $st->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan_konservasi_energi_' . $dari . '_sd_' . $sampai . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Tanggal', 'Jam', 'NIS', 'Nama', 'Kelas', 'Perangkat', 'Tahapan', 'Kondisi', 'Sesuai', 'Keterangan']);
    while ($r = $st->fetch()) {
        $r = array_map(function ($v) {
            $v = (string)$v;
            return ($v !== '' && strpbrk($v[0], '=+-@') !== false) ? "'" . $v : $v;
        }, array_values($r));
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

// Ringkasan
$st = $pdo->prepare('SELECT COUNT(DISTINCT c.id_checklist) AS jml_checklist, COUNT(*) AS poin,
                            COALESCE(SUM(d.status = \'ya\'), 0) AS ya, COALESCE(SUM(d.status = \'tidak\'), 0) AS tidak ' . $dasar);
$st->execute($params);
$ring = $st->fetch();
$persen = (int)$ring['poin'] > 0 ? round($ring['ya'] / $ring['poin'] * 100) : 0;

// Rekap per perangkat
$st = $pdo->prepare('SELECT p.nama_perangkat, COUNT(DISTINCT c.id_checklist) AS jml_checklist, COUNT(*) AS poin,
                            SUM(d.status = \'ya\') AS ya, SUM(d.status = \'tidak\') AS tidak ' . $dasar . '
                     GROUP BY p.id_perangkat, p.nama_perangkat ORDER BY tidak DESC, p.nama_perangkat');
$st->execute($params);
$rekap = $st->fetchAll();

// Temuan: poin yang belum sesuai
$st = $pdo->prepare('SELECT c.id_checklist, c.tanggal, s.nama, s.kelas, p.nama_perangkat, d.tahapan, d.kondisi, d.keterangan ' . $dasar . '
                     AND d.status = \'tidak\' ORDER BY c.tanggal DESC, c.id_checklist DESC');
$st->execute($params);
$temuan = $st->fetchAll();

// Daftar checklist
$st = $pdo->prepare('SELECT c.id_checklist, c.tanggal, c.jam, s.nama, s.kelas,
                            COUNT(*) AS poin, SUM(d.status = \'tidak\') AS tidak ' . $dasar . '
                     GROUP BY c.id_checklist, c.tanggal, c.jam, s.nama, s.kelas
                     ORDER BY c.tanggal DESC, c.jam DESC, c.id_checklist DESC');
$st->execute($params);
$daftar = $st->fetchAll();

$kelasList = $pdo->query('SELECT DISTINCT kelas FROM siswa ORDER BY kelas')->fetchAll(PDO::FETCH_COLUMN);
$perangkatList = $pdo->query('SELECT id_perangkat, nama_perangkat FROM perangkat ORDER BY nama_perangkat')->fetchAll();

$qs = http_build_query(['dari' => $dari, 'sampai' => $sampai, 'kelas' => $kelas, 'perangkat' => $idPerangkat ?: '']);

$title = 'Laporan';
$active = 'laporan';
include 'partials/header.php';
?>
<div class="judul-baris">
  <h1>Laporan checklist</h1>
  <div class="baris-tombol tanpa-cetak" style="margin:0">
    <a class="tombol pucat" href="laporan.php?<?= e($qs) ?>&amp;format=csv">Unduh CSV</a>
    <button type="button" onclick="window.print()">Cetak laporan</button>
  </div>
</div>

<div class="kartu tanpa-cetak">
  <form method="get" class="grid-form">
    <div><label for="dari">Dari tanggal</label><input type="date" id="dari" name="dari" value="<?= e($dari) ?>"></div>
    <div><label for="sampai">Sampai tanggal</label><input type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>"></div>
    <div><label for="kelas">Kelas</label>
      <select id="kelas" name="kelas">
        <option value="">Semua kelas</option>
        <?php foreach ($kelasList as $k): ?>
          <option value="<?= e($k) ?>" <?= $kelas === $k ? 'selected' : '' ?>><?= e($k) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label for="perangkat">Perangkat</label>
      <select id="perangkat" name="perangkat">
        <option value="0">Semua perangkat</option>
        <?php foreach ($perangkatList as $p): ?>
          <option value="<?= (int)$p['id_perangkat'] ?>" <?= $idPerangkat === (int)$p['id_perangkat'] ? 'selected' : '' ?>><?= e($p['nama_perangkat']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><button type="submit">Tampilkan laporan</button></div>
  </form>
</div>

<p class="periode">Periode <?= tgl($dari) ?> sampai <?= tgl($sampai) ?>
  <?= $kelas !== '' ? ', kelas ' . e($kelas) : '' ?></p>

<div class="ringkasan">
  <div class="kartu"><div class="angka"><?= (int)$ring['jml_checklist'] ?></div><div class="label">Checklist</div></div>
  <div class="kartu"><div class="angka"><?= (int)$ring['poin'] ?></div><div class="label">Poin diperiksa</div></div>
  <div class="kartu"><div class="angka"><?= $persen ?>%</div><div class="label">Poin sudah sesuai</div></div>
  <div class="kartu"><div class="angka"><?= (int)$ring['tidak'] ?></div><div class="label">Poin belum sesuai</div></div>
</div>

<div class="kartu">
  <h2>Rekap per perangkat</h2>
  <?php if (!$rekap): ?>
    <p class="kosong">Belum ada data pada periode ini.</p>
  <?php else: ?>
  <div class="tabel-scroll">
  <table>
    <tr><th>Perangkat</th><th>Checklist</th><th>Poin</th><th>Sesuai</th><th>Belum sesuai</th><th>Persentase sesuai</th></tr>
    <?php foreach ($rekap as $r): $pr = $r['poin'] > 0 ? round($r['ya'] / $r['poin'] * 100) : 0; ?>
    <tr>
      <td><?= e($r['nama_perangkat']) ?></td>
      <td><?= (int)$r['jml_checklist'] ?></td>
      <td><?= (int)$r['poin'] ?></td>
      <td><?= (int)$r['ya'] ?></td>
      <td><?= (int)$r['tidak'] ?></td>
      <td><div class="bar-persen"><span style="width:<?= $pr ?>%"></span></div> <?= $pr ?>%</td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</div>

<div class="kartu">
  <h2>Poin yang belum sesuai</h2>
  <?php if (!$temuan): ?>
    <p class="kosong">Tidak ada poin yang belum sesuai pada periode ini.</p>
  <?php else: ?>
  <div class="tabel-scroll">
  <table>
    <tr><th>Tanggal</th><th>Siswa</th><th>Kelas</th><th>Perangkat</th><th>Tahapan</th><th>Kondisi</th><th>Keterangan</th></tr>
    <?php foreach ($temuan as $r): ?>
    <tr>
      <td><?= tgl($r['tanggal']) ?></td><td><?= e($r['nama']) ?></td><td><?= e($r['kelas']) ?></td>
      <td><?= e($r['nama_perangkat']) ?></td><td><?= e($r['tahapan']) ?></td>
      <td><?= e($r['kondisi']) ?></td><td><?= e($r['keterangan']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</div>

<div class="kartu">
  <h2>Daftar checklist</h2>
  <?php if (!$daftar): ?>
    <p class="kosong">Belum ada checklist pada periode ini.</p>
  <?php else: ?>
  <div class="tabel-scroll">
  <table>
    <tr><th>Tanggal</th><th>Jam</th><th>Siswa</th><th>Kelas</th><th>Poin sesuai</th><th class="tanpa-cetak"></th></tr>
    <?php foreach ($daftar as $r): ?>
    <tr>
      <td><?= tgl($r['tanggal']) ?></td>
      <td><?= e(substr($r['jam'], 0, 5)) ?></td>
      <td><?= e($r['nama']) ?></td>
      <td><?= e($r['kelas']) ?></td>
      <td><?= (int)$r['poin'] - (int)$r['tidak'] ?> dari <?= (int)$r['poin'] ?></td>
      <td class="tanpa-cetak"><a href="checklist_detail.php?id=<?= (int)$r['id_checklist'] ?>">Lihat</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php include 'partials/footer.php'; ?>
