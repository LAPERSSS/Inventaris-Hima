<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo', 'bph', 'ketua']);

$id = (int)($_GET['id'] ?? 0);
$nomor = 'PJ-' . str_pad($id, 4, '0', STR_PAD_LEFT);

function ambil_peminjaman(PDO $pdo, int $id) {
    $s = $pdo->prepare(
        "SELECT p.*, pj.nama AS peminjam, pj.instansi, pj.no_hp, pj.email, u.nama AS petugas
         FROM peminjaman p
         JOIN peminjam pj ON pj.id_peminjam = p.id_peminjam
         JOIN pengguna u  ON u.id_pengguna  = p.id_pengguna
         WHERE p.id_peminjaman = ?"
    );
    $s->execute([$id]);
    return $s->fetch();
}

$p = ambil_peminjaman($pdo, $id);
if (!$p) { flash('danger', 'Peminjaman tidak ditemukan.'); redirect('peminjaman/index.php'); }

$errors = [];

if (is_post()) {
    require_role(['psdo']);
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($p['tanggal_kembali']) {
        flash('danger', 'Peminjaman ini sudah selesai.');
        redirect('peminjaman/detail.php?id=' . $id);
    }

    // ---------- batalkan (salah input) ----------
    if ($aksi === 'batal') {
        $pdo->beginTransaction();
        $ids = $pdo->prepare("SELECT id_barang FROM detail_peminjaman WHERE id_peminjaman = ?");
        $ids->execute([$id]);
        $barangIds = $ids->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare("DELETE FROM peminjaman WHERE id_peminjaman = ?")->execute([$id]); // detail ikut terhapus (CASCADE)
        foreach ($barangIds as $idb) sync_tersedia($pdo, (int)$idb);
        $pdo->commit();
        flash('success', "Peminjaman {$nomor} dibatalkan dan stok dikembalikan.");
        redirect('peminjaman/index.php');
    }

    // ---------- proses pengembalian ----------
    if ($aksi === 'kembali') {
        $tglKembali = $_POST['tanggal_kembali'] ?? '';
        $kondisi    = $_POST['kondisi'] ?? [];

        if (!valid_date($tglKembali))                $errors[] = 'Tanggal kembali tidak valid.';
        elseif ($tglKembali < $p['tanggal_pinjam'])  $errors[] = 'Tanggal kembali tidak boleh sebelum tanggal pinjam.';
        elseif ($tglKembali > date('Y-m-d'))         $errors[] = 'Tanggal kembali tidak boleh di masa depan.';

        if (!$errors) {
            $pdo->beginTransaction();
            $d = $pdo->prepare(
                "SELECT d.*, b.nama_barang, b.satuan FROM detail_peminjaman d
                 JOIN barang b ON b.id_barang = d.id_barang WHERE d.id_peminjaman = ?"
            );
            $d->execute([$id]);
            $items = $d->fetchAll();

            $pdo->prepare("UPDATE peminjaman SET tanggal_kembali = ?, status = 'kembali' WHERE id_peminjaman = ?")
                ->execute([$tglKembali, $id]);

            $upd = $pdo->prepare("UPDATE detail_peminjaman SET kondisi_kembali = ? WHERE id_detail = ?");
            $catatan = [];
            foreach ($items as $it) {
                $k = $kondisi[$it['id_detail']] ?? 'baik';
                if (!in_array($k, ['baik', 'rusak', 'hilang'], true)) $k = 'baik';
                $upd->execute([$k, $it['id_detail']]);

                if ($k === 'baik') {
                    sync_tersedia($pdo, (int)$it['id_barang']);
                } else {
                    // unit rusak/hilang dikeluarkan dari stok dan tercatat di riwayat
                    ubah_total($pdo, (int)$it['id_barang'], -(int)$it['jumlah']);
                    catat_transaksi($pdo, (int)$it['id_barang'], 'keluar', (int)$it['jumlah'], $tglKembali,
                        ($k === 'rusak' ? 'Rusak' : 'Hilang') . " saat dipinjam ({$nomor}, {$p['instansi']})");
                    $catatan[] = "{$it['jumlah']} {$it['satuan']} {$it['nama_barang']} {$k}";
                }
            }
            $pdo->commit();

            $msg = "Pengembalian {$nomor} dicatat.";
            if ($catatan) $msg .= ' Dikeluarkan dari stok: ' . implode(', ', $catatan) . '.';
            flash($catatan ? 'warning' : 'success', $msg);
            redirect('peminjaman/detail.php?id=' . $id);
        }
    }
}

$d = $pdo->prepare(
    "SELECT d.*, b.nama_barang, b.kode_inventaris, b.satuan, b.biaya_sewa
     FROM detail_peminjaman d JOIN barang b ON b.id_barang = d.id_barang
     WHERE d.id_peminjaman = ?"
);
$d->execute([$id]);
$items = $d->fetchAll();

$aktif   = !$p['tanggal_kembali'];
$status  = $aktif ? ($p['tanggal_jatuh_tempo'] < date('Y-m-d') ? 'terlambat' : 'dipinjam') : 'kembali';
$telat   = max(0, (int)((strtotime($p['tanggal_kembali'] ?: date('Y-m-d')) - strtotime($p['tanggal_jatuh_tempo'])) / 86400));

$title    = 'Peminjaman ' . $nomor;
$subtitle = $p['peminjam'] . ' · ' . $p['instansi'];
$active   = 'peminjaman';
$back     = 'peminjaman/index.php';
$actions  = '<button onclick="window.print()" class="btn btn-light"><i class="bi bi-printer me-1"></i>Cetak nota</button>';
require __DIR__ . '/../includes/header.php';
?>

<?= show_errors($errors) ?>

<div class="row g-3 align-items-start">
  <div class="col-xl-4">
    <div class="card mb-3">
      <div class="card-header"><span>Ringkasan</span><?= badge($status) ?></div>
      <div class="card-body">
        <dl class="info-list">
          <dt>Peminjam</dt><dd class="fw-semibold"><?= e($p['peminjam']) ?></dd>
          <dt>Instansi</dt><dd><?= e($p['instansi']) ?></dd>
          <dt>Kontak</dt><dd><?= e($p['no_hp']) ?><?= $p['email'] ? '<br><span class="cell-sub">' . e($p['email']) . '</span>' : '' ?></dd>
          <dt>Tanggal pinjam</dt><dd><?= tgl($p['tanggal_pinjam']) ?></dd>
          <dt>Jatuh tempo</dt><dd><?= tgl($p['tanggal_jatuh_tempo']) ?></dd>
          <dt>Dikembalikan</dt><dd><?= $p['tanggal_kembali'] ? tgl($p['tanggal_kembali']) : '<span class="text-muted">belum</span>' ?>
            <?php if ($telat > 0): ?><div class="cell-sub text-danger">terlambat <?= $telat ?> hari</div><?php endif; ?></dd>
          <dt>Dilayani oleh</dt><dd><?= e($p['petugas']) ?></dd>
        </dl>
      </div>
    </div>

    <?php if ($aktif && can_edit()): ?>
      <div class="card">
        <div class="card-body d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <div class="small text-muted">Salah input? Batalkan agar stok kembali.</div>
          <?= post_button('', 'Batalkan', 'btn btn-sm btn-outline-danger', "Batalkan peminjaman {$nomor}?", ['aksi' => 'batal']) ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-xl-8">
    <?php if ($aktif && can_edit()): ?>
      <!-- form pengembalian -->
      <form method="post" class="card">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="kembali">
        <div class="card-header">
          <span><i class="bi bi-box-arrow-in-left me-1"></i>Proses pengembalian</span>
          <span class="hint">periksa kondisi setiap barang</span>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead><tr><th>Barang</th><th class="text-center">Jumlah</th><th style="width:170px">Kondisi kembali</th><th class="text-end">Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td><div class="cell-main"><?= e($it['nama_barang']) ?></div><div class="cell-sub mono"><?= e($it['kode_inventaris']) ?></div></td>
                <td class="text-center"><?= (int)$it['jumlah'] ?> <?= e($it['satuan']) ?></td>
                <td>
                  <select name="kondisi[<?= (int)$it['id_detail'] ?>]" class="form-select">
                    <option value="baik">Baik</option>
                    <option value="rusak">Rusak</option>
                    <option value="hilang">Hilang</option>
                  </select>
                </td>
                <td class="text-end"><?= rupiah($it['subtotal_biaya']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card-body border-top">
          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label">Tanggal kembali</label>
              <input type="date" name="tanggal_kembali" class="form-control" required
                     min="<?= e($p['tanggal_pinjam']) ?>" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-8">
              <div class="total-box d-flex justify-content-between align-items-center">
                <span class="text-muted">Total biaya sewa</span>
                <span class="fs-5 fw-bold"><?= rupiah($p['total_biaya']) ?></span>
              </div>
            </div>
          </div>
          <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>Barang yang kembali rusak atau hilang otomatis dicatat sebagai barang keluar.</div>
        </div>
        <div class="card-footer bg-transparent p-3 px-4">
          <button class="btn btn-primary" onclick="return confirm('Simpan pengembalian?')"><i class="bi bi-check-lg me-1"></i>Simpan pengembalian</button>
        </div>
      </form>

    <?php else: ?>
      <!-- rincian (selesai / mode lihat) -->
      <div class="card">
        <div class="card-header"><span>Rincian barang</span><span class="hint"><?= count($items) ?> jenis</span></div>
        <div class="table-responsive">
          <table class="table">
            <thead><tr><th>Barang</th><th class="text-center">Jumlah</th><th>Kondisi keluar</th><th>Kondisi kembali</th><th class="text-end">Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td><div class="cell-main"><?= e($it['nama_barang']) ?></div><div class="cell-sub mono"><?= e($it['kode_inventaris']) ?></div></td>
                <td class="text-center"><?= (int)$it['jumlah'] ?> <?= e($it['satuan']) ?></td>
                <td><?= badge($it['kondisi_keluar']) ?></td>
                <td><?= $it['kondisi_kembali'] ? badge($it['kondisi_kembali']) : '<span class="text-muted">–</span>' ?></td>
                <td class="text-end"><?= rupiah($it['subtotal_biaya']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr><td colspan="4" class="text-end fw-semibold">Total</td><td class="text-end fw-bold"><?= rupiah($p['total_biaya']) ?></td></tr>
            </tfoot>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
