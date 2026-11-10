<?php
require_once 'config.php';

$pelanggan_id = (int)($_GET['pelanggan_id'] ?? 0);
$idsRaw = $_GET['ids'] ?? [];

if (is_array($idsRaw)) {
    $ids = array_filter(array_map('intval', $idsRaw));
} else {
    $ids = array_filter(array_map('intval', explode(',', (string)$idsRaw)));
}

if ($pelanggan_id <= 0 || empty($ids)) {
    die('Parameter tidak valid. Pilih pelanggan dan transaksi terlebih dahulu.');
}

$stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id=?");
$stmt->execute([$pelanggan_id]);
$pelanggan = $stmt->fetch();
if (!$pelanggan) die('Pelanggan tidak ditemukan');

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("
    SELECT t.* FROM transaksi t
    WHERE t.id IN ($placeholders) AND t.pelanggan_id = ? AND t.status = 'hutang'
    ORDER BY t.tanggal ASC, t.id ASC
");
$stmt->execute([...$ids, $pelanggan_id]);
$transaksiList = $stmt->fetchAll();

if (empty($transaksiList)) die('Tidak ada transaksi hutang yang valid.');

$itemsByTrx = [];
$pembayaranByTrx = [];
$grandTotal = 0;
$grandBayar = 0;
$grandSisa = 0;

foreach ($transaksiList as $trx) {
    $grandTotal += $trx['total'];
    $grandBayar += $trx['bayar'];
    $grandSisa  += $trx['sisa'];

    $stmt = $pdo->prepare("SELECT * FROM transaksi_item WHERE transaksi_id=? ORDER BY id");
    $stmt->execute([$trx['id']]);
    $itemsByTrx[$trx['id']] = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM pembayaran WHERE transaksi_id=? ORDER BY tanggal, id");
    $stmt->execute([$trx['id']]);
    $pembayaranByTrx[$trx['id']] = $stmt->fetchAll();
}

$tanggalAwal  = min(array_column($transaksiList, 'tanggal'));
$tanggalAkhir = max(array_column($transaksiList, 'tanggal'));
$noRekap = 'REKAP-' . date('Ymd') . '-' . str_pad($pelanggan_id, 3, '0', STR_PAD_LEFT);

/* Watermark (reuse dari settings) */
$wmText      = trim($settings['watermark_text'] ?? '');
$wmOpacity   = max(0.03, min(0.20, ((int)($settings['watermark_opacity'] ?? 6)) / 100));
$wmLunasOnly = !empty($settings['watermark_lunas_only']);
$showWatermark = $wmText !== '' && (!$wmLunasOnly || $grandSisa <= 0);

$autoDownload = !empty($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rekap Hutang <?= htmlspecialchars($pelanggan['nama']) ?> · <?= htmlspecialchars(NAMA_TOKO) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html{-webkit-text-size-adjust:100%}
body{
  font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Inter',system-ui,sans-serif;
  background:#f4f4f5;padding:32px 16px;color:#18181b;line-height:1.6;font-size:14px;
  -webkit-font-smoothing:antialiased;
}
.toolbar{max-width:720px;margin:0 auto 20px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  padding:10px 18px;border-radius:8px;border:none;cursor:pointer;
  font-size:13px;font-weight:600;text-decoration:none;transition:.15s;
  font-family:inherit;white-space:nowrap;
}
.btn-ghost{background:#fff;color:#52525b;border:1px solid #e4e4e7}
.btn-ghost:hover{background:#fafafa}
.btn-primary{background:#18181b;color:#fff}
.btn-primary:hover{background:#27272a}
.btn:disabled{opacity:.6;cursor:wait}

.nota{
  max-width:720px;margin:0 auto;background:#fff;
  padding:56px 56px 40px;
  box-shadow:0 1px 2px rgba(0,0,0,.04),0 4px 16px rgba(0,0,0,.04);
  position:relative;overflow:hidden;
}

/* WATERMARK */
.watermark{
  position:absolute;top:50%;left:50%;
  transform:translate(-50%,-50%) rotate(-25deg);
  font-size:96px;font-weight:900;letter-spacing:0.1em;
  text-transform:uppercase;color:#18181b;line-height:1;
  text-align:center;max-width:90%;word-break:break-word;
  pointer-events:none;user-select:none;white-space:nowrap;z-index:0;
  -webkit-print-color-adjust:exact;print-color-adjust:exact;
}
.nota > *:not(.watermark){position:relative;z-index:1}

.header{
  display:flex;justify-content:space-between;align-items:flex-start;
  gap:32px;margin-bottom:28px;flex-wrap:wrap;
}
.brand-block{display:flex;align-items:flex-start;gap:16px;flex:1;min-width:200px}
.brand-logo{max-width:56px;max-height:56px;object-fit:contain;flex-shrink:0}
.brand-info h1{font-size:20px;font-weight:700;letter-spacing:-.02em;line-height:1.15;color:#18181b;margin-bottom:6px}
.brand-info p{font-size:12.5px;color:#71717a;line-height:1.55}

.invoice-block{text-align:right;flex-shrink:0}
.invoice-label{font-size:9px;letter-spacing:.25em;text-transform:uppercase;color:#a1a1aa;font-weight:600;margin-bottom:4px}
.invoice-no{font-size:16px;font-weight:700;letter-spacing:-.01em;color:#18181b;line-height:1.15;margin-bottom:6px;word-break:break-word}
.invoice-date{font-size:12.5px;color:#71717a}

.divider{height:1px;background:#e4e4e7;margin:0 0 24px}

.meta{display:grid;grid-template-columns:1fr auto;gap:24px;margin-bottom:28px;align-items:flex-start}
.section-label{font-size:9px;letter-spacing:.25em;text-transform:uppercase;color:#a1a1aa;font-weight:600;margin-bottom:8px}
.meta-name{font-size:14.5px;font-weight:600;color:#18181b;margin-bottom:4px;line-height:1.4}
.meta-text{font-size:12.5px;color:#71717a;line-height:1.6}

.status-badge{
  display:inline-flex;align-items:center;gap:7px;
  padding:6px 14px;border-radius:20px;font-size:10px;font-weight:700;
  text-transform:uppercase;letter-spacing:.1em;border:1px solid;
}
.status-badge.hutang{background:#fffbeb;color:#b45309;border-color:#fde68a}
.status-badge.lunas{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}
.status-badge .dot{width:6px;height:6px;border-radius:50%;background:currentColor}

/* ==== TRX GROUP ==== */
.trx-group{
  border:1px solid #e4e4e7;border-radius:12px;
  padding:16px 18px;margin-bottom:14px;
  page-break-inside:avoid;break-inside:avoid;
}
.trx-group-header{
  display:flex;justify-content:space-between;align-items:flex-start;
  gap:12px;padding-bottom:10px;margin-bottom:10px;
  border-bottom:1px solid #f4f4f5;flex-wrap:wrap;
}
.trx-group-no{font-weight:700;font-size:14px;color:#18181b}
.trx-group-date{font-size:11.5px;color:#a1a1aa;margin-top:2px}
.trx-group-total{text-align:right}
.trx-group-total .lbl{font-size:10px;color:#a1a1aa;text-transform:uppercase;letter-spacing:.1em;font-weight:600}
.trx-group-total .val{font-size:15px;font-weight:700;color:#18181b;font-variant-numeric:tabular-nums}

/* ==== ITEMS TABLE ==== */
.items-table{width:100%;border-collapse:collapse;font-size:12.5px}
.items-table thead th{
  text-align:left;font-size:9px;letter-spacing:.15em;text-transform:uppercase;
  color:#a1a1aa;font-weight:600;padding:8px 0;
  border-bottom:1px solid #e4e4e7;
}
.items-table thead th.num{text-align:right;width:50px}
.items-table thead th.price{text-align:right;width:100px}
.items-table thead th.total{text-align:right;width:110px}
.items-table tbody td{
  padding:8px 0;border-bottom:1px solid #f9fafb;vertical-align:top;
}
.items-table tbody tr:last-child td{border-bottom:none}
.items-table tbody td.num{text-align:right;color:#71717a;font-variant-numeric:tabular-nums}
.items-table tbody td.price{text-align:right;color:#52525b;font-variant-numeric:tabular-nums}
.items-table tbody td.total{text-align:right;color:#18181b;font-weight:600;font-variant-numeric:tabular-nums}
.item-name{font-weight:600;color:#18181b;line-height:1.4}
.item-meta{font-size:11px;color:#a1a1aa;margin-top:2px}

/* ==== MINI SUMMARY PER TRX ==== */
.trx-mini-summary{
  display:flex;justify-content:flex-end;gap:24px;
  margin-top:10px;padding-top:10px;border-top:1px dotted #e4e4e7;
  font-size:11.5px;color:#71717a;
}
.trx-mini-summary strong{color:#18181b;font-weight:600}

/* ==== GRAND TOTAL ==== */
.grand-total{
  background:#fafafa;border:1.5px solid #18181b;border-radius:14px;
  padding:20px 24px;margin-top:24px;
  page-break-inside:avoid;break-inside:avoid;
}
.grand-total-row{
  display:flex;justify-content:space-between;padding:6px 0;
  font-size:13px;gap:32px;
}
.grand-total-row span:first-child{color:#71717a}
.grand-total-row span:last-child{color:#18181b;font-weight:500;font-variant-numeric:tabular-nums}
.grand-total-row.due span:last-child{color:#b91c1c;font-weight:700}
.grand-total-final{
  display:flex;justify-content:space-between;align-items:baseline;
  padding-top:14px;margin-top:8px;border-top:2px solid #18181b;
  gap:32px;
}
.grand-total-final span:first-child{
  font-size:12px;letter-spacing:.15em;text-transform:uppercase;
  color:#18181b;font-weight:700;
}
.grand-total-final span:last-child{
  font-size:22px;font-weight:800;color:#18181b;
  letter-spacing:-.02em;font-variant-numeric:tabular-nums;
}

/* ==== SIGNATURE ==== */
.signature{
  display:grid;grid-template-columns:1fr 1fr;
  gap:80px;margin-top:48px;padding-top:32px;
}
.signature-box{text-align:center;font-size:11px;color:#71717a}
.signature-box .space{height:56px}
.signature-box .line{
  border-top:1px solid #18181b;padding-top:10px;
  color:#18181b;font-weight:500;letter-spacing:.05em;
}

.nota-footer{
  margin-top:40px;padding-top:20px;border-top:1px solid #e4e4e7;
  text-align:center;font-size:11px;color:#a1a1aa;
  letter-spacing:.02em;line-height:1.7;
}
.nota-footer strong{color:#71717a;font-weight:600}

/* ==== RESPONSIVE ==== */
@media(max-width:700px){
  body{padding:16px 8px}
  .toolbar{margin-bottom:12px;gap:8px}
  .btn{flex:1;padding:11px 12px;font-size:12px}
  .nota{padding:28px 20px 24px;border-radius:0}
  .header{flex-direction:column;gap:16px;margin-bottom:20px}
  .invoice-block{text-align:left;width:100%}
  .brand-info h1{font-size:16px}
  .invoice-no{font-size:14px}
  .meta{grid-template-columns:1fr;gap:14px}
  .trx-group{padding:12px 14px}
  .items-table{font-size:11.5px}
  .items-table thead th.price,.items-table tbody td.price{display:none}
  .items-table thead th.num{width:36px}
  .items-table thead th.total{width:90px}
  .grand-total{padding:16px 18px}
  .grand-total-final span:last-child{font-size:18px}
  .signature{grid-template-columns:1fr;gap:32px;margin-top:32px}
  .signature-box .space{height:40px}
  .watermark{font-size:56px}
  .nota-footer{margin-top:28px;font-size:10px}
}

@media print{
  @page{size:A4;margin:12mm}
  body{background:#fff !important;padding:0}
  .toolbar{display:none !important}
  .nota{box-shadow:none;max-width:100%;padding:0}
  .status-badge,.grand-total,.watermark,.nota-footer{
    -webkit-print-color-adjust:exact;print-color-adjust:exact;
  }
  .trx-group,.grand-total,.signature-box{
    page-break-inside:avoid;break-inside:avoid;
  }
}
</style>
</head>
<body>

<div class="toolbar">
  <a href="gabung_nota.php?pelanggan_id=<?= $pelanggan_id ?>" class="btn btn-ghost">← Kembali</a>
  <button class="btn btn-ghost" onclick="window.print()">🖨️ Print</button>
  <button class="btn btn-primary" id="btnDownload" onclick="downloadPDF()">💾 Download PDF</button>
</div>

<div class="nota" id="notaContent">

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
      <div class="invoice-label">Rekap Hutang</div>
      <div class="invoice-no"><?= $noRekap ?></div>
      <div class="invoice-date"><?= tgl_indo(date('Y-m-d')) ?></div>
    </div>
  </header>

  <div class="divider"></div>

  <!-- META -->
  <section class="meta">
    <div>
      <div class="section-label">Kepada</div>
      <div class="meta-name"><?= htmlspecialchars($pelanggan['nama']) ?></div>
      <?php if ($pelanggan['telepon']): ?>
        <div class="meta-text"><?= htmlspecialchars($pelanggan['telepon']) ?></div>
      <?php endif; ?>
      <?php if ($pelanggan['alamat']): ?>
        <div class="meta-text"><?= nl2br(htmlspecialchars($pelanggan['alamat'])) ?></div>
      <?php endif; ?>
    </div>
    <div>
      <div class="section-label">Periode</div>
      <div class="meta-text" style="color:#18181b;font-weight:600">
        <?= tgl_indo($tanggalAwal) ?> — <?= tgl_indo($tanggalAkhir) ?>
      </div>
      <div class="meta-text" style="margin-top:6px;margin-bottom:8px">
        <strong><?= count($transaksiList) ?></strong> nota digabung
      </div>
      <span class="status-badge hutang"><span class="dot"></span> Belum Lunas</span>
    </div>
  </section>

  <!-- DAFTAR TRANSAKSI -->
  <div class="section-label" style="margin-bottom:12px">Daftar Nota Hutang</div>

  <?php foreach ($transaksiList as $trx): ?>
    <div class="trx-group">
      <div class="trx-group-header">
        <div>
          <div class="trx-group-no"><?= htmlspecialchars($trx['no_nota']) ?></div>
          <div class="trx-group-date"><?= tgl_indo($trx['tanggal']) ?></div>
        </div>
        <div class="trx-group-total">
          <div class="lbl">Total Nota</div>
          <div class="val"><?= rupiah($trx['total']) ?></div>
        </div>
      </div>

      <?php if (!empty($itemsByTrx[$trx['id']])): ?>
        <table class="items-table">
          <thead>
            <tr>
              <th>Item</th>
              <th class="num">Qty</th>
              <th class="price">Harga</th>
              <th class="total">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($itemsByTrx[$trx['id']] as $it): ?>
              <tr>
                <td>
                  <div class="item-name"><?= htmlspecialchars($it['nama_item']) ?></div>
                  <?php
                    $mp = [];
                    if (!empty($it['bahan'])) $mp[] = $it['bahan'];
                    if ((float)$it['panjang'] > 0 && (float)$it['lebar'] > 0) $mp[] = $it['panjang'].' × '.$it['lebar'].' cm';
                    elseif ((float)$it['panjang'] > 0) $mp[] = $it['panjang'].' cm';
                    elseif ((float)$it['lebar'] > 0) $mp[] = $it['lebar'].' cm';
                    if ($mp):
                  ?>
                    <div class="item-meta"><?= htmlspecialchars(implode(' · ', $mp)) ?></div>
                  <?php endif; ?>
                </td>
                <td class="num"><?= (int)$it['qty'] ?></td>
                <td class="price"><?= rupiah($it['harga']) ?></td>
                <td class="total"><?= rupiah($it['subtotal']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <div class="trx-mini-summary">
        <span>Dibayar: <strong><?= rupiah($trx['bayar']) ?></strong></span>
        <span>Sisa: <strong style="color:#b91c1c"><?= rupiah($trx['sisa']) ?></strong></span>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- GRAND TOTAL -->
  <div class="grand-total">
    <div class="grand-total-row">
      <span>Subtotal (<?= count($transaksiList) ?> nota)</span>
      <span><?= rupiah($grandTotal) ?></span>
    </div>
    <div class="grand-total-row">
      <span>Total Dibayar</span>
      <span style="color:#15803d"><?= rupiah($grandBayar) ?></span>
    </div>
    <div class="grand-total-row due">
      <span>Total Sisa Hutang</span>
      <span><?= rupiah($grandSisa) ?></span>
    </div>
    <div class="grand-total-final">
      <span>Total Hutang</span>
      <span><?= rupiah($grandSisa) ?></span>
    </div>
  </div>

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

  <div class="nota-footer">
    Dokumen ini adalah rekap dari <strong><?= count($transaksiList) ?> nota</strong> yang belum lunas<br>
    <strong><?= htmlspecialchars(NAMA_TOKO) ?></strong>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
async function downloadPDF() {
  const btn = document.getElementById('btnDownload');
  const originalText = btn.innerHTML;

  if (typeof html2canvas === 'undefined' || typeof window.jspdf === 'undefined') {
    alert('Library PDF belum siap. Cek koneksi lalu refresh.\n\nFallback: tombol "Print" → Save as PDF.');
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
      scale: 2, useCORS: true, allowTaint: true, logging: false,
      backgroundColor: '#ffffff', windowWidth: 740,
      scrollX: 0, scrollY: -window.scrollY
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
      const pxPerMm = canvas.width / contentWidth;
      const pageHeightPx = Math.floor(contentHeight * pxPerMm);
      let yPos = 0, page = 0;
      while (yPos < canvas.height) {
        if (page > 0) pdf.addPage();
        const sliceH = Math.min(pageHeightPx, canvas.height - yPos);
        const sc = document.createElement('canvas');
        sc.width = canvas.width; sc.height = sliceH;
        const ctx = sc.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, sc.width, sc.height);
        ctx.drawImage(canvas, 0, yPos, canvas.width, sliceH, 0, 0, canvas.width, sliceH);
        const sd = sc.toDataURL('image/jpeg', 0.98);
        const sh = (sliceH * contentWidth) / canvas.width;
        pdf.addImage(sd, 'JPEG', margin, margin, contentWidth, sh);
        yPos += pageHeightPx; page++;
      }
      const totalPages = pdf.internal.getNumberOfPages();
      for (let i = 1; i <= totalPages; i++) {
        pdf.setPage(i);
        pdf.setFontSize(8);
        pdf.setTextColor(160);
        pdf.text(`Halaman ${i} dari ${totalPages}`, pageWidth / 2, pageHeight - 4, { align: 'center' });
      }
    }

    pdf.save('<?= $noRekap ?>-<?= preg_replace('/[^A-Za-z0-9]/', '-', $pelanggan['nama']) ?>.pdf');

  } catch (err) {
    console.error('PDF error:', err);
    alert('Gagal membuat PDF: ' + err.message + '\n\nFallback: tombol "Print" → Save as PDF.');
  } finally {
    element.setAttribute('style', originalStyle);
    btn.disabled = false;
    btn.innerHTML = originalText;
  }
}

<?php if ($autoDownload): ?>
window.addEventListener('load', () => setTimeout(downloadPDF, 600));
<?php endif; ?>
</script>

</body>
</html>