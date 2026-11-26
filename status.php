<?php
// Halaman publik: cek status pengajuan dengan kode + 4 digit terakhir nomor WhatsApp
require_once __DIR__ . '/includes/auth.php';

$kode  = strtoupper(trim($_GET['kode'] ?? ''));
$hp4   = hanya_angka($_GET['hp'] ?? '');
$baru  = $kode !== '' && ($_SESSION['pengajuan_baru'] ?? '') === $kode;   // baru saja dikirim di browser ini
$aju   = null;
$error = '';

if ($kode !== '') {
    $s = $pdo->prepare("SELECT * FROM pengajuan WHERE kode_pengajuan = ?");
    $s->execute([$kode]);
    $cari = $s->fetch();

    if (!$baru && $hp4 === '') {
        $error = '';  // form belum lengkap, tampilkan form saja
    } elseif (!$cari || (!$baru && substr(hanya_angka($cari['no_hp']), -4) !== substr($hp4, -4))) {
        $error = 'Pengajuan tidak ditemukan. Periksa kembali kode dan 4 digit terakhir nomor WhatsApp.';
    } else {
        $aju = $cari;
    }
}

$items = [];
if ($aju) {
    $s = $pdo->prepare(
        "SELECT d.jumlah, b.nama_barang, b.satuan, b.biaya_sewa
         FROM detail_pengajuan d JOIN barang b ON b.id_barang = d.id_barang
         WHERE d.id_pengajuan = ?"
    );
    $s->execute([$aju['id_pengajuan']]);
    $items = $s->fetchAll();
}

$title     = 'Cek Status Pengajuan';
$pubActive = 'status';
require __DIR__ . '/includes/public_header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8 col-xl-7">

    <?php if ($baru && $aju): ?>
      <div class="card mb-3 border-success">
        <div class="card-body p-4 text-center">
          <div class="stat-icon tone-green mx-auto mb-3" style="width:56px;height:56px;font-size:1.6rem"><i class="bi bi-check2"></i></div>
          <h1 class="h5 fw-bold mb-1">Pengajuan terkirim!</h1>
          <p class="text-muted mb-3">Simpan kode di bawah ini untuk mengecek status pengajuan.</p>
          <div class="kode-box mb-3"><?= e($aju['kode_pengajuan']) ?></div>
          <a href="<?= e(wa_link("Halo " . CP_NAMA . ", saya {$aju['nama']} ({$aju['instansi']}) baru saja mengirim pengajuan peminjaman dengan kode {$aju['kode_pengajuan']}. Mohon dicek, terima kasih.")) ?>"
             target="_blank" rel="noopener" class="btn btn-success"><i class="bi bi-whatsapp me-1"></i>Kabari <?= e(CP_NAMA) ?></a>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$aju): ?>
      <div class="mb-4">
        <h1 class="page-title">Cek status pengajuan</h1>
        <p class="page-sub">Masukkan kode pengajuan dan 4 digit terakhir nomor WhatsApp yang dipakai saat mengajukan.</p>
      </div>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="get" class="card">
        <div class="card-body p-4 row g-3 align-items-end">
          <div class="col-md-6">
            <label class="form-label">Kode pengajuan</label>
            <input name="kode" class="form-control mono text-uppercase" required placeholder="AJ-XXXXXX" maxlength="12" value="<?= e($kode) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">4 digit terakhir HP</label>
            <input name="hp" class="form-control" required inputmode="numeric" maxlength="4" placeholder="0000">
          </div>
          <div class="col-md-3"><button class="btn btn-primary w-100">Cek</button></div>
        </div>
      </form>

    <?php else:
        $durasi = (int)((strtotime($aju['tanggal_kembali_rencana']) - strtotime($aju['tanggal_pinjam'])) / 86400) + 0;
        $estimasi = array_sum(array_map(fn($i) => $i['jumlah'] * $i['biaya_sewa'], $items)); ?>

      <div class="card">
        <div class="card-header">
          <span class="mono"><?= e($aju['kode_pengajuan']) ?></span>
          <?= badge($aju['status']) ?>
        </div>
        <div class="card-body">
          <!-- alur status -->
          <div class="status-track mb-4">
            <div class="done"><i class="bi bi-send"></i>Dikirim<small><?= date('d/m H:i', strtotime($aju['dibuat_pada'])) ?></small></div>
            <div class="<?= $aju['status'] === 'menunggu' ? 'now' : 'done' ?>"><i class="bi bi-hourglass-split"></i>Diperiksa PSDO</div>
            <div class="<?= $aju['status'] === 'disetujui' ? 'done' : ($aju['status'] === 'ditolak' ? 'fail' : '') ?>">
              <i class="bi <?= $aju['status'] === 'ditolak' ? 'bi-x-lg' : 'bi-check2' ?>"></i><?= $aju['status'] === 'ditolak' ? 'Ditolak' : 'Disetujui' ?>
              <?php if ($aju['diproses_pada']): ?><small><?= date('d/m H:i', strtotime($aju['diproses_pada'])) ?></small><?php endif; ?>
            </div>
          </div>

          <?php if ($aju['status'] === 'disetujui'): ?>
            <div class="alert alert-success small"><i class="bi bi-check-circle me-1"></i>Pengajuan disetujui. Silakan ambil barang di PKM sesuai tanggal pinjam dengan menunjukkan kode ini.</div>
          <?php elseif ($aju['status'] === 'ditolak'): ?>
            <div class="alert alert-danger small"><i class="bi bi-x-circle me-1"></i>Pengajuan tidak dapat disetujui. Silakan ajukan ulang dengan tanggal atau barang lain.</div>
          <?php else: ?>
            <div class="alert alert-warning small"><i class="bi bi-hourglass-split me-1"></i>Pengajuan sedang menunggu pemeriksaan Biro PSDO.</div>
          <?php endif; ?>

          <?php if ($aju['catatan_psdo']): ?>
            <div class="total-box mb-3"><div class="cell-sub mb-1">Catatan dari Biro PSDO</div><?= e($aju['catatan_psdo']) ?></div>
          <?php endif; ?>

          <dl class="info-list mb-3">
            <dt>Peminjam</dt><dd><?= e($aju['nama']) ?> · <?= e($aju['instansi']) ?></dd>
            <dt>Tanggal</dt><dd><?= tgl($aju['tanggal_pinjam']) ?> – <?= tgl($aju['tanggal_kembali_rencana']) ?> <span class="cell-sub">(<?= $durasi ?: 'hari yang sama' ?><?= $durasi ? ' hari' : '' ?>)</span></dd>
            <dt>Keperluan</dt><dd><?= e($aju['keperluan']) ?></dd>
          </dl>

          <table class="table table-sm border rounded">
            <thead><tr><th>Barang</th><th class="text-center">Jumlah</th></tr></thead>
            <tbody>
              <?php foreach ($items as $i): ?>
                <tr><td><?= e($i['nama_barang']) ?></td><td class="text-center"><?= (int)$i['jumlah'] ?> <?= e($i['satuan']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <div class="small text-muted">Estimasi biaya sewa: <strong><?= rupiah($estimasi) ?></strong></div>
        </div>
      </div>

      <div class="text-center mt-3">
        <a href="status.php" class="btn btn-link text-decoration-none">Cek pengajuan lain</a>
        <a href="katalog.php" class="btn btn-link text-decoration-none">Kembali ke katalog</a>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require __DIR__ . '/includes/public_footer.php'; ?>
