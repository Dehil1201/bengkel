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
$uangBayar   = $_POST['uangBayar'] ?? 0;   // default 0, pastikan tipe numerik
$kembalian   = $_POST['kembalian'] ?? 0;   // default 0
$status     = $_POST['status'] ?? 'pending';
$jenis      = $_POST['jenis'] ?? 'penjualan'; // default penjualan
$metode_bayar  = $_POST['metode_bayar'] ?? ''; // default penjualan
$discount  = $_POST['diskon'] ?? 0; // default 0
$total_bayar  = $_POST['total_bayar'] ?? 0; // default 0
$tanggal    = date('Y-m-d H:i:s');
$tanggal_pelunasan    = $_POST['tanggal_pelunasan'] ?? null;


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

    // ------------------------
    // MODIFIKASI: Status Pembayaran otomatis
    // ------------------------
    $uangBayarNum = floatval($uangBayar);
    $totalBayarNum = floatval($total_bayar);

    
    $selisih = $totalBayarNum - $uangBayarNum; // Ini yang jadi jumlah hutang/piutang

    if ($uangBayarNum >= $totalBayarNum) {
        $status_pembayaran = 'lunas';
    } else {
        $status_pembayaran = 'belum lunas';
    }

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
                id_supplier = ".($id_supplier ? "'$id_supplier'" : "NULL").",
                id_bengkel = (SELECT bengkel_id FROM users WHERE id_user='$id_user'),
                status = '$status',
                status_pembayaran = '$status_pembayaran',     -- MODIFIKASI
                total = '$total',
                uang_bayar = '$uangBayarNum',
                metode_bayar = '$metode_bayar',
                discount = '$discount',
                total_bayar = '$totalBayarNum',
                kembalian = '$kembalian',
                jenis = '$jenis',
                tanggal = '$tanggal'
            WHERE no_faktur='$no_faktur'");
        if(!$update) throw new Exception("Gagal update header: ".mysqli_error($conn),500);
    } else {
        // Insert
        $insert = mysqli_query($conn, "INSERT INTO transaksi 
            (no_faktur, id_user, kendaraan, no_polisi, id_pelanggan, id_teknisi, id_bengkel, status, status_pembayaran, total, uang_bayar, kembalian, tanggal, jenis, metode_bayar, total_bayar, discount, id_supplier) 
            VALUES (
                '$no_faktur',
                '$id_user',
                ".($kendaraan ? "'$kendaraan'" : "NULL").",
                ".($no_polisi ? "'$no_polisi'" : "NULL").",
                ".($id_pelanggan ? "'$id_pelanggan'" : "NULL").",
                ".($id_teknisi ? "'$id_teknisi'" : "NULL").",
                (SELECT bengkel_id FROM users WHERE id_user='$id_user'),
                '$status',
                '$status_pembayaran',     -- MODIFIKASI
                '$total',
                '$uangBayarNum',
                '$kembalian',
                '$tanggal',
                '$jenis',
                '$metode_bayar',
                '$totalBayarNum',
                '$discount',
                ".($id_supplier ? "'$id_supplier'" : "NULL")."
            )");
        if(!$insert) throw new Exception("Gagal insert header: ".mysqli_error($conn),500);
    }

    // ------------------------
    // MODIFIKASI: Tangani hutang/piutang berdasarkan status pembayaran
    // ------------------------
    if ($status_pembayaran === 'belum lunas') {
        if (strtolower($jenis) === 'pembelian') {
            // Hutang insert/update
            $cekHutang = mysqli_query($conn, "SELECT id_hutang FROM hutang WHERE no_faktur = '$no_faktur' LIMIT 1");
            if (mysqli_num_rows($cekHutang) > 0) {
                $id_hutang = mysqli_fetch_assoc($cekHutang)['id_hutang'];
                $updHutang = mysqli_query($conn, "UPDATE hutang SET 
                    tanggal_hutang = '$tanggal',
                    jumlah = '$selisih',
                    status = 'belum lunas',
                    tanggal_pelunasan = '$tanggal_pelunasan'
                    WHERE id_hutang = $id_hutang");
                if (!$updHutang) throw new Exception("Gagal update hutang: " . mysqli_error($conn), 500);
            } else {
                $insHutang = mysqli_query($conn, "INSERT INTO hutang 
                    (no_faktur, tanggal_hutang, jumlah, status, tanggal_pelunasan) VALUES
                    ('$no_faktur', '$tanggal', '$selisih', 'belum lunas', '$tanggal_pelunasan')");
                if (!$insHutang) throw new Exception("Gagal insert hutang: " . mysqli_error($conn), 500);
            }
        } else {
            // Piutang insert/update
            $cekPiutang = mysqli_query($conn, "SELECT id_piutang FROM piutang WHERE no_faktur = '$no_faktur' LIMIT 1");
            if (mysqli_num_rows($cekPiutang) > 0) {
                $id_piutang = mysqli_fetch_assoc($cekPiutang)['id_piutang'];
                $updPiutang = mysqli_query($conn, "UPDATE piutang SET 
                    tanggal_piutang = '$tanggal',
                    jumlah = '$selisih',
                    status = 'belum lunas',
                    tanggal_pelunasan = '$tanggal_pelunasan'
                    WHERE id_piutang = $id_piutang");
                if (!$updPiutang) throw new Exception("Gagal update piutang: " . mysqli_error($conn), 500);
            } else {
                $insPiutang = mysqli_query($conn, "INSERT INTO piutang 
                    (no_faktur, tanggal_piutang, jumlah, status, tanggal_pelunasan) VALUES
                    ('$no_faktur', '$tanggal', '$selisih', 'belum lunas', '$tanggal_pelunasan')");
                if (!$insPiutang) throw new Exception("Gagal insert piutang: " . mysqli_error($conn), 500);
            }
        }
    } else {
        // JANGAN HAPUS DATA, TAPI UPDATE STATUS DAN TANGGAL PELUNASAN (history tetap ada)
        if (strtolower($jenis) === 'pembelian') {
            $updHutang = mysqli_query($conn, "UPDATE hutang SET 
                status = 'lunas', 
                tanggal_pelunasan = '$tanggal' 
                WHERE no_faktur = '$no_faktur'");
            if (!$updHutang) throw new Exception("Gagal update status hutang lunas: ".mysqli_error($conn), 500);
        } else {
            $updPiutang = mysqli_query($conn, "UPDATE piutang SET 
                status = 'lunas', 
                tanggal_pelunasan = '$tanggal' 
                WHERE no_faktur = '$no_faktur'");
            if (!$updPiutang) throw new Exception("Gagal update status piutang lunas: ".mysqli_error($conn), 500);
        }
    }

    // Jika pembelian update stok sparepart
    if (strtolower($jenis) === 'pembelian') {
        $qDetail = mysqli_query($conn, "SELECT kode_sparepart, qty FROM transaksi_detail_sparepart WHERE no_faktur='$no_faktur'");
        while ($row = mysqli_fetch_assoc($qDetail)) {
            $kode = $row['kode_sparepart'];
            $qty = (int) $row['qty'];

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
            "status_pembayaran" => $status_pembayaran,
            "pelanggan" => $id_pelanggan,
            "teknisi" => $id_teknisi,
            "jenis_transaksi" => $jenis
        ]
    ]);

} catch(Exception $e){
    mysqli_rollback($conn);
    echo json_encode([
        "status_code" => $e->getCode() ?: 500,
        "message" => $e->getMessage()
    ]);
}
