<?php
// Konfigurasi database
$host = "localhost";
$user = "root";
$pass = ""; // ganti jika ada password
$dbName = "rcmotor";

// Nama file hasil export (tidak ada tanggal)
$filename = "rcmotor.sql";

// Path lengkap mysqldump jika perlu (Windows XAMPP)
$mysqldump = "D:\\www\\mysql\\bin\\mysqldump.exe";

// Jalankan mysqldump
$command = "{$mysqldump} --user={$user} --password={$pass} --add-drop-table {$dbName} > {$filename}";

// Eksekusi command
system($command, $output);

if ($output === 0) {
    echo "Export database berhasil. File: {$filename}";
} else {
    echo "Export database gagal!";
}
?>