<?php
require_once 'config.php';
$title = 'Dashboard';

$bulan = date('Y-m');
$stmt = $pdo->prepare("SELECT COUNT(*) c, COALESCE(SUM(total),0) t FROM transaksi WHERE DATE_FORMAT(tanggal,'%Y-%m')=?");
$stmt->execute([$bulan]);
$trxBulan = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM pembayaran WHERE DATE_FORMAT(tanggal,'%Y-%m')=?");
$stmt->execute([$bulan]);
$pemasukanBulan = (float)$stmt->fetchColumn();

$totalHutang = (float)$pdo->query("SELECT COALESCE(SUM(sisa),0) FROM transaksi WHERE status='hutang'")->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM pengeluaran WHERE DATE_FORMAT(tanggal,'%Y-%m')=?");
$stmt->execute([$bulan]);
$pengeluaranBulan = (float)$stmt->fetchColumn();

$labaBersih = $pemasukanBulan - $pengeluaranBulan;

$recent = $pdo->query("SELECT t.*, p.nama pelanggan FROM transaksi t LEFT JOIN pelanggan p ON p.id=t.pelanggan_id ORDER BY t.id DESC LIMIT 8")->fetchAll();
$hutangList = $pdo->query("SELECT t.*, p.nama pelanggan FROM transaksi t LEFT JOIN pelanggan p ON p.id=t.pelanggan_id WHERE t.status='hutang' ORDER BY t.tanggal ASC LIMIT 8")->fetchAll();

include 'header.php';
?>
<h1>Dashboard</h1>
<div class="stat-grid">
  <div class="stat"><div class="stat-label">Transaksi Bulan Ini</div><div class="stat-value blue"><?= $trxBulan['c'] ?></div></div>
  <div class="stat"><div class="stat-label">Pemasukan Bulan Ini</div><div class="stat-value green"><?= rupiah($pemasukanBulan) ?></div></div>
  <div class="stat"><div class="stat-label">Pengeluaran Bulan Ini</div><div class="stat-value red"><?= rupiah($pengeluaranBulan) ?></div></div>
  <div class="stat"><div class="stat-label">Laba Bersih</div><div class="stat-value <?= $labaBersih>=0?'green':'red' ?>"><?= rupiah($labaBersih) ?></div></div>
  <div class="stat"><div class="stat-label">Total Hutang</div><div class="stat-value orange"><?= rupiah($totalHutang) ?></div></div>
</div>

<div class="card">
  <div class="d-flex justify-between mb-2">
    <h3 style="font-size:16px;margin:0">Transaksi Terbaru</h3>
    <a href="transaksi_form.php" class="btn btn-primary btn-sm desktop-only">+ Baru</a>
  </div>

  <!-- Mobile native list -->
  <div class="mobile-only">
    <?php if (!$recent): ?>
      <div class="empty-state"><div class="empty-icon">📋</div><p>Belum ada transaksi</p></div>
    <?php else: foreach ($recent as $r): ?>
      <a href="transaksi_view.php?id=<?= $r['id'] ?>" class="list-card">
        <div class="list-card-top">
          <div>
            <div class="list-card-title"><?= htmlspecialchars($r['no_nota']) ?></div>
            <div class="list-card-meta"><?= tgl_indo($r['tanggal']) ?> · <?= htmlspecialchars($r['pelanggan'] ?? '-') ?></div>
          </div>
          <span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span>
        </div>
        <div class="list-card-row">
          <span class="text-muted" style="font-size:12.5px">Total</span>
          <span class="list-card-amount"><?= rupiah($r['total']) ?></span>
        </div>
      </a>
    <?php endforeach; endif; ?>
  </div>

  <!-- Desktop table -->
  <div class="desktop-only">
    <table>
      <thead><tr><th>No. Nota</th><th>Tanggal</th><th>Pelanggan</th><th class="text-right">Total</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php if (!$recent): ?>
        <tr><td colspan="6" class="text-center text-muted" style="padding:24px">Belum ada transaksi</td></tr>
      <?php endif; ?>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['no_nota']) ?></strong></td>
          <td><?= tgl_indo($r['tanggal']) ?></td>
          <td><?= htmlspecialchars($r['pelanggan'] ?? '-') ?></td>
          <td class="text-right fw-bold"><?= rupiah($r['total']) ?></td>
          <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
          <td><a href="transaksi_view.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">Detail</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h3 style="font-size:16px;margin-bottom:12px">Hutang Belum Lunas</h3>

  <div class="mobile-only">
    <?php if (!$hutangList): ?>
      <div class="empty-state"><div class="empty-icon">🎉</div><p>Tidak ada hutang</p></div>
    <?php else: foreach ($hutangList as $r): ?>
      <a href="transaksi_view.php?id=<?= $r['id'] ?>" class="list-card">
        <div class="list-card-top">
          <div>
            <div class="list-card-title"><?= htmlspecialchars($r['no_nota']) ?></div>
            <div class="list-card-meta"><?= tgl_indo($r['tanggal']) ?> · <?= htmlspecialchars($r['pelanggan'] ?? '-') ?></div>
          </div>
          <span class="badge badge-hutang">hutang</span>
        </div>
        <div class="list-card-row">
          <span class="text-muted" style="font-size:12.5px">Sisa</span>
          <span class="list-card-amount" style="color:var(--danger)"><?= rupiah($r['sisa']) ?></span>
        </div>
      </a>
    <?php endforeach; endif; ?>
  </div>

  <div class="desktop-only">
    <table>
      <thead><tr><th>No. Nota</th><th>Tanggal</th><th>Pelanggan</th><th class="text-right">Total</th><th class="text-right">Sisa</th><th></th></tr></thead>
      <tbody>
      <?php if (!$hutangList): ?>
        <tr><td colspan="6" class="text-center text-muted" style="padding:24px">Tidak ada hutang 🎉</td></tr>
      <?php endif; ?>
      <?php foreach ($hutangList as $r): ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['no_nota']) ?></strong></td>
          <td><?= tgl_indo($r['tanggal']) ?></td>
          <td><?= htmlspecialchars($r['pelanggan'] ?? '-') ?></td>
          <td class="text-right"><?= rupiah($r['total']) ?></td>
          <td class="text-right fw-bold" style="color:var(--danger)"><?= rupiah($r['sisa']) ?></td>
          <td><a href="transaksi_view.php?id=<?= $r['id'] ?>" class="btn btn-warning btn-sm">Bayar</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'footer.php'; ?>
