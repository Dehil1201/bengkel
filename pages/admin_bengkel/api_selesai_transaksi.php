<?php
include "../../inc/koneksi.php";
session_start();
header('Content-Type: application/json');

/* =========================
   AMBIL DATA
========================= */
$no_faktur  = $_POST['no_faktur'] ?? '';
$id_user    = $_SESSION['id_user'] ?? null;

$id_pelanggan = $_POST['id_pelanggan'] ?? null;
$kendaraan = $_POST['kendaraan'] ?? null;
$no_polisi = $_POST['no_polisi'] ?? null;
$id_teknisi   = $_POST['id_teknisi'] ?? null;
$id_supplier = $_POST['id_supplier'] ?? null;

$uangBayar   = floatval($_POST['uangBayarHidden'] ?? 0);
$kembalian   = floatval($_POST['kembalianHidden'] ?? 0);
$discount    = floatval($_POST['diskon'] ?? 0);
$total_bayar = floatval($_POST['total_bayar_hidden'] ?? 0);

$status      = $_POST['status'] ?? 'pending';
$jenis       = $_POST['jenis'] ?? 'penjualan';
$metode_bayar = $_POST['metode_bayar'] ?? 'Tunai';

$tanggal = ($_POST['tanggal'] ?? date('Y-m-d')) . ' ' . date('H:i:s');
$tanggal_pelunasan = $_POST['tanggal_pelunasan'] ?? null;
$deskripsi = $_POST['deskripsi'] ?? null;

if (!$no_faktur || !$id_user) {
    echo json_encode(["status_code"=>400,"message"=>"No faktur & user wajib diisi"]);
    exit;
}

/* =========================
   TRANSAKSI DB
========================= */
mysqli_begin_transaction($conn);

try {

    /* =========================
       AMBIL ID BENGKEL (SEKALI)
    ========================= */
    $qBengkel = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
    $id_bengkel = mysqli_fetch_assoc($qBengkel)['bengkel_id'] ?? null;
    if (!$id_bengkel) throw new Exception("Bengkel tidak ditemukan",400);

    /* =========================
       HITUNG TOTAL
    ========================= */
    $q1 = mysqli_query($conn, "SELECT SUM(subtotal) total FROM transaksi_detail_sparepart WHERE no_faktur='$no_faktur'");
    $q2 = mysqli_query($conn, "SELECT SUM(biaya) total FROM transaksi_detail_servis WHERE no_faktur='$no_faktur'");

    $total_sparepart = floatval(mysqli_fetch_assoc($q1)['total'] ?? 0);
    $total_servis    = floatval(mysqli_fetch_assoc($q2)['total'] ?? 0);
    $total = $total_sparepart + $total_servis;

    /* =========================
       STATUS PEMBAYARAN
    ========================= */
    $status_pembayaran = ($uangBayar >= $total_bayar) ? 'lunas' : 'belum lunas';
    $selisih = $total_bayar;

    /* =========================
       HEADER TRANSAKSI
    ========================= */
    $cek = mysqli_query($conn, "SELECT no_faktur FROM transaksi WHERE no_faktur='$no_faktur' LIMIT 1");

    if (mysqli_num_rows($cek)) {
        mysqli_query($conn, "UPDATE transaksi SET
            id_user='$id_user',
            id_bengkel='$id_bengkel',
            id_pelanggan=".($id_pelanggan?"'$id_pelanggan'":"NULL").",
            id_teknisi=".($id_teknisi?"'$id_teknisi'":"NULL").",
            id_supplier=".($id_supplier?"'$id_supplier'":"NULL").",
            kendaraan=".($kendaraan?"'$kendaraan'":"NULL").",
            no_polisi=".($no_polisi?"'$no_polisi'":"NULL").",
            status='$status',
            status_pembayaran='$status_pembayaran',
            total='$total',
            uang_bayar='$uangBayar',
            kembalian='$kembalian',
            metode_bayar='$metode_bayar',
            total_bayar='$total_bayar',
            discount='$discount',
            jenis='$jenis',
            tanggal='$tanggal',
            deskripsi='$deskripsi'
        WHERE no_faktur='$no_faktur' LIMIT 1");
    } else {
        mysqli_query($conn, "INSERT INTO transaksi VALUES (
            NULL,
            '$no_faktur',
            '$id_user',
            '$id_bengkel',
            ".($id_pelanggan?"'$id_pelanggan'":"NULL").",
            ".($id_teknisi?"'$id_teknisi'":"NULL").",
            ".($id_supplier?"'$id_supplier'":"NULL").",
            ".($kendaraan?"'$kendaraan'":"NULL").",
            ".($no_polisi?"'$no_polisi'":"NULL").",
            '$status',
            '$status_pembayaran',
            '$total',
            '$uangBayar',
            '$kembalian',
            '$tanggal',
            '$jenis',
            '$metode_bayar',
            '$total_bayar',
            '$discount',
            '$deskripsi'
        )");
    }

    /* =========================
       HUTANG / PIUTANG
    ========================= */
    if ($metode_bayar === 'Non Tunai' && $selisih > 0) {

        if ($jenis === 'pembelian') {
            mysqli_query($conn, "INSERT INTO hutang (no_faktur,tanggal_hutang,jumlah,status,tanggal_pelunasan)
            VALUES ('$no_faktur','$tanggal','$selisih','belum lunas','$tanggal_pelunasan')
            ON DUPLICATE KEY UPDATE
            jumlah='$selisih', status='belum lunas', tanggal_pelunasan='$tanggal_pelunasan'");
        } else {
            mysqli_query($conn, "INSERT INTO piutang (no_faktur,tanggal_piutang,jumlah,status,tanggal_pelunasan)
            VALUES ('$no_faktur','$tanggal','$selisih','belum lunas','$tanggal_pelunasan')
            ON DUPLICATE KEY UPDATE
            jumlah='$selisih', status='belum lunas', tanggal_pelunasan='$tanggal_pelunasan'");
        }

    } else {
        if ($jenis === 'pembelian') {
            mysqli_query($conn, "UPDATE hutang SET status='lunas', tanggal_pelunasan='$tanggal'
            WHERE no_faktur='$no_faktur' LIMIT 1");
        } else {
            mysqli_query($conn, "UPDATE piutang SET status='lunas', tanggal_pelunasan='$tanggal'
            WHERE no_faktur='$no_faktur' LIMIT 1");
        }
    }

    mysqli_commit($conn);

    echo json_encode([
        "status_code"=>200,
        "message"=>"Transaksi berhasil",
        "no_faktur"=>$no_faktur,
        "status_pembayaran"=>$status_pembayaran
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode([
        "status_code"=>$e->getCode() ?: 500,
        "message"=>$e->getMessage()
    ]);
}
