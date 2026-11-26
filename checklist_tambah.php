<?php
require 'config.php';
require_login();
$user = current_user();

const TAHAPAN = [
    'SEBELUM' => ['Sebelum digunakan', 'Perangkat dalam kondisi mati dan siap digunakan'],
    'SELAMA'  => ['Selama digunakan', 'Digunakan sesuai kebutuhan dan hemat energi'],
    'SESUDAH' => ['Sesudah digunakan', 'Perangkat dimatikan setelah digunakan'],
    '5R'      => ['Penerapan 5R', 'Area perangkat rapi dan bersih'],
];

$siswaList = $pdo->query('SELECT nis, nama, kelas FROM siswa ORDER BY kelas, nama')->fetchAll();
$perangkatList = $pdo->query('SELECT id_perangkat, nama_perangkat FROM perangkat ORDER BY nama_perangkat')->fetchAll();

// Siswa dan guru: nama dikunci (paten) sesuai akun yang login, tidak bisa memilih nama lain.
// Dicocokkan lewat NIS = username (siswa juga lewat nama sama). Kalau belum ada di tabel siswa, dibuatkan otomatis.
$nisSendiri = null;
if (in_array($user['level'], ['siswa', 'guru'], true)) {
    foreach ($siswaList as $s) {
        $cocokNama = $user['level'] === 'siswa' && mb_strtolower($s['nama']) === mb_strtolower($user['nama']);
        if ($s['nis'] === substr($user['username'], 0, 10) || $cocokNama) { $nisSendiri = $s['nis']; break; }
    }
    if ($nisSendiri === null) {
        try {
            $nisBaru = substr($user['username'], 0, 10);
            $pdo->prepare('INSERT INTO siswa (nis, nama, kelas) VALUES (?, ?, ?)')
                ->execute([$nisBaru, $user['nama'], $user['level'] === 'guru' ? 'Guru' : '-']);
            $nisSendiri = $nisBaru;
            $siswaList = $pdo->query('SELECT nis, nama, kelas FROM siswa ORDER BY kelas, nama')->fetchAll();
        } catch (PDOException $ex) {
            $nisSendiri = null;
        }
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (in_array($user['level'], ['siswa', 'guru'], true) && $nisSendiri === null) {
        die('Akun Anda belum terhubung ke data siswa. Hubungi admin.');
    }
    $nis = $nisSendiri ?? ($_POST['nis'] ?? '');
    $tanggal = $_POST['tanggal'] ?? '';
    $jam = $_POST['jam'] ?? '';
    $idPerangkat = (int)($_POST['id_perangkat'] ?? 0);

    $nisAda = in_array($nis, array_column($siswaList, 'nis'), true);
    $perangkatAda = in_array($idPerangkat, array_map('intval', array_column($perangkatList, 'id_perangkat')), true);
    $tglOk = DateTime::createFromFormat('Y-m-d', $tanggal) !== false;
    $jamOk = DateTime::createFromFormat('H:i', $jam) !== false;

    $rows = [];
    $detailOk = true;
    foreach (TAHAPAN as $kode => $info) {
        $kondisi = trim($_POST['kondisi'][$kode] ?? '');
        $status = $_POST['status'][$kode] ?? '';
        $ket = trim($_POST['ket'][$kode] ?? '');
        if ($kondisi === '' || strlen($kondisi) > 100 || !in_array($status, ['ya', 'tidak'], true)) $detailOk = false;
        $rows[] = [$kode, $kondisi, $status, $ket];
    }

    if (!$nisAda) $error = 'Pilih siswa terlebih dahulu.';
    elseif (!$perangkatAda) $error = 'Pilih perangkat yang diperiksa.';
    elseif (!$tglOk || !$jamOk) $error = 'Tanggal atau jam tidak valid.';
    elseif (!$detailOk) $error = 'Setiap tahapan perlu kondisi (maks. 100 karakter) dan status ya atau tidak.';
    else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO checklist (nis, tanggal, jam) VALUES (?, ?, ?)')->execute([$nis, $tanggal, $jam . ':00']);
            $idChecklist = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO detail_checklist (id_checklist, id_perangkat, tahapan, kondisi, status, keterangan) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($rows as [$kode, $kondisi, $status, $ket]) {
                $ins->execute([$idChecklist, $idPerangkat, $kode, $kondisi, $status, $ket]);
            }
            $pdo->commit();
            flash('Checklist tersimpan.');
            redirect('checklist_detail.php?id=' . $idChecklist);
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Checklist gagal disimpan. Coba lagi.';
        }
    }
}

$v = fn($k, $d = '') => $_POST[$k] ?? $d;

$title = 'Isi checklist';
$active = 'checklist';
include 'partials/header.php';
?>
<div class="judul-baris"><h1>Isi checklist baru</h1></div>
<?php if ($error): ?><div class="notif err"><?= e($error) ?></div><?php endif; ?>

<form method="post">
  <?= csrf_field() ?>
  <div class="kartu">
    <div class="grid-form">
      <div>
        <label for="nis">Siswa</label>
        <?php if ($nisSendiri): ?>
          <?php foreach ($siswaList as $s) if ($s['nis'] === $nisSendiri): ?>
            <input type="text" id="nis" value="<?= e($s['nama'] . ($s['kelas'] !== '-' ? ' - ' . $s['kelas'] : '')) ?>" readonly>
          <?php endif; ?>
        <?php else: ?>
          <select id="nis" name="nis" required>
            <option value="">Pilih siswa</option>
            <?php foreach ($siswaList as $s): ?>
              <option value="<?= e($s['nis']) ?>" <?= $v('nis') === $s['nis'] ? 'selected' : '' ?>><?= e($s['nama'] . ' - ' . $s['kelas']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>
      <div><label for="tanggal">Tanggal</label>
        <input type="date" id="tanggal" name="tanggal" value="<?= e($v('tanggal', date('Y-m-d'))) ?>" required></div>
      <div><label for="jam">Jam</label>
        <input type="time" id="jam" name="jam" value="<?= e($v('jam', date('H:i'))) ?>" required></div>
      <div>
        <label for="id_perangkat">Perangkat yang diperiksa</label>
        <select id="id_perangkat" name="id_perangkat" required>
          <option value="">Pilih perangkat</option>
          <?php foreach ($perangkatList as $p): ?>
            <option value="<?= (int)$p['id_perangkat'] ?>" <?= (int)$v('id_perangkat') === (int)$p['id_perangkat'] ? 'selected' : '' ?>><?= e($p['nama_perangkat']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <?php foreach (TAHAPAN as $kode => [$label, $default]): ?>
  <div class="tahapan">
    <h2><?= e($label) ?></h2>
    <div class="grid-form">
      <div><label for="kondisi_<?= $kode ?>">Kondisi yang diperiksa</label>
        <input type="text" id="kondisi_<?= $kode ?>" name="kondisi[<?= $kode ?>]" maxlength="100" value="<?= e($_POST['kondisi'][$kode] ?? $default) ?>" required></div>
      <div><label>Sudah sesuai?</label>
        <div class="radio-baris">
          <?php $st = $_POST['status'][$kode] ?? 'ya'; ?>
          <label><input type="radio" name="status[<?= $kode ?>]" value="ya" <?= $st === 'ya' ? 'checked' : '' ?>> Ya</label>
          <label><input type="radio" name="status[<?= $kode ?>]" value="tidak" <?= $st === 'tidak' ? 'checked' : '' ?>> Tidak</label>
        </div></div>
      <div><label for="ket_<?= $kode ?>">Keterangan</label>
        <input type="text" id="ket_<?= $kode ?>" name="ket[<?= $kode ?>]" value="<?= e($_POST['ket'][$kode] ?? '') ?>"></div>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="baris-tombol">
    <button type="submit">Simpan checklist</button>
    <a class="tombol pucat" href="checklist.php">Batal</a>
  </div>
</form>
<?php include 'partials/footer.php'; ?>
