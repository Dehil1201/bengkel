<?php
    date_default_timezone_set("Asia/jakarta");

    // Definisikan konstanta untuk kredensial database
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', ''); // Jika ada password, isi di sini. Contoh: 'your_password'
    define('DB_NAME', 'rcmotor'); // Ganti dengan nama database kamu: lms_sekolah

    // Buat koneksi menggunakan konstanta
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Cek koneksi
    if (!$conn) {
        die("Koneksi database gagal: " . mysqli_connect_error());
    }


    #error_reporting(0); // Jika ingin menonaktifkan error reporting
?>