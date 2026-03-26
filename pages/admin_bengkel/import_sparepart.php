<?php
include '../../inc/koneksi.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_FILES['file_excel']['name']) {

    $file = $_FILES['file_excel']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet()->toArray();

        $success = 0;
        $failed = 0;

        foreach ($sheet as $index => $row) {

            // skip header
            if ($index == 0) continue;
            session_start();
            $bengkel_id = $_SESSION['id_bengkel'] ?? 0;
            $kode      = mysqli_real_escape_string($conn, $row[0]);
            $nama      = mysqli_real_escape_string($conn, $row[1]);
            $merk      = mysqli_real_escape_string($conn, $row[2]);
            $kategori  = mysqli_real_escape_string($conn, $row[3]);
            $stok      = (int)$row[4];
            $harga_beli= (int)$row[5];
            $harga_jual= (int)$row[6];

            // ================== MERK ==================
            if (empty($merk)) {
                $id_merk = 0;
            } else {
                $cekMerk = mysqli_query($conn, "SELECT id_merk FROM merk_sparepart WHERE nama_merk='$merk'");
                
                if(mysqli_num_rows($cekMerk) > 0){
                    $id_merk = mysqli_fetch_assoc($cekMerk)['id_merk'];
                } else {
                    $id_merk = 0; // ❌ tidak insert, langsung 0
                }
            }

            // ================== KATEGORI ==================
            if (empty($kategori)) {
                $id_kategori = 0;
            } else {
                $cekKategori = mysqli_query($conn, "SELECT id_kategori FROM kategori_sparepart WHERE nama_kategori='$kategori'");
                
                if(mysqli_num_rows($cekKategori) > 0){
                    $id_kategori = mysqli_fetch_assoc($cekKategori)['id_kategori'];
                } else {
                    $id_kategori = 0; // ❌ tidak insert
                }
            }

            $query = mysqli_query($conn, "
                INSERT INTO spareparts 
                (kode_sparepart, nama_sparepart, merk_id, kategori_id, stok_pcs, harga_beli, bengkel_id)
                VALUES
                ('$kode', '$nama', '$id_merk', '$id_kategori', '$stok', '$harga_beli', '$bengkel_id')
            ");

            if($query){
                $success++;
            } else {
                $failed++;
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => "Import selesai. Berhasil: $success, Gagal: $failed"
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }

} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'File tidak ditemukan'
    ]);
}