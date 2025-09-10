<?php
include "../../inc/koneksi.php";
header('Content-Type: application/json');

$action       = $_POST['action'] ?? ''; // create, update, delete
$no_faktur    = $_POST['no_faktur'] ?? '';
$id_detail    = $_POST['id_detail'] ?? '';
$nama_servis  = $_POST['nama_servis'] ?? '';
$biaya = $_POST['biaya'] ?? 0;
$id_servis = $_POST['id_servis'] ?? 0;


if ($action == 'create') {
    if ($nama_servis == '') {
        echo json_encode(["status_code"=>400,"message"=>"Data tidak lengkap"]); exit;
    }
    $insert = mysqli_query($conn, "INSERT INTO transaksi_detail_servis 
        (no_faktur,id_servis, nama_servis, biaya) 
        VALUES ('$no_faktur','$id_servis', '$nama_servis', '$biaya')");
    echo json_encode($insert ? ["status_code"=>200,"message"=>"Servis berhasil ditambahkan"]
                             : ["status_code"=>500,"message"=>"Gagal input servis: ".mysqli_error($conn)]);
}

elseif ($action == 'update') {
    if ($id_detail == '') { echo json_encode(["status_code"=>400,"message"=>"ID detail kosong"]); exit; }
    $update = mysqli_query($conn, "UPDATE transaksi_detail_servis 
        SET nama_servis='$nama_servis', biaya='$biaya'
        WHERE id_detail='$id_detail'");
    echo json_encode($update ? ["status_code"=>200,"message"=>"Servis berhasil diupdate"]
                             : ["status_code"=>500,"message"=>"Gagal update servis: ".mysqli_error($conn)]);
}

elseif ($action == 'delete') {
    if ($id_detail == '') { echo json_encode(["status_code"=>400,"message"=>"ID detail kosong"]); exit; }
    $delete = mysqli_query($conn, "DELETE FROM transaksi_detail_servis WHERE id_detail='$id_detail'");
    echo json_encode($delete ? ["status_code"=>200,"message"=>"Servis berhasil dihapus"]
                             : ["status_code"=>500,"message"=>"Gagal hapus servis: ".mysqli_error($conn)]);
}

else {
    echo json_encode(["status_code"=>400,"message"=>"Action tidak valid"]);
}
