<?php
header("Content-Type: application/json");
include "../../inc/koneksi.php";

$no_faktur = $_POST['no_faktur'] ?? null;

if (!$no_faktur) {
    echo json_encode(["success" => false, "message" => "No Faktur tidak boleh kosong"]);
    exit;
}

// Ambil jenis transaksi
$q = $conn->query("SELECT jenis FROM transaksi WHERE no_faktur='$no_faktur' LIMIT 1");
if ($q->num_rows == 0) {
    echo json_encode(["success" => false, "message" => "Faktur tidak ditemukan"]);
    exit;
}
$jenis = $q->fetch_assoc()['jenis'];

// Mulai transaction
$conn->begin_transaction();

try {
    // ===============================
    // Penjualan sparepart (PJ) atau pembelian
    // ===============================
    if (substr($no_faktur,0,2) === "PJ" || $jenis === "pembelian") {
        // Update stok sekaligus
        if ($jenis === "penjualan") {
            $conn->query("
                UPDATE spareparts s
                JOIN transaksi_detail_sparepart d ON s.kode_sparepart = d.kode_sparepart
                SET s.stok_pcs = s.stok_pcs + d.qty
                WHERE d.no_faktur = '$no_faktur'
            ");
        } else {
            // pembelian
            $conn->query("
                UPDATE spareparts s
                JOIN transaksi_detail_sparepart d ON s.kode_sparepart = d.kode_sparepart
                SET s.stok_pcs = s.stok_pcs - d.qty
                WHERE d.no_faktur = '$no_faktur'
            ");
        }

        // Hapus semua detail sparepart
        $conn->query("DELETE FROM transaksi_detail_sparepart WHERE no_faktur='$no_faktur'");
    }

    // ===============================
    // Penjualan service (PS)
    // ===============================
    if (substr($no_faktur,0,2) === "PS") {
        // Update stok sparepart yang digunakan untuk service
        $conn->query("
            UPDATE spareparts s
            JOIN transaksi_detail_sparepart d ON s.kode_sparepart = d.kode_sparepart
            SET s.stok = s.stok + d.qty
            WHERE d.no_faktur='$no_faktur'
        ");

        // Hapus detail sparepart sekaligus
        $conn->query("DELETE FROM transaksi_detail_sparepart WHERE no_faktur='$no_faktur'");

        // Hapus detail service sekaligus
        $conn->query("DELETE FROM transaksi_detail_servis WHERE no_faktur='$no_faktur'");
    }

    // ===============================
    // Hapus header transaksi
    // ===============================
    $conn->query("DELETE FROM transaksi WHERE no_faktur='$no_faktur'");

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Transaksi berhasil dihapus beserta detailnya, stok diperbarui",
        "no_faktur" => $no_faktur
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => "Gagal menghapus transaksi: ".$e->getMessage()
    ]);
}
