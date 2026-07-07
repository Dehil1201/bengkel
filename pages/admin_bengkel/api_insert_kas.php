<?php

session_start();
include "../../inc/koneksi.php";

header("Content-Type: application/json");

mysqli_begin_transaction($conn);

try{

    if(empty($_SESSION['id_user'])){
        throw new Exception("Silahkan login.");
    }

    $id_user=$_SESSION['id_user'];

    $q=mysqli_query($conn,"
        SELECT bengkel_id
        FROM users
        WHERE id_user='$id_user'
        LIMIT 1
    ");

    $user=mysqli_fetch_assoc($q);

    if(!$user){
        throw new Exception("User tidak ditemukan.");
    }

    $id_bengkel=$user['bengkel_id'];

    $tanggal=$_POST['tanggal'];
    $no_faktur=$_POST['no_faktur'];
    $tipe=$_POST['tipe'];
    $kas_id=$_POST['kas_id'];

    $nominal=str_replace([".","Rp"," "],"",$_POST['nominal']);

    $keterangan=mysqli_real_escape_string($conn,$_POST['keterangan']);

    $jenis=($tipe=="pemasukan")
            ? "pemasukan lain"
            : "pengeluaran lain";

    $sql="INSERT INTO transaksi(

        no_faktur,
        tanggal,
        jenis,
        metode_bayar,
        total,
        discount,
        total_bayar,
        uang_bayar,
        kembalian,
        id_user,
        id_bengkel,
        status,
        status_pembayaran,
        deskripsi,
        kas_id,
        tipe

    )VALUES(

        '$no_faktur',
        '$tanggal',
        '$jenis',
        'Kas',
        '$nominal',
        0,
        '$nominal',
        '$nominal',
        0,
        '$id_user',
        '$id_bengkel',
        'selesai',
        'lunas',
        '$keterangan',
        '$kas_id',
        '$tipe'

    )";

    if(!mysqli_query($conn,$sql)){
        throw new Exception(mysqli_error($conn));
    }

    mysqli_commit($conn);

    echo json_encode([
        "success"=>true,
        "message"=>"Transaksi kas berhasil disimpan.",
        "no_faktur"=>$no_faktur
    ]);

}catch(Exception $e){

    mysqli_rollback($conn);

    echo json_encode([
        "success"=>false,
        "message"=>$e->getMessage()
    ]);

}