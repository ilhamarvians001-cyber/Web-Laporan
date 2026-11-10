<?php
require_once 'config.php';
$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$title = $isEdit ? 'Edit Transaksi' : 'Transaksi Baru';

$pelangganList = $pdo->query("SELECT * FROM pelanggan ORDER BY nama")->fetchAll();
$trx = ['tanggal'=>date('Y-m-d'),'pelanggan_id'=>0,'diskon'=>0,'catatan'=>'','no_nota'=>''];
$items = [];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM transaksi WHERE id=?");
    $stmt->execute([$id]);
    $trx = $stmt->fetch();
    if (!$trx) { flash('Transaksi tidak ditemukan','danger'); redirect('transaksi.php'); }
    $stmt = $pdo->prepare("SELECT * FROM transaksi_item WHERE transaksi_id=? ORDER BY id");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = $_POST['tanggal'];
    $pelanggan_id = (int)$_POST['pelanggan_id'] ?: null;
    $diskon = (float)$_POST['diskon'];
    $catatan = trim($_POST['catatan'] ?? '');
    $bayarAwal = (float)($_POST['bayar_awal'] ?? 0);
    $nama_items = $_POST['nama_item'] ?? [];
    $bahans = $_POST['bahan'] ?? [];
    $panjangs = $_POST['panjang'] ?? [];
    $lebars = $_POST['lebar'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $hargas = $_POST['harga'] ?? [];

    $newItems = []; $subtotal = 0;
    for ($i=0; $i<count($nama_items); $i++) {
        if (trim($nama_items[$i]) === '') continue;
        $q = (int)$qtys[$i]; $h = (float)$hargas[$i]; $sub = $q*$h;
        $subtotal += $sub;
        $newItems[] = ['nama_item'=>$nama_items[$i],'bahan'=>$bahans[$i]??'','panjang'=>(float)($panjangs[$i]??0),'lebar'=>(float)($lebars[$i]??0),'qty'=>$q,'harga'=>$h,'subtotal'=>$sub];
    }
    $total = $subtotal - $diskon; if ($total < 0) $total = 0;

    try {
        $pdo->beginTransaction();
        if (!$isEdit) {
            $no_nota = generateNoNota($pdo);
            $status = $bayarAwal >= $total ? 'lunas' : 'hutang';
            $pdo->prepare("INSERT INTO transaksi (no_nota,tanggal,pelanggan_id,subtotal,diskon,total,bayar,sisa,status,catatan) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$no_nota,$tanggal,$pelanggan_id,$subtotal,$diskon,$total,$bayarAwal,max($total-$bayarAwal,0),$status,$catatan]);
            $newId = $pdo->lastInsertId();
            if ($bayarAwal > 0) {
                $pdo->prepare("INSERT INTO pembayaran (transaksi_id,tanggal,jumlah,keterangan) VALUES (?,?,?,?)")
                    ->execute([$newId,$tanggal,$bayarAwal,'Pembayaran awal']);
            }
            flash("Transaksi berhasil dibuat. No Nota: $no_nota");
        } else {
            $newId = $id;
            $pdo->prepare("UPDATE transaksi SET tanggal=?,pelanggan_id=?,subtotal=?,diskon=?,total=?,catatan=? WHERE id=?")
                ->execute([$tanggal,$pelanggan_id,$subtotal,$diskon,$total,$catatan,$id]);
            $pdo->prepare("DELETE FROM transaksi_item WHERE transaksi_id=?")->execute([$id]);
            flash('Transaksi berhasil diupdate');
        }
        foreach ($newItems as $it) {
            $pdo->prepare("INSERT INTO transaksi_item (transaksi_id,nama_item,bahan,panjang,lebar,qty,harga,subtotal) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$newId,$it['nama_item'],$it['bahan'],$it['panjang'],$it['lebar'],$it['qty'],$it['harga'],$it['subtotal']]);
        }
        recalcTransaksi($pdo, $newId);
        $pdo->commit();
        redirect('transaksi_view.php?id='.$newId);
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('Gagal: '.$e->getMessage(),'danger');
    }
}

$selectedPelangganNama = '';
if (!empty($trx['pelanggan_id'])) {
    foreach ($pelangganList as $p) {
        if ($p['id'] == $trx['pelanggan_id']) { $selectedPelangganNama = $p['nama']; break; }
    }
}

include 'header.php';
?>

<style>
/* ============================================
   SEARCHABLE SELECT (DESKTOP)
   ============================================ */
.searchable-select{position:relative}
.ss-input-wrap{position:relative}
.ss-input-wrap .form-control{padding-right:38px}
.ss-clear{
  position:absolute;right:6px;top:50%;transform:translateY(-50%);
  width:28px;height:28px;border:none;background:#f1f5f9;color:#64748b;
  border-radius:50%;cursor:pointer;font-size:16px;line-height:1;
  display:none;align-items:center;justify-content:center;transition:.15s;
}
.ss-clear:hover{background:#e2e8f0;color:#0f172a}
.ss-clear.show{display:flex}

.ss-dropdown{
  display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;
  background:#fff;border:1px solid #e2e8f0;border-radius:10px;
  box-shadow:0 12px 28px -6px rgba(15,23,42,.18),0 4px 12px -4px rgba(15,23,42,.08);
  max-height:280px;overflow-y:auto;z-index:100;
  animation:ssSlide .15s ease;
}
.ss-dropdown.show{display:block}
@keyframes ssSlide{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}
.ss-item{
  padding:11px 14px;cursor:pointer;transition:background .1s;
  display:flex;justify-content:space-between;align-items:center;gap:10px;
  border-bottom:1px solid #f8fafc;
}
.ss-item:last-child{border-bottom:none}
.ss-item:hover,.ss-item.active{background:#eef2ff}
.ss-item.selected{background:#eef2ff}
.ss-item-left{flex:1;min-width:0}
.ss-item-name{font-weight:600;color:#0f172a;font-size:13.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ss-item-meta{font-size:11.5px;color:#64748b;margin-top:2px}
.ss-item-mark{color:#4f46e5;font-weight:700;flex-shrink:0;font-size:15px;display:none}
.ss-item.selected .ss-item-mark{display:block}
.ss-empty{padding:20px 16px;text-align:center;color:#94a3b8;font-size:13px}
.ss-empty strong{color:#4f46e5;display:block;margin-bottom:4px;font-size:14px}
.ss-hint{
  padding:8px 14px;background:#f8fafc;color:#94a3b8;font-size:11px;
  border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;
}

/* Desktop: sembunyikan tombol trigger mobile */
.mobile-trigger{display:none}

/* ============================================
   MOBILE PICKER (FULLSCREEN)
   ============================================ */
.picker-overlay{
  display:none;position:fixed;inset:0;background:#fff;z-index:2000;
  flex-direction:column;
}
.picker-overlay.show{display:flex}
.picker-header{
  display:flex;align-items:center;gap:8px;
  padding:12px 14px;background:#fff;
  border-bottom:1px solid #e2e8f0;flex-shrink:0;
}
.picker-back{
  background:none;border:none;padding:8px 12px;
  font-size:18px;cursor:pointer;color:#334155;
  border-radius:8px;line-height:1;
}
.picker-back:active{background:#f1f5f9}
.picker-title{flex:1;font-size:15px;font-weight:700;text-align:center;color:#0f172a}
.picker-add{
  background:var(--primary);color:#fff;border:none;
  padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;
  cursor:pointer;white-space:nowrap;
}
.picker-add:active{background:var(--primary-dark)}
.picker-search-wrap{
  padding:12px 14px;background:#fff;
  border-bottom:1px solid #e2e8f0;flex-shrink:0;
}
.picker-search{
  width:100%;padding:12px 14px 12px 40px;
  border:1px solid #e2e8f0;border-radius:10px;
  font-size:16px;background:#f8fafc;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E");
  background-repeat:no-repeat;background-position:14px center;
  -webkit-appearance:none;appearance:none;
}
.picker-search:focus{
  outline:none;border-color:#6366f1;background-color:#fff;
  box-shadow:0 0 0 3px rgba(99,102,241,.15);
}
.picker-search::-webkit-search-cancel-button{-webkit-appearance:none}
.picker-list{
  flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch;
  background:#f8fafc;
}
.picker-item{
  display:flex;align-items:center;gap:12px;
  padding:14px 16px;background:#fff;
  border-bottom:1px solid #f1f5f9;
  cursor:pointer;transition:background .1s;
}
.picker-item:active{background:#eef2ff}
.picker-item.selected{
  background:#eef2ff;
  border-left:4px solid #4f46e5;
  padding-left:12px;
}
.picker-avatar{
  width:40px;height:40px;
  background:linear-gradient(135deg,#6366f1,#4f46e5);
  color:#fff;border-radius:50%;
  display:grid;place-items:center;
  font-weight:700;font-size:16px;flex-shrink:0;
}
.picker-info{flex:1;min-width:0}
.picker-name{font-size:14.5px;font-weight:600;color:#0f172a;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.picker-meta{font-size:12.5px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.picker-check{color:#4f46e5;font-weight:700;font-size:18px;flex-shrink:0}
.picker-empty{padding:60px 20px;text-align:center;color:#94a3b8}
.picker-empty-icon{font-size:56px;margin-bottom:12px;opacity:.5}
.picker-empty strong{display:block;font-size:15px;color:#334155;margin-bottom:6px}
.picker-empty p{font-size:13px;line-height:1.5}
.picker-selected-badge{
  margin:12px 14px;padding:10px 14px;
  background:#eef2ff;border:1px solid #c7d2fe;
  border-radius:10px;font-size:13px;color:#3730a3;
  display:flex;justify-content:space-between;align-items:center;gap:10px;
}
.picker-selected-badge button{
  background:none;border:none;color:#dc2626;
  font-size:12px;font-weight:600;cursor:pointer;padding:4px 8px;
  border-radius:6px;
}
.picker-selected-badge button:active{background:#fee2e2}

/* ============================================
   MODAL PELANGGAN BARU
   ============================================ */
.modal-backdrop{
  display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);
  z-index:3000;align-items:center;justify-content:center;padding:16px;
  backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px);
}
.modal-backdrop.show{display:flex}
@keyframes slideUp{from{opacity:0;transform:translateY(20px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
.modal-box{
  background:#fff;border-radius:16px;padding:0;width:100%;max-width:440px;
  box-shadow:0 25px 50px -12px rgba(0,0,0,.35);
  animation:slideUp .25s cubic-bezier(.16,1,.3,1);
  max-height:92vh;overflow-y:auto;
}
.modal-header{
  display:flex;justify-content:space-between;align-items:center;
  padding:20px 24px 16px;border-bottom:1px solid #f1f5f9;
}
.modal-header h3{font-size:17px;font-weight:700;color:#0f172a}
.modal-close{
  background:none;border:none;font-size:24px;line-height:1;cursor:pointer;
  color:#94a3b8;padding:4px 8px;border-radius:6px;transition:.15s;
}
.modal-close:hover{background:#f1f5f9;color:#0f172a}
.modal-box form{padding:20px 24px 22px}
.modal-box .form-group{margin-bottom:14px}
.modal-error{
  display:none;padding:10px 14px;background:#fee2e2;color:#991b1b;
  border-radius:8px;font-size:13px;font-weight:500;margin-bottom:12px;
  border:1px solid #fecaca;
}
.modal-actions{display:flex;gap:8px;margin-top:6px}
.modal-actions .btn{flex:1}

/* ============================================
   MOBILE-FRIENDLY ITEM CARDS
   ============================================ */
@media (max-width: 700px) {
  #itemsWrap { overflow-x: visible !important; }
  #itemsTable { display: block !important; min-width: 0 !important; border-collapse: separate; border-spacing: 0; margin-bottom: 8px; }
  #itemsTable thead { display: none; }
  #itemsTable tbody { display: block; }
  #itemsTable tbody tr {
    display: grid !important; grid-template-columns: 1fr 1fr;
    gap: 12px 10px; background: #fff; border: 1px solid #e2e8f0;
    border-radius: 14px; padding: 16px; margin-bottom: 12px;
    box-shadow: 0 1px 4px rgba(0,0,0,.04); position: relative;
  }
  #itemsTable tbody tr td { display: block !important; padding: 0 !important; border: none !important; width: auto !important; min-width: 0; }
  #itemsTable tbody tr td::before {
    content: attr(data-label); display: block; font-size: 10.5px;
    font-weight: 700; color: #64748b; text-transform: uppercase;
    letter-spacing: .5px; margin-bottom: 5px;
  }
  #itemsTable tbody tr td.col-nama,
  #itemsTable tbody tr td.col-bahan,
  #itemsTable tbody tr td.col-subtotal { grid-column: 1 / -1; }
  #itemsTable tbody tr td.col-nama { padding-right: 46px !important; }
  #itemsTable tbody tr td.col-action { position: absolute; top: 12px; right: 12px; width: auto !important; }
  #itemsTable tbody tr td.col-action::before { display: none; }
  #itemsTable tbody tr td.col-action .btn {
    width: 36px !important; height: 36px !important; padding: 0 !important;
    font-size: 20px; line-height: 1; min-height: 0 !important;
    border-radius: 50% !important; box-shadow: 0 2px 6px rgba(239,68,68,.3);
  }
  #itemsTable tbody tr td.col-subtotal {
    background: linear-gradient(135deg, #eef2ff, #e0e7ff);
    border: 1px solid #c7d2fe !important; border-radius: 10px;
    padding: 10px 14px !important; text-align: left !important;
    font-size: 14px; color: #4f46e5; font-weight: 700;
  }
  #itemsTable tbody tr td.col-subtotal::before { color: #6366f1; margin-bottom: 2px; }
  #itemsTable .form-control { font-size: 16px !important; padding: 10px 12px; min-height: 42px; }
  #btnAddItem {
    width: 100% !important; padding: 13px !important; font-size: 14px !important;
    border-radius: 10px !important; border-style: dashed !important;
    border-width: 2px !important; color: var(--primary) !important;
  }
  .total-box { padding: 14px !important; }

  /* Sembunyikan dropdown desktop, tampilkan tombol trigger */
  .searchable-select{display:none}
  .mobile-trigger{display:flex}
}

/* ============================================
   TOTAL DISPLAY
   ============================================ */
.total-box {
  margin-top: 16px; padding-top: 16px;
  border-top: 2px dashed #e2e8f0;
}
.total-sub {
  display: flex; justify-content: space-between; align-items: center;
  padding: 8px 0; font-size: 13.5px; color: #64748b;
}
.total-sub strong { color: #0f172a; }
.total-main {
  display: flex; justify-content: space-between; align-items: center;
  margin-top: 10px; padding: 14px 18px;
  background: linear-gradient(135deg, #4f46e5, #7c3aed);
  border-radius: 12px; color: #fff;
  box-shadow: 0 6px 20px rgba(79,70,229,.3);
}
.total-main span { font-weight: 700; font-size: 14px; letter-spacing: .5px; }
.total-main strong { font-size: 22px; font-weight: 800; letter-spacing: -.5px; }

/* Mobile trigger button */
.mobile-trigger{
  width:100%;padding:12px 14px;border:1px solid #e2e8f0;border-radius:10px;
  background:#fff;font-size:15px;color:#0f172a;text-align:left;
  align-items:center;gap:10px;cursor:pointer;min-height:46px;
  font-family:inherit;
}
.mobile-trigger:active{background:#f8fafc}
.mobile-trigger .placeholder{flex:1;color:#94a3b8}
.mobile-trigger .value{flex:1;font-weight:600;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mobile-trigger .chevron{color:#94a3b8;font-size:18px;flex-shrink:0}
</style>

<h1><?= $isEdit ? '✏️ Edit Transaksi' : '➕ Transaksi Baru' ?></h1>

<form method="post">

  <!-- ===== INFO TRANSAKSI ===== -->
  <div class="card">
    <div class="row">
      <div class="form-group">
        <label>No. Nota</label>
        <input class="form-control" value="<?= $isEdit ? htmlspecialchars($trx['no_nota']) : '(otomatis)' ?>" disabled>
      </div>
      <div class="form-group">
        <label>Tanggal *</label>
        <input type="date" name="tanggal" class="form-control" required value="<?= htmlspecialchars($trx['tanggal']) ?>">
      </div>
      <div class="form-group">
        <label>Pelanggan</label>
        <div style="display:flex;gap:8px;align-items:stretch">
          <!-- DESKTOP: Searchable dropdown -->
          <div class="searchable-select" style="flex:1" id="pelangganSelect">
            <div class="ss-input-wrap">
              <input type="text"
                     id="pelangganInput"
                     class="form-control"
                     placeholder="Ketik nama atau nomor HP untuk cari..."
                     autocomplete="off"
                     value="<?= htmlspecialchars($selectedPelangganNama) ?>">
              <button type="button" class="ss-clear <?= $selectedPelangganNama ? 'show' : '' ?>"
                      id="ssClear" title="Kosongkan" onclick="clearPelanggan()">×</button>
            </div>
            <div class="ss-dropdown" id="pelangganDropdown"></div>
          </div>

          <!-- MOBILE: Tombol trigger picker -->
          <button type="button" class="mobile-trigger" id="mobileTrigger" onclick="openPicker()">
            <span id="mobileTriggerText" class="<?= $selectedPelangganNama ? 'value' : 'placeholder' ?>">
              <?= $selectedPelangganNama ? htmlspecialchars($selectedPelangganNama) : 'Pilih pelanggan...' ?>
            </span>
            <span class="chevron">›</span>
          </button>

          <input type="hidden" name="pelanggan_id" id="pelangganId"
                 value="<?= (int)($trx['pelanggan_id'] ?? 0) ?: '' ?>">

          <button type="button" class="btn btn-primary"
                  onclick="openPelangganModal()"
                  style="flex-shrink:0;white-space:nowrap;padding:0 16px;font-weight:700"
                  title="Tambah pelanggan baru">
            + Baru
          </button>
        </div>
        <small class="text-muted" style="display:block;margin-top:6px">
          Kosongkan untuk <strong>Tanpa nama / umum</strong>
        </small>
      </div>
    </div>
  </div>

  <!-- ===== ITEM PENGERJAAN ===== -->
  <div class="card" id="itemsWrap">
    <h3 style="font-size:15px;margin-bottom:12px">Item Pengerjaan</h3>
    <table id="itemsTable">
      <thead>
        <tr>
          <th>Nama Item</th>
          <th>Bahan</th>
          <th style="width:80px">P (cm)</th>
          <th style="width:80px">L (cm)</th>
          <th style="width:70px">Qty</th>
          <th style="width:130px">Harga</th>
          <th class="text-right">Subtotal</th>
          <th></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <button type="button" id="btnAddItem" class="btn btn-outline btn-sm mt-2" onclick="addRow()">+ Tambah Item</button>
  </div>

  <!-- ===== DISKON & TOTAL ===== -->
  <div class="card">
    <div class="row">
      <div class="form-group">
        <label>Diskon (Rp)</label>
        <input type="number" name="diskon" id="diskon" class="form-control" value="<?= (float)($trx['diskon'] ?? 0) ?>" oninput="recalc()">
      </div>
      <?php if (!$isEdit): ?>
        <div class="form-group">
          <label>Bayar Awal / DP (Rp)</label>
          <input type="number" name="bayar_awal" class="form-control" value="0">
        </div>
      <?php endif; ?>
      <div class="form-group">
        <label>Catatan</label>
        <input name="catatan" class="form-control" value="<?= htmlspecialchars($trx['catatan'] ?? '') ?>" placeholder="Opsional">
      </div>
    </div>

    <div class="total-box">
      <div class="total-sub">
        <span>Subtotal</span>
        <strong id="subtotalText">Rp 0</strong>
      </div>
      <div class="total-main">
        <span>TOTAL</span>
        <strong id="totalText">Rp 0</strong>
      </div>
    </div>
  </div>

  <!-- ===== ACTION ===== -->
  <div class="d-flex">
    <button class="btn btn-primary">💾 Simpan</button>
    <a href="transaksi.php" class="btn btn-outline">Batal</a>
  </div>

</form>

<!-- ============================================
     MOBILE PICKER (FULLSCREEN)
     ============================================ -->
<div class="picker-overlay" id="pickerOverlay">
  <div class="picker-header">
    <button type="button" class="picker-back" onclick="closePicker()" aria-label="Kembali">←</button>
    <div class="picker-title">Pilih Pelanggan</div>
    <button type="button" class="picker-add" onclick="closePicker();openPelangganModal()">+ Baru</button>
  </div>
  <div class="picker-search-wrap">
    <input type="search" class="picker-search" id="pickerSearch"
           placeholder="Cari nama atau nomor HP..."
           autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
  </div>
  <div class="picker-list" id="pickerList"></div>
</div>

<!-- ============================================
     MODAL: TAMBAH PELANGGAN BARU
     ============================================ -->
<div class="modal-backdrop" id="modalPelanggan" onclick="if(event.target===this)closePelangganModal()">
  <div class="modal-box" role="dialog" aria-modal="true">
    <div class="modal-header">
      <h3>👤 Pelanggan Baru</h3>
      <button type="button" class="modal-close" onclick="closePelangganModal()" aria-label="Tutup">×</button>
    </div>
    <form id="formPelangganBaru" onsubmit="submitPelangganBaru(event)">
      <div class="form-group">
        <label>Nama *</label>
        <input name="nama" id="modalNama" class="form-control" required autocomplete="off" maxlength="100">
      </div>
      <div class="form-group">
        <label>Telepon / WhatsApp</label>
        <input name="telepon" class="form-control" placeholder="081234567890" inputmode="tel" autocomplete="off" maxlength="30">
      </div>
      <div class="form-group">
        <label>Alamat</label>
        <textarea name="alamat" class="form-control" rows="2" autocomplete="off"></textarea>
      </div>
      <div class="modal-error" id="modalError"></div>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" onclick="closePelangganModal()">Batal</button>
        <button type="submit" id="btnSimpanPelanggan" class="btn btn-primary">✓ Simpan</button>
      </div>
    </form>
  </div>
</div>

<script>
/* ============================================
   DATA PELANGGAN
   ============================================ */
const pelangganList = <?= json_encode($pelangganList, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const pelangganInput    = document.getElementById('pelangganInput');
const pelangganIdInput  = document.getElementById('pelangganId');
const pelangganDropdown = document.getElementById('pelangganDropdown');
const ssClearBtn        = document.getElementById('ssClear');
const mobileTriggerText = document.getElementById('mobileTriggerText');
const pickerSearchEl    = document.getElementById('pickerSearch');
const pickerListEl      = document.getElementById('pickerList');

let activeIndex = -1;

function isMobile(){ return window.matchMedia('(max-width: 700px)').matches; }

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[c]));
}

/* ============================================
   FILTER HELPER — dipakai desktop & mobile
   ============================================ */
function filterPelanggan(query) {
  const q = (query || '').toLowerCase().trim();
  const qDigits = q.replace(/\D/g, '');

  if (!q) return pelangganList;

  return pelangganList.filter(p => {
    const nama    = (p.nama    || '').toLowerCase();
    const telepon = (p.telepon || '').replace(/\D/g, '');

    // Cocok by nama (kalau ada huruf)
    if (nama.includes(q)) return true;

    // Cocok by telepon — HANYA kalau query mengandung digit
    if (qDigits.length > 0 && telepon.includes(qDigits)) return true;

    return false;
  });
}

/* ============================================
   DESKTOP DROPDOWN
   ============================================ */
function renderDropdown(filter = '') {
  const filtered = filterPelanggan(filter);
  const selectedId = pelangganIdInput.value;

  if (filtered.length === 0) {
    pelangganDropdown.innerHTML = `<div class="ss-empty">
      <strong>Pelanggan tidak ditemukan</strong>
      Klik "+ Baru" untuk menambahkan
    </div>`;
  } else {
    let html = `<div class="ss-hint"><span>${filtered.length} hasil</span><span>↑↓ navigasi · Enter pilih</span></div>`;
    html += filtered.slice(0, 50).map(p => {
      const isSelected = String(p.id) === String(selectedId);
      return `<div class="ss-item ${isSelected ? 'selected' : ''}" data-id="${p.id}">
        <div class="ss-item-left">
          <div class="ss-item-name">${escapeHtml(p.nama)}</div>
          ${p.telepon ? `<div class="ss-item-meta">📞 ${escapeHtml(p.telepon)}</div>` : ''}
        </div>
        <span class="ss-item-mark">✓</span>
      </div>`;
    }).join('');
    pelangganDropdown.innerHTML = html;

    pelangganDropdown.querySelectorAll('.ss-item').forEach((el, idx) => {
      el.addEventListener('click', () => {
        const id = el.dataset.id;
        const p = pelangganList.find(x => String(x.id) === String(id));
        if (p) selectPelanggan(p.id, p.nama);
      });
      el.addEventListener('mouseenter', () => {
        activeIndex = idx;
        updateActiveItem();
      });
    });
  }
  activeIndex = -1;
}

function updateActiveItem() {
  const items = pelangganDropdown.querySelectorAll('.ss-item');
  items.forEach((el, i) => el.classList.toggle('active', i === activeIndex));
  if (activeIndex >= 0 && items[activeIndex]) {
    items[activeIndex].scrollIntoView({ block: 'nearest' });
  }
}

function showDropdown(){ renderDropdown(''); pelangganDropdown.classList.add('show'); }
function hideDropdown(){ pelangganDropdown.classList.remove('show'); activeIndex = -1; }

if (pelangganInput) {
  pelangganInput.addEventListener('focus', () => { if (!isMobile()) showDropdown(); });

  const handleInput = () => {
    if (isMobile()) return;
    pelangganIdInput.value = '';
    ssClearBtn.classList.toggle('show', pelangganInput.value.trim() !== '');
    renderDropdown(pelangganInput.value);
    pelangganDropdown.classList.add('show');
  };
  pelangganInput.addEventListener('input', handleInput);
  pelangganInput.addEventListener('keyup', handleInput);
  pelangganInput.addEventListener('compositionend', handleInput);

  pelangganInput.addEventListener('keydown', (e) => {
    const items = pelangganDropdown.querySelectorAll('.ss-item');
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (!pelangganDropdown.classList.contains('show')) showDropdown();
      activeIndex = Math.min(activeIndex + 1, items.length - 1);
      updateActiveItem();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = Math.max(activeIndex - 1, 0);
      updateActiveItem();
    } else if (e.key === 'Enter') {
      if (activeIndex >= 0 && items[activeIndex]) {
        e.preventDefault();
        const el = items[activeIndex];
        const p = pelangganList.find(x => String(x.id) === el.dataset.id);
        if (p) selectPelanggan(p.id, p.nama);
      }
    } else if (e.key === 'Escape') {
      hideDropdown(); pelangganInput.blur();
    }
  });
}

document.addEventListener('click', (e) => {
  if (!e.target.closest('#pelangganSelect')) hideDropdown();
});

/* ============================================
   MOBILE PICKER
   ============================================ */
function openPicker(){
  document.getElementById('pickerOverlay').classList.add('show');
  document.body.style.overflow = 'hidden';
  pickerSearchEl.value = '';
  renderPickerList('');
  setTimeout(() => pickerSearchEl.focus(), 150);
}

function closePicker(){
  document.getElementById('pickerOverlay').classList.remove('show');
  document.body.style.overflow = '';
}

function renderPickerList(filter){
  const filtered = filterPelanggan(filter);
  const selectedId = pelangganIdInput.value;

  let html = '';

  // Badge pelanggan terpilih
  if (selectedId) {
    const cur = pelangganList.find(x => String(x.id) === String(selectedId));
    if (cur) {
      html += `<div class="picker-selected-badge">
        <span>✓ Terpilih: <strong>${escapeHtml(cur.nama)}</strong></span>
        <button type="button" onclick="clearPelanggan();renderPickerList(document.getElementById('pickerSearch').value)">Hapus</button>
      </div>`;
    }
  }

  if (filtered.length === 0) {
    html += `<div class="picker-empty">
      <div class="picker-empty-icon">🔍</div>
      <strong>Tidak ditemukan</strong>
      <p>Coba kata kunci lain, atau tap "+ Baru" untuk tambah pelanggan</p>
    </div>`;
    pickerListEl.innerHTML = html;
    return;
  }

  html += filtered.map(p => {
    const initial = (p.nama || '?').charAt(0).toUpperCase();
    const isSelected = String(p.id) === String(selectedId);
    return `<div class="picker-item ${isSelected ? 'selected' : ''}" data-id="${p.id}">
      <div class="picker-avatar">${initial}</div>
      <div class="picker-info">
        <div class="picker-name">${escapeHtml(p.nama)}</div>
        ${p.telepon ? `<div class="picker-meta">📞 ${escapeHtml(p.telepon)}</div>` : ''}
      </div>
      ${isSelected ? '<span class="picker-check">✓</span>' : ''}
    </div>`;
  }).join('');

  pickerListEl.innerHTML = html;

  pickerListEl.querySelectorAll('.picker-item').forEach(el => {
    el.addEventListener('click', () => {
      const id = el.dataset.id;
      const p = pelangganList.find(x => String(x.id) === String(id));
      if (p) {
        selectPelanggan(p.id, p.nama);
        closePicker();
      }
    });
  });
}

/* 3 event sekaligus untuk keandalan mobile keyboard */
pickerSearchEl.addEventListener('input',  (e) => renderPickerList(e.target.value));
pickerSearchEl.addEventListener('keyup',  (e) => renderPickerList(e.target.value));
pickerSearchEl.addEventListener('search', (e) => renderPickerList(e.target.value));
pickerSearchEl.addEventListener('compositionend', (e) => renderPickerList(e.target.value));

/* ============================================
   SELECT / CLEAR
   ============================================ */
function selectPelanggan(id, nama) {
  pelangganIdInput.value = id;
  if (pelangganInput) {
    pelangganInput.value = nama;
    ssClearBtn.classList.add('show');
  }
  if (mobileTriggerText) {
    mobileTriggerText.textContent = nama;
    mobileTriggerText.classList.remove('placeholder');
    mobileTriggerText.classList.add('value');
  }
  hideDropdown();
}

function clearPelanggan() {
  pelangganIdInput.value = '';
  if (pelangganInput) pelangganInput.value = '';
  if (ssClearBtn) ssClearBtn.classList.remove('show');
  if (mobileTriggerText) {
    mobileTriggerText.textContent = 'Pilih pelanggan...';
    mobileTriggerText.classList.remove('value');
    mobileTriggerText.classList.add('placeholder');
  }
  hideDropdown();
}

/* ============================================
   MODAL PELANGGAN BARU
   ============================================ */
function openPelangganModal() {
  document.getElementById('modalPelanggan').classList.add('show');
  document.body.style.overflow = 'hidden';
  setTimeout(() => document.getElementById('modalNama').focus(), 100);
}

function closePelangganModal() {
  document.getElementById('modalPelanggan').classList.remove('show');
  document.body.style.overflow = '';
  document.getElementById('formPelangganBaru').reset();
  document.getElementById('modalError').style.display = 'none';
}

async function submitPelangganBaru(e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('btnSimpanPelanggan');
  const errEl = document.getElementById('modalError');
  errEl.style.display = 'none';

  const nama = document.getElementById('modalNama').value.trim();
  if (!nama) {
    errEl.textContent = 'Nama wajib diisi';
    errEl.style.display = 'block';
    return;
  }

  btn.disabled = true;
  const originalText = btn.textContent;
  btn.textContent = '⏳ Menyimpan...';

  try {
    const fd = new FormData(form);
    const res = await fetch('pelanggan_ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Gagal menyimpan');

    const p = data.pelanggan;
    pelangganList.push(p);
    pelangganList.sort((a, b) => (a.nama || '').localeCompare(b.nama || '', 'id'));

    selectPelanggan(p.id, p.nama);
    closePelangganModal();
  } catch (err) {
    errEl.textContent = err.message;
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = originalText;
  }
}

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    if (document.getElementById('modalPelanggan').classList.contains('show')) closePelangganModal();
    else if (document.getElementById('pickerOverlay').classList.contains('show')) closePicker();
    else hideDropdown();
  }
});

/* ============================================
   ITEM ROWS
   ============================================ */
function fmt(n){ return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }

function addRow(d = {}) {
  const tbody = document.querySelector('#itemsTable tbody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td class="col-nama" data-label="Nama Item *">
      <input name="nama_item[]" class="form-control" required value="${escapeAttr(d.nama_item||'')}" placeholder="Misal: Gelang stainless">
    </td>
    <td class="col-bahan" data-label="Bahan">
      <input name="bahan[]" class="form-control" value="${escapeAttr(d.bahan||'')}" placeholder="Misal: Stainless 316">
    </td>
    <td class="col-panjang" data-label="Panjang (cm)">
      <input type="number" step="0.01" name="panjang[]" class="form-control" value="${d.panjang||0}">
    </td>
    <td class="col-lebar" data-label="Lebar (cm)">
      <input type="number" step="0.01" name="lebar[]" class="form-control" value="${d.lebar||0}">
    </td>
    <td class="col-qty" data-label="Qty">
      <input type="number" name="qty[]" class="form-control qty" value="${d.qty||1}" min="1" oninput="recalc()">
    </td>
    <td class="col-harga" data-label="Harga (Rp)">
      <input type="number" step="0.01" name="harga[]" class="form-control harga" value="${d.harga||0}" min="0" oninput="recalc()">
    </td>
    <td class="col-subtotal text-right subtotal-cell" data-label="Subtotal">Rp 0</td>
    <td class="col-action">
      <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();recalc()" title="Hapus item">×</button>
    </td>
  `;
  tbody.appendChild(tr);
  recalc();
}

function escapeAttr(s) {
  return String(s).replace(/[&<>"']/g, c => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[c]));
}

function recalc() {
  let sub = 0;
  document.querySelectorAll('#itemsTable tbody tr').forEach(tr => {
    const q = parseFloat(tr.querySelector('.qty').value) || 0;
    const h = parseFloat(tr.querySelector('.harga').value) || 0;
    const s = q * h;
    tr.querySelector('.subtotal-cell').textContent = fmt(s);
    sub += s;
  });
  const diskon = parseFloat(document.getElementById('diskon').value) || 0;
  const total = Math.max(sub - diskon, 0);
  document.getElementById('subtotalText').textContent = fmt(sub);
  document.getElementById('totalText').textContent = fmt(total);
}

<?php if ($items): foreach ($items as $it): ?>
addRow(<?= json_encode($it) ?>);
<?php endforeach; else: ?>
addRow();
<?php endif; ?>
</script>

<?php include 'footer.php'; ?>