<?php
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$nama   = trim($_POST['nama'] ?? '');
$telp   = trim($_POST['telepon'] ?? '');
$alamat = trim($_POST['alamat'] ?? '');

if ($nama === '') {
    echo json_encode(['success' => false, 'error' => 'Nama wajib diisi']);
    exit;
}

if (mb_strlen($nama) > 100) {
    echo json_encode(['success' => false, 'error' => 'Nama maksimal 100 karakter']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO pelanggan (nama, telepon, alamat) VALUES (?,?,?)");
    $stmt->execute([$nama, $telp, $alamat]);
    $id = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'pelanggan' => [
            'id'      => $id,
            'nama'    => $nama,
            'telepon' => $telp,
            'alamat'  => $alamat,
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Gagal menyimpan: ' . $e->getMessage()]);
}