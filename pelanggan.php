<?php
require_once 'config.php';
$title = 'Pelanggan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama']);
    $telp = trim($_POST['telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    if ($nama === '') { flash('Nama wajib diisi', 'danger'); redirect('pelanggan.php'); }
    if ($id > 0) {
        $pdo->prepare("UPDATE pelanggan SET nama=?,telepon=?,alamat=? WHERE id=?")->execute([$nama,$telp,$alamat,$id]);
        flash('Pelanggan berhasil diupdate');
    } else {
        $pdo->prepare("INSERT INTO pelanggan (nama,telepon,alamat) VALUES (?,?,?)")->execute([$nama,$telp,$alamat]);
        flash('Pelanggan berhasil ditambahkan');
    }
    redirect('pelanggan.php');
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE pelanggan_id=?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) { flash('Tidak bisa hapus, pelanggan masih memiliki transaksi', 'danger'); }
    else { $pdo->prepare("DELETE FROM pelanggan WHERE id=?")->execute([$id]); flash('Pelanggan dihapus'); }
    redirect('pelanggan.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM pelanggan WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}

$pelanggan = $pdo->query("
  SELECT p.*, (SELECT COUNT(*) FROM transaksi WHERE pelanggan_id=p.id) trx_count,
    (SELECT COALESCE(SUM(sisa),0) FROM transaksi WHERE pelanggan_id=p.id AND status='hutang') total_hutang
  FROM pelanggan p ORDER BY p.nama
")->fetchAll();

include 'header.php';
?>
<h1>👥 Pelanggan</h1>
<div class="card">
  <h3 style="font-size:15px;margin-bottom:12px"><?= $edit ? 'Edit Pelanggan' : 'Tambah Pelanggan' ?></h3>
  <form method="post">
    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
    <div class="row">
      <div class="form-group"><label>Nama *</label><input name="nama" class="form-control" required value="<?= htmlspecialchars($edit['nama'] ?? '') ?>"></div>
      <div class="form-group"><label>Telepon</label><input name="telepon" class="form-control" value="<?= htmlspecialchars($edit['telepon'] ?? '') ?>"></div>
      <div class="form-group"><label>Alamat</label><input name="alamat" class="form-control" value="<?= htmlspecialchars($edit['alamat'] ?? '') ?>"></div>
    </div>
    <button class="btn btn-primary"><?= $edit ? 'Update' : 'Simpan' ?></button>
    <?php if ($edit): ?><a href="pelanggan.php" class="btn btn-outline">Batal</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <table>
    <thead><tr><th>Nama</th><th>Telepon</th><th>Alamat</th><th class="text-center">Transaksi</th><th class="text-right">Hutang</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pelanggan as $p): ?>
      <tr>
        <td><strong><?= htmlspecialchars($p['nama']) ?></strong></td>
        <td><?= htmlspecialchars($p['telepon'] ?: '-') ?></td>
        <td><?= htmlspecialchars($p['alamat'] ?: '-') ?></td>
        <td class="text-center"><?= $p['trx_count'] ?></td>
        <td class="text-right"><?= $p['total_hutang']>0 ? '<span style="color:var(--danger);font-weight:600">'.rupiah($p['total_hutang']).'</span>' : '-' ?></td>
        <td class="text-right">
          <a href="?edit=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
          <a href="?hapus=<?= $p['id'] ?>" onclick="return confirm('Hapus pelanggan?')" class="btn btn-danger btn-sm">Hapus</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include 'footer.php'; ?>