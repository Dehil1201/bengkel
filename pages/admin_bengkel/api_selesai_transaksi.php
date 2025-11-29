<?php
include "../../inc/koneksi.php";
session_start();
header('Content-Type: application/json');

// ====================
// Ambil input
// ====================
$id_user    = $_SESSION['id_user'] ?? null;
$id_pelanggan = $_POST['id_pelanggan'] ?? null;
$kendaraan = $_POST['kendaraan'] ?? null;
$no_polisi = $_POST['no_polisi'] ?? null;
$id_teknisi = $_POST['id_teknisi'] ?? null;
$id_supplier = $_POST['id_supplier'] ?? null;
$uangBayar = floatval($_POST['uangBayarHidden'] ?? 0);
$kembalian = floatval($_POST['kembalianHidden'] ?? 0);
$status = $_POST['status'] ?? 'pending';
$jenis = $_POST['jenis'] ?? 'penjualan';
$metode_bayar = $_POST['metode_bayar'] ?? '';
$discount = floatval($_POST['diskon'] ?? 0);
$total_bayar = floatval($_POST['total_bayar_hidden'] ?? 0);
$tanggal_pelunasan = $_POST['tanggal_pelunasan'] ?? null;
$deskripsi = $_POST['deskripsi'] ?? null;
$no_faktur  = $_POST['no_faktur'] ?? '';
$tanggal = $_POST['tanggal'] ?? '';

// ====================
// Tangani tanggal
// ====================
if (empty($tanggal)) {
    $tanggal = date('Y-m-d H:i:s');
} else {
    $tanggal .= ' ' . date('H:i:s');
}

$dt = DateTime::createFromFormat('Y-m-d H:i:s', $tanggal);
if (!$dt) {
    echo json_encode(["status_code"=>400, "message"=>"Format tanggal tidak valid"]);
    exit;
}
$tanggal = $dt->format('Y-m-d H:i:s');

// ====================
// Validasi wajib
// ====================
if (!$id_user) {
    echo json_encode(["status_code"=>400,"message"=>"User tidak ditemukan"]);
    exit;
}

// ====================
// Generate no faktur jika baru
// ====================
if (empty($no_faktur)) {
    $q_user = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
    $d_user = mysqli_fetch_assoc($q_user);
    $id_bengkel = $d_user['bengkel_id'];

    $q = mysqli_query($conn, "SELECT MAX(SUBSTRING_INDEX(no_faktur,'.',-1)) AS max_urut
                              FROM transaksi
                              WHERE id_user='$id_user' AND id_bengkel='$id_bengkel' AND jenis='$jenis'");
    $row = mysqli_fetch_assoc($q);
    $no_urut = str_pad(((int)$row['max_urut']) + 1, 4, "0", STR_PAD_LEFT);
    $no_faktur = "PJ." . date("Ymd") . "." . $id_user . "." . $id_bengkel . "." . $no_urut;
}

// ====================
// Hitung total detail
// ====================
// Ambil total transaksi berdasarkan no_faktur yang dikirim
$sqlTotalSparepart = mysqli_query($conn, "SELECT SUM(subtotal) AS total_sparepart 
                                          FROM transaksi_detail_sparepart 
                                          WHERE no_faktur='$no_faktur'");
$totalSparepart = mysqli_fetch_assoc($sqlTotalSparepart)['total_sparepart'] ?? 0;

$sqlTotalServis = mysqli_query($conn, "SELECT SUM(biaya) AS total_servis 
                                       FROM transaksi_detail_servis 
                                       WHERE no_faktur='$no_faktur'");
$totalServis = mysqli_fetch_assoc($sqlTotalServis)['total_servis'] ?? 0;

// total transaksi
$total = $totalSparepart + $totalServis;


// ====================
// Status pembayaran
// ====================
$selisih = $total_bayar - $uangBayar;
$status_pembayaran = ($uangBayar >= $total_bayar) ? 'lunas' : 'belum lunas';

// ====================
// Mulai transaksi
// ====================
mysqli_begin_transaction($conn);

try {
    // ====================
    // Cek header transaksi
    // ====================
    $cekHeader = mysqli_query($conn, "SELECT * FROM transaksi WHERE no_faktur='$no_faktur' LIMIT 1");
    $id_bengkel = mysqli_fetch_assoc(mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user'"))['bengkel_id'];

    if(mysqli_num_rows($cekHeader) > 0){
        // Update header
        $update = mysqli_query($conn, "UPDATE transaksi 
            SET id_pelanggan=".($id_pelanggan ? "'$id_pelanggan'" : "NULL").",
                id_teknisi=".($id_teknisi ? "'$id_teknisi'" : "NULL").",
                kendaraan=".($kendaraan ? "'$kendaraan'" : "NULL").",
                no_polisi=".($no_polisi ? "'$no_polisi'" : "NULL").",
                id_user='$id_user',
                id_supplier=".($id_supplier ? "'$id_supplier'" : "NULL").",
                id_bengkel='$id_bengkel',
                status='$status',
                status_pembayaran='$status_pembayaran',
                total='$total',
                uang_bayar='$uangBayar',
                metode_bayar='$metode_bayar',
                discount='$discount',
                total_bayar='$total_bayar',
                kembalian='$kembalian',
                jenis='$jenis',
                tanggal='$tanggal',
                deskripsi='$deskripsi'
            WHERE no_faktur='$no_faktur'");
        if(!$update) throw new Exception("Gagal update header: ".mysqli_error($conn),500);
    } else {
        // Insert header baru
        $insert = mysqli_query($conn, "INSERT INTO transaksi 
            (no_faktur,id_user,kendaraan,no_polisi,id_pelanggan,id_teknisi,id_bengkel,status,status_pembayaran,total,uang_bayar,kembalian,tanggal,jenis,metode_bayar,total_bayar,discount,id_supplier,deskripsi) 
            VALUES (
                '$no_faktur','$id_user',".($kendaraan ? "'$kendaraan'" : "NULL").",".($no_polisi ? "'$no_polisi'" : "NULL").",
                ".($id_pelanggan ? "'$id_pelanggan'" : "NULL").",".($id_teknisi ? "'$id_teknisi'" : "NULL").",'$id_bengkel',
                '$status','$status_pembayaran','$total','$uangBayar','$kembalian','$tanggal','$jenis','$metode_bayar','$total_bayar','$discount',".($id_supplier ? "'$id_supplier'" : "NULL").",'$deskripsi')");
        if(!$insert) throw new Exception("Gagal insert header: ".mysqli_error($conn),500);
    }

    // ====================
    // Tangani hutang/piutang
    // ====================
    if($status_pembayaran==='belum lunas'){
        if(strtolower($jenis)=='pembelian'){
            // Hutang
            $cekHutang = mysqli_query($conn, "SELECT id_hutang FROM hutang WHERE no_faktur='$no_faktur' LIMIT 1");
            if(mysqli_num_rows($cekHutang)>0){
                $id_hutang = mysqli_fetch_assoc($cekHutang)['id_hutang'];
                mysqli_query($conn, "UPDATE hutang SET tanggal_hutang='$tanggal', jumlah='$selisih', status='belum lunas', tanggal_pelunasan='$tanggal_pelunasan' WHERE id_hutang=$id_hutang");
            } else {
                mysqli_query($conn, "INSERT INTO hutang(no_faktur,tanggal_hutang,jumlah,status,tanggal_pelunasan) VALUES('$no_faktur','$tanggal','$selisih','belum lunas','$tanggal_pelunasan')");
            }
        } else {
            // Piutang
            $cekPiutang = mysqli_query($conn, "SELECT id_piutang FROM piutang WHERE no_faktur='$no_faktur' LIMIT 1");
            if(mysqli_num_rows($cekPiutang)>0){
                $id_piutang = mysqli_fetch_assoc($cekPiutang)['id_piutang'];
                mysqli_query($conn, "UPDATE piutang SET tanggal_piutang='$tanggal', jumlah='$selisih', status='belum lunas', tanggal_pelunasan='$tanggal_pelunasan' WHERE id_piutang=$id_piutang");
            } else {
                mysqli_query($conn, "INSERT INTO piutang(no_faktur,tanggal_piutang,jumlah,status,tanggal_pelunasan) VALUES('$no_faktur','$tanggal','$selisih','belum lunas','$tanggal_pelunasan')");
            }
        }
    } else {
        // Update status lunas
        if(strtolower($jenis)=='pembelian'){
            mysqli_query($conn,"UPDATE hutang SET status='lunas', tanggal_pelunasan='$tanggal' WHERE no_faktur='$no_faktur'");
        } else {
            mysqli_query($conn,"UPDATE piutang SET status='lunas', tanggal_pelunasan='$tanggal' WHERE no_faktur='$no_faktur'");
        }
    }

    // ====================
    // Update stok jika pembelian
    // ====================
    if(strtolower($jenis)=='pembelian'){
        $qDetail = mysqli_query($conn,"SELECT kode_sparepart, qty FROM transaksi_detail_sparepart WHERE no_faktur='$no_faktur'");
        while($row=mysqli_fetch_assoc($qDetail)){
            $kode=$row['kode_sparepart'];
            $qty=(int)$row['qty'];
            mysqli_query($conn,"UPDATE spareparts SET stok_pcs=stok_pcs+$qty WHERE kode_sparepart='$kode'");
        }
    }

    mysqli_commit($conn);

    echo json_encode([
        "status_code"=>200,
        "message"=>"Transaksi berhasil diselesaikan",
        "data"=>[
            "no_faktur"=>$no_faktur,
            "total"=>$total,
            "status"=>$status,
            "status_pembayaran"=>$status_pembayaran,
            "pelanggan"=>$id_pelanggan,
            "teknisi"=>$id_teknisi,
            "jenis_transaksi"=>$jenis
        ]
    ]);

} catch(Exception $e){
    mysqli_rollback($conn);
    echo json_encode(["status_code"=>$e->getCode()?:500,"message"=>$e->getMessage()]);
}
