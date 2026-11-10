<?php
if (session_status() === PHP_SESSION_NONE) session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'uavys9sv_laser');
define('DB_USER', 'uavys9sv_authentic');
define('DB_PASS', 'Jakarta288');

if (!is_dir(__DIR__.'/uploads')) @mkdir(__DIR__.'/uploads', 0755, true);

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage() . "<br>Pastikan database sudah dibuat (import db.sql)");
}

/* ============ PENGATURAN DINAMIS ============ */
function loadSettings(PDO $pdo): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    try {
        foreach ($pdo->query("SELECT nama_key, nilai FROM pengaturan") as $r) {
            $cache[$r['nama_key']] = $r['nilai'];
        }
    } catch (Exception $e) {}
    return $cache;
}

function saveSetting(PDO $pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO pengaturan (nama_key, nilai) VALUES (?,?)
        ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
    $stmt->execute([$key, $value]);
}

$settings = loadSettings($pdo);

if (!defined('NAMA_TOKO'))   define('NAMA_TOKO',   $settings['nama_toko']   ?? 'Laser Engrave Studio');
if (!defined('ALAMAT_TOKO')) define('ALAMAT_TOKO', $settings['alamat_toko'] ?? '');
if (!defined('TELP_TOKO'))   define('TELP_TOKO',   $settings['telp_toko']   ?? '');
if (!defined('LOGO_PATH'))   define('LOGO_PATH', !empty($settings['logo']) ? 'uploads/'.$settings['logo'] : '');

/* ============ AUTH GUARD ============ */
$PUBLIC_PAGES = ['login.php', 'installer.php', 'nota.php', 'manifest.php'];
$current_page = basename($_SERVER['PHP_SELF']);
if (!in_array($current_page, $PUBLIC_PAGES) && empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/* ============ HELPERS ============ */
function rupiah($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

function tgl_indo($tgl) {
    if (!$tgl) return '-';
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $d = explode('-', substr($tgl, 0, 10));
    return (int)$d[2] . ' ' . $bulan[(int)$d[1]] . ' ' . $d[0];
}

function generateNoNota(PDO $pdo) {
    $prefix = 'INV-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("SELECT no_nota FROM transaksi WHERE no_nota LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $num = $last ? ((int)substr($last, -4) + 1) : 1;
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function redirect($url) { header("Location: $url"); exit; }
function flash($msg, $type = 'success') { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; }
function getFlash() {
    if (!empty($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

function recalcTransaksi(PDO $pdo, $trx_id) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah),0) FROM pembayaran WHERE transaksi_id = ?");
    $stmt->execute([$trx_id]);
    $bayar = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT total FROM transaksi WHERE id = ?");
    $stmt->execute([$trx_id]);
    $total = (float)$stmt->fetchColumn();

    $sisa = $total - $bayar;
    $status = $sisa <= 0.009 ? 'lunas' : 'hutang';

    $pdo->prepare("UPDATE transaksi SET bayar=?, sisa=?, status=? WHERE id=?")
        ->execute([$bayar, max($sisa, 0), $status, $trx_id]);
}

/* ============================================
   WHATSAPP HELPERS — Kirim Nota via WA
   ============================================ */

function formatWaNumber($phone) {
    $phone = preg_replace('/\D/', '', (string)$phone);
    if ($phone === '') return '';
    if (substr($phone, 0, 2) === '62') $phone = substr($phone, 2);
    if (substr($phone, 0, 1) === '0')  $phone = substr($phone, 1);
    if ($phone === '') return '';
    return '62' . $phone;
}

function getWaLink($phone, $message = '') {
    $num = formatWaNumber($phone);
    if ($num === '') return '';
    $url = 'https://wa.me/' . $num;
    if ($message !== '') $url .= '?text=' . rawurlencode($message);
    return $url;
}

function getBaseUrl() {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/\\');
    return $scheme . '://' . $host . $path;
}

/* ============================================
   NOTA PUBLIC TOKEN — untuk QR Code
   ============================================ */
if (!defined('NOTA_SECRET')) {
    define('NOTA_SECRET', hash('sha256', DB_PASS . '|' . DB_NAME . '|laser-nota-v1'));
}

function generateNotaToken(array $trx): string {
    $data = ($trx['id'] ?? '') . '|' . ($trx['no_nota'] ?? '');
    return substr(hash_hmac('sha256', $data, NOTA_SECRET), 0, 16);
}

function verifyNotaToken(array $trx, string $token): bool {
    if ($token === '') return false;
    $expected = generateNotaToken($trx);

    // Defense-in-depth: pastikan panjang cocok sebelum perbandingan timing-safe
    if (strlen($token) !== strlen($expected)) return false;

    return hash_equals($expected, $token);
}

function getNotaPublicUrl(array $trx): string {
    return getBaseUrl() . '/nota.php?id=' . ($trx['id'] ?? 0)
         . '&token=' . generateNotaToken($trx);
}

/**
 * Build pesan WhatsApp notifikasi transaksi (dengan link publik ber-token)
 */
function buildNotaWaMessage($trx, $items = null) {
    $notaUrl = getNotaPublicUrl($trx);

    $namaPelanggan = !empty($trx['pelanggan']) ? $trx['pelanggan'] : 'Kak';

    $lines = [];
    $lines[] = 'Halo *' . $namaPelanggan . '* 👋';
    $lines[] = '';
    $lines[] = 'Terima kasih telah order di *' . NAMA_TOKO . '*';
    $lines[] = '';
    $lines[] = '📋 No. Nota: *' . ($trx['no_nota'] ?? '-') . '*';
    $lines[] = '📅 Tanggal: ' . tgl_indo($trx['tanggal'] ?? '');

    if (!empty($items)) {
        $lines[] = '';
        $lines[] = '🔨 *Detail Pesanan:*';
        $maxShow = 5;
        $shown = 0;
        $totalItems = count($items);
        foreach ($items as $it) {
            if ($shown >= $maxShow) {
                $sisa = $totalItems - $maxShow;
                if ($sisa > 0) $lines[] = '   • _...dan ' . $sisa . ' item lainnya_';
                break;
            }
            $ukuran = '';
            $p = isset($it['panjang']) ? (float)$it['panjang'] : 0;
            $l = isset($it['lebar'])   ? (float)$it['lebar']   : 0;
            if ($p > 0 && $l > 0) {
                $ukuran = ' (' . $p . '×' . $l . ' cm)';
            } elseif ($p > 0) {
                $ukuran = ' (' . $p . ' cm)';
            } elseif ($l > 0) {
                $ukuran = ' (' . $l . ' cm)';
            }
            $lines[] = '   • ' . ($it['nama_item'] ?? '-') . $ukuran . ' ×' . (int)($it['qty'] ?? 1);
            $shown++;
        }
    }

    $lines[] = '';
    $lines[] = '💰 Total: *' . rupiah($trx['total'] ?? 0) . '*';

    if (($trx['status'] ?? '') === 'lunas') {
        $lines[] = '✅ Status: *LUNAS* — Terima kasih 🙏';
    } else {
        if (($trx['bayar'] ?? 0) > 0) {
            $lines[] = '💵 Dibayar: ' . rupiah($trx['bayar']);
        }
        $lines[] = '⚠️ Sisa: *' . rupiah($trx['sisa'] ?? 0) . '*';
    }

    $lines[] = '';
    $lines[] = '🔗 Lihat nota lengkap:';
    $lines[] = $notaUrl;
    $lines[] = '';
    $lines[] = '📸 Follow: @laser.engrave.studio';

    return implode("\n", $lines);
}