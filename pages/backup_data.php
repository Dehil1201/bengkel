<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php yang di-include di index.php
// Pastikan juga session_start() sudah aktif di index.php

// Cek hak akses: hanya admin yang bisa mengakses halaman ini
if (function_exists('get_user_role') && get_user_role() !== 'admin') {
    echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat halaman ini.</div>";
    exit(); // Hentikan eksekusi script
} elseif (!function_exists('get_user_role')) {
    echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan. Pastikan file fungsi.php ter-include.</div>";
    exit();
}

// Konfigurasi Database - Konstanta ini seharusnya sudah terdefinisi melalui inc/koneksi.php
$db_host = DB_HOST;
$db_user = DB_USER;
$db_pass = DB_PASS;
$db_name = DB_NAME;

// Path ke mysqldump
// Di XAMPP Windows, biasanya: "C:\\xampp\\mysql\\bin\\mysqldump.exe"
// Di Linux/Mac, biasanya: "/usr/bin/mysqldump" atau "/usr/local/bin/mysqldump"
$mysqldump_path = "D:\\www\\mysql\\bin\\mysqldump.exe"; // Coba path default dulu, jika gagal baru tentukan path lengkap

// Logika Backup
if (isset($_POST['do_backup'])) {
    // Validasi sederhana jika konstanta entah kenapa kosong (walau seharusnya tidak)
    if (empty($db_host) || empty($db_user) || empty($db_name)) {
        echo "<div class='alert alert-danger'>Kredensial database tidak lengkap. Backup tidak dapat dilakukan. Pastikan DB_HOST, DB_USER, DB_PASS, DB_NAME terdefinisi di inc/koneksi.php.</div>";
        exit();
    }

    // Nama file backup
    $backup_file_name = $db_name . '_' . date("Ymd_His") . '.sql';
    $backup_path = __DIR__ . '/../backup/' . $backup_file_name; // Simpan di folder 'backup' di root proyek

    // Pastikan folder backup ada dan bisa ditulis
    if (!is_dir(__DIR__ . '/../backup/')) {
        mkdir(__DIR__ . '/../backup/', 0777, true);
    }

    // Perintah mysqldump
    $command = sprintf(
        "%s -h %s -u %s %s %s > %s",
        escapeshellarg($mysqldump_path),
        escapeshellarg($db_host),
        escapeshellarg($db_user),
        (empty($db_pass) ? '' : '-p' . escapeshellarg($db_pass)), // Tambahkan -p jika ada password
        escapeshellarg($db_name),
        escapeshellarg($backup_path)
    );

    // Eksekusi perintah
    $output = [];
    $return_var = 0;
    exec($command, $output, $return_var);

    if ($return_var === 0) {
        // Jika backup berhasil, paksa unduh file
        if (file_exists($backup_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($backup_file_name) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($backup_path));
            flush(); // Flush system output buffer
            readfile($backup_path);
            unlink($backup_path); // Hapus file dari server setelah diunduh
            exit;
        } else {
            echo "<div class='alert alert-danger'>Backup berhasil dibuat, tetapi file tidak ditemukan untuk diunduh.</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>Gagal membuat backup database. Pesan Error: <pre>" . implode("\n", $output) . "</pre> Pastikan 'mysqldump' tersedia di PATH server atau tentukan path lengkapnya.</div>";
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Backup Database</h3>
            </div>
            <div class="box-body">
                <p>Klik tombol di bawah ini untuk membuat salinan (*backup*) lengkap *database* sekolahmu.</p>
                <p>File *backup* akan diunduh secara otomatis ke komputermu dan akan berformat `.sql`.</p>
                <p class="text-warning"><i class="fa fa-exclamation-triangle"></i> **Penting:** Pastikan kamu menyimpan file *backup* ini di tempat yang aman dan terpisah dari server. File ini berisi semua data penting sekolahmu.</p>
                <form method="POST" action="?page=backup_data">
                    <button type="submit" name="do_backup" class="btn btn-primary btn-lg">
                        <i class="fa fa-database"></i> Buat & Unduh Backup Sekarang
                    </button>
                </form>
            </div>
            </div>
        </div>
</div>