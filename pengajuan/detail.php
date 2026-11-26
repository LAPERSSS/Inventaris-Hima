<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo', 'bph', 'ketua']);

$id = (int)($_GET['id'] ?? 0);

function ambil_pengajuan(PDO $pdo, int $id) {
    $s = $pdo->prepare(
        "SELECT a.*, u.nama AS petugas FROM pengajuan a
         LEFT JOIN pengguna u ON u.id_pengguna = a.id_pengguna
         WHERE a.id_pengajuan = ?"
    );
    $s->execute([$id]);
    return $s->fetch();
}

$a = ambil_pengajuan($pdo, $id);
if (!$a) { flash('danger', 'Pengajuan tidak ditemukan.'); redirect('pengajuan/index.php'); }

$errors = [];

if (is_post()) {
    require_role(['psdo']);
    csrf_check();
    $aksi    = $_POST['aksi'] ?? '';
    $catatan = trim($_POST['catatan_psdo'] ?? '');

    if ($a['status'] !== 'menunggu') {
        flash('danger', 'Pengajuan ini sudah diproses.');
        redirect('pengajuan/detail.php?id=' . $id);
    }

    // ---------- tolak ----------
    if ($aksi === 'tolak') {
        if ($catatan === '') {
            $errors[] = 'Tuliskan alasan penolakan agar peminjam mengetahuinya.';
        } else {
            $pdo->prepare(
                "UPDATE pengajuan SET status = 'ditolak', catatan_psdo = ?, id_pengguna = ?, diproses_pada = NOW()
                 WHERE id_pengajuan = ? AND status = 'menunggu'"
            )->execute([$catatan, user()['id_pengguna'], $id]);
            flash('success', "Pengajuan {$a['kode_pengajuan']} ditolak.");
            redirect('pengajuan/detail.php?id=' . $id);
        }
    }

    // ---------- setujui -> otomatis menjadi peminjaman ----------
    if ($aksi === 'setujui') {
        $gratis = !empty($_POST['gratis']);
        $pdo->beginTransaction();
        try {
            $d = $pdo->prepare("SELECT id_barang, jumlah FROM detail_pengajuan WHERE id_pengajuan = ?");
            $d->execute([$id]);
            $permintaan = $d->fetchAll();

            // kunci & cek stok setiap barang
            $s = $pdo->prepare("SELECT * FROM barang WHERE id_barang = ? FOR UPDATE");
            $rinci = []; $total = 0;
            foreach ($permintaan as $p) {
                $s->execute([$p['id_barang']]);
                $b = $s->fetch();
                if ($b['kondisi'] !== 'baik')              $errors[] = "{$b['nama_barang']} sedang {$b['kondisi']}.";
                elseif ($p['jumlah'] > $b['jumlah_tersedia']) $errors[] = "{$b['nama_barang']}: diminta {$p['jumlah']}, tersedia {$b['jumlah_tersedia']}.";
                $sub = $gratis ? 0 : $p['jumlah'] * $b['biaya_sewa'];
                $total += $sub;
                $rinci[] = [(int)$p['id_barang'], (int)$p['jumlah'], $sub];
            }
            if ($errors) throw new RuntimeException('stok');

            // pakai data peminjam lama jika nomor HP-nya sama, jika tidak buat baru
            $s = $pdo->prepare("SELECT id_peminjam FROM peminjam WHERE no_hp = ? LIMIT 1");
            $s->execute([$a['no_hp']]);
            $idPeminjam = (int)$s->fetchColumn();
            if (!$idPeminjam) {
                $pdo->prepare("INSERT INTO peminjam (nama, instansi, no_hp, email) VALUES (?, ?, ?, ?)")
                    ->execute([$a['nama'], $a['instansi'], $a['no_hp'], $a['email']]);
                $idPeminjam = (int)$pdo->lastInsertId();
            }

            $pdo->prepare(
                "INSERT INTO peminjaman (id_peminjam, id_pengguna, tanggal_pinjam, tanggal_jatuh_tempo, status, total_biaya)
                 VALUES (?, ?, ?, ?, 'dipinjam', ?)"
            )->execute([$idPeminjam, user()['id_pengguna'], $a['tanggal_pinjam'], $a['tanggal_kembali_rencana'], $total]);
            $idPinjam = (int)$pdo->lastInsertId();

            $ins = $pdo->prepare(
                "INSERT INTO detail_peminjaman (id_peminjaman, id_barang, jumlah, kondisi_keluar, subtotal_biaya)
                 VALUES (?, ?, ?, 'baik', ?)"
            );
            foreach ($rinci as [$idb, $jml, $sub]) {
                $ins->execute([$idPinjam, $idb, $jml, $sub]);
                sync_tersedia($pdo, $idb);
            }

            $pdo->prepare(
                "UPDATE pengajuan SET status = 'disetujui', catatan_psdo = ?, id_pengguna = ?, id_peminjaman = ?, diproses_pada = NOW()
                 WHERE id_pengajuan = ?"
            )->execute([$catatan ?: null, user()['id_pengguna'], $idPinjam, $id]);
            $pdo->commit();

            flash('success', "Pengajuan {$a['kode_pengajuan']} disetujui dan tercatat sebagai peminjaman PJ-" . str_pad($idPinjam, 4, '0', STR_PAD_LEFT) . '. Stok sudah dikurangi.');
            redirect('pengajuan/detail.php?id=' . $id);
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
        }
    }
}

$s = $pdo->prepare(
    "SELECT d.jumlah, b.id_barang, b.nama_barang, b.kode_inventaris, b.satuan, b.biaya_sewa, b.jumlah_tersedia, b.kondisi
     FROM detail_pengajuan d JOIN barang b ON b.id_barang = d.id_barang
     WHERE d.id_pengajuan = ?"
);
$s->execute([$id]);
$items = $s->fetchAll();

$cukup    = array_reduce($items, fn($ok, $i) => $ok && $i['kondisi'] === 'baik' && $i['jumlah'] <= $i['jumlah_tersedia'], true);
$estimasi = array_sum(array_map(fn($i) => $i['jumlah'] * $i['biaya_sewa'], $items));
$menunggu = $a['status'] === 'menunggu';
$durasi   = (int)((strtotime($a['tanggal_kembali_rencana']) - strtotime($a['tanggal_pinjam'])) / 86400);

$title    = 'Pengajuan ' . $a['kode_pengajuan'];
$subtitle = $a['nama'] . ' · ' . $a['instansi'];
$active   = 'pengajuan';
$back     = 'pengajuan/index.php';
$hpWa     = preg_replace('/^0/', '62', hanya_angka($a['no_hp']));
$actions  = (strpos($a['no_hp'], 'x') === false)
    ? '<a href="https://wa.me/' . e($hpWa) . '" target="_blank" rel="noopener" class="btn btn-light"><i class="bi bi-whatsapp me-1"></i>Chat peminjam</a>'
    : '';
require __DIR__ . '/../includes/header.php';
?>

<?= show_errors($errors) ?>

<div class="row g-3 align-items-start">
  <div class="col-xl-5">
    <div class="card mb-3">
      <div class="card-header"><span>Data pengajuan</span><?= badge($a['status']) ?></div>
      <div class="card-body">
        <dl class="info-list">
          <dt>Kode</dt><dd class="mono"><?= e($a['kode_pengajuan']) ?></dd>
          <dt>Penanggung jawab</dt><dd class="fw-semibold"><?= e($a['nama']) ?></dd>
          <dt>Instansi</dt><dd><?= e($a['instansi']) ?></dd>
          <dt>Kontak</dt><dd><?= e($a['no_hp']) ?><?= $a['email'] ? '<br><span class="cell-sub">' . e($a['email']) . '</span>' : '' ?></dd>
          <dt>Tanggal pinjam</dt><dd><?= tgl($a['tanggal_pinjam']) ?></dd>
          <dt>Rencana kembali</dt><dd><?= tgl($a['tanggal_kembali_rencana']) ?> <span class="cell-sub">(<?= $durasi ?> hari)</span></dd>
          <dt>Keperluan</dt><dd><?= nl2br(e($a['keperluan'])) ?></dd>
          <dt>Diajukan</dt><dd><?= date('d/m/Y H:i', strtotime($a['dibuat_pada'])) ?></dd>
          <?php if (!$menunggu): ?>
            <dt>Diproses</dt><dd><?= date('d/m/Y H:i', strtotime($a['diproses_pada'])) ?> oleh <?= e($a['petugas'] ?? '-') ?></dd>
            <?php if ($a['catatan_psdo']): ?><dt>Catatan</dt><dd><?= e($a['catatan_psdo']) ?></dd><?php endif; ?>
            <?php if ($a['id_peminjaman']): ?>
              <dt>Peminjaman</dt><dd><a href="<?= url('peminjaman/detail.php?id=' . $a['id_peminjaman']) ?>" class="fw-semibold text-decoration-none">PJ-<?= str_pad($a['id_peminjaman'], 4, '0', STR_PAD_LEFT) ?> <i class="bi bi-arrow-right"></i></a></dd>
            <?php endif; ?>
          <?php endif; ?>
        </dl>
      </div>
    </div>
  </div>

  <div class="col-xl-7">
    <div class="card mb-3">
      <div class="card-header">
        <span>Barang yang diminta</span>
        <?php if ($menunggu): ?>
          <span class="pill <?= $cukup ? 'pill-success' : 'pill-danger' ?>"><?= $cukup ? 'Stok mencukupi' : 'Stok tidak cukup' ?></span>
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Barang</th><th class="text-center">Diminta</th><?php if ($menunggu): ?><th class="text-center">Tersedia kini</th><?php endif; ?><th class="text-end">Estimasi</th></tr></thead>
          <tbody>
          <?php foreach ($items as $i):
              $kurang = $i['kondisi'] !== 'baik' || $i['jumlah'] > $i['jumlah_tersedia']; ?>
            <tr>
              <td><a href="<?= url('barang/detail.php?id=' . $i['id_barang']) ?>" class="cell-main text-decoration-none"><?= e($i['nama_barang']) ?></a>
                  <div class="cell-sub mono"><?= e($i['kode_inventaris']) ?></div></td>
              <td class="text-center"><?= (int)$i['jumlah'] ?> <?= e($i['satuan']) ?></td>
              <?php if ($menunggu): ?>
                <td class="text-center <?= $kurang ? 'text-danger fw-semibold' : '' ?>">
                  <?= $i['kondisi'] === 'baik' ? (int)$i['jumlah_tersedia'] : e($i['kondisi']) ?>
                </td>
              <?php endif; ?>
              <td class="text-end"><?= rupiah($i['jumlah'] * $i['biaya_sewa']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot><tr><td colspan="<?= $menunggu ? 3 : 2 ?>" class="text-end fw-semibold">Estimasi total</td><td class="text-end fw-bold"><?= rupiah($estimasi) ?></td></tr></tfoot>
        </table>
      </div>
    </div>

    <?php if ($menunggu && can_edit()): ?>
      <form method="post" class="card">
        <?= csrf_field() ?>
        <div class="card-header"><span>Keputusan Biro PSDO</span></div>
        <div class="card-body">
          <label class="form-label">Catatan untuk peminjam</label>
          <textarea name="catatan_psdo" class="form-control mb-1" rows="2" placeholder="mis. Ambil di PKM jam 10.00 / alasan penolakan"><?= e(old('catatan_psdo')) ?></textarea>
          <div class="form-text mb-3">Catatan ini tampil di halaman cek status. Wajib diisi jika menolak.</div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="gratis" id="gratis" value="1">
            <label class="form-check-label" for="gratis">Peminjam internal HIMA Ilkom (tanpa biaya sewa)</label>
          </div>
        </div>
        <div class="card-footer bg-transparent d-flex flex-wrap gap-2 p-3 px-4">
          <button name="aksi" value="setujui" class="btn btn-success" <?= $cukup ? '' : 'disabled' ?>
                  onclick="return confirm('Setujui pengajuan? Data akan langsung menjadi peminjaman dan stok dikurangi.')">
            <i class="bi bi-check-lg me-1"></i>Setujui &amp; jadikan peminjaman
          </button>
          <button name="aksi" value="tolak" class="btn btn-outline-danger" onclick="return confirm('Tolak pengajuan ini?')">
            <i class="bi bi-x-lg me-1"></i>Tolak
          </button>
          <?php if (!$cukup): ?><span class="small text-danger align-self-center">Stok tidak cukup untuk menyetujui. Tolak atau hubungi peminjam.</span><?php endif; ?>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
