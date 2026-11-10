<?php
require_once 'config.php';
$title = 'Pengeluaran';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tgl = $_POST['tanggal'];
    $kat = trim($_POST['kategori'] ?? '');
    $ket = trim($_POST['keterangan'] ?? '');
    $jml = (float)$_POST['jumlah'];
    if ($jml <= 0) { flash('Jumlah harus > 0','danger'); }
    else {
        $pdo->prepare("INSERT INTO pengeluaran (tanggal,kategori,keterangan,jumlah) VALUES (?,?,?,?)")->execute([$tgl,$kat,$ket,$jml]);
        flash('Pengeluaran berhasil dicatat');
    }
    redirect('pengeluaran.php');
}

if (isset($_GET['hapus'])) {
    $pdo->prepare("DELETE FROM pengeluaran WHERE id=?")->execute([(int)$_GET['hapus']]);
    flash('Pengeluaran dihapus');
    redirect('pengeluaran.php');
}

$bulan = $_GET['bulan'] ?? date('Y-m');
$stmt = $pdo->prepare("SELECT * FROM pengeluaran WHERE DATE_FORMAT(tanggal,'%Y-%m')=? ORDER BY tanggal DESC, id DESC");
$stmt->execute([$bulan]);
$data = $stmt->fetchAll();
$totalBulan = array_sum(array_column($data, 'jumlah'));

include 'header.php';
?>
<h1>💸 Pengeluaran</h1>

<div class="card">
  <h3 style="font-size:15px;margin-bottom:12px">Tambah Pengeluaran</h3>
  <form method="post" class="row">
    <div class="form-group"><label>Tanggal *</label><input type="date" name="tanggal" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
    <div class="form-group"><label>Kategori</label>
      <select name="kategori" class="form-control">
        <option>Bahan Baku</option><option>Listrik</option><option>Gaji</option>
        <option>Operasional</option><option>Perawatan</option><option>Lainnya</option>
      </select>
    </div>
    <div class="form-group"><label>Keterangan</label><input name="keterangan" class="form-control"></div>
    <div class="form-group"><label>Jumlah (Rp) *</label><input type="number" name="jumlah" class="form-control" required min="1"></div>
    <div class="form-group" style="display:flex;align-items:flex-end"><button class="btn btn-primary">Simpan</button></div>
  </form>
</div>

<div class="card">
  <form method="get" class="d-flex mb-2" style="gap:12px;align-items:flex-end">
    <div class="form-group" style="margin-bottom:0"><label>Filter Bulan</label><input type="month" name="bulan" class="form-control" value="<?= $bulan ?>"></div>
    <button class="btn btn-outline">Tampilkan</button>
  </form>
  <div class="d-flex justify-between mb-2" style="padding:12px;background:#fef3c7;border-radius:8px">
    <span>Total Pengeluaran Bulan Ini</span>
    <strong style="color:var(--danger);font-size:18px"><?= rupiah($totalBulan) ?></strong>
  </div>
  <table>
    <thead><tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th class="text-right">Jumlah</th><th></th></tr></thead>
    <tbody>
    <?php if (!$data): ?>
      <tr><td colspan="5" class="text-center text-muted" style="padding:24px">Tidak ada pengeluaran</td></tr>
    <?php endif; ?>
    <?php foreach ($data as $r): ?>
      <tr>
        <td><?= tgl_indo($r['tanggal']) ?></td>
        <td><span class="badge" style="background:#e0e7ff;color:#3730a3"><?= htmlspecialchars($r['kategori'] ?: '-') ?></span></td>
        <td><?= htmlspecialchars($r['keterangan'] ?: '-') ?></td>
        <td class="text-right fw-bold" style="color:var(--danger)"><?= rupiah($r['jumlah']) ?></td>
        <td class="text-right"><a href="?bulan=<?= $bulan ?>&hapus=<?= $r['id'] ?>" onclick="return confirm('Hapus?')" class="btn btn-danger btn-sm">Hapus</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include 'footer.php'; ?>