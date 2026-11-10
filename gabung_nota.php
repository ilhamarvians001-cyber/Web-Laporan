<?php
require_once 'config.php';
$title = 'Gabung Nota Hutang';

$pelanggan_id = (int)($_GET['pelanggan_id'] ?? 0);

/* ============ BAYAR SEMUA HANDLER ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bayar_semua'])) {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    $tanggal = $_POST['tanggal_bayar'] ?? date('Y-m-d');
    $jumlah = (float)($_POST['jumlah_bayar'] ?? 0);
    $keterangan = trim($_POST['keterangan_bayar'] ?? 'Pembayaran gabungan');

    if ($jumlah <= 0 || empty($ids)) {
        flash('Pilih nota & isi jumlah pembayaran', 'danger');
        redirect("gabung_nota.php?pelanggan_id=$pelanggan_id");
    }

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            SELECT id, sisa FROM transaksi
            WHERE id IN ($placeholders) AND pelanggan_id = ? AND status = 'hutang'
            ORDER BY tanggal ASC, id ASC
        ");
        $stmt->execute([...$ids, $pelanggan_id]);
        $trxList = $stmt->fetchAll();

        if (empty($trxList)) {
            flash('Tidak ada transaksi hutang valid', 'danger');
            redirect("gabung_nota.php?pelanggan_id=$pelanggan_id");
        }

        $totalSisa = array_sum(array_column($trxList, 'sisa'));
        if ($jumlah > $totalSisa) $jumlah = $totalSisa;

        $pdo->beginTransaction();
        $sisaBayar = $jumlah;
        $terpakai = 0;
        foreach ($trxList as $t) {
            if ($sisaBayar <= 0) break;
            $bayarIni = min($sisaBayar, (float)$t['sisa']);
            if ($bayarIni <= 0) continue;

            $pdo->prepare("INSERT INTO pembayaran (transaksi_id, tanggal, jumlah, keterangan) VALUES (?,?,?,?)")
                ->execute([$t['id'], $tanggal, $bayarIni, $keterangan]);
            recalcTransaksi($pdo, $t['id']);

            $sisaBayar -= $bayarIni;
            $terpakai += $bayarIni;
        }
        $pdo->commit();
        flash('Pembayaran gabungan berhasil: ' . rupiah($terpakai));
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('Gagal: ' . $e->getMessage(), 'danger');
    }
    redirect("gabung_nota.php?pelanggan_id=$pelanggan_id");
}

/* ============ LOAD DATA ============ */
$pelanggan = null;
$hutangList = [];

if ($pelanggan_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id=?");
    $stmt->execute([$pelanggan_id]);
    $pelanggan = $stmt->fetch();

    if ($pelanggan) {
        $stmt = $pdo->prepare("
            SELECT t.* FROM transaksi t
            WHERE t.pelanggan_id = ? AND t.status = 'hutang'
            ORDER BY t.tanggal ASC, t.id ASC
        ");
        $stmt->execute([$pelanggan_id]);
        $hutangList = $stmt->fetchAll();
    }
}

$customers = $pdo->query("
    SELECT p.id, p.nama, p.telepon,
           COUNT(t.id) as jumlah_nota,
           COALESCE(SUM(t.sisa), 0) as total_hutang
    FROM pelanggan p
    INNER JOIN transaksi t ON t.pelanggan_id = p.id AND t.status = 'hutang'
    GROUP BY p.id
    HAVING jumlah_nota > 0
    ORDER BY total_hutang DESC
")->fetchAll();

$totalHutangSemua = array_sum(array_column($customers, 'total_hutang'));

include 'header.php';
?>

<style>
/* ==== GABUNG NOTA STYLES ==== */
.gn-summary{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
  gap:12px;margin-bottom:20px;
}
.gn-stat{
  background:#fff;border:1px solid #e2e8f0;border-radius:12px;
  padding:14px 18px;
}
.gn-stat-label{font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;font-weight:700;margin-bottom:6px}
.gn-stat-value{font-size:20px;font-weight:800;color:#0f172a;line-height:1.2}
.gn-stat-value.danger{color:#dc2626}
.gn-stat-value.primary{color:#4f46e5}

.gn-customer-list{display:flex;flex-direction:column;gap:10px}
.gn-customer{
  display:flex;justify-content:space-between;align-items:center;
  padding:14px 18px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;
  text-decoration:none;color:inherit;transition:.15s;gap:14px;
}
.gn-customer:hover{border-color:#c7d2fe;background:#fafbff;transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.04)}
.gn-customer-info{flex:1;min-width:0}
.gn-customer-name{font-size:14.5px;font-weight:700;color:#0f172a;margin-bottom:3px}
.gn-customer-meta{font-size:12px;color:#64748b}
.gn-customer-total{text-align:right;flex-shrink:0}
.gn-customer-total strong{display:block;font-size:15px;color:#dc2626;font-weight:800;font-variant-numeric:tabular-nums}
.gn-customer-total small{font-size:11px;color:#94a3b8}

/* ==== NOTA CARD (checklist) ==== */
.gn-nota-list{display:flex;flex-direction:column;gap:10px;margin-bottom:20px}
.gn-nota-card{
  display:flex;gap:12px;align-items:flex-start;
  padding:14px 16px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;
  transition:.15s;cursor:pointer;
}
.gn-nota-card:hover{border-color:#c7d2fe;background:#fafbff}
.gn-nota-card.selected{border-color:#6366f1;background:#eef2ff;box-shadow:0 0 0 3px rgba(99,102,241,.1)}
.gn-nota-card input[type="checkbox"]{width:20px;height:20px;margin-top:2px;cursor:pointer;flex-shrink:0;accent-color:#6366f1}
.gn-nota-info{flex:1;min-width:0}
.gn-nota-top{display:flex;justify-content:space-between;gap:12px;align-items:baseline;flex-wrap:wrap;margin-bottom:4px}
.gn-nota-no{font-weight:700;font-size:14px;color:#0f172a}
.gn-nota-sisa{font-weight:800;font-size:14px;color:#dc2626;font-variant-numeric:tabular-nums}
.gn-nota-meta{font-size:12px;color:#64748b}
.gn-nota-meta strong{color:#334155;font-weight:600}

/* ==== TOTAL BOX ==== */
.gn-total-box{
  background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;
  padding:18px 22px;border-radius:14px;
  display:flex;justify-content:space-between;align-items:center;
  margin-bottom:16px;box-shadow:0 8px 24px rgba(79,70,229,.25);
}
.gn-total-box .label{font-size:12px;opacity:.9;font-weight:600;letter-spacing:.05em;text-transform:uppercase}
.gn-total-box .value{font-size:24px;font-weight:800;letter-spacing:-.02em;font-variant-numeric:tabular-nums}

/* ==== ACTION BUTTONS ==== */
.gn-actions{
  display:flex;gap:10px;flex-wrap:wrap;
}
.gn-actions .btn{flex:1;min-width:140px}

/* ==== MODAL ==== */
.modal-backdrop{
  display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);
  z-index:1000;align-items:center;justify-content:center;padding:20px;
  backdrop-filter:blur(2px);
}
.modal-backdrop.show{display:flex}
.modal-box{
  background:#fff;border-radius:16px;padding:24px;
  width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.3);
  max-height:90vh;overflow-y:auto;
}
.modal-box h3{font-size:18px;margin-bottom:6px;font-weight:700}
.modal-box p.sub{font-size:13px;color:#64748b;margin-bottom:18px}
.modal-box .btn-block{width:100%;margin-top:8px}

@media(max-width:700px){
  .gn-customer{flex-direction:column;align-items:flex-start;gap:8px}
  .gn-customer-total{text-align:left;width:100%}
  .gn-total-box .value{font-size:20px}
  .gn-actions .btn{flex:1 1 100%}
}
</style>

<div class="d-flex justify-between mb-2">
  <h1>🔗 Gabung Nota Hutang</h1>
  <a href="transaksi.php?status=hutang" class="btn btn-outline">← Kembali</a>
</div>

<?php if (!$pelanggan): ?>

  <!-- ==============================================
       MODE 1: PILIH PELANGGAN
  ============================================== -->
  <div class="gn-summary">
    <div class="gn-stat">
      <div class="gn-stat-label">Total Pelanggan Berhutang</div>
      <div class="gn-stat-value primary"><?= count($customers) ?></div>
    </div>
    <div class="gn-stat">
      <div class="gn-stat-label">Total Nilai Hutang</div>
      <div class="gn-stat-value danger"><?= rupiah($totalHutangSemua) ?></div>
    </div>
  </div>

  <?php if (empty($customers)): ?>
    <div class="card" style="text-align:center;padding:40px 20px">
      <div style="font-size:48px;margin-bottom:12px">🎉</div>
      <h3 style="font-size:16px;margin-bottom:6px">Tidak ada hutang!</h3>
      <p class="text-muted" style="font-size:13px">Semua transaksi sudah lunas.</p>
    </div>
  <?php else: ?>
    <div class="card">
      <h3 style="font-size:15px;margin-bottom:12px">Pilih Pelanggan</h3>
      <div class="gn-customer-list">
        <?php foreach ($customers as $c): ?>
          <a href="gabung_nota.php?pelanggan_id=<?= $c['id'] ?>" class="gn-customer">
            <div class="gn-customer-info">
              <div class="gn-customer-name"><?= htmlspecialchars($c['nama']) ?></div>
              <div class="gn-customer-meta">
                <?= (int)$c['jumlah_nota'] ?> nota hutang
                <?php if ($c['telepon']): ?> · <?= htmlspecialchars($c['telepon']) ?><?php endif; ?>
              </div>
            </div>
            <div class="gn-customer-total">
              <strong><?= rupiah($c['total_hutang']) ?></strong>
              <small>total sisa</small>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

<?php else: ?>

  <!-- ==============================================
       MODE 2: PILIH NOTA UNTUK DIGABUNG
  ============================================== -->
  <div class="gn-summary">
    <div class="gn-stat">
      <div class="gn-stat-label">Pelanggan</div>
      <div class="gn-stat-value" style="font-size:15px"><?= htmlspecialchars($pelanggan['nama']) ?></div>
    </div>
    <div class="gn-stat">
      <div class="gn-stat-label">Jumlah Nota Hutang</div>
      <div class="gn-stat-value primary"><?= count($hutangList) ?></div>
    </div>
    <div class="gn-stat">
      <div class="gn-stat-label">Total Sisa</div>
      <div class="gn-stat-value danger">
        <?= rupiah(array_sum(array_column($hutangList, 'sisa'))) ?>
      </div>
    </div>
  </div>

  <?php if (empty($hutangList)): ?>
    <div class="card" style="text-align:center;padding:40px 20px">
      <div style="font-size:48px;margin-bottom:12px">✅</div>
      <h3 style="font-size:16px;margin-bottom:6px">Tidak ada hutang</h3>
      <p class="text-muted" style="font-size:13px">Pelanggan ini sudah lunas semua.</p>
      <a href="gabung_nota.php" class="btn btn-primary btn-sm" style="margin-top:14px">← Pilih Pelanggan Lain</a>
    </div>
  <?php else: ?>

    <div class="card">
      <form id="formGabung" method="get" action="nota_gabung.php" target="_blank">
        <input type="hidden" name="pelanggan_id" value="<?= $pelanggan_id ?>">

        <div class="d-flex justify-between mb-2">
          <h3 style="font-size:15px">Pilih Nota yang Digabung</h3>
          <button type="button" class="btn btn-outline btn-sm" onclick="toggleAll()">☑ Pilih Semua</button>
        </div>

        <div class="gn-nota-list">
          <?php foreach ($hutangList as $h): ?>
            <label class="gn-nota-card selected" id="card-<?= $h['id'] ?>">
              <input type="checkbox" name="ids[]" value="<?= $h['id'] ?>"
                     checked
                     onchange="toggleCard(<?= $h['id'] ?>)"
                     data-sisa="<?= $h['sisa'] ?>"
                     class="check-nota">
              <div class="gn-nota-info">
                <div class="gn-nota-top">
                  <span class="gn-nota-no"><?= htmlspecialchars($h['no_nota']) ?></span>
                  <span class="gn-nota-sisa"><?= rupiah($h['sisa']) ?></span>
                </div>
                <div class="gn-nota-meta">
                  <strong><?= tgl_indo($h['tanggal']) ?></strong> ·
                  Total: <?= rupiah($h['total']) ?> ·
                  Dibayar: <?= rupiah($h['bayar']) ?>
                  <?php if ($h['catatan']): ?>
                    · <em><?= htmlspecialchars(mb_strimwidth($h['catatan'], 0, 30, '...')) ?></em>
                  <?php endif; ?>
                </div>
              </div>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="gn-total-box">
          <span class="label">Total Terpilih</span>
          <span class="value" id="totalTerpilih"><?= rupiah(array_sum(array_column($hutangList, 'sisa'))) ?></span>
        </div>

        <div class="gn-actions">
          <button type="submit" class="btn btn-primary">🖨️ Cetak Rekap Gabungan</button>
          <button type="button" class="btn btn-success" onclick="openBayarSemua()">💰 Bayar Semua</button>
          <a href="gabung_nota.php" class="btn btn-outline">← Batal</a>
        </div>
      </form>
    </div>

    <!-- MODAL BAYAR SEMUA -->
    <div class="modal-backdrop" id="modalBayar">
      <div class="modal-box">
        <h3>💰 Bayar Semua Nota</h3>
        <p class="sub">Pembayaran dibagi dari nota <strong>terlama</strong> terlebih dahulu.</p>

        <form method="post" id="formBayar">
          <input type="hidden" name="bayar_semua" value="1">
          <div id="hiddenIds"></div>

          <div class="form-group">
            <label>Tanggal Bayar</label>
            <input type="date" name="tanggal_bayar" class="form-control" required value="<?= date('Y-m-d') ?>">
          </div>

          <div class="form-group">
            <label>Jumlah Pembayaran (Rp)</label>
            <input type="number" name="jumlah_bayar" id="jumlahBayar" class="form-control" required min="1" step="1">
            <small class="text-muted">
              Maks: <strong id="maksBayar"><?= rupiah(array_sum(array_column($hutangList, 'sisa'))) ?></strong> ·
              <a href="#" onclick="isiLunas();return false;" style="color:var(--primary);font-weight:600">Isi lunas</a>
            </small>
          </div>

          <div class="form-group">
            <label>Keterangan (opsional)</label>
            <input name="keterangan_bayar" class="form-control" placeholder="Misal: Pelunasan 3 nota">
          </div>

          <button type="submit" class="btn btn-success btn-block">✓ Catat Pembayaran</button>
          <button type="button" class="btn btn-outline btn-block" onclick="closeBayarSemua()">Batal</button>
        </form>
      </div>
    </div>

    <script>
    function toggleCard(id){
      const card = document.getElementById('card-' + id);
      const check = card.querySelector('.check-nota');
      if (check.checked) card.classList.add('selected');
      else card.classList.remove('selected');
      recalcTotal();
    }

    function toggleAll(){
      const checks = document.querySelectorAll('.check-nota');
      const allChecked = [...checks].every(c => c.checked);
      checks.forEach(c => {
        c.checked = !allChecked;
        toggleCard(c.value);
      });
    }

    function recalcTotal(){
      let total = 0;
      document.querySelectorAll('.check-nota').forEach(c => {
        if (c.checked) total += parseFloat(c.dataset.sisa) || 0;
      });
      document.getElementById('totalTerpilih').textContent = 'Rp ' + Math.round(total).toLocaleString('id-ID');
      document.getElementById('maksBayar').textContent = 'Rp ' + Math.round(total).toLocaleString('id-ID');
      document.getElementById('jumlahBayar').max = Math.round(total);
    }

    function openBayarSemua(){
      const checked = [...document.querySelectorAll('.check-nota')].filter(c => c.checked);
      if (checked.length === 0) { alert('Pilih minimal 1 nota terlebih dahulu'); return; }

      const container = document.getElementById('hiddenIds');
      container.innerHTML = '';
      let total = 0;
      checked.forEach(c => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'ids[]';
        inp.value = c.value;
        container.appendChild(inp);
        total += parseFloat(c.dataset.sisa) || 0;
      });

      document.getElementById('jumlahBayar').value = Math.round(total);
      document.getElementById('maksBayar').textContent = 'Rp ' + Math.round(total).toLocaleString('id-ID');
      document.getElementById('modalBayar').classList.add('show');
    }

    function closeBayarSemua(){
      document.getElementById('modalBayar').classList.remove('show');
    }

    function isiLunas(){
      document.getElementById('jumlahBayar').value = document.getElementById('jumlahBayar').max;
    }

    // Recalc saat load awal
    document.addEventListener('DOMContentLoaded', recalcTotal);
    </script>

  <?php endif; ?>
<?php endif; ?>

<?php include 'footer.php'; ?>