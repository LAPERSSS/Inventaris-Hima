<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo']);

$errors = [];

// daftar barang + jumlah yang seharusnya ada di PKM (total - sedang dipinjam)
$barang = $pdo->query(
    "SELECT b.id_barang, b.kode_inventaris, b.nama_barang, b.satuan, b.kondisi, b.jumlah_total, k.nama_kategori,
            b.jumlah_total - COALESCE((SELECT SUM(d.jumlah) FROM detail_peminjaman d
                JOIN peminjaman p ON p.id_peminjaman = d.id_peminjaman
                WHERE p.tanggal_kembali IS NULL AND d.id_barang = b.id_barang), 0) AS seharusnya
     FROM barang b JOIN kategori k ON k.id_kategori = b.id_kategori
     ORDER BY k.nama_kategori, b.nama_barang"
)->fetchAll();

if (is_post()) {
    csrf_check();
    $tanggal = $_POST['tanggal_periksa'] ?? '';
    $jenis   = $_POST['jenis'] ?? '';
    $catatan = trim($_POST['catatan'] ?? '');
    $cek     = $_POST['cek'] ?? [];

    if (!valid_date($tanggal))           $errors[] = 'Tanggal pemeriksaan tidak valid.';
    elseif ($tanggal > date('Y-m-d'))    $errors[] = 'Tanggal tidak boleh di masa depan.';
    if (!in_array($jenis, ['pkm_service', 'insidental'], true)) $errors[] = 'Pilih jenis pemeriksaan.';
    if (!$cek)                           $errors[] = 'Centang minimal satu barang yang diperiksa.';

    $valid = array_column($barang, null, 'id_barang');
    foreach ($cek as $idb => $_) {
        if (!isset($valid[(int)$idb])) { $errors[] = 'Ada barang yang tidak dikenal.'; break; }
        if ((int)($_POST['ditemukan'][$idb] ?? -1) < 0) { $errors[] = "Jumlah ditemukan untuk {$valid[$idb]['nama_barang']} tidak valid."; }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO pemeriksaan (id_pengguna, tanggal_periksa, jenis, catatan) VALUES (?, ?, ?, ?)")
            ->execute([user()['id_pengguna'], $tanggal, $jenis, $catatan]);
        $idPeriksa = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare(
            "INSERT INTO detail_pemeriksaan (id_pemeriksaan, id_barang, kondisi, jumlah_ditemukan, tindakan)
             VALUES (?, ?, ?, ?, ?)"
        );
        $updKondisi = $pdo->prepare("UPDATE barang SET kondisi = ? WHERE id_barang = ?");
        $masalah = 0;
        foreach ($cek as $idb => $_) {
            $idb = (int)$idb;
            $k   = $_POST['kondisi'][$idb] ?? 'baik';
            if (!in_array($k, ['baik', 'rusak', 'hilang'], true)) $k = 'baik';
            $jml = (int)$_POST['ditemukan'][$idb];
            $tin = trim($_POST['tindakan'][$idb] ?? '');
            $ins->execute([$idPeriksa, $idb, $k, $jml, $tin ?: null]);

            // kondisi terkini barang mengikuti hasil pemeriksaan
            $updKondisi->execute([$k, $idb]);
            sync_tersedia($pdo, $idb);
            if ($k !== 'baik' || $jml < $valid[$idb]['seharusnya']) $masalah++;
        }
        $pdo->commit();

        flash($masalah ? 'warning' : 'success',
              'Hasil pemeriksaan disimpan (' . count($cek) . ' barang)' . ($masalah ? ", {$masalah} barang perlu tindak lanjut." : ', semua aman.'));
        redirect('pemeriksaan/detail.php?id=' . $idPeriksa);
    }
}

$title    = 'Pemeriksaan Baru';
$subtitle = 'Isi kondisi dan jumlah fisik yang ditemukan. Kondisi barang di sistem akan ikut diperbarui.';
$active   = 'pemeriksaan';
$back     = 'pemeriksaan/index.php';
require __DIR__ . '/../includes/header.php';

$posted = is_post();
?>

<?= show_errors($errors) ?>

<form method="post">
  <?= csrf_field() ?>
  <div class="card mb-3">
    <div class="card-body row g-3">
      <div class="col-md-3">
        <label class="form-label">Tanggal pemeriksaan</label>
        <input type="date" name="tanggal_periksa" class="form-control" required max="<?= date('Y-m-d') ?>" value="<?= e(old('tanggal_periksa', date('Y-m-d'))) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Jenis</label>
        <select name="jenis" class="form-select">
          <option value="pkm_service" <?= sel(old('jenis', 'pkm_service'), 'pkm_service') ?>>PKM Service (rutin bulanan)</option>
          <option value="insidental"  <?= sel(old('jenis'), 'insidental') ?>>Insidental (sebelum dipakai)</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Catatan umum</label>
        <input name="catatan" class="form-control" placeholder="mis. PKM Service bulan <?= e(['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][(int)date('n')]) ?>" value="<?= e(old('catatan')) ?>">
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span>Daftar periksa barang</span>
      <span class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-light" onclick="centang(true)">Centang semua</button>
        <button type="button" class="btn btn-sm btn-light" onclick="centang(false)">Kosongkan</button>
      </span>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr><th style="width:40px"></th><th>Barang</th><th class="text-center">Seharusnya ada</th>
              <th style="width:130px">Kondisi</th><th style="width:110px">Ditemukan</th><th style="min-width:200px">Tindakan</th></tr>
        </thead>
        <tbody>
        <?php $katSebelum = null;
        foreach ($barang as $b):
            $idb = (int)$b['id_barang'];
            if ($b['nama_kategori'] !== $katSebelum): $katSebelum = $b['nama_kategori']; ?>
              <tr class="table-light"><td colspan="6" class="small fw-semibold text-muted py-2"><?= e($katSebelum) ?></td></tr>
            <?php endif;
            $checked = $posted ? isset($_POST['cek'][$idb]) : true; ?>
          <tr class="item-row">
            <td><input type="checkbox" class="form-check-input cek" name="cek[<?= $idb ?>]" value="1" <?= $checked ? 'checked' : '' ?>></td>
            <td><div class="cell-main"><?= e($b['nama_barang']) ?></div>
                <div class="cell-sub"><span class="mono"><?= e($b['kode_inventaris']) ?></span> · tercatat <?= e($b['kondisi']) ?></div></td>
            <td class="text-center"><?= (int)$b['seharusnya'] ?> <span class="cell-sub"><?= e($b['satuan']) ?></span></td>
            <td>
              <select name="kondisi[<?= $idb ?>]" class="form-select">
                <?php foreach (['baik', 'rusak', 'hilang'] as $k): ?>
                  <option value="<?= $k ?>" <?= sel($_POST['kondisi'][$idb] ?? $b['kondisi'], $k) ?>><?= ucfirst($k) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="number" name="ditemukan[<?= $idb ?>]" class="form-control" min="0" value="<?= e($_POST['ditemukan'][$idb] ?? $b['seharusnya']) ?>"></td>
            <td><input name="tindakan[<?= $idb ?>]" class="form-control" placeholder="opsional" value="<?= e($_POST['tindakan'][$idb] ?? '') ?>"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-transparent d-flex gap-2 align-items-center p-3 px-4">
      <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan hasil</button>
      <a href="index.php" class="btn btn-light">Batal</a>
      <span class="ms-auto small text-muted" id="jml-cek"></span>
    </div>
  </div>
</form>

<script>
  const cbs = document.querySelectorAll('.cek');
  const info = () => document.getElementById('jml-cek').textContent = [...cbs].filter(c => c.checked).length + ' barang dicentang';
  function centang(v) { cbs.forEach(c => c.checked = v); info(); }
  cbs.forEach(c => c.addEventListener('change', info)); info();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
