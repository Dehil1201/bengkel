<?php
session_start();
include "../../inc/koneksi.php";
header('Content-Type: application/json; charset=utf-8');

// ===== Helper Error Response =====
function respond($success, $message, $data = [], $status_code = null) {
    // Default status_code otomatis
    if ($status_code === null) {
        $status_code = $success ? 200 : 400;
    }

    // Set header status code HTTP
    http_response_code($status_code);

    echo json_encode([
        "success"      => $success,
        "status_code"  => $status_code,
        "message"      => $message,
        "data"         => $data
    ]);
    exit;
}

// ===== Validasi Login =====
$id_user = $_SESSION['id_user'] ?? null;
if (!$id_user) {
    respond(false, "Session expired. Silakan login ulang.", [], 401);
}

// ===== Validasi Parameter =====
$kode_sparepart = $_REQUEST['kode_sparepart'] ?? '';
if (empty($kode_sparepart)) {
    respond(false, "Parameter 'kode_sparepart' wajib diisi.", [], 422);
}

// ===== Ambil bengkel_id user =====
$qUser = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
if (!$qUser) respond(false, "Gagal ambil data user: " . mysqli_error($conn), [], 500);
$dUser = mysqli_fetch_assoc($qUser);
$id_bengkel = $dUser['bengkel_id'] ?? null;

// ===== Cek sparepart berdasarkan kode dan bengkel =====
$qSp = mysqli_query($conn, "
    SELECT id_sparepart, nama_sparepart 
    FROM spareparts 
    WHERE kode_sparepart = '$kode_sparepart' AND bengkel_id = '$id_bengkel'
    LIMIT 1
");
if (!$qSp) respond(false, "Gagal ambil data sparepart: " . mysqli_error($conn), [], 500);
$dSp = mysqli_fetch_assoc($qSp);
if (!$dSp) respond(false, "Sparepart dengan kode '$kode_sparepart' tidak ditemukan di bengkel ini.", [], 404);

$id_sparepart = $dSp['id_sparepart'];

// ===== Ambil data harga per satuan =====
$qHarga = mysqli_query($conn, "
    SELECT 
        hj.id_harga_jual,
        hj.tipe_harga,
        hj.persentase_jual,
        hj.harga_jual,
        hj.isi_per_pcs_jual,
        st.id_satuan,
        st.nama_satuan
    FROM harga_jual_sparepart hj
    JOIN satuan st ON hj.satuan_jual_id = st.id_satuan
    WHERE hj.sparepart_id = '$id_sparepart' and hj.harga_jual > 0
    ORDER BY hj.tipe_harga ASC
");
if (!$qHarga) respond(false, "Gagal ambil harga jual: " . mysqli_error($conn), [], 500);

$hargaList = [];
while ($row = mysqli_fetch_assoc($qHarga)) {
    $hargaList[] = [
        "id_harga_jual"    => (int)$row['id_harga_jual'],
        "tipe_harga"       => (int)$row['tipe_harga'],
        "nama_satuan"      => $row['nama_satuan'],
        "id_satuan"        => (int)$row['id_satuan'],
        "persentase_jual"  => (float)$row['persentase_jual'],
        "harga_jual"       => (float)$row['harga_jual'],
        "isi_per_pcs_jual" => (int)$row['isi_per_pcs_jual']
    ];
}

// ===== Response JSON =====
respond(true, "Data harga satuan ditemukan.", [
    "kode_sparepart" => $kode_sparepart,
    "nama_sparepart" => $dSp['nama_sparepart'],
    "harga_satuan"   => $hargaList
], 200);
