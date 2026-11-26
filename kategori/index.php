<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['psdo']);

$errors = [];
$editId = (int)($_GET['edit'] ?? 0);

if (is_post()) {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $id = (int)$_POST['id'];
        $s = $pdo->prepare("SELECT COUNT(*) FROM barang WHERE id_kategori = ?");
        $s->execute([$id]);
        if ($s->fetchColumn() > 0) {
            flash('danger', 'Kategori masih dipakai oleh barang, tidak bisa dihapus.');
        } else {
            $pdo->prepare("DELETE FROM kategori WHERE id_kategori = ?")->execute([$id]);
            flash('success', 'Kategori dihapus.');
        }
        redirect('kategori/index.php');
    }

    // simpan (tambah / ubah)
    $id    = (int)($_POST['id'] ?? 0);
    $nama  = trim($_POST['nama_kategori'] ?? '');
    $umur  = (int)($_POST['umur_pakai_tahun'] ?? 0);

    if ($nama === '')            $errors[] = 'Nama kategori wajib diisi.';
    if ($umur < 1 || $umur > 50) $errors[] = 'Umur pakai harus antara 1 sampai 50 tahun.';

    $s = $pdo->prepare("SELECT COUNT(*) FROM kategori WHERE nama_kategori = ? AND id_kategori <> ?");
    $s->execute([$nama, $id]);
    if ($s->fetchColumn() > 0) $errors[] = 'Nama kategori sudah ada.';

    if (!$errors) {
        if ($id) {
            $pdo->prepare("UPDATE kategori SET nama_kategori = ?, umur_pakai_tahun = ? WHERE id_kategori = ?")
                ->execute([$nama, $umur, $id]);
            flash('success', 'Kategori diperbarui.');
        } else {
            $pdo->prepare("INSERT INTO kategori (nama_kategori, umur_pakai_tahun) VALUES (?, ?)")
                ->execute([$nama, $umur]);
            flash('success', 'Kategori ditambahkan.');
        }
        redirect('kategori/index.php');
    }
    $editId = $id;
}

$daftar = $pdo->query(
    "SELECT k.*, (SELECT COUNT(*) FROM barang b WHERE b.id_kategori = k.id_kategori) AS jml_barang
     FROM kategori k ORDER BY k.nama_kategori"
)->fetchAll();

$edit = null;
if ($editId) {
    foreach ($daftar as $k) if ((int)$k['id_kategori'] === $editId) $edit = $k;
}

$title    = 'Kategori Barang';
$subtitle = 'Jenis barang beserta standar umur pakainya, dipakai untuk peringatan penggantian.';
$active   = 'kategori';
require __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 align-items-start">
  <div class="col-lg-8">
    <div class="card">
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Kategori</th><th class="text-center">Umur pakai</th><th class="text-center">Jumlah barang</th><th class="text-end">Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($daftar as $k): ?>
            <tr>
              <td class="cell-main"><?= e($k['nama_kategori']) ?></td>
              <td class="text-center"><?= (int)$k['umur_pakai_tahun'] ?> tahun</td>
              <td class="text-center"><?= (int)$k['jml_barang'] ?></td>
              <td class="text-end text-nowrap">
                <a href="?edit=<?= (int)$k['id_kategori'] ?>" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                <?= post_button('', '<i class="bi bi-trash"></i>', 'btn btn-sm btn-light text-danger',
                                'Hapus kategori ' . $k['nama_kategori'] . '?', ['aksi' => 'hapus', 'id' => $k['id_kategori']]) ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$daftar): ?><tr><td colspan="4" class="empty">Belum ada kategori.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><span><?= $edit ? 'Ubah kategori' : 'Tambah kategori' ?></span></div>
      <div class="card-body">
        <?= show_errors($errors) ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)($edit['id_kategori'] ?? 0) ?>">
          <div class="mb-3">
            <label class="form-label">Nama kategori</label>
            <input name="nama_kategori" class="form-control" maxlength="50" required
                   value="<?= e(old('nama_kategori', $edit['nama_kategori'] ?? '')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Standar umur pakai (tahun)</label>
            <input type="number" name="umur_pakai_tahun" class="form-control" min="1" max="50" required
                   value="<?= e(old('umur_pakai_tahun', $edit['umur_pakai_tahun'] ?? 3)) ?>">
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary">Simpan</button>
            <?php if ($edit): ?><a href="index.php" class="btn btn-light">Batal</a><?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
