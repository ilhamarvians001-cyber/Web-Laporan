<?php
require_once 'config.php';
$title = 'Pengaturan';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_umum'])) {
    saveSetting($pdo, 'nama_toko', trim($_POST['nama_toko']));
    saveSetting($pdo, 'alamat_toko', trim($_POST['alamat_toko']));
    saveSetting($pdo, 'telp_toko', trim($_POST['telp_toko']));
    flash('Pengaturan berhasil disimpan');
    redirect('pengaturan.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_logo'])) {
    if (!empty($_FILES['logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png','jpg','jpeg','webp','svg'])) {
            flash('Format logo harus PNG/JPG/WEBP/SVG', 'danger');
        } elseif ($_FILES['logo']['size'] > 2*1024*1024) {
            flash('Ukuran logo maksimal 2MB', 'danger');
        } else {
            $newName = 'logo_'.time().'.'.$ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__.'/uploads/'.$newName)) {
                $old = $settings['logo'] ?? '';
                if ($old && file_exists(__DIR__.'/uploads/'.$old)) @unlink(__DIR__.'/uploads/'.$old);
                saveSetting($pdo, 'logo', $newName);
                flash('Logo berhasil diperbarui');
            } else {
                flash('Gagal upload file', 'danger');
            }
        }
    }
    redirect('pengaturan.php');
}

if (isset($_GET['hapus_logo'])) {
    $old = $settings['logo'] ?? '';
    if ($old && file_exists(__DIR__.'/uploads/'.$old)) @unlink(__DIR__.'/uploads/'.$old);
    saveSetting($pdo, 'logo', '');
    flash('Logo dihapus');
    redirect('pengaturan.php');
}

/* ============ SIMPAN WATERMARK ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_watermark'])) {
    saveSetting($pdo, 'watermark_text', trim($_POST['watermark_text'] ?? ''));
    $opacity = (int)($_POST['watermark_opacity'] ?? 6);
    $opacity = max(3, min(20, $opacity));
    saveSetting($pdo, 'watermark_opacity', (string)$opacity);
    saveSetting($pdo, 'watermark_lunas_only', !empty($_POST['watermark_lunas_only']) ? '1' : '0');
    flash('Watermark berhasil disimpan');
    redirect('pengaturan.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ubah_password'])) {
    $old  = $_POST['old_password'] ?? '';
    $new  = $_POST['new_password'] ?? '';
    $new2 = $_POST['new_password2'] ?? '';
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    $hash = $stmt->fetchColumn();
    if (!password_verify($old, $hash))     flash('Password lama salah', 'danger');
    elseif (strlen($new) < 6)              flash('Password baru minimal 6 karakter', 'danger');
    elseif ($new !== $new2)                flash('Konfirmasi password tidak sama', 'danger');
    else {
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        flash('Password berhasil diubah');
    }
    redirect('pengaturan.php');
}

include 'header.php';

$wmText      = trim($settings['watermark_text'] ?? '');
$wmOpacity   = (int)($settings['watermark_opacity'] ?? 6);
$wmLunasOnly = !empty($settings['watermark_lunas_only']);
?>

<style>
/* Preview watermark di pengaturan */
.wm-preview{
  position: relative; background: #fff; border: 1px solid #e2e8f0;
  border-radius: 12px; height: 120px; overflow: hidden;
  display: grid; place-items: center;
  background-image:
    linear-gradient(45deg,#f8fafc 25%,transparent 25%),
    linear-gradient(-45deg,#f8fafc 25%,transparent 25%),
    linear-gradient(45deg,transparent 75%,#f8fafc 75%),
    linear-gradient(-45deg,transparent 75%,#f8fafc 75%);
  background-size: 20px 20px;
  background-position: 0 0, 0 10px, 10px -10px, -10px 0;
}
.wm-preview-text{
  transform: rotate(-25deg);
  font-size: 40px; font-weight: 900; letter-spacing: 0.1em;
  color: #18181b; text-transform: uppercase; user-select: none;
  line-height: 1; text-align: center; padding: 0 20px;
  word-break: break-word;
}
.wm-preview-label{
  position: absolute; top: 8px; left: 10px;
  font-size: 9px; letter-spacing: 0.2em; text-transform: uppercase;
  color: #94a3b8; font-weight: 700;
}
</style>

<h1>⚙️ Pengaturan</h1>

<!-- ============ INFO TOKO ============ -->
<div class="card">
  <h3>🏪 Informasi Toko</h3>
  <form method="post">
    <input type="hidden" name="simpan_umum" value="1">
    <div class="row">
      <div class="form-group"><label>Nama Toko</label><input name="nama_toko" class="form-control" required value="<?= htmlspecialchars($settings['nama_toko'] ?? '') ?>"></div>
      <div class="form-group"><label>No. HP / Telp</label><input name="telp_toko" class="form-control" value="<?= htmlspecialchars($settings['telp_toko'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label>Alamat</label><textarea name="alamat_toko" class="form-control" rows="2"><?= htmlspecialchars($settings['alamat_toko'] ?? '') ?></textarea></div>
    <button class="btn btn-primary">💾 Simpan</button>
  </form>
</div>

<!-- ============ LOGO ============ -->
<div class="card">
  <h3>🖼️ Logo Toko</h3>
  <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
    <div style="border:2px dashed var(--border);border-radius:10px;padding:16px;min-width:140px;min-height:100px;display:grid;place-items:center;background:#fafbfc">
      <?php if (LOGO_PATH && file_exists(LOGO_PATH)): ?>
        <img src="<?= LOGO_PATH ?>" alt="Logo" style="max-height:80px;max-width:160px;object-fit:contain">
      <?php else: ?>
        <span class="text-muted" style="font-size:12px">Belum ada logo</span>
      <?php endif; ?>
    </div>
    <div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="upload_logo" value="1">
        <input type="file" name="logo" accept="image/*" required class="form-control" style="max-width:280px;margin-bottom:8px;padding:8px">
        <button class="btn btn-primary btn-sm">📤 Upload Logo</button>
        <?php if (LOGO_PATH): ?>
          <a href="?hapus_logo=1" onclick="return confirm('Hapus logo?')" class="btn btn-danger btn-sm">🗑️ Hapus</a>
        <?php endif; ?>
      </form>
      <p class="text-muted" style="font-size:11px;margin-top:8px">Format: PNG/JPG/SVG · Maks 2MB · Rekomendasi rasio 1:1 atau 4:1</p>
    </div>
  </div>
</div>

<!-- ============ WATERMARK ============ -->
<div class="card">
  <h3>💧 Watermark Nota</h3>
  <p class="text-muted" style="font-size:12.5px;margin-bottom:16px;margin-top:-6px">
    Watermark tampil di belakang nota (samar, tidak mengganggu baca). Berguna sebagai penanda status, misal <strong>LUNAS</strong>, <strong>DP</strong>, <strong>COPY</strong>, dll.
  </p>

  <form method="post">
    <input type="hidden" name="simpan_watermark" value="1">
    <div class="row">
      <div class="form-group">
        <label>Teks Watermark</label>
        <input name="watermark_text" id="wmText"
               class="form-control"
               value="<?= htmlspecialchars($wmText) ?>"
               placeholder="Misal: LUNAS, DP, COPY, PAID"
               maxlength="30"
               oninput="updatePreview()">
        <small class="text-muted">Kosongkan untuk menonaktifkan</small>
      </div>
      <div class="form-group">
        <label>Opacity (%)</label>
        <input type="number" name="watermark_opacity" id="wmOpacity"
               class="form-control"
               min="3" max="20" step="1"
               value="<?= $wmOpacity ?>"
               oninput="updatePreview()">
        <small class="text-muted">3%–20% · Rekomendasi <strong>6%</strong> (samar) atau <strong>12%</strong> (jelas)</small>
      </div>
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:500">
        <input type="checkbox" name="watermark_lunas_only" value="1"
               id="wmLunasOnly" <?= $wmLunasOnly ? 'checked' : '' ?>
               onchange="updatePreview()"
               style="width:18px;height:18px;cursor:pointer">
        <span>Hanya tampil pada nota berstatus <strong>LUNAS</strong></span>
      </label>
    </div>

    <!-- Preview -->
    <div class="form-group" style="margin-top:16px">
      <label>Preview</label>
      <div class="wm-preview">
        <span class="wm-preview-label">Preview Nota</span>
        <div class="wm-preview-text" id="wmPreview" style="<?= $wmText === '' ? 'display:none' : '' ?>">
          <?= htmlspecialchars($wmText ?: 'LUNAS') ?>
        </div>
        <div id="wmPreviewEmpty" style="color:#94a3b8;font-size:12px;<?= $wmText !== '' ? 'display:none' : '' ?>">
          Watermark nonaktif
        </div>
      </div>
    </div>

    <button class="btn btn-primary">💾 Simpan Watermark</button>
  </form>
</div>

<!-- ============ PASSWORD ============ -->
<div class="card">
  <h3>🔑 Ubah Password</h3>
  <form method="post" style="max-width:420px">
    <input type="hidden" name="ubah_password" value="1">
    <div class="form-group"><label>Password Lama</label><input type="password" name="old_password" class="form-control" required></div>
    <div class="form-group"><label>Password Baru</label><input type="password" name="new_password" class="form-control" required></div>
    <div class="form-group"><label>Konfirmasi Password Baru</label><input type="password" name="new_password2" class="form-control" required></div>
    <button class="btn btn-primary">🔒 Ubah Password</button>
  </form>
</div>

<!-- ============ AKUN ============ -->
<div class="card">
  <h3>👤 Akun Saat Ini</h3>
  <p><strong>Username:</strong> <?= htmlspecialchars($_SESSION['username']) ?></p>
  <p><strong>Nama:</strong> <?= htmlspecialchars($_SESSION['nama']) ?></p>
  <p><strong>Role:</strong> <span class="badge" style="background:#eef2ff;color:#4f46e5"><?= htmlspecialchars($_SESSION['role']) ?></span></p>
</div>

<script>
function updatePreview() {
  const text = document.getElementById('wmText').value.trim();
  const opacity = parseInt(document.getElementById('wmOpacity').value) || 6;
  const preview = document.getElementById('wmPreview');
  const empty = document.getElementById('wmPreviewEmpty');

  if (text === '') {
    preview.style.display = 'none';
    empty.style.display = 'block';
  } else {
    preview.style.display = 'block';
    empty.style.display = 'none';
    preview.textContent = text;
    preview.style.opacity = (opacity / 100) * 1.5; // boost visual agar terlihat di preview
  }
}
document.addEventListener('DOMContentLoaded', updatePreview);
</script>

<?php include 'footer.php'; ?>