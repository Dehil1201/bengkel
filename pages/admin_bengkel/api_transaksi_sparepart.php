<?php
include "../../inc/koneksi.php";
header('Content-Type: application/json');

$action        = $_POST['action'] ?? ''; // create, update, delete
$no_faktur     = $_POST['no_faktur'] ?? '';
$id_detail     = $_POST['id_detail'] ?? '';
$kode_sparepart= $_POST['kode_sparepart'] ?? '';
$nama_sparepart= $_POST['nama_sparepart'] ?? '';
$satuan        = $_POST['satuan'] ?? '';
$qty           = (int)($_POST['qty'] ?? 0);
$harga         = (int)($_POST['harga'] ?? 0);

mysqli_begin_transaction($conn);

try {
    if ($action == 'create') {
        if (!$no_faktur || $kode_sparepart == '' || $nama_sparepart == '') {
            throw new Exception("Data tidak lengkap", 400);
        }

        // ✅ Cek stok sparepart
        $cekStok = mysqli_query($conn, "SELECT stok_pcs FROM spareparts 
                                        WHERE kode_sparepart='$kode_sparepart' 
                                        FOR UPDATE");
        $stokRow = mysqli_fetch_assoc($cekStok);

        if (!$stokRow) {
            throw new Exception("Sparepart tidak ditemukan", 404);
        }
        if ($stokRow['stok_pcs'] < $qty) {
            throw new Exception("Stok tidak mencukupi! Sisa stok: ".$stokRow['stok_pcs'], 400);
        }

        // ✅ Cek apakah sparepart sudah ada di faktur
        $cek = mysqli_query($conn, "SELECT * FROM transaksi_detail_sparepart 
                                    WHERE no_faktur='$no_faktur' 
                                    AND kode_sparepart='$kode_sparepart' 
                                    LIMIT 1");
        $row = mysqli_fetch_assoc($cek);

        if ($row) {
            // hanya cek tambahan qty, bukan total qty
            if ($stokRow['stok_pcs'] < $qty) {
                throw new Exception("Stok tidak cukup! Sisa stok: ".$stokRow['stok_pcs'], 400);
            }
        
            $newQty = $row['qty'] + $qty;
            $newSubtotal = $newQty * $harga;
        
            $update = mysqli_query($conn, "UPDATE transaksi_detail_sparepart 
                                           SET qty='$newQty', subtotal='$newSubtotal' 
                                           WHERE id_detail='".$row['id_detail']."'");
            if (!$update) throw new Exception("Gagal update sparepart: ".mysqli_error($conn), 500);
        }else {
            // Insert baru
            $subtotal = $qty * $harga;
            $insert = mysqli_query($conn, "INSERT INTO transaksi_detail_sparepart 
                (no_faktur, kode_sparepart, satuan, nama_sparepart, qty, harga, subtotal) 
                VALUES ('$no_faktur','$kode_sparepart','$satuan', '$nama_sparepart', '$qty', '$harga', '$subtotal')");
            if (!$insert) throw new Exception("Gagal input sparepart: ".mysqli_error($conn), 500);
        }

        // ✅ Kurangi stok sparepart
        $kurangStok = mysqli_query($conn, "UPDATE spareparts 
                                           SET stok_pcs = stok_pcs - $qty 
                                           WHERE kode_sparepart='$kode_sparepart'");
        if (!$kurangStok) throw new Exception("Gagal update stok: ".mysqli_error($conn), 500);

        mysqli_commit($conn);
        echo json_encode(["status_code"=>200,"message"=>"Sparepart berhasil ditambahkan"]);
    }

    elseif ($action == 'update') {
        if ($id_detail == '') throw new Exception("ID detail kosong", 400);

        // Ambil detail lama untuk hitung selisih qty
        $detail = mysqli_query($conn, "SELECT * FROM transaksi_detail_sparepart WHERE id_detail='$id_detail' FOR UPDATE");
        $old = mysqli_fetch_assoc($detail);
        if (!$old) throw new Exception("Detail sparepart tidak ditemukan", 404);

        $selisih = ($qty - $old['qty']);

        // Jika ada perubahan qty, cek stok
        if ($selisih > 0) {
            $cekStok = mysqli_query($conn, "SELECT stok_pcs FROM spareparts 
                                            WHERE kode_sparepart='$kode_sparepart' 
                                            FOR UPDATE");
            $stokRow = mysqli_fetch_assoc($cekStok);
            if ($stokRow['stok_pcs'] < $selisih) {
                throw new Exception("Stok tidak cukup! Tambahan qty maksimal ".$stokRow['stok_pcs'], 400);
            }
        }

        $subtotal = $qty * $harga;
        $update = mysqli_query($conn, "UPDATE transaksi_detail_sparepart 
            SET kode_sparepart='$kode_sparepart', nama_sparepart='$nama_sparepart', 
                qty='$qty', harga='$harga', subtotal='$subtotal' 
            WHERE id_detail='$id_detail'");
        if (!$update) throw new Exception("Gagal update sparepart: ".mysqli_error($conn), 500);

        // Update stok (kurangi / tambah sesuai selisih)
        if ($selisih != 0) {
            $updateStok = mysqli_query($conn, "UPDATE spareparts 
                                               SET stok_pcs = stok_pcs - $selisih 
                                               WHERE kode_sparepart='$kode_sparepart'");
            if (!$updateStok) throw new Exception("Gagal update stok: ".mysqli_error($conn), 500);
        }

        mysqli_commit($conn);
        echo json_encode(["status_code"=>200,"message"=>"Sparepart berhasil diupdate"]);
    }

    elseif ($action == 'delete') {
        if ($id_detail == '') throw new Exception("ID detail kosong", 400);

        // Ambil detail untuk mengembalikan stok
        $detail = mysqli_query($conn, "SELECT * FROM transaksi_detail_sparepart WHERE id_detail='$id_detail' FOR UPDATE");
        $old = mysqli_fetch_assoc($detail);
        if (!$old) throw new Exception("Detail sparepart tidak ditemukan", 404);

        $delete = mysqli_query($conn, "DELETE FROM transaksi_detail_sparepart WHERE id_detail='$id_detail'");
        if (!$delete) throw new Exception("Gagal hapus sparepart: ".mysqli_error($conn), 500);
        $kode_sparepart = htmlspecialchars($old['kode_sparepart']);
        // Kembalikan stok
        $restoreStok = mysqli_query($conn, "
            UPDATE spareparts 
            SET stok_pcs = stok_pcs + ".$old['qty']." 
            WHERE kode_sparepart = '".$old['kode_sparepart']."'
        ");

        if (!$restoreStok) throw new Exception("Gagal mengembalikan stok: ".mysqli_error($conn), 500);

        mysqli_commit($conn);
        echo json_encode(["status_code"=>200,"message"=>"Sparepart berhasil dihapus"]);
    }

    else {
        throw new Exception("Action tidak valid", 400);
    }

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode([
        "status_code" => $e->getCode() ?: 500,
        "message" => $e->getMessage()
    ]);
}
