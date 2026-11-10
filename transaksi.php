<?php
require_once 'config.php';
$title = 'Transaksi';

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(t.no_nota LIKE ? OR p.nama LIKE ? OR p.telepon LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($status !== '') { $where[] = "t.status = ?"; $params[] = $status; }
$sql = "SELECT t.*, p.nama pelanggan, p.telepon FROM transaksi t
        LEFT JOIN pelanggan p ON p.id=t.pelanggan_id"
        . ($where ? ' WHERE '.implode(' AND ', $where) : '')
        . " ORDER BY t.tanggal DESC, t.id DESC LIMIT 200";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$data = $stmt->fetchAll();

include 'header.php';
?>

<style>
.btn-wa-sm{
  background:#25D366;color:#fff;
  padding:6px 12px;font-size:12px;min-height:32px;
  border-radius:16px;display:inline-flex;align-items:center;justify-content:center;
  text-decoration:none;font-weight:700;line-height:1;
  box-shadow:0 1px 3px rgba(37,211,102,.3);
}
.btn-wa-sm:active{filter:brightness(.92)}
</style>

<div class="d-flex justify-between mb-2">
  <h1>Transaksi</h1>
  <a href="transaksi_form.php" class="btn btn-primary desktop-only">+ Transaksi Baru</a>
</div>

<div class="card">
  <form method="get" class="filter-bar">
    <div class="form-group" style="margin-bottom:0;flex:2">
      <label>Cari</label>
      <input name="q" class="form-control" placeholder="No nota / pelanggan / HP" value="<?= htmlspecialchars($q) ?>">
    </div>
    <div class="form-group" style="margin-bottom:0;flex:1">
      <label>Status</label>
      <select name="status" class="form-control">
        <option value="">Semua</option>
        <option value="lunas" <?= $status==='lunas'?'selected':'' ?>>Lunas</option>
        <option value="hutang" <?= $status==='hutang'?'selected':'' ?>>Hutang</option>
      </select>
    </div>
    <div class="form-group" style="margin-bottom:0;display:flex;align-items:flex-end">
      <button class="btn btn-primary" style="width:100%">Filter</button>
    </div>
  </form>

  <!-- Mobile list -->
  <div class="mobile-only" style="margin-top:8px">
    <?php if (!$data): ?>
      <div class="empty-state"><div class="empty-icon">🔍</div><p>Tidak ada data</p></div>
    <?php else: foreach ($data as $r): ?>
      <div class="list-card">
        <a href="transaksi_view.php?id=<?= $r['id'] ?>" style="text-decoration:none;color:inherit;display:block">
          <div class="list-card-top">
            <div>
              <div class="list-card-title"><?= htmlspecialchars($r['no_nota']) ?></div>
              <div class="list-card-meta"><?= tgl_indo($r['tanggal']) ?> · <?= htmlspecialchars($r['pelanggan'] ?? '-') ?></div>
            </div>
            <span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span>
          </div>
          <div class="list-card-row">
            <div>
              <span class="text-muted" style="font-size:12px">Total</span>
              <div class="list-card-amount"><?= rupiah($r['total']) ?></div>
            </div>
            <div class="text-right">
              <span class="text-muted" style="font-size:12px">Sisa</span>
              <div class="list-card-amount" style="<?= $r['sisa']>0?'color:var(--danger)':'' ?>">
                <?= $r['sisa']>0 ? rupiah($r['sisa']) : 'Lunas' ?>
              </div>
            </div>
          </div>
        </a>
        <div class="list-card-actions">
          <?php if (!empty($r['telepon'])): ?>
            <a href="<?= getWaLink($r['telepon'], buildNotaWaMessage($r)) ?>" target="_blank" class="btn-wa-sm">💬 WA</a>
          <?php endif; ?>
          <a href="transaksi_view.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">Detail</a>
          <a href="nota.php?id=<?= $r['id'] ?>" target="_blank" class="btn btn-primary btn-sm">Nota</a>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Desktop table -->
  <div class="desktop-only" style="margin-top:8px">
    <table>
      <thead><tr><th>No. Nota</th><th>Tanggal</th><th>Pelanggan</th><th class="text-right">Total</th><th class="text-right">Sisa</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php if (!$data): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:24px">Tidak ada data</td></tr>
      <?php endif; ?>
      <?php foreach ($data as $r): ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['no_nota']) ?></strong></td>
          <td><?= tgl_indo($r['tanggal']) ?></td>
          <td><?= htmlspecialchars($r['pelanggan'] ?? '-') ?></td>
          <td class="text-right"><?= rupiah($r['total']) ?></td>
          <td class="text-right"><?= $r['sisa']>0 ? '<span style="color:var(--danger);font-weight:600">'.rupiah($r['sisa']).'</span>' : '<span class="text-muted">Lunas</span>' ?></td>
          <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
          <td class="text-right">
            <?php if (!empty($r['telepon'])): ?>
              <a href="<?= getWaLink($r['telepon'], buildNotaWaMessage($r)) ?>" target="_blank" class="btn-wa-sm" title="Kirim WA">💬 WA</a>
            <?php endif; ?>
            <a href="transaksi_view.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">Detail</a>
            <a href="nota.php?id=<?= $r['id'] ?>" target="_blank" class="btn btn-primary btn-sm">Nota</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'footer.php'; ?>
