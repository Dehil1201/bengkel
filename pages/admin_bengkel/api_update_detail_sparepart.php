<?php
include "../../inc/koneksi.php";

$id = $_POST['id_detail'];
$harga = intval($_POST['harga']);
$qty = intval($_POST['qty']);
$discount = intval($_POST['discount']);

$subtotal = ($harga * $qty) - $discount;

$update = mysqli_query($conn, "
    UPDATE transaksi_detail_sparepart
    SET harga='$harga', qty='$qty', discount='$discount', subtotal='$subtotal'
    WHERE id_detail='$id'
");

echo json_encode([
    "status_code" => $update ? 200 : 400,
    "message" => $update ? "Updated" : mysqli_error($conn)
]);
?>
