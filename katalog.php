<?php
// Halaman publik: daftar barang HIMA yang bisa dipinjam (tanpa login)
require_once __DIR__ . '/includes/auth.php';

$q   = trim($_GET['q'] ?? '');
$kat = (int)($_GET['kategori'] ?? 0);
$ada = !empty($_GET['tersedia']);

$where  = ["b.kondisi = 'baik'"];   // barang rusak/hilang tidak ditampilkan
$params = [];
if ($q !== '') { $where[] = "b.nama_barang LIKE ?"; $params[] = "%$q%"; }
if ($kat)      { $where[] = "b.id_kategori = ?";    $params[] = $kat; }
if ($ada)      { $where[] = "b.jumlah_tersedia > 0"; }

$stmt = $pdo->prepare(
    "SELECT b.id_barang, b.nama_barang, b.satuan, b.jumlah_total, b.jumlah_tersedia, b.biaya_sewa, b.keterangan,
            k.nama_kategori
     FROM barang b JOIN kategori k ON k.id_kategori = b.id_kategori
     WHERE " . implode(' AND ', $where) . "
     ORDER BY (b.jumlah_tersedia > 0) DESC, k.nama_kategori, b.nama_barang"
);
$stmt->execute($params);
$barang = $stmt->fetchAll();

$kategori = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori")->fetchAll();

$ikonKategori = [
    'Elektronik' => 'bi-plug', 'Audio' => 'bi-speaker', 'Perlengkapan Acara' => 'bi-flag',
    'Furnitur' => 'bi-lamp', 'Alat Tulis Kantor' => 'bi-pencil',
];

$title     = 'Katalog Barang';
$pubActive = 'katalog';
require __DIR__ . '/includes/public_header.php';
?>

<section class="pub-hero mb-4">
  <div>
    <h1>Pinjam perlengkapan HIMA Ilmu Komputer</h1>
    <p class="mb-0">Lihat ketersediaan barang secara langsung, lalu ajukan peminjaman tanpa harus menunggu balasan chat.</p>
  </div>
  <a href="ajukan.php" class="btn btn-light btn-lg fw-semibold"><i class="bi bi-send me-2"></i>Ajukan peminjaman</a>
</section>

<div class="pub-steps mb-4">
  <div><span>1</span>Pilih barang di katalog</div>
  <div><span>2</span>Isi form pengajuan</div>
  <div><span>3</span>Tunggu persetujuan Biro PSDO</div>
  <div><span>4</span>Ambil barang di PKM</div>
</div>

<form class="card toolbar mb-3" method="get">
  <input type="search" name="q" class="form-control search" placeholder="Cari barang…" value="<?= e($q) ?>">
  <select name="kategori" class="form-select" onchange="this.form.submit()">
    <option value="">Semua kategori</option>
    <?php foreach ($kategori as $k): ?>
      <option value="<?= (int)$k['id_kategori'] ?>" <?= sel($kat, $k['id_kategori']) ?>><?= e($k['nama_kategori']) ?></option>
    <?php endforeach; ?>
  </select>
  <div class="form-check d-flex align-items-center gap-2 m-0">
    <input class="form-check-input m-0" type="checkbox" name="tersedia" value="1" id="cek-ada" <?= $ada ? 'checked' : '' ?> onchange="this.form.submit()">
    <label class="form-check-label small" for="cek-ada">Hanya yang tersedia</label>
  </div>
  <button class="btn btn-light"><i class="bi bi-search"></i></button>
</form>

<div class="row g-3">
  <?php foreach ($barang as $b):
      $tersedia = (int)$b['jumlah_tersedia'];
      $ikon = $ikonKategori[$b['nama_kategori']] ?? 'bi-box-seam'; ?>
    <div class="col-sm-6 col-lg-4 col-xl-3">
      <div class="card h-100 item-card <?= $tersedia ? '' : 'habis' ?>">
        <div class="card-body d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <span class="stat-icon tone-blue"><i class="bi <?= $ikon ?>"></i></span>
            <?php if ($tersedia): ?>
              <span class="pill pill-success"><?= $tersedia ?> tersedia</span>
            <?php else: ?>
              <span class="pill pill-secondary">Sedang dipinjam</span>
            <?php endif; ?>
          </div>
          <div class="cell-sub mb-1"><?= e($b['nama_kategori']) ?></div>
          <h3 class="h6 fw-bold mb-1"><?= e($b['nama_barang']) ?></h3>
          <div class="cell-sub mb-3">dari <?= (int)$b['jumlah_total'] ?> <?= e($b['satuan']) ?></div>
          <div class="mt-auto d-flex justify-content-between align-items-center">
            <div>
              <?php if ($b['biaya_sewa'] > 0): ?>
                <span class="fw-bold"><?= rupiah($b['biaya_sewa']) ?></span><span class="cell-sub"> / <?= e($b['satuan']) ?></span>
              <?php else: ?>
                <span class="fw-semibold text-success">Gratis</span>
              <?php endif; ?>
            </div>
            <?php if ($tersedia): ?>
              <a href="ajukan.php?barang=<?= (int)$b['id_barang'] ?>" class="btn btn-sm btn-primary">Ajukan</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$barang): ?>
    <div class="col-12"><div class="card"><div class="empty"><i class="bi bi-search d-block fs-3 mb-1"></i>Barang tidak ditemukan.</div></div></div>
  <?php endif; ?>
</div>

<p class="small text-muted mt-4 mb-0">
  <i class="bi bi-info-circle me-1"></i>Ketersediaan diperbarui otomatis setiap ada peminjaman dan pengembalian.
  Biaya sewa berlaku untuk peminjam dari luar HIMA Ilmu Komputer.
</p>

<?php require __DIR__ . '/includes/public_footer.php'; ?>
