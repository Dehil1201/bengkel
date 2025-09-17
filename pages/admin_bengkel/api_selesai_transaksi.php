<?php
include "../../inc/koneksi.php";
session_start();
header('Content-Type: application/json');


$no_faktur  = $_POST['no_faktur'] ?? '';
$id_user    = $_SESSION['id_user'] ?? null; // Ambil dari session
$id_pelanggan = $_POST['id_pelanggan'] ?? null; // optional
$kendaraan = $_POST['kendaraan'] ?? null; // optional
$no_polisi = $_POST['no_polisi'] ?? null; // optional
$id_teknisi   = $_POST['id_teknisi'] ?? null;   // optional
$id_supplier   = $_POST['id_supplier'] ?? null;   // optional
$uangBayar   = $_POST['uangBayar'] ?? null;   // optional
$kembalian   = $_POST['kembalian'] ?? null;   // optional
$status     = $_POST['status'] ?? 'pending';
$jenis      = $_POST['jenis'] ?? 'penjualan'; // default penjualan
$metode_bayar  = $_POST['metode_bayar'] ?? ''; // default penjualan
$discount  = $_POST['diskon'] ?? ''; // default penjualan
$total_bayar  = $_POST['total_bayar'] ?? ''; // default penjualan
$tanggal    = date('Y-m-d H:i:s');

if (!$no_faktur || !$id_user) {
    echo json_encode([
        "status_code" => 400,
        "message" => "No Faktur dan User wajib diisi"
    ]);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // Ambil total transaksi
    $sqlTotalSparepart = mysqli_query($conn, "SELECT SUM(subtotal) AS total_sparepart FROM transaksi_detail_sparepart WHERE no_faktur='$no_faktur'");
    $totalSparepart = mysqli_fetch_assoc($sqlTotalSparepart)['total_sparepart'] ?? 0;

    $sqlTotalServis = mysqli_query($conn, "SELECT SUM(biaya) AS total_servis FROM transaksi_detail_servis WHERE no_faktur='$no_faktur'");
    $totalServis = mysqli_fetch_assoc($sqlTotalServis)['total_servis'] ?? 0;

    $total = $totalSparepart + $totalServis;

    // Cek apakah header transaksi sudah ada
    $cekHeader = mysqli_query($conn, "SELECT * FROM transaksi WHERE no_faktur='$no_faktur' LIMIT 1");

    if(mysqli_num_rows($cekHeader) > 0){
        // Update
        $update = mysqli_query($conn, "UPDATE transaksi 
            SET id_pelanggan = ".($id_pelanggan ? "'$id_pelanggan'" : "NULL").",
                id_teknisi = ".($id_teknisi ? "'$id_teknisi'" : "NULL").",
                kendaraan = ".($kendaraan ? "'$kendaraan'" : "NULL").",
                no_polisi = ".($no_polisi ? "'$no_polisi'" : "NULL").",
                id_user = '$id_user',
                id_supplier = '$id_supplier',
                id_bengkel = (SELECT bengkel_id FROM users WHERE id_user='$id_user'),
                status = '$status',
                total = '$total',
                uang_bayar = '$uangBayar',
                metode_bayar = '$metode_bayar',
                discount = '$discount',
                total_bayar = '$total_bayar',
                jenis = '$jenis',
                kembalian = '$kembalian',
                tanggal = '$tanggal'
            WHERE no_faktur='$no_faktur'");
        if(!$update) throw new Exception("Gagal update header: ".mysqli_error($conn),500);
    } else {
        // Insert
        $insert = mysqli_query($conn, "INSERT INTO transaksi 
            (no_faktur, id_user, kendaraan, no_polisi, id_pelanggan, id_teknisi, id_bengkel, status, total, uang_bayar, kembalian, tanggal, jenis, metode_bayar,total_bayar, discount, id_supplier) 
            VALUES (
                '$no_faktur',
                '$id_user',
                ".($kendaraan ? "'$kendaraan'" : "NULL").",
                ".($no_polisi ? "'$no_polisi'" : "NULL").",
                ".($id_pelanggan ? "'$id_pelanggan'" : "NULL").",
                ".($id_teknisi ? "'$id_teknisi'" : "NULL").",
                (SELECT bengkel_id FROM users WHERE id_user='$id_user'),
                '$status',
                '$total',
                '$uangBayar',
                '$kembalian',
                '$tanggal',
                '$jenis',
                '$metode_bayar',
                '$total_bayar',
                '$discount',
                '$id_supplier'
            )");
        if(!$insert) throw new Exception("Gagal insert header: ".mysqli_error($conn),500);
    }

    if (strtolower($jenis) === 'pembelian') {
        $qDetail = mysqli_query($conn, "SELECT kode_sparepart, qty FROM transaksi_detail_sparepart WHERE no_faktur='$no_faktur'");
        while ($row = mysqli_fetch_assoc($qDetail)) {
            $kode = $row['kode_sparepart'];
            $qty = (int) $row['qty'];

            // Tambah stok ke spareparts
            $up = mysqli_query($conn, "UPDATE spareparts 
                                       SET stok_pcs = stok_pcs + $qty 
                                       WHERE kode_sparepart = '$kode'");
            if (!$up) throw new Exception("Gagal update stok untuk $kode: " . mysqli_error($conn), 500);
        }
    }

    mysqli_commit($conn);

    echo json_encode([
        "status_code" => 200,
        "message" => "Transaksi berhasil diselesaikan",
        "data" => [
            "no_faktur" => $no_faktur,
            "total" => $total,
            "status" => $status,
            "pelanggan" => $id_pelanggan,
            "teknisi" => $id_teknisi
        ]
    ]);

} catch(Exception $e){
    mysqli_rollback($conn);
    echo json_encode([
        "status_code" => $e->getCode() ?: 500,
        "message" => $e->getMessage()
    ]);
}
