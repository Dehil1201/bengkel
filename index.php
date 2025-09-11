<?php
ob_start();
// Pastikan session_start() sudah ada di awal script ini atau di file 'inc/koneksi.php'
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// Pastikan file koneksi dan excel reader disertakan
include 'inc/koneksi.php';
require_once 'inc/functions.php';
include 'plugins/php_reader/excel_reader2.php';
$page = $_GET['page'] ?? 'default_page'; // Menggunakan operator null coalescing untuk menghindari error jika 'page' tidak disetel
$breadcrumb = str_replace("_", " ", $page);

// Cek apakah pengguna sudah login
if (!isset($_SESSION['email']) || $_SESSION['email'] == "") {
    header("location:login.php");
    exit();
} else {
    // Pastikan $_SESSION['role'] sudah diisi saat login.
    if (!isset($_SESSION['role'])) {
        $query_user_role = mysqli_query($conn, "SELECT role FROM users WHERE email = '$_SESSION[email]'");
        $user_data_from_db = mysqli_fetch_array($query_user_role);
        $_SESSION['role'] = $user_data_from_db['role'] ?? 'siswa';
    }

    $current_user_role = $_SESSION['role'];

    // Ambil data pengguna lengkap dari tabel 'users'
    $query_data_users = mysqli_query($conn, "SELECT nama_lengkap, role, users.bengkel_id, bengkels.nama_bengkel FROM users join bengkels on users.bengkel_id = bengkels.id_bengkel WHERE email = '$_SESSION[email]'");
    $data_users = mysqli_fetch_array($query_data_users);

    $roletext = str_replace("_"," ", $data_users['role']);
    $nama_bengkel = htmlspecialchars($data_users['nama_bengkel']);
    $id_bengkel = htmlspecialchars($data_users['bengkel_id']);

    // Periksa apakah data pengguna ditemukan. Jika tidak, redirect ke logout.
    if (!$data_users) {
        header("location:logout.php");
        exit();
    }

    // Fungsi helper untuk mendapatkan peran pengguna dari sesi.
    if (!function_exists('get_user_role')) {
        function get_user_role() {
            return $_SESSION['role'] ?? 'guest';
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>RC Motor | Dashboard</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <link rel="stylesheet" href="bower_components/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="dist/css/AdminLTE.min.css">
    <link rel="stylesheet" href="dist/css/skins/_all-skins.min.css">
    <link rel="icon" href="">
    <link rel="stylesheet" href="bower_components/morris.js/morris.css">
    <link rel="stylesheet" href="bower_components/jvectormap/jquery-jvectormap.css">
    <link rel="stylesheet" href="bower_components/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css">
    <link rel="stylesheet" href="bower_components/bootstrap-daterangepicker/daterangepicker.css">
    <link rel="stylesheet" href="plugins/bootstrap-wysihtml5/bootstrap3-wysihtml5.min.css">
    <link rel="stylesheet" type="text/css" href="plugins/datatable/datatables.css">
    <script src="bower_components/jquery/dist/jquery.min.js"></script>
    <script src="bower_components/jquery-ui/jquery-ui.min.js"></script>
    <script type="text/javascript" src="plugins/datatable/datatables.js"></script>
    <script type="text/javascript" src="plugins/printThis/printThis.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
</head>
<body class="hold-transition skin-blue sidebar-mini">
<div class="wrapper">

    <header class="main-header">
        <a href="#" class="logo">
            <span class="logo-mini"><b>RC</b>-Motor</span>
            <span class="logo-lg"><b>RC</b>-Motor</span>
        </a>
        <nav class="navbar navbar-static-top">
            <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
                <span class="sr-only">Toggle navigation</span>
            </a>

            <div class="navbar-custom-menu">
                <ul class="nav navbar-nav">
                    <li class="dropdown user tasks-menu">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                            <?= $nama_bengkel; ?>
                        </a>
                    </li>
                    <li class="dropdown user tasks-menu">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                            <i class="fa fa-calendar" aria-hidden="true"></i>
                            <span class="" id="tanggal">
                                <?php
                                    function hari_ini(){
                                        $hari = date ("D");
                                        
                                        switch($hari){
                                            case 'Sun':
                                                $hari_ini = "Minggu";
                                            break;
                                            case 'Mon': 
                                                $hari_ini = "Senin";
                                            break;
                                            case 'Tue':
                                                $hari_ini = "Selasa";
                                            break;
                                            case 'Wed':
                                                $hari_ini = "Rabu";
                                            break;
                                            case 'Thu':
                                                $hari_ini = "Kamis";
                                            break;
                                            case 'Fri':
                                                $hari_ini = "Jumat";
                                            break;
                                            case 'Sat':
                                                $hari_ini = "Sabtu";
                                            break;
                                            default:
                                                $hari_ini = "Tidak di ketahui";
                                            break;
                                        }
                                        return $hari_ini;
                                    }
                                ?>
                                <?= hari_ini().', '.date("Y-m-d"); ?>
                            </span>
                            &nbsp;
                            <i class="fa fa-clock-o" aria-hidden="true"></i>
                            <span class="" id="jam">
                            </span>
                        </a>
                    </li>
                    <li class="dropdown user user-menu">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                            <img src="dist/img/avatar3.png" class="user-image" alt="User Image">
                            <span class="hidden-xs"><?= $data_users['nama_lengkap']; ?> (<?= strtoupper($roletext); ?>)</span>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="user-header">
                                <img src="dist/img/avatar3.png" class="img-circle" alt="User Image">
                                <p>
                                    <?= $data_users['nama_lengkap']; ?> - <?= strtoupper($roletext); ?>
                                </p>
                            </li>
                            <li class="user-footer">
                                <div class="pull-right">
                                    <a href="logout.php" class="btn btn-default btn-flat">Sign out</a>
                                </div>
                                <div class="pull-left">
                                    <a href="?page=ubah password" class="btn btn-default">Ubah Password</a>
                                </div>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
    </header>
    <aside class="main-sidebar">
        <section class="sidebar">
            <div class="user-panel">
                <div class="pull-left image">
                    <img src="dist/img/avatar3.png" class="img-circle" alt="User Image">
                </div>
                <div class="pull-left info">
                    <p><?= $data_users['nama_lengkap']; ?></p>
                    <a href="#"><i class="fa fa-circle text-success"></i> Online</a>
                </div>
            </div>
            <form action="#" method="get" class="sidebar-form">
                <div class="input-group">
                    <input type="text" name="q" class="form-control" placeholder="Search...">
                    <span class="input-group-btn">
                        <button type="submit" name="search" id="search-btn" class="btn btn-flat"><i class="fa fa-search"></i>
                        </button>
                    </span>
                </div>
            </form>
            <?php 
            $page = $_GET['page'] ?? 'dashboard';
            ?>

            <ul class="sidebar-menu" data-widget="tree">
                <li class="header">MAIN NAVIGATION</li>

                <li class="<?php echo ($page === 'dashboard') ? 'active' : ''; ?>">
                    <a href="?page=dashboard">
                        <i class="fa fa-dashboard"></i><span>Dashboard</span>
                    </a>
                </li>
                
                <?php if (get_user_role() === 'super_user'): ?>
                <li class="<?php echo ($page === 'users_data') ? 'active' : ''; ?>">
                    <a href="?page=users_data">
                        <i class="fa fa-users"></i><span>Management Pengguna</span>
                    </a>
                </li>
                <li class="<?php echo ($page === 'management_bengkel') ? 'active' : ''; ?>">
                    <a href="?page=management_bengkel">
                        <i class="fa fa-wrench"></i><span>Management Bengkel</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (get_user_role() === 'admin_bengkel'): ?>
                <li class="treeview <?php echo (in_array($page, ['sparepart', 'barcode', 'supplier', 'pelanggan', 'teknisi', 'jenis_services', 'merk', 'sub_merk', 'stok_opname', 'history_stok_masuk', 'history_stok_keluar'])) ? 'active menu-open' : ''; ?>">
                    <a href="#">
                        <i class="fa fa-database"></i><span>Master Data</span>
                        <span class="pull-right-container">
                            <i class="fa fa-angle-left pull-right"></i>
                        </span>
                    </a>
                    <ul class="treeview-menu">
                        <li class="<?php echo ($page === 'sparepart') ? 'active' : ''; ?>">
                            <a href="?page=sparepart"><i class="fa fa-list"></i> Sparepart</a>
                        </li>
                        <li class="<?php echo ($page === 'barcode') ? 'active' : ''; ?>">
                            <a href="?page=barcode"><i class="fa fa-list"></i> Barcode</a>
                        </li>
                        <li class="<?php echo ($page === 'supplier') ? 'active' : ''; ?>">
                            <a href="?page=supplier"><i class="fa fa-list"></i> Supplier</a>
                        </li>
                        <li class="<?php echo ($page === 'pelanggan') ? 'active' : ''; ?>">
                            <a href="?page=pelanggan"><i class="fa fa-list"></i> Pelanggan</a>
                        </li>
                        <li class="<?php echo ($page === 'teknisi') ? 'active' : ''; ?>">
                            <a href="?page=teknisi"><i class="fa fa-list"></i> Teknisi</a>
                        </li>
                        <li class="<?php echo ($page === 'jenis_services') ? 'active' : ''; ?>">
                            <a href="?page=jenis_services"><i class="fa fa-list"></i> Jenis Services</a>
                        </li>
                        <li class="<?php echo ($page === 'merk') ? 'active' : ''; ?>">
                            <a href="?page=merk"><i class="fa fa-list"></i> Merk</a>
                        </li>
                        <li class="<?php echo ($page === 'sub_merk') ? 'active' : ''; ?>">
                            <a href="?page=sub_merk"><i class="fa fa-list"></i> Sub Merk</a>
                        </li>
                        <li class="<?php echo ($page === 'stok_opname') ? 'active' : ''; ?>">
                            <a href="?page=stok_opname"><i class="fa fa-list"></i> Stok Opname</a>
                        </li>
                        <li class="<?php echo ($page === 'history_stok_masuk') ? 'active' : ''; ?>">
                            <a href="?page=history_stok_masuk"><i class="fa fa-list"></i> History Stok Masuk</a>
                        </li>
                        <li class="<?php echo ($page === 'history_stok_keluar') ? 'active' : ''; ?>">
                            <a href="?page=history_stok_keluar"><i class="fa fa-list"></i> History Stok Keluar</a>
                        </li>
                    </ul>
                </li>

                <li class="treeview <?php echo (in_array($page, ['asset', 'modal', 'biaya'])) ? 'active menu-open' : ''; ?>">
                    <a href="#">
                        <i class="fa fa-money"></i><span>Keuangan</span>
                        <span class="pull-right-container">
                            <i class="fa fa-angle-left pull-right"></i>
                        </span>
                    </a>
                    <ul class="treeview-menu">
                        <li class="<?php echo ($page === 'asset') ? 'active' : ''; ?>">
                            <a href="?page=asset"><i class="fa fa-list"></i> Aset</a>
                        </li>
                        <li class="<?php echo ($page === 'modal') ? 'active' : ''; ?>">
                            <a href="?page=modal"><i class="fa fa-list"></i> Modal</a>
                        </li>
                        <li class="<?php echo ($page === 'biaya') ? 'active' : ''; ?>">
                            <a href="?page=biaya"><i class="fa fa-list"></i> Biaya</a>
                        </li>
                    </ul>
                </li>

                <li class="<?php echo ($page === 'kasir') ? 'active' : ''; ?>">
                    <a href="?page=kasir">
                        <i class="fa fa-shopping-cart"></i><span>Kasir</span>
                    </a>
                </li>
                
                <li class="<?php echo ($page === 'jasa_services') ? 'active' : ''; ?>">
                    <a href="?page=jasa_services">
                        <i class="fa fa-cogs"></i><span>Jasa Services</span>
                    </a>
                </li>

                <li class="treeview <?php echo (in_array($page, ['penjualan', 'jasa_services_report', 'pembelian', 'piutang', 'hutang', 'return_penjualan', 'return_pembelian'])) ? 'active menu-open' : ''; ?>">
                    <a href="#">
                        <i class="fa fa-exchange"></i><span>Transaksi</span>
                        <span class="pull-right-container">
                            <i class="fa fa-angle-left pull-right"></i>
                        </span>
                    </a>
                    <ul class="treeview-menu">
                        <li class="<?php echo ($page === 'penjualan') ? 'active' : ''; ?>">
                            <a href="?page=penjualan"><i class="fa fa-list"></i> Penjualan</a>
                        </li>
                        <li class="<?php echo ($page === 'jasa_services_report') ? 'active' : ''; ?>">
                            <a href="?page=jasa_services_report"><i class="fa fa-list"></i> Jasa Services</a>
                        </li>
                        <li class="<?php echo ($page === 'pembelian') ? 'active' : ''; ?>">
                            <a href="?page=pembelian"><i class="fa fa-list"></i> Pembelian</a>
                        </li>
                        <li class="<?php echo ($page === 'piutang') ? 'active' : ''; ?>">
                            <a href="?page=piutang"><i class="fa fa-list"></i> Piutang</a>
                        </li>
                        <li class="<?php echo ($page === 'hutang') ? 'active' : ''; ?>">
                            <a href="?page=hutang"><i class="fa fa-list"></i> Hutang</a>
                        </li>
                        <li class="<?php echo ($page === 'return_penjualan') ? 'active' : ''; ?>">
                            <a href="?page=return_penjualan"><i class="fa fa-list"></i> Return Penjualan</a>
                        </li>
                        <li class="<?php echo ($page === 'return_pembelian') ? 'active' : ''; ?>">
                            <a href="?page=return_pembelian"><i class="fa fa-list"></i> Return Pembelian</a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (get_user_role() === 'owner_bengkel'): ?>
                <li class="<?php echo ($page === 'management_users_by_owner') ? 'active' : ''; ?>">
                    <a href="?page=management_users_by_owner">
                        <i class="fa fa-users"></i><span>Manajemen Pengguna</span>
                    </a>
                </li>
                <li class="<?php echo ($page === 'management_teknisi') ? 'active' : ''; ?>">
                    <a href="?page=management_teknisi">
                        <i class="fa fa-users"></i><span>Manajemen Teknisi</span>
                    </a>
                </li>

                <li class="treeview <?php echo (in_array($page, ['laporan_penjualan', 'laporan_jasa_services', 'laporan_pembelian', 'laporan_keuangan', 'laporan_stok', 'laporan_piutang_hutang'])) ? 'active menu-open' : ''; ?>">
                    <a href="#">
                        <i class="fa fa-bar-chart"></i><span>Laporan</span>
                        <span class="pull-right-container">
                            <i class="fa fa-angle-left pull-right"></i>
                        </span>
                    </a>
                    <ul class="treeview-menu">
                        <li class="<?php echo ($page === 'laporan_penjualan') ? 'active' : ''; ?>">
                            <a href="?page=laporan_penjualan"><i class="fa fa-list"></i> Penjualan</a>
                        </li>
                        <li class="<?php echo ($page === 'laporan_jasa_services') ? 'active' : ''; ?>">
                            <a href="?page=laporan_jasa_services"><i class="fa fa-list"></i> Jasa Services</a>
                        </li>
                        <li class="<?php echo ($page === 'laporan_pembelian') ? 'active' : ''; ?>">
                            <a href="?page=laporan_pembelian"><i class="fa fa-list"></i> Pembelian</a>
                        </li>
                        <li class="<?php echo ($page === 'laporan_keuangan') ? 'active' : ''; ?>">
                            <a href="?page=laporan_keuangan"><i class="fa fa-list"></i> Keuangan</a>
                        </li>
                        <li class="<?php echo ($page === 'laporan_stok') ? 'active' : ''; ?>">
                            <a href="?page=laporan_stok"><i class="fa fa-list"></i> Stok</a>
                        </li>
                        <li class="<?php echo ($page === 'laporan_piutang_hutang') ? 'active' : ''; ?>">
                            <a href="?page=laporan_piutang_hutang"><i class="fa fa-list"></i> Piutang & Hutang</a>
                        </li>
                    </ul>
                </li>
                
                <?php endif; ?>
                
            </ul>
        </section>
    </aside>

    <div class="content-wrapper">
        <section class="content-header">
            <h1>
                RC MOTOR / <?= strtoupper($breadcrumb ?? 'DASHBOARD'); ?>
                <small>Control panel</small>
            </h1>
            <ol class="breadcrumb">
                <li>RC MOTOR</li>
                <li class="text-capitalize"><?= $breadcrumb ?? 'Tidak Tersedia'; ?></li>
            </ol>
        </section>

        <section class="content">
            <?php
            $page = $_GET['page'] ?? 'dashboard';
            $halaman = str_replace(" ", "_", $page);
            $folder = ''; // Default folder

            if ($current_user_role == 'super_user') {
                $folder = 'super_user/';
            } else if ($current_user_role == 'admin_bengkel') {
                $folder = 'admin_bengkel/';
            } else if ($current_user_role == 'owner_bengkel') {
                $folder = 'owner_bengkel/';
            } else {
                // Atur folder untuk peran lain jika ada
                $folder = '';
            }

            $halaman2 = "pages/".$folder. $halaman . ".php";

            if (file_exists($halaman2)) {
                include $halaman2;
            } else {
                echo "<div class='alert alert-danger'>Halaman <strong>" . htmlspecialchars($page) . "</strong> Tidak ditemukan atau tidak ada akses untuk peran ini.</div>";
            }
            ?>
        </section>
        </div>
    <footer class="main-footer">
        <strong>Copyright &copy; 2025 <a href=""> RC Motor</a>.</strong> All rights
        reserved.
    </footer>
</div>
<script>
    $.widget.bridge('uibutton', $.ui.button);
</script>
<script src="bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
<script src="bower_components/raphael/raphael.min.js"></script>
<script src="bower_components/morris.js/morris.min.js"></script>
<script src="bower_components/jquery-sparkline/dist/jquery.sparkline.min.js"></script>
<script src="bower_components/jquery-knob/dist/jquery.knob.min.js"></script>
<script src="bower_components/moment/min/moment.min.js"></script>
<script src="bower_components/bootstrap-daterangepicker/daterangepicker.js"></script>
<script src="bower_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>
<script src="plugins/bootstrap-wysihtml5/bootstrap3-wysihtml5.all.min.js"></script>
<script src="bower_components/jquery-slimscroll/jquery.slimscroll.min.js"></script>
<script src="bower_components/fastclick/lib/fastclick.js"></script>
<script src="dist/js/adminlte.min.js"></script>
<script type="text/javascript">

    $('table').attr('width','100%');

    window.onload = function() { jam(); }
        
    function jam() {
        var e = document.getElementById('jam'),
        d = new Date(), h, m, s;
        h = d.getHours();
        m = set(d.getMinutes());
        s = set(d.getSeconds());
    
        e.innerHTML = h +':'+ m +':'+ s;
    
        setTimeout('jam()', 1000);
    }
    
    function set(e) {
        e = e < 10 ? '0'+ e : e;
        return e;
    }
</script>
</body>
</html>
<?php
}
ob_end_flush();
?>