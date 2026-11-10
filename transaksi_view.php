<?php
require_once 'config.php';
$id = (int)($_GET['id'] ?? 0);
$title = 'Detail Transaksi';

$stmt = $pdo->prepare("SELECT t.*, p.nama pelanggan, p.telepon, p.alamat FROM transaksi t LEFT JOIN pelanggan p ON p.id=t.pelanggan_id WHERE t.id=?");
$stmt->execute([$id]);
$trx = $stmt->fetch();
if (!$trx) { flash('Transaksi tidak ditemukan', 'danger'); redirect('transaksi.php'); }

// Handle add payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $jumlah = (float)$_POST['jumlah'];
    $tgl    = $_POST['tanggal'];
    $ket    = trim($_POST['keterangan'] ?? '');
    if ($jumlah <= 0) {
        flash('Jumlah pembayaran harus > 0', 'danger');
    } else {
        $pdo->prepare("INSERT INTO pembayaran (transaksi_id,tanggal,jumlah,keterangan) VALUES (?,?,?,?)")
            ->execute([$id, $tgl, $jumlah, $ket]);
        recalcTransaksi($pdo, $id);
        flash('Pembayaran berhasil dicatat');
    }
    redirect('transaksi_view.php?id='.$id);
}

// Handle delete payment
if (isset($_GET['del_payment'])) {
    $pid = (int)$_GET['del_payment'];
    $pdo->prepare("DELETE FROM pembayaran WHERE id=? AND transaksi_id=?")->execute([$pid, $id]);
    recalcTransaksi($pdo, $id);
    flash('Pembayaran dihapus');
    redirect('transaksi_view.php?id='.$id);
}

// Handle delete transaksi
if (isset($_GET['hapus'])) {
    $pdo->prepare("DELETE FROM transaksi WHERE id=?")->execute([$id]);
    flash('Transaksi dihapus');
    redirect('transaksi.php');
}

$stmt = $pdo->prepare("SELECT * FROM transaksi_item WHERE transaksi_id=? ORDER BY id");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM pembayaran WHERE transaksi_id=? ORDER BY tanggal, id");
$stmt->execute([$id]);
$pembayaran = $stmt->fetchAll();

$notaPublicUrl = getNotaPublicUrl($trx);

include 'header.php';
?>

<div class="d-flex justify-between mb-2">
  <h1>🧾 <?= htmlspecialchars($trx['no_nota']) ?></h1>
  <div class="d-flex">
    <?php if (!empty($trx['telepon'])): ?>
      <a href="<?= getWaLink($trx['telepon'], buildNotaWaMessage($trx, $items)) ?>"
         target="_blank"
         class="btn"
         style="background:#25D366;color:#fff;font-weight:700;"
         title="Kirim nota via WhatsApp ke <?= htmlspecialchars($trx['pelanggan'] ?? '') ?>">
        💬 Kirim WA
      </a>
    <?php elseif (!empty($trx['pelanggan_id'])): ?>
      <a href="pelanggan.php?edit=<?= $trx['pelanggan_id'] ?>"
         class="btn btn-outline"
         title="Pelanggan belum punya nomor HP">
        📵 Isi No. HP
      </a>
    <?php endif; ?>
    <button type="button" class="btn btn-outline"
            onclick="copyPublicLink(this)"
            title="Salin link publik nota (bisa dibuka tanpa login)">
      🔗 Salin Link
    </button>
    <a href="transaksi_form.php?id=<?= $id ?>" class="btn btn-outline">✏️ Edit</a>
    <a href="nota.php?id=<?= $id ?>" target="_blank" class="btn btn-primary">🖨️ Cetak Nota</a>
    <a href="?id=<?= $id ?>&hapus=1" onclick="return confirm('Hapus transaksi ini?')" class="btn btn-danger">🗑️ Hapus</a>
  </div>
</div>

<div class="card">
  <div class="row">
    <div><div class="stat-label">Tanggal</div><div class="fw-bold"><?= tgl_indo($trx['tanggal']) ?></div></div>
    <div><div class="stat-label">Pelanggan</div><div class="fw-bold"><?= htmlspecialchars($trx['pelanggan'] ?? 'Umum') ?></div></div>
    <div><div class="stat-label">Telepon</div><div><?= htmlspecialchars($trx['telepon'] ?? '-') ?></div></div>
    <div><div class="stat-label">Status</div><span class="badge badge-<?= $trx['status'] ?>"><?= $trx['status'] ?></span></div>
  </div>
  <?php if ($trx['catatan']): ?><div class="mt-2 text-muted">Catatan: <?= htmlspecialchars($trx['catatan']) ?></div><?php endif; ?>
</div>

<div class="card">
  <h3 style="font-size:15px;margin-bottom:12px">Item Pengerjaan</h3>
  <table>
    <thead><tr><th>Nama</th><th>Bahan</th><th>Ukuran</th><th class="text-center">Qty</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr>
        <td><strong><?= htmlspecialchars($it['nama_item']) ?></strong></td>
        <td><?= htmlspecialchars($it['bahan'] ?: '-') ?></td>
        <td><?= ($it['panjang']>0||$it['lebar']>0) ? $it['panjang'].' × '.$it['lebar'].' cm' : '-' ?></td>
        <td class="text-center"><?= $it['qty'] ?></td>
        <td class="text-right"><?= rupiah($it['harga']) ?></td>
        <td class="text-right fw-bold"><?= rupiah($it['subtotal']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div style="max-width:320px;margin-left:auto;margin-top:16px">
    <div class="d-flex justify-between" style="padding:6px 0"><span>Subtotal</span><strong><?= rupiah($trx['subtotal']) ?></strong></div>
    <div class="d-flex justify-between" style="padding:6px 0"><span>Diskon</span><strong>- <?= rupiah($trx['diskon']) ?></strong></div>
    <div class="d-flex justify-between" style="padding:8px 0;border-top:1px solid var(--border);font-size:18px"><span>Total</span><strong><?= rupiah($trx['total']) ?></strong></div>
    <div class="d-flex justify-between" style="padding:6px 0;color:var(--success)"><span>Dibayar</span><strong><?= rupiah($trx['bayar']) ?></strong></div>
    <div class="d-flex justify-between" style="padding:6px 0;color:<?= $trx['sisa']>0?'var(--danger)':'var(--success)' ?>"><span>Sisa</span><strong><?= rupiah($trx['sisa']) ?></strong></div>
  </div>
</div>

<?php if ($trx['status'] === 'hutang'): ?>
<div class="card">
  <h3 style="font-size:15px;margin-bottom:12px">💰 Tambah Pembayaran</h3>
  <form method="post" class="row">
    <input type="hidden" name="add_payment" value="1">
    <div class="form-group"><label>Tanggal</label><input type="date" name="tanggal" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
    <div class="form-group"><label>Jumlah Bayar</label><input type="number" name="jumlah" class="form-control" required min="1" max="<?= $trx['sisa'] ?>"></div>
    <div class="form-group"><label>Keterangan</label><input name="keterangan" class="form-control" placeholder="opsional"></div>
    <div class="form-group" style="display:flex;align-items:flex-end"><button class="btn btn-success">+ Catat Pembayaran</button></div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h3 style="font-size:15px;margin-bottom:12px">Riwayat Pembayaran</h3>
  <table>
    <thead><tr><th>Tanggal</th><th>Keterangan</th><th class="text-right">Jumlah</th><th></th></tr></thead>
    <tbody>
    <?php if (!$pembayaran): ?>
      <tr><td colspan="4" class="text-center text-muted" style="padding:16px">Belum ada pembayaran</td></tr>
    <?php endif; ?>
    <?php foreach ($pembayaran as $p): ?>
      <tr>
        <td><?= tgl_indo($p['tanggal']) ?></td>
        <td><?= htmlspecialchars($p['keterangan'] ?: '-') ?></td>
        <td class="text-right fw-bold" style="color:var(--success)"><?= rupiah($p['jumlah']) ?></td>
        <td class="text-right"><a href="?id=<?= $id ?>&del_payment=<?= $p['id'] ?>" onclick="return confirm('Hapus pembayaran ini?')" class="btn btn-danger btn-sm">Hapus</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
function copyPublicLink(btn) {
  const url = <?= json_encode($notaPublicUrl) ?>;
  const orig = btn.innerHTML;
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(url).then(() => {
      btn.innerHTML = '✓ Tersalin!';
      setTimeout(() => btn.innerHTML = orig, 1500);
    }).catch(() => prompt('Salin link berikut:', url));
  } else {
    const ta = document.createElement('textarea');
    ta.value = url;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      btn.innerHTML = '✓ Tersalin!';
      setTimeout(() => btn.innerHTML = orig, 1500);
    } catch (e) {
      prompt('Salin link berikut:', url);
    }
    document.body.removeChild(ta);
  }
}
</script>

<?php include 'footer.php'; ?>