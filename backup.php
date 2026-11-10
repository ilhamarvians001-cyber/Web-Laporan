<?php
require_once 'config.php';
$title = 'Backup Database';

/* ============ FOLDER BACKUP ============ */
$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
    @file_put_contents($backupDir . '/.htaccess', "Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
    @file_put_contents($backupDir . '/index.php', "<?php http_response_code(403); die('Forbidden');");
}

/* ============ HELPER ============ */
function formatBytes($bytes) {
    $bytes = (int)$bytes;
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 2) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}

/* ============ GENERATE SQL DUMP ============ */
function generateSqlDump(PDO $pdo): string {
    $out  = "-- ============================================\n";
    $out .= "-- Database Backup: " . DB_NAME . "\n";
    $out .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $out .= "-- Tool: Laser Engrave Studio\n";
    $out .= "-- ============================================\n\n";
    $out .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $out .= "SET time_zone = '+00:00';\n";
    $out .= "SET NAMES utf8mb4;\n";
    $out .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")
                  ->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $t) {
        $row = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
        $createSql = $row[1] ?? '';
        $out .= "--\n-- Struktur tabel `$t`\n--\n";
        $out .= "DROP TABLE IF EXISTS `$t`;\n";
        $out .= $createSql . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            $out .= "-- (tidak ada data)\n\n";
            continue;
        }
        $out .= "--\n-- Data `$t` (" . count($rows) . " baris)\n--\n";

        $columns = array_keys($rows[0]);
        $colList = '`' . implode('`, `', $columns) . '`';

        foreach (array_chunk($rows, 50) as $chunk) {
            $values = [];
            foreach ($chunk as $row) {
                $vals = [];
                foreach ($row as $v) {
                    $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string)$v);
                }
                $values[] = '(' . implode(',', $vals) . ')';
            }
            $out .= "INSERT INTO `$t` ($colList) VALUES\n" . implode(",\n", $values) . ";\n";
        }
        $out .= "\n";
    }

    $out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $out .= "\n-- Selesai.\n";
    return $out;
}

/* ============ ACTION: DOWNLOAD LANGSUNG ============ */
if (($_GET['action'] ?? '') === 'download') {
    @set_time_limit(120);
    @ini_set('memory_limit', '256M');
    $sql = generateSqlDump($pdo);
    $filename = 'backup_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    header('Cache-Control: no-store');
    echo $sql;
    exit;
}

/* ============ ACTION: SIMPAN KE SERVER ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    @set_time_limit(120);
    @ini_set('memory_limit', '256M');
    $sql = generateSqlDump($pdo);
    $filename = 'backup_' . date('Ymd_His') . '.sql';
    $path = $backupDir . '/' . $filename;
    if (file_put_contents($path, $sql) !== false) {
        flash('Backup disimpan di server: ' . $filename . ' (' . formatBytes(strlen($sql)) . ')');
    } else {
        flash('Gagal menyimpan backup (cek permission folder backups/)', 'danger');
    }
    redirect('backup.php');
}

/* ============ ACTION: DOWNLOAD FILE TERSIMPAN ============ */
if (($_GET['action'] ?? '') === 'get' && !empty($_GET['file'])) {
    $file = basename($_GET['file']);
    $path = $backupDir . '/' . $file;
    if (is_file($path) && strpos($file, 'backup_') === 0 && substr($file, -4) === '.sql') {
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    flash('File backup tidak ditemukan', 'danger');
    redirect('backup.php');
}

/* ============ ACTION: HAPUS FILE ============ */
if (($_GET['action'] ?? '') === 'delete' && !empty($_GET['file'])) {
    $file = basename($_GET['file']);
    $path = $backupDir . '/' . $file;
    if (is_file($path) && strpos($file, 'backup_') === 0) {
        @unlink($path);
        flash('File backup dihapus: ' . $file);
    } else {
        flash('File tidak ditemukan', 'danger');
    }
    redirect('backup.php');
}

/* ============ INFO DATABASE ============ */
$tables     = [];
$dbSizeRows = 0;
$dbSizeBytes = 0;
try {
    $dbSizeBytes = (int)$pdo->query("
        SELECT COALESCE(SUM(data_length + index_length), 0)
        FROM information_schema.TABLES
        WHERE table_schema = " . $pdo->quote(DB_NAME)
    )->fetchColumn();

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $dbSizeRows += (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    }
} catch (Exception $e) {}

/* ============ DAFTAR BACKUP TERSIMPAN ============ */
$backups = [];
foreach (glob($backupDir . '/backup_*.sql') ?: [] as $f) {
    $backups[] = [
        'name' => basename($f),
        'size' => filesize($f),
        'time' => filemtime($f),
    ];
}
usort($backups, fn($a, $b) => $b['time'] - $a['time']);
$lastBackup = $backups[0]['time'] ?? null;

include 'header.php';
?>

<style>
.bk-hero{
  background:linear-gradient(135deg,#4f46e5,#7c3aed);
  color:#fff;padding:22px 24px;border-radius:14px;
  display:flex;justify-content:space-between;align-items:center;
  margin-bottom:18px;box-shadow:0 8px 24px rgba(79,70,229,.25);
  gap:16px;flex-wrap:wrap;
}
.bk-hero h2{font-size:18px;margin:0;font-weight:700}
.bk-hero p{font-size:12.5px;opacity:.9;margin:2px 0 0}
.bk-hero-actions{display:flex;gap:8px;flex-wrap:wrap}
.bk-btn-download{
  background:#fff;color:#4f46e5;border:none;padding:10px 18px;
  border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;
  display:inline-flex;align-items:center;gap:6px;text-decoration:none;
}
.bk-btn-download:hover{background:#f1f5f9}
.bk-stats{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
  gap:12px;margin-bottom:18px;
}
.bk-stat{
  background:#fff;border:1px solid #e2e8f0;border-radius:12px;
  padding:14px 18px;
}
.bk-stat-label{
  font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;
  color:#94a3b8;font-weight:700;margin-bottom:6px;
}
.bk-stat-value{font-size:20px;font-weight:800;color:#0f172a;line-height:1.2}
.bk-stat-value.green{color:#10b981}
.bk-info{
  background:#fffbeb;border:1px solid #fde68a;
  border-radius:10px;padding:14px 18px;margin-bottom:18px;
  font-size:12.5px;color:#78350f;line-height:1.6;
}
.bk-info strong{color:#92400e}
.bk-info code{
  background:#fef3c7;padding:1px 6px;border-radius:4px;
  font-size:11.5px;color:#92400e;
}
.bk-row-actions{display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap}

@media (max-width: 600px) {
  .bk-hero{padding:18px;flex-direction:column;align-items:flex-start}
  .bk-hero-actions{width:100%}
  .bk-hero-actions .bk-btn-download{flex:1;justify-content:center}
  .bk-stat-value{font-size:16px}
}
</style>

<!-- HERO -->
<div class="bk-hero">
  <div>
    <h2>💾 Backup Database</h2>
    <p>Amankan data toko — download atau simpan ke server dalam format SQL.</p>
  </div>
  <div class="bk-hero-actions">
    <a href="?action=download" class="bk-btn-download">⬇️ Download .sql</a>
  </div>
</div>

<!-- STATS -->
<div class="bk-stats">
  <div class="bk-stat">
    <div class="bk-stat-label">Ukuran Database</div>
    <div class="bk-stat-value"><?= formatBytes($dbSizeBytes) ?></div>
  </div>
  <div class="bk-stat">
    <div class="bk-stat-label">Jumlah Tabel</div>
    <div class="bk-stat-value"><?= count($tables) ?></div>
  </div>
  <div class="bk-stat">
    <div class="bk-stat-label">Total Baris Data</div>
    <div class="bk-stat-value green"><?= number_format($dbSizeRows, 0, ',', '.') ?></div>
  </div>
  <div class="bk-stat">
    <div class="bk-stat-label">Backup Terakhir</div>
    <div class="bk-stat-value" style="font-size:14px">
      <?= $lastBackup ? tgl_indo(date('Y-m-d', $lastBackup)) . ' ' . date('H:i', $lastBackup) : '—' ?>
    </div>
  </div>
</div>

<!-- PANDUAN RESTORE -->
<div class="bk-info">
  <strong>📌 Cara Restore:</strong>
  Buka <code>phpMyAdmin</code> (atau <code>mysql</code> CLI), pilih database
  <code><?= htmlspecialchars(DB_NAME) ?></code>, lalu <strong>Import</strong> file <code>.sql</code>
  yang sudah di-download. File ini sudah berisi <code>DROP TABLE</code> + <code>CREATE TABLE</code> + <code>INSERT</code>,
  jadi aman untuk restore bersih.
</div>

<!-- SIMPAN KE SERVER -->
<div class="card">
  <div class="d-flex justify-between mb-2">
    <h3 style="font-size:15px;margin:0">💾 Simpan Backup ke Server</h3>
    <form method="post" style="margin:0">
      <button name="save" value="1" class="btn btn-primary btn-sm">Simpan Sekarang</button>
    </form>
  </div>
  <p class="text-muted" style="font-size:12.5px;margin:0">
    File akan disimpan di folder <code>backups/</code> dan bisa di-download ulang kapan saja.
    Folder ini diproteksi agar tidak bisa diakses langsung dari URL.
  </p>
</div>

<!-- DAFTAR FILE BACKUP -->
<div class="card">
  <h3 style="font-size:15px;margin-bottom:12px">📂 File Backup Tersimpan (<?= count($backups) ?>)</h3>
  <?php if (empty($backups)): ?>
    <p class="text-muted text-center" style="padding:24px 0">Belum ada file backup di server.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Nama File</th>
          <th class="text-right">Ukuran</th>
          <th>Tanggal</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($backups as $b): ?>
        <tr>
          <td><strong><?= htmlspecialchars($b['name']) ?></strong></td>
          <td class="text-right"><?= formatBytes($b['size']) ?></td>
          <td><?= tgl_indo(date('Y-m-d', $b['time'])) ?> · <?= date('H:i', $b['time']) ?></td>
          <td class="text-right bk-row-actions">
            <a href="?action=get&file=<?= urlencode($b['name']) ?>" class="btn btn-outline btn-sm">⬇️ Download</a>
            <a href="?action=delete&file=<?= urlencode($b['name']) ?>"
               onclick="return confirm('Hapus file backup ini?')"
               class="btn btn-danger btn-sm">🗑️ Hapus</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include 'footer.php'; ?>