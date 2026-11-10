<?php
require_once 'config.php';
$title = 'Laporan';

$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');

$stmt = $pdo->prepare("SELECT t.*, p.nama pelanggan FROM transaksi t LEFT JOIN pelanggan p ON p.id=t.pelanggan_id WHERE t.tanggal BETWEEN ? AND ? ORDER BY t.tanggal");
$stmt->execute([$dari, $sampai]);
$transaksi = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM pengeluaran WHERE tanggal BETWEEN ? AND ? ORDER BY tanggal");
$stmt->execute([$dari, $sampai]);
$pengeluaran = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM pembayaran WHERE tanggal BETWEEN ? AND ?");
$stmt->execute([$dari, $sampai]);
$totalPemasukan = (float)$stmt->fetchColumn();

$totalPengeluaran = array_sum(array_column($pengeluaran, 'jumlah'));
$laba = $totalPemasukan - $totalPengeluaran;
$totalNilaiTransaksi = array_sum(array_column($transaksi, 'total'));
$totalPiutangBaru = array_sum(array_column($transaksi, 'sisa'));

include 'header.php';
?>
<h1>📈 Laporan Keuangan</h1>

<div class="card">
  <form method="get" class="row">
    <div class="form-group"><label>Dari Tanggal</label><input type="date" name="dari" class="form-control" value="<?= $dari ?>"></div>
    <div class="form-group"><label>Sampai Tanggal</label><input type="date" name="sampai" class="form-control" value="<?= $sampai ?>"></div>
    <div class="form-group" style="display:flex;align-items:flex-end"><button class="btn btn-primary">Tampilkan</button></div>
  </form>
</div>

<div class="stat-grid">
  <div class="stat"><div class="stat-label">Nilai Transaksi</div><div class="stat-value blue"><?= rupiah($totalNilaiTransaksi) ?></div><div class="text-muted" style="font-size:12px;margin-top:4px"><?= count($transaksi) ?> transaksi</div></div>
  <div class="stat"><div class="stat-label">Pemasukan (Kas)</div><div class="stat-value green"><?= rupiah($totalPemasukan) ?></div></div>
  <div class="stat"><div class="stat-label">Pengeluaran</div><div class="stat-value red"><?= rupiah($totalPengeluaran) ?></div></div>
  <div class="stat"><div class="stat-label">Laba Bersih</div><div class="stat-value <?= $laba>=0?'green':'red' ?>"><?= rupiah($laba) ?></div></div>
  <div class="stat"><div class="stat-label">Piutang Baru</div><div class="stat-value orange"><?= rupiah($totalPiutangBaru) ?></div></div>
</div>

<div class="card">
  <h3 style="font-size:15px;margin-bottom:12px">Detail Transaksi</h3>
  <table>
    <thead><tr><th>Tanggal</th><th>No. Nota</th><th>Pelanggan</th><th class="text-right">Total</th><th class="text-right">Dibayar</th><th class="text-right">Sisa</th><th>Status</th></tr></thead>
    <tbody>
    <?php if (!$transaksi): ?><tr><td colspan="7" class="text-center text-muted" style="padding:24px">Tidak ada transaksi</td></tr><?php endif; ?>
    <?php foreach ($transaksi as $t): ?>
      <tr>
        <td><?= tgl_indo($t['tanggal']) ?></td>
        <td><?= htmlspecialchars($t['no_nota']) ?></td>
        <td><?= htmlspecialchars($t['pelanggan'] ?? '-') ?></td>
        <td class="text-right"><?= rupiah($t['total']) ?></td>
        <td class="text-right" style="color:var(--success)"><?= rupiah($t['bayar']) ?></td>
        <td class="text-right" style="color:var(--danger)"><?= rupiah($t['sisa']) ?></td>
        <td><span class="badge badge-<?= $t['status'] ?>"><?= $t['status'] ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h3 style="font-size:15px;margin-bottom:12px">Detail Pengeluaran</h3>
  <table>
    <thead><tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th class="text-right">Jumlah</th></tr></thead>
    <tbody>
    <?php if (!$pengeluaran): ?><tr><td colspan="4" class="text-center text-muted" style="padding:24px">Tidak ada pengeluaran</td></tr><?php endif; ?>
    <?php foreach ($pengeluaran as $p): ?>
      <tr>
        <td><?= tgl_indo($p['tanggal']) ?></td>
        <td><?= htmlspecialchars($p['kategori'] ?: '-') ?></td>
        <td><?= htmlspecialchars($p['keterangan'] ?: '-') ?></td>
        <td class="text-right fw-bold" style="color:var(--danger)"><?= rupiah($p['jumlah']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include 'footer.php'; ?>