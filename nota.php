<?php
require_once 'config.php';

/* Anti-cache: token & status bisa berubah, jangan biarkan browser serve versi lama */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT t.*, p.nama pelanggan, p.telepon, p.alamat FROM transaksi t LEFT JOIN pelanggan p ON p.id=t.pelanggan_id WHERE t.id=?");
$stmt->execute([$id]);
$trx = $stmt->fetch();
if (!$trx) {
    http_response_code(404);
    die('Transaksi tidak ditemukan');
}

/* ============================================
   AUTH: login ATAU token valid
   ============================================
   - Jika ?token= ada di URL → WAJIB valid (walau user sedang login).
     Tujuan: konsistensi behavior, memudahkan testing, dan mencegah
     kesan "token tidak berfungsi" saat admin masih login.
   - Jika ?token= tidak ada → butuh login.
   ============================================ */
$isLoggedIn   = !empty($_SESSION['user_id']);
$hasToken     = isset($_GET['token']) && $_GET['token'] !== '';
$isValidToken = $hasToken && verifyNotaToken($trx, (string)$_GET['token']);

// Token ada tapi tidak valid → tampilkan error page yang jelas
if ($hasToken && !$isValidToken) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Link Tidak Valid · <?= htmlspecialchars(NAMA_TOKO) ?></title>
    <style>
      *{box-sizing:border-box;margin:0;padding:0}
      body{
        font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;
        background:#f4f4f5;min-height:100vh;display:grid;place-items:center;padding:20px;
        color:#18181b;line-height:1.6;
      }
      .box{
        background:#fff;padding:40px 32px;border-radius:16px;max-width:420px;width:100%;
        text-align:center;box-shadow:0 10px 40px rgba(0,0,0,.08);
      }
      .icon{
        width:64px;height:64px;border-radius:50%;background:#fee2e2;
        display:grid;place-items:center;margin:0 auto 20px;font-size:32px;
      }
      h1{font-size:20px;font-weight:700;color:#18181b;margin-bottom:10px}
      p{font-size:13.5px;color:#71717a;margin-bottom:24px;line-height:1.7}
      .btn{
        display:inline-flex;align-items:center;justify-content:center;
        padding:11px 22px;border-radius:8px;background:#18181b;color:#fff;
        text-decoration:none;font-size:13.5px;font-weight:600;transition:.15s;
      }
      .btn:hover{background:#27272a}
      .note{
        margin-top:20px;padding-top:20px;border-top:1px solid #f4f4f5;
        font-size:11.5px;color:#a1a1aa;line-height:1.6;
      }
    </style>
    </head>
    <body>
      <div class="box">
        <div class="icon">🔒</div>
        <h1>Link Tidak Valid</h1>
        <p>
          Link nota ini tidak valid atau sudah kedaluwarsa.<br>
          Pastikan Anda menyalin URL lengkap termasuk <strong>token</strong>-nya.
        </p>
        <a href="<?= $isLoggedIn ? 'index.php' : 'login.php' ?>" class="btn">
          <?= $isLoggedIn ? '← Kembali ke Dashboard' : 'Ke Halaman Login' ?>
        </a>
        <div class="note">
          Jika Anda menerima link ini dari toko, minta link baru kepada mereka.<br>
          <strong><?= htmlspecialchars(NAMA_TOKO) ?></strong>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// Tanpa token + belum login → redirect ke login
if (!$isLoggedIn && !$hasToken) {
    header('Location: login.php');
    exit;
}

/* Link publik nota (untuk QR) */
$notaPublicUrl = getNotaPublicUrl($trx);

$stmt = $pdo->prepare("SELECT * FROM transaksi_item WHERE transaksi_id=? ORDER BY id");
$stmt->execute([$id]);
$items = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT * FROM pembayaran WHERE transaksi_id=? ORDER BY tanggal, id");
$stmt->execute([$id]);
$pembayaran = $stmt->fetchAll();

$autoDownload = !empty($_GET['auto']);

/* ============ WATERMARK CONFIG ============ */
$wmText      = trim($settings['watermark_text'] ?? '');
$wmOpacity   = max(0.03, min(0.20, ((int)($settings['watermark_opacity'] ?? 6)) / 100));
$wmLunasOnly = !empty($settings['watermark_lunas_only']);
$showWatermark = $wmText !== '' && (!$wmLunasOnly || $trx['status'] === 'lunas');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nota <?= htmlspecialchars($trx['no_nota']) ?> · <?= htmlspecialchars(NAMA_TOKO) ?></title>
<style>
/* ============ RESET ============ */
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html{-webkit-text-size-adjust:100%}

body{
  font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Inter',system-ui,sans-serif;
  background:#f4f4f5;
  padding:32px 16px;
  color:#18181b;
  line-height:1.6;
  font-size:14px;
  -webkit-font-smoothing:antialiased;
}

/* ============ TOOLBAR ============ */
.toolbar{
  max-width:720px;margin:0 auto 20px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;
}
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  padding:10px 18px;border-radius:8px;border:none;cursor:pointer;
  font-size:13px;font-weight:600;text-decoration:none;transition:.15s;
  font-family:inherit;white-space:nowrap;
}
.btn-ghost{background:#fff;color:#52525b;border:1px solid #e4e4e7}
.btn-ghost:hover{background:#fafafa;border-color:#d4d4d8}
.btn-primary{background:#18181b;color:#fff}
.btn-primary:hover{background:#27272a}
.btn:disabled{opacity:.6;cursor:wait}

/* ============ NOTA CARD ============ */
.nota{
  max-width:720px;margin:0 auto;background:#fff;
  padding:56px 56px 40px;
  box-shadow:0 1px 2px rgba(0,0,0,.04),0 4px 16px rgba(0,0,0,.04);
  position:relative;
  overflow:hidden;
}

/* ============ WATERMARK ============ */
.watermark{
  position:absolute;
  top:50%;left:50%;
  transform:translate(-50%,-50%) rotate(-25deg);
  font-size:96px;
  font-weight:900;
  letter-spacing:0.1em;
  text-transform:uppercase;
  color:#18181b;
  line-height:1;
  text-align:center;
  max-width:90%;
  word-break:break-word;
  pointer-events:none;
  user-select:none;
  white-space:nowrap;
  z-index:0;
  font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Inter',system-ui,sans-serif;
  -webkit-print-color-adjust:exact;
  print-color-adjust:exact;
}

.nota > *:not(.watermark){
  position:relative;
  z-index:1;
}

/* ============ HEADER ============ */
.header{
  display:flex;justify-content:space-between;align-items:flex-start;
  gap:32px;margin-bottom:32px;flex-wrap:wrap;
}
.brand-block{display:flex;align-items:flex-start;gap:16px;flex:1;min-width:200px}
.brand-logo{
  max-width:56px;max-height:56px;object-fit:contain;
  flex-shrink:0;
}
.brand-info h1{
  font-size:20px;font-weight:700;letter-spacing:-0.02em;
  line-height:1.15;color:#18181b;margin-bottom:6px;
}
.brand-info p{
  font-size:12.5px;color:#71717a;line-height:1.55;
}

.invoice-block{text-align:right;flex-shrink:0}
.invoice-label{
  font-size:9px;letter-spacing:0.25em;text-transform:uppercase;
  color:#a1a1aa;font-weight:600;margin-bottom:4px;
}
.invoice-no{
  font-size:20px;font-weight:700;letter-spacing:-0.02em;
  color:#18181b;line-height:1.15;margin-bottom:6px;
  word-break:break-word;
}
.invoice-date{font-size:12.5px;color:#71717a}

/* ============ DIVIDER ============ */
.divider{height:1px;background:#e4e4e7;margin:0 0 28px}

/* ============ META ============ */
.meta{
  display:grid;grid-template-columns:1fr auto;gap:24px;
  margin-bottom:32px;align-items:flex-start;
}
.section-label{
  font-size:9px;letter-spacing:0.25em;text-transform:uppercase;
  color:#a1a1aa;font-weight:600;margin-bottom:10px;
}
.meta-name{
  font-size:14.5px;font-weight:600;color:#18181b;
  margin-bottom:4px;line-height:1.4;
}
.meta-text{font-size:12.5px;color:#71717a;line-height:1.6}

.status-badge{
  display:inline-flex;align-items:center;gap:7px;
  padding:6px 14px;border-radius:20px;
  font-size:10px;font-weight:700;
  text-transform:uppercase;letter-spacing:0.1em;
  border:1px solid;
}
.status-badge.lunas{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}
.status-badge.hutang{background:#fffbeb;color:#b45309;border-color:#fde68a}
.status-badge .dot{width:6px;height:6px;border-radius:50%;background:currentColor}

/* ============ TABLE ============ */
.items-table{width:100%;border-collapse:collapse;margin-bottom:8px;font-size:13px}
.items-table thead th{
  text-align:left;font-size:9.5px;letter-spacing:0.15em;
  text-transform:uppercase;color:#a1a1aa;
  font-weight:600;padding:12px 0;
  border-bottom:1px solid #18181b;
}
.items-table thead th.num{text-align:right;width:60px}
.items-table thead th.price{text-align:right;width:110px}
.items-table thead th.total{text-align:right;width:120px}

.items-table tbody td{
  padding:16px 0;
  border-bottom:1px solid #f4f4f5;
  vertical-align:top;
}
.items-table tbody tr:last-child td{border-bottom:none}
.items-table tbody td.num{text-align:right;color:#71717a;font-variant-numeric:tabular-nums}
.items-table tbody td.price{text-align:right;color:#52525b;font-variant-numeric:tabular-nums}
.items-table tbody td.total{text-align:right;color:#18181b;font-weight:600;font-variant-numeric:tabular-nums}
.item-name{font-weight:600;color:#18181b;margin-bottom:2px;line-height:1.4}
.item-meta{font-size:11.5px;color:#a1a1aa;letter-spacing:0.01em}

/* ============ SUMMARY ============ */
.summary{
  margin-top:24px;padding-top:24px;
  border-top:1px solid #e4e4e7;
  display:flex;justify-content:flex-end;
}
.summary-table{min-width:280px;max-width:100%}
.summary-row{
  display:flex;justify-content:space-between;
  padding:7px 0;font-size:13px;gap:32px;
}
.summary-row span:first-child{color:#71717a}
.summary-row span:last-child{color:#18181b;font-weight:500;font-variant-numeric:tabular-nums}
.summary-row.discount span:last-child{color:#b45309}
.summary-row.paid span:last-child{color:#15803d}
.summary-row.due span:last-child{color:#b91c1c}
.summary-row.due.clear span:last-child{color:#15803d}

.summary-total{
  display:flex;justify-content:space-between;align-items:baseline;
  padding:14px 0 6px;margin-top:6px;
  border-top:1px solid #e4e4e7;gap:32px;
}
.summary-total span:first-child{
  font-size:11px;letter-spacing:0.15em;text-transform:uppercase;
  color:#18181b;font-weight:700;
}
.summary-total span:last-child{
  font-size:18px;font-weight:700;color:#18181b;
  letter-spacing:-0.01em;font-variant-numeric:tabular-nums;
}

/* ============ PAYMENTS ============ */
.payments{margin-top:32px;padding-top:24px;border-top:1px solid #e4e4e7}
.payment-list{display:flex;flex-direction:column;gap:0;margin-top:4px}
.payment-item{
  display:flex;justify-content:space-between;align-items:center;
  padding:10px 0;font-size:13px;border-bottom:1px dotted #e4e4e7;gap:16px;
}
.payment-item:last-child{border-bottom:none}
.payment-date{color:#71717a;font-size:12.5px;min-width:100px}
.payment-note{flex:1;color:#a1a1aa;font-size:12px}
.payment-amount{color:#18181b;font-weight:600;font-variant-numeric:tabular-nums}

/* ============ NOTE ============ */
.note-block{
  margin-top:24px;padding:14px 16px;
  background:#fafafa;border-left:2px solid #18181b;
  font-size:12.5px;color:#52525b;line-height:1.6;
}
.note-block strong{
  display:block;font-size:9px;letter-spacing:0.2em;
  text-transform:uppercase;color:#a1a1aa;font-weight:600;margin-bottom:6px;
}

/* ============ QR SECTION ============ */
.qr-section{
  margin-top:32px;padding-top:24px;
  border-top:1px solid #e4e4e7;
}
.qr-box{
  display:flex;align-items:center;gap:16px;
}
.qr-img{
  width:100px;height:100px;flex-shrink:0;
  border:1px solid #e4e4e7;border-radius:8px;padding:2px;
  background:#fff;display:grid;place-items:center;overflow:hidden;
}
.qr-img img{
  display:block;width:96px !important;height:96px !important;
}
.qr-info .qr-title{
  font-size:13px;font-weight:600;color:#18181b;margin-bottom:2px;
}
.qr-info .qr-sub{
  font-size:11.5px;color:#71717a;line-height:1.5;
}

/* ============ SIGNATURE ============ */
.signature{
  display:grid;grid-template-columns:1fr 1fr;
  gap:80px;margin-top:56px;padding-top:32px;
}
.signature-box{text-align:center;font-size:11px;color:#71717a}
.signature-box .space{height:56px}
.signature-box .line{
  border-top:1px solid #18181b;
  padding-top:10px;color:#18181b;
  font-weight:500;letter-spacing:0.05em;
}

/* ============ FOOTER ============ */
.nota-footer{
  margin-top:48px;padding-top:20px;
  border-top:1px solid #e4e4e7;
  text-align:center;font-size:11px;color:#a1a1aa;
  letter-spacing:0.02em;line-height:1.7;
}
.nota-footer strong{color:#71717a;font-weight:600}

/* ============ RESPONSIVE ============ */
@media (max-width:700px){
  body{padding:16px 8px}
  .toolbar{margin-bottom:12px;gap:8px}
  .btn{flex:1;padding:11px 12px;font-size:12px}

  .nota{padding:32px 22px 28px;border-radius:0}
  .header{flex-direction:column;gap:20px;margin-bottom:24px}
  .invoice-block{text-align:left;width:100%}
  .brand-info h1{font-size:17px}
  .invoice-no{font-size:17px}

  .meta{grid-template-columns:1fr;gap:16px;margin-bottom:24px}

  .items-table{font-size:12px}
  .items-table thead th{padding:10px 0;font-size:9px}
  .items-table tbody td{padding:12px 0}
  .item-meta{font-size:11px}

  .summary-table{width:100%;min-width:0}
  .summary-row{font-size:12px;gap:16px}
  .summary-total span:last-child{font-size:16px}

  .qr-section{margin-top:24px;padding-top:18px}
  .qr-img{width:88px;height:88px}
  .qr-img img{width:84px !important;height:84px !important}

  .signature{grid-template-columns:1fr;gap:32px;margin-top:40px}
  .signature-box .space{height:40px}

  .nota-footer{margin-top:32px;font-size:10px}

  .watermark{font-size:56px}
}

@media (max-width:400px){
  .items-table thead th.price,
  .items-table tbody td.price{display:none}
  .items-table thead th.num{width:36px}
}

/* ============ PRINT ============ */
@media print{
  @page{size:A4;margin:14mm}
  body{background:#fff !important;padding:0}
  .toolbar{display:none !important}
  .nota{box-shadow:none;max-width:100%;padding:0}

  .status-badge.lunas,
  .status-badge.hutang,
  .summary-total,
  .nota-footer,
  .note-block,
  .watermark,
  .qr-img{
    -webkit-print-color-adjust:exact;
    print-color-adjust:exact;
  }

  .items-table tbody tr,
  .payment-item,
  .signature-box,
  .summary,
  .payments,
  .qr-section{
    page-break-inside:avoid;
    break-inside:avoid;
  }
  .items-table thead{display:table-header-group}
}
</style>
</head>
<body>

<!-- ============ TOOLBAR ============ -->
<div class="toolbar">
  <?php if ($isLoggedIn): ?>
    <a href="transaksi_view.php?id=<?= $id ?>" class="btn btn-ghost">← Kembali</a>
  <?php endif; ?>
  <button class="btn btn-ghost" onclick="window.print()" title="Print atau Save as PDF">🖨️ Print</button>
  <button class="btn btn-primary" id="btnDownload" onclick="downloadPDF()">💾 Download PDF</button>
</div>

<!-- ============ NOTA ============ -->
<div class="nota" id="notaContent">

  <!-- WATERMARK -->
  <?php if ($showWatermark): ?>
    <div class="watermark" aria-hidden="true" style="opacity:<?= $wmOpacity ?>">
      <?= htmlspecialchars($wmText) ?>
    </div>
  <?php endif; ?>

  <!-- HEADER -->
  <header class="header">
    <div class="brand-block">
      <?php if (LOGO_PATH && file_exists(LOGO_PATH)): ?>
        <img src="<?= LOGO_PATH ?>" alt="Logo" class="brand-logo">
      <?php endif; ?>
      <div class="brand-info">
        <h1><?= htmlspecialchars(NAMA_TOKO) ?></h1>
        <?php if (ALAMAT_TOKO): ?><p><?= nl2br(htmlspecialchars(ALAMAT_TOKO)) ?></p><?php endif; ?>
        <?php if (TELP_TOKO): ?><p><?= htmlspecialchars(TELP_TOKO) ?></p><?php endif; ?>
      </div>
    </div>
    <div class="invoice-block">
      <div class="invoice-label">Invoice</div>
      <div class="invoice-no"><?= htmlspecialchars($trx['no_nota']) ?></div>
      <div class="invoice-date"><?= tgl_indo($trx['tanggal']) ?></div>
    </div>
  </header>

  <div class="divider"></div>

  <!-- META -->
  <section class="meta">
    <div>
      <div class="section-label">Kepada</div>
      <div class="meta-name"><?= htmlspecialchars($trx['pelanggan'] ?? 'Umum / Walk-in') ?></div>
      <?php if ($trx['telepon']): ?>
        <div class="meta-text"><?= htmlspecialchars($trx['telepon']) ?></div>
      <?php endif; ?>
      <?php if ($trx['alamat']): ?>
        <div class="meta-text"><?= nl2br(htmlspecialchars($trx['alamat'])) ?></div>
      <?php endif; ?>
    </div>
    <div>
      <div class="section-label">Status</div>
      <?php if ($trx['status'] === 'lunas'): ?>
        <span class="status-badge lunas"><span class="dot"></span> Lunas</span>
      <?php else: ?>
        <span class="status-badge hutang"><span class="dot"></span> Belum Lunas</span>
      <?php endif; ?>
    </div>
  </section>

  <!-- ITEMS -->
  <table class="items-table">
    <thead>
      <tr>
        <th>Deskripsi</th>
        <th class="num">Qty</th>
        <th class="price">Harga</th>
        <th class="total">Jumlah</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr>
        <td>
          <div class="item-name"><?= htmlspecialchars($it['nama_item']) ?></div>
          <?php
            $metaParts = [];
            if (!empty($it['bahan'])) $metaParts[] = $it['bahan'];
            if ((float)$it['panjang'] > 0 && (float)$it['lebar'] > 0) {
                $metaParts[] = $it['panjang'] . ' × ' . $it['lebar'] . ' cm';
            } elseif ((float)$it['panjang'] > 0) {
                $metaParts[] = $it['panjang'] . ' cm';
            } elseif ((float)$it['lebar'] > 0) {
                $metaParts[] = $it['lebar'] . ' cm';
            }
            if ($metaParts):
          ?>
            <div class="item-meta"><?= htmlspecialchars(implode(' · ', $metaParts)) ?></div>
          <?php endif; ?>
        </td>
        <td class="num"><?= (int)$it['qty'] ?></td>
        <td class="price"><?= rupiah($it['harga']) ?></td>
        <td class="total"><?= rupiah($it['subtotal']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- SUMMARY -->
  <section class="summary">
    <div class="summary-table">
      <div class="summary-row">
        <span>Subtotal</span><span><?= rupiah($trx['subtotal']) ?></span>
      </div>
      <?php if ($trx['diskon'] > 0): ?>
      <div class="summary-row discount">
        <span>Diskon</span><span>− <?= rupiah($trx['diskon']) ?></span>
      </div>
      <?php endif; ?>
      <div class="summary-row paid">
        <span>Dibayar</span><span><?= rupiah($trx['bayar']) ?></span>
      </div>
      <div class="summary-row due <?= $trx['sisa'] <= 0 ? 'clear' : '' ?>">
        <span>Sisa</span>
        <span><?= $trx['sisa'] > 0 ? rupiah($trx['sisa']) : 'LUNAS' ?></span>
      </div>
      <div class="summary-total">
        <span>Total</span><span><?= rupiah($trx['total']) ?></span>
      </div>
    </div>
  </section>

  <!-- PAYMENTS -->
  <?php if (count($pembayaran) > 0): ?>
  <section class="payments">
    <div class="section-label">Riwayat Pembayaran</div>
    <div class="payment-list">
      <?php foreach ($pembayaran as $p): ?>
        <div class="payment-item">
          <span class="payment-date"><?= tgl_indo($p['tanggal']) ?></span>
          <span class="payment-note"><?= htmlspecialchars($p['keterangan'] ?: '—') ?></span>
          <span class="payment-amount"><?= rupiah($p['jumlah']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- CATATAN -->
  <?php if ($trx['catatan']): ?>
    <div class="note-block">
      <strong>Catatan</strong>
      <?= nl2br(htmlspecialchars($trx['catatan'])) ?>
    </div>
  <?php endif; ?>

  <!-- QR CODE -->
  <section class="qr-section">
    <div class="qr-box">
      <div class="qr-img" id="qrNota" data-text="<?= htmlspecialchars($notaPublicUrl) ?>"></div>
      <div class="qr-info">
        <div class="qr-title">Verifikasi Nota Online</div>
        <div class="qr-sub">Scan untuk membuka nota versi digital<br>(bisa langsung dari HP)</div>
      </div>
    </div>
  </section>

  <!-- SIGNATURE -->
  <div class="signature">
    <div class="signature-box">
      <div class="space"></div>
      <div class="line">Pelanggan</div>
    </div>
    <div class="signature-box">
      <div class="space"></div>
      <div class="line">Hormat Kami</div>
    </div>
  </div>

  <!-- FOOTER -->
  <div class="nota-footer">
    Terima kasih atas kepercayaan Anda<br>
    <strong><?= htmlspecialchars(NAMA_TOKO) ?></strong>
  </div>

</div>

<!-- ============ SCRIPT PDF & QR ============ -->
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
/* ============ QR GENERATION ============ */
async function generateQRCode() {
  const container = document.getElementById('qrNota');
  if (!container || !container.dataset.text) return;
  if (typeof qrcode === 'undefined') {
    container.innerHTML = '<span style="font-size:10px;color:#94a3b8;text-align:center;line-height:1.2">QR<br>offline</span>';
    return;
  }
  try {
    const qr = qrcode(0, 'M');
    qr.addData(container.dataset.text);
    qr.make();
    const dataUrl = qr.createDataURL(4, 0);
    await new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => {
        img.width = 96;
        img.height = 96;
        img.alt = 'QR Nota';
        img.style.display = 'block';
        container.innerHTML = '';
        container.appendChild(img);
        resolve();
      };
      img.onerror = reject;
      img.src = dataUrl;
    });
  } catch (e) {
    console.error('QR error:', e);
    container.innerHTML = '<span style="font-size:10px;color:#94a3b8">QR gagal</span>';
  }
}

/* ============ PDF ============ */
async function downloadPDF() {
  const btn = document.getElementById('btnDownload');
  const originalText = btn.innerHTML;

  if (typeof html2canvas === 'undefined' || typeof window.jspdf === 'undefined') {
    alert('Library PDF belum siap.\n\nCek koneksi internet, lalu refresh halaman.\n\nFallback: tombol "Print" → Save as PDF.');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '⏳ Memproses...';

  const element = document.getElementById('notaContent');
  const originalStyle = element.getAttribute('style') || '';

  element.style.width = '720px';
  element.style.maxWidth = '720px';
  element.style.boxShadow = 'none';

  try {
    await new Promise(r => setTimeout(r, 200));

    const canvas = await html2canvas(element, {
      scale: 2,
      useCORS: true,
      allowTaint: true,
      logging: false,
      backgroundColor: '#ffffff',
      windowWidth: 740,
      scrollX: 0,
      scrollY: -window.scrollY
    });

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');
    const pageWidth  = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const margin = 10;
    const contentWidth  = pageWidth - margin * 2;
    const contentHeight = pageHeight - margin * 2;

    const imgData = canvas.toDataURL('image/jpeg', 0.98);
    const imgWidth  = contentWidth;
    const imgHeight = (canvas.height * imgWidth) / canvas.width;

    if (imgHeight <= contentHeight) {
      pdf.addImage(imgData, 'JPEG', margin, margin, imgWidth, imgHeight);
    } else if (imgHeight <= contentHeight * 1.4) {
      const scale  = contentHeight / imgHeight;
      const finalW = imgWidth * scale;
      const finalH = imgHeight * scale;
      const xOff   = (pageWidth - finalW) / 2;
      pdf.addImage(imgData, 'JPEG', xOff, margin, finalW, finalH);
    } else {
      const pxPerMm      = canvas.width / contentWidth;
      const pageHeightPx = Math.floor(contentHeight * pxPerMm);
      let yPos = 0, page = 0;

      while (yPos < canvas.height) {
        if (page > 0) pdf.addPage();
        const sliceH = Math.min(pageHeightPx, canvas.height - yPos);
        const sliceCanvas = document.createElement('canvas');
        sliceCanvas.width  = canvas.width;
        sliceCanvas.height = sliceH;
        const ctx = sliceCanvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, sliceCanvas.width, sliceCanvas.height);
        ctx.drawImage(canvas, 0, yPos, canvas.width, sliceH, 0, 0, canvas.width, sliceH);

        const sliceData = sliceCanvas.toDataURL('image/jpeg', 0.98);
        const sliceHmm  = (sliceH * contentWidth) / canvas.width;
        pdf.addImage(sliceData, 'JPEG', margin, margin, contentWidth, sliceHmm);

        yPos += pageHeightPx;
        page++;
      }

      const totalPages = pdf.internal.getNumberOfPages();
      for (let i = 1; i <= totalPages; i++) {
        pdf.setPage(i);
        pdf.setFontSize(8);
        pdf.setTextColor(160);
        pdf.text(`Halaman ${i} dari ${totalPages}`, pageWidth / 2, pageHeight - 4, { align: 'center' });
      }
    }

    pdf.save('Nota-<?= htmlspecialchars($trx['no_nota']) ?>.pdf');

  } catch (err) {
    console.error('PDF error:', err);
    alert('Gagal membuat PDF: ' + err.message + '\n\nFallback: tombol "Print" → Save as PDF.');
  } finally {
    element.setAttribute('style', originalStyle);
    btn.disabled = false;
    btn.innerHTML = originalText;
  }
}

/* ============ INIT: QR dulu, baru auto-download ============ */
window.addEventListener('load', async () => {
  await generateQRCode();
  <?php if ($autoDownload): ?>
  setTimeout(() => { if (typeof downloadPDF === 'function') downloadPDF(); }, 300);
  <?php endif; ?>
});
</script>

</body>
</html>