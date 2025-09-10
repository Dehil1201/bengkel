<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include '../../inc/koneksi.php';

function sanitize_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

$response = ['status' => 'error', 'message' => 'Permintaan tidak valid.'];

if (isset($_POST['aksi'])) {
    $aksi = $_POST['aksi'];
    
    $tables = [
        'kategori' => ['table' => 'kategori_sparepart', 'id_col' => 'id_kategori', 'name_col' => 'nama_kategori'],
        'merk' => ['table' => 'merk_sparepart', 'id_col' => 'id_merk', 'name_col' => 'nama_merk'],
        'satuan' => ['table' => 'satuan', 'id_col' => 'id_satuan', 'name_col' => 'nama_satuan'],
    ];

    if ($aksi == 'tambah') {
        $tipe = sanitize_input($_POST['tipe']);
        $nama = sanitize_input($_POST['nama']);
        
        if (isset($tables[$tipe])) {
            $config = $tables[$tipe];
            $query = "INSERT INTO {$config['table']} ({$config['name_col']}) VALUES ('$nama')";
            if (mysqli_query($conn, $query)) {
                $response = ['status' => 'success', 'message' => "{$tipe} berhasil ditambahkan."];
            } else {
                $response['message'] = "Gagal menambahkan {$tipe}: " . mysqli_error($conn);
            }
        }
    }

    if ($aksi == 'hapus') {
        $tipe = sanitize_input($_POST['tipe']);
        $id = sanitize_input($_POST['id']);
        
        if (isset($tables[$tipe])) {
            $config = $tables[$tipe];
            $query = "DELETE FROM {$config['table']} WHERE {$config['id_col']} = '$id'";
            if (mysqli_query($conn, $query)) {
                $response = ['status' => 'success', 'message' => "{$tipe} berhasil dihapus."];
            } else {
                $response['message'] = "Gagal menghapus {$tipe}: " . mysqli_error($conn);
            }
        }
    }
    
    if (strpos($aksi, 'get_all_') === 0) {
        $tipe = str_replace('get_all_', '', $aksi);
        
        if (isset($tables[$tipe])) {
            $config = $tables[$tipe];
            $query = "SELECT {$config['id_col']} AS id, {$config['name_col']} AS nama FROM {$config['table']} ORDER BY {$config['name_col']} ASC";
            $result = mysqli_query($conn, $query);
            $data = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
            $response = ['status' => 'success', 'data' => $data];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit();
?>