<?php
require_once 'config.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=300');

/* ============ LOGO / ICON ============ */
$logoUrl  = '';
$logoMime = 'image/png';

if (LOGO_PATH && file_exists(__DIR__ . '/' . LOGO_PATH)) {
    $logoUrl = LOGO_PATH;
    $ext     = strtolower(pathinfo(LOGO_PATH, PATHINFO_EXTENSION));
    $logoMime = match($ext) {
        'png'        => 'image/png',
        'jpg', 'jpeg'=> 'image/jpeg',
        'webp'       => 'image/webp',
        'svg'        => 'image/svg+xml',
        default      => 'image/png',
    };
}

/* Fallback SVG (maskable-safe, letter pertama nama toko) */
$initial = mb_strtoupper(mb_substr(NAMA_TOKO ?: 'A', 0, 1));
$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">'
     . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
     . '<stop offset="0" stop-color="#4f46e5"/>'
     . '<stop offset="1" stop-color="#6d5ff0"/>'
     . '</linearGradient></defs>'
     . '<rect width="512" height="512" fill="url(#g)"/>'
     . '<text x="256" y="266" font-family="system-ui,-apple-system,Segoe UI,sans-serif"'
     . ' font-size="260" font-weight="700" fill="#fff"'
     . ' text-anchor="middle" dominant-baseline="middle">'
     . htmlspecialchars($initial)
     . '</text></svg>';
$svgData = 'data:image/svg+xml;base64,' . base64_encode($svg);

/* ============ ICONS ARRAY (native Android quality) ============ */
$icons = [];

/* Prefer generated PNG icons (best for Android install & splash) */
$iconSizes = [72, 96, 128, 144, 152, 192, 384, 512];
foreach ($iconSizes as $s) {
    $path = "icons/icon-{$s}.png";
    if (file_exists(__DIR__ . '/' . $path)) {
        $icons[] = [
            'src'     => $path,
            'sizes'   => "{$s}x{$s}",
            'type'    => 'image/png',
            'purpose' => 'any',
        ];
    }
}
foreach ($iconSizes as $s) {
    $path = "icons/icon-maskable-{$s}.png";
    if (file_exists(__DIR__ . '/' . $path)) {
        $icons[] = [
            'src'     => $path,
            'sizes'   => "{$s}x{$s}",
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ];
    }
}

/* Fallback to original logo if no icons folder */
if (empty($icons) && $logoUrl !== '') {
    $icons[] = [
        'src'     => $logoUrl,
        'sizes'   => 'any',
        'type'    => $logoMime,
        'purpose' => 'any',
    ];
}

/* Always keep SVG fallback */
$icons[] = [
    'src'     => $svgData,
    'sizes'   => 'any',
    'type'    => 'image/svg+xml',
    'purpose' => 'any',
];
$icons[] = [
    'src'     => $svgData,
    'sizes'   => 'any',
    'type'    => 'image/svg+xml',
    'purpose' => 'maskable',
];

/* ============ SHORT NAME ============ */
$shortName = trim(NAMA_TOKO);
if (mb_strlen($shortName) > 12) {
    $shortName = mb_substr($shortName, 0, 12);
}
if (mb_strlen($shortName) < 3) $shortName = 'Gold';

/* ============ SHORTCUTS (quick actions like native app) ============ */
$shortcuts = [
    [
        'name'        => 'Transaksi Baru',
        'short_name'  => 'Transaksi',
        'description' => 'Buat transaksi / nota baru',
        'url'         => 'transaksi_form.php',
        'icons'       => [
            ['src' => 'icons/icon-96.png', 'sizes' => '96x96', 'type' => 'image/png'],
        ],
    ],
    [
        'name'        => 'Daftar Transaksi',
        'short_name'  => 'Daftar',
        'description' => 'Lihat semua transaksi',
        'url'         => 'transaksi.php',
        'icons'       => [
            ['src' => 'icons/icon-96.png', 'sizes' => '96x96', 'type' => 'image/png'],
        ],
    ],
    [
        'name'        => 'Laporan',
        'short_name'  => 'Laporan',
        'description' => 'Laporan penjualan & keuangan',
        'url'         => 'laporan.php',
        'icons'       => [
            ['src' => 'icons/icon-96.png', 'sizes' => '96x96', 'type' => 'image/png'],
        ],
    ],
    [
        'name'        => 'Pelanggan',
        'short_name'  => 'Pelanggan',
        'description' => 'Kelola data pelanggan',
        'url'         => 'pelanggan.php',
        'icons'       => [
            ['src' => 'icons/icon-96.png', 'sizes' => '96x96', 'type' => 'image/png'],
        ],
    ],
];

/* ============ MANIFEST ============ */
$manifest = [
    'id'               => './',
    'name'             => NAMA_TOKO ?: 'Authentic Gold',
    'short_name'       => $shortName,
    'description'      => 'Sistem manajemen toko emas / laser engrave — transaksi, pelanggan, laporan, dan backup. Bekerja seperti aplikasi native Android.',
    'start_url'        => './index.php',
    'scope'            => './',
    'display'          => 'standalone',
    'display_override' => ['standalone', 'minimal-ui'],
    'orientation'      => 'portrait-primary',
    'theme_color'      => '#4f46e5',
    'background_color' => '#4f46e5',
    'icons'            => $icons,
    'shortcuts'        => $shortcuts,
    'lang'             => 'id',
    'dir'              => 'ltr',
    'categories'       => ['business', 'productivity', 'finance'],
    'prefer_related_applications' => false,
    'handle_links'     => 'preferred',
    'launch_handler'   => [
        'client_mode' => 'navigate-existing',
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
