<?php
// Pastikan session_start() dipanggil di awal setiap skrip yang menggunakan sesi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database
include 'inc/koneksi.php';

// Periksa apakah pengguna sudah login. Jika ya, arahkan langsung ke dashboard.
if (isset($_SESSION['email']) && $_SESSION['email'] != "") {
    header("location:index.php?page=dashboard");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>RC Motor | Log in</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <link rel="stylesheet" href="bower_components/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="bower_components/Ionicons/css/ionicons.min.css">
    <link rel="stylesheet" href="dist/css/AdminLTE.min.css">
    <link rel="icon" href="dist/img/logo_sekolah.png">
    <link rel="stylesheet" href="plugins/iCheck/square/blue.css">

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo">
        <a href=""><b>RC </b>MOTOR</a>
    </div>
    <div class="login-box-body">
        <p class="login-box-msg">Sign in to start your session</p>
        <?php
        if (isset($_POST['login'])) {
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $password_plain = $_POST['password']; // Ambil kata sandi dalam bentuk teks biasa

            // --- Ubah query untuk hanya mencari berdasarkan email ---
            $sql = mysqli_query($conn, "SELECT id_user, email, password, role, nama_lengkap FROM users WHERE email = '$email'");
            $data_user = mysqli_fetch_assoc($sql);

            // --- Gunakan password_verify() untuk membandingkan kata sandi ---
            // Hanya jalankan verifikasi jika ada data pengguna ditemukan
            if ($data_user && password_verify($password_plain, $data_user['password'])) {
                // Login berhasil
                $_SESSION['id_user'] = $data_user['id_user'];
                $_SESSION['email'] = $data_user['email'];
                $_SESSION['role'] = $data_user['role'];
                $_SESSION['nama_lengkap'] = $data_user['nama_lengkap'];

                ?>
                <script>
                    window.location.href="index.php?page=dashboard";
                </script>
                <?php
            } else {
                // Login gagal
                ?>
                <div class="alert alert-danger">
                    <strong>Login Gagal! </strong><br><i>Email atau Password yang Anda masukkan salah!</i>
                </div>
                <?php
            }
        }
        ?>
        <form method="post">
            <div class="form-group has-feedback">
                <input type="email" class="form-control" placeholder="email" name="email" required="">
                <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
            </div>
            <div class="form-group has-feedback">
                <input type="password" class="form-control" placeholder="Password" name="password" required="">
                <span class="glyphicon glyphicon-lock form-control-feedback"></span>
            </div>
            <div class="row">
                <div class="col-xs-8">
                </div>
                <div class="col-xs-4">
                    <button type="submit" class="btn btn-primary btn-block btn-flat" name="login">Sign In</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script src="bower_components/jquery/dist/jquery.min.js"></script>
<script src="bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
<script src="plugins/iCheck/icheck.min.js"></script>
<script>
    $(function () {
        $('input').iCheck({
            checkboxClass: 'icheckbox_square-blue',
            radioClass: 'iradio_square-blue',
            increaseArea: '20%' /* optional */
        });
    });
</script>
</body>
</html>