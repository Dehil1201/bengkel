<?php
session_start();
include "../../inc/koneksi.php"; // koneksi pakai $conn (MySQLi)
header('Content-Type: application/json; charset=utf-8');

$response = [
    "status_code" => 500,
    "message" => "Terjadi kesalahan server"
];

try {
    $no_retur      = $_POST['no_retur'] ?? null;
    $no_faktur     = $_POST['no_faktur'] ?? null;
    $tanggal_retur = $_POST['tanggal_retur'] ?? date('Y-m-d');
    $alasan        = $_POST['alasan'] ?? null;
    $total_retur   = intval($_POST['total_retur'] ?? 0);

    if (!$no_retur || !$no_faktur) {
        $response['status_code'] = 400;
        $response['message'] = "No retur dan No faktur wajib diisi";
        echo json_encode($response);
        exit;
    }

    // === MULAI TRANSACTION ===
    $conn->begin_transaction();

    // ambil data pelanggan, bengkel, user dari transaksi
    $stmt = $conn->prepare("SELECT id_pelanggan, id_bengkel, id_user FROM transaksi WHERE no_faktur = ?");
    $stmt->bind_param("s", $no_faktur);
    $stmt->execute();
    $trx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$trx) {
        $conn->rollback();
        $response['status_code'] = 404;
        $response['message'] = "Transaksi dengan no_faktur $no_faktur tidak ditemukan";
        echo json_encode($response);
        exit;
    }

    $id_pelanggan = $trx['id_pelanggan'];
    $id_bengkel   = $trx['id_bengkel'];
    $id_user      = $trx['id_user'];

    // cek retur sudah ada atau belum
    $stmt = $conn->prepare("SELECT id_retur_penjualan FROM retur_penjualan WHERE no_retur = ?");
    $stmt->bind_param("s", $no_retur);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($exists) {
        $stmt = $conn->prepare("UPDATE retur_penjualan 
            SET no_faktur = ?, id_pelanggan = ?, id_user = ?, id_bengkel = ?, 
                tanggal_retur = ?, alasan = ?, total_retur = ? 
            WHERE no_retur = ?");
        $stmt->bind_param("siiissis", $no_faktur, $id_pelanggan, $id_user, $id_bengkel,
                          $tanggal_retur, $alasan, $total_retur, $no_retur);
        $stmt->execute();
        $stmt->close();

        $response['message'] = "Retur berhasil diperbarui";
    } else {
        $stmt = $conn->prepare("INSERT INTO retur_penjualan 
            (no_retur, no_faktur, id_pelanggan, id_user, id_bengkel, tanggal_retur, alasan, total_retur, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssiiissi", $no_retur, $no_faktur, $id_pelanggan, $id_user, $id_bengkel,
                          $tanggal_retur, $alasan, $total_retur);
        $stmt->execute();
        $stmt->close();

        $response['message'] = "Retur berhasil disimpan";
    }

    // ambil detail retur
    $stmt = $conn->prepare("SELECT kode_sparepart, qty FROM retur_penjualan_detail WHERE no_retur = ?");
    $stmt->bind_param("s", $no_retur);
    $stmt->execute();
    $details = $stmt->get_result();
    $stmt->close();

    while ($d = $details->fetch_assoc()) {
        $kode_sparepart = $d['kode_sparepart'];
        $qty_retur      = $d['qty'];

        // update stok_pcs di spareparts
        $updateStok = $conn->prepare("UPDATE spareparts SET stok_pcs = stok_pcs + ? WHERE kode_sparepart = ?");
        $updateStok->bind_param("is", $qty_retur, $kode_sparepart);
        $updateStok->execute();
        $updateStok->close();

        // update qty di transaksi_detail_sparepart
        $updateQty = $conn->prepare("UPDATE transaksi_detail_sparepart 
                                    SET qty = qty - ? 
                                    WHERE no_faktur = ? AND kode_sparepart = ?");
        $updateQty->bind_param("iss", $qty_retur, $no_faktur, $kode_sparepart);
        $updateQty->execute();
        $updateQty->close();
    }

    // jika semua query berhasil, commit
    $conn->commit();
    $response['status_code'] = 200;

} catch (Exception $e) {
    $conn->rollback(); // rollback jika ada error
    $response['status_code'] = 500;
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
