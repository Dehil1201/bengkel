<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php yang di-include di index.php
// Pastikan juga session_start() sudah aktif di index.php

// Inisialisasi variabel untuk menghindari "Undefined variable" jika query gagal
$total_kuis = 0;
$total_pengguna = 0;
$total_siswa = 0;
$total_guru = 0;


// --- Query untuk mengambil jumlah total Pengguna ---
// Menggunakan tabel 'users'
$query_pengguna = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users");
if ($query_pengguna) {
    $data_pengguna = mysqli_fetch_assoc($query_pengguna);
    $total_pengguna = $data_pengguna['total'];
} else {
    // Debugging: Tampilkan error jika query gagal
    // echo "Error query pengguna: " . mysqli_error($conn);
}

?>

<div class="row">
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-green">
            <div class="inner">
                <h3><?= $total_kuis; ?></h3>
                <p>Total Kuis</p>
            </div>
            <div class="icon">
                <i class="ion ion-ios-compose"></i>
            </div>
            <a href="?page=kuis" class="small-box-footer">Lihat Selengkapnya <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3><?= $total_pengguna; ?></h3>
                <p>Total Pengguna Sistem</p>
            </div>
            <div class="icon">
                <i class="ion ion-person-add"></i>
            </div>
            <a href="?page=users_data" class="small-box-footer">Lihat Selengkapnya <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-red">
            <div class="inner">
                <h3></h3>
                <p>Total Siswa</p>
            </div>
            <div class="icon">
                <i class="ion ion-university"></i>
            </div>
            <a href="?page=data_siswa" class="small-box-footer">Lihat Selengkapnya <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-blue-active">
            <div class="inner">
                <h3></h3>
                <p>Total Guru</p>
            </div>
            <div class="icon">
                <i class="ion ion-ios-people"></i>
            </div>
            <a href="?page=data_guru_mapel" class="small-box-footer">Lihat Selengkapnya <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    </div>
<div class="row">
    <div class="col-md-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Selamat Datang!</h3>
            </div>
            <div class="box-body">
                <p>Selamat datang di sistem RC MOTOR. Gunakan menu di samping untuk navigasi.</p>
                <p>Anda login sebagai **<?= ucfirst($_SESSION['role']); ?>**.</p>
                <p>Jika ada pertanyaan atau kendala, silakan hubungi administrator sistem.</p>
            </div>
        </div>
    </div>
</div>