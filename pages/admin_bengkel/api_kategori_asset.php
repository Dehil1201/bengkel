<?php
session_start();
include "../../inc/koneksi.php"; // sesuaikan path koneksi

header('Content-Type: application/json');

function sanitize($data){
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

// Fallback sementara jika get_user_role() belum ada
if (!function_exists('get_user_role')) {
    function get_user_role() {
        return $_SESSION['role_user'] ?? 'admin_bengkel';
    }
}

// Ambil role user
$user_role = get_user_role();
$allowed_roles = ['owner_bengkel','admin_bengkel'];
if(!in_array($user_role, $allowed_roles)){
    echo json_encode(['status'=>'error','message'=>'Akses ditolak']);
    exit();
}

// Ambil bengkel yang bisa diakses
$accessible_bengkel_ids = [];
if($user_role === 'owner_bengkel'){
    $owner_id = $_SESSION['id_user'];
    $q = mysqli_query($conn,"SELECT id_bengkel FROM bengkels WHERE owner_id='$owner_id'");
    while($r = mysqli_fetch_assoc($q)){
        $accessible_bengkel_ids[] = $r['id_bengkel'];
    }
}elseif($user_role === 'admin_bengkel'){
    $user_id = $_SESSION['id_user'];
    $q = mysqli_query($conn,"SELECT bengkel_id FROM users WHERE id_user='$user_id'");
    if($r = mysqli_fetch_assoc($q)){
        $accessible_bengkel_ids[] = $r['bengkel_id'];
    }
}
if(empty($accessible_bengkel_ids)){
    echo json_encode(['status'=>'error','message'=>'Anda tidak terdaftar di bengkel manapun']);
    exit();
}
$bengkel_ids_string = "'".implode("','",$accessible_bengkel_ids)."'";

// ==================================================
// ACTION
// ==================================================
$action = $_REQUEST['action'] ?? '';

if($action === 'list'){
    $data = [];
    $q = mysqli_query($conn,"SELECT * FROM kategori_asset WHERE bengkel_id IN ($bengkel_ids_string) ORDER BY nama_kategori ASC");
    while($r = mysqli_fetch_assoc($q)){
        $data[] = $r;
    }
    echo json_encode(['status'=>'success','data'=>$data]);
    exit();
}

if($action === 'tambah' || $action === 'ubah'){
    $nama = sanitize($_POST['nama_kategori']);
    $id_kategori = sanitize($_POST['id_kategori'] ?? '');

    // Untuk owner/admin, default bengkel_id
    $bengkel_id = $accessible_bengkel_ids[0];

    if(empty($nama)){
        echo json_encode(['status'=>'error','message'=>'Nama kategori tidak boleh kosong']);
        exit();
    }

    if($action === 'tambah'){
        $q = mysqli_query($conn,"INSERT INTO kategori_asset(nama_kategori,bengkel_id) VALUES('$nama','$bengkel_id')");
        if($q){
            $insert_id = mysqli_insert_id($conn);
            echo json_encode(['status'=>'success','message'=>'Kategori berhasil ditambahkan','id_kategori'=>$insert_id]);
        }else{
            echo json_encode(['status'=>'error','message'=>mysqli_error($conn)]);
        }
    }elseif($action === 'ubah'){
        if(empty($id_kategori)){
            echo json_encode(['status'=>'error','message'=>'ID kategori tidak valid']);
            exit();
        }
        // Cek akses
        $qCheck = mysqli_query($conn,"SELECT bengkel_id FROM kategori_asset WHERE id_kategori='$id_kategori'");
        if($r = mysqli_fetch_assoc($qCheck)){
            if(!in_array($r['bengkel_id'],$accessible_bengkel_ids)){
                echo json_encode(['status'=>'error','message'=>'Akses ditolak']);
                exit();
            }
        }else{
            echo json_encode(['status'=>'error','message'=>'Kategori tidak ditemukan']);
            exit();
        }
        $q = mysqli_query($conn,"UPDATE kategori_asset SET nama_kategori='$nama' WHERE id_kategori='$id_kategori'");
        if($q){
            echo json_encode(['status'=>'success','message'=>'Kategori berhasil diubah']);
        }else{
            echo json_encode(['status'=>'error','message'=>mysqli_error($conn)]);
        }
    }
    exit();
}

if($action === 'hapus'){
    $id_kategori = sanitize($_POST['id_kategori'] ?? '');
    if(empty($id_kategori)){
        echo json_encode(['status'=>'error','message'=>'ID kategori tidak valid']);
        exit();
    }
    // Cek akses
    $qCheck = mysqli_query($conn,"SELECT bengkel_id FROM kategori_asset WHERE id_kategori='$id_kategori'");
    if($r = mysqli_fetch_assoc($qCheck)){
        if(!in_array($r['bengkel_id'],$accessible_bengkel_ids)){
            echo json_encode(['status'=>'error','message'=>'Akses ditolak']);
            exit();
        }
    }else{
        echo json_encode(['status'=>'error','message'=>'Kategori tidak ditemukan']);
        exit();
    }
    $q = mysqli_query($conn,"DELETE FROM kategori_asset WHERE id_kategori='$id_kategori'");
    if($q){
        echo json_encode(['status'=>'success','message'=>'Kategori berhasil dihapus']);
    }else{
        echo json_encode(['status'=>'error','message'=>mysqli_error($conn)]);
    }
    exit();
}

// Default response jika action tidak dikenali
echo json_encode(['status'=>'error','message'=>'Action tidak valid']);
