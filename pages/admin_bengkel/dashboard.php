<?php
// Pastikan koneksi $conn sudah aktif

// --- 1. Statistik dasar ---
$total_spareparts = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM spareparts where bengkel_id = '$id_bengkel'
"))['total'] ?? 0;

$total_pelanggan = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM pelanggans where bengkel_id = '$id_bengkel'
"))['total'] ?? 0;

$omset_bulan_ini = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT SUM(total) AS total
    FROM transaksi
    WHERE MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE()) and id_bengkel = '$id_bengkel'
"))['total'] ?? 0;

$laba_bulan_ini = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT SUM(td.subtotal - (td.qty * s.hpp_per_pcs)) AS laba
    FROM transaksi_detail_sparepart td
    JOIN spareparts s ON td.kode_sparepart = s.kode_sparepart
    JOIN transaksi t ON td.no_faktur = t.no_faktur
    WHERE MONTH(t.tanggal) = MONTH(CURDATE()) AND YEAR(t.tanggal) = YEAR(CURDATE()) and bengkel_id = '$id_bengkel'
"))['laba'] ?? 0;

// --- 2. Grafik bulanan & harian ---
// Data Omset & Laba per 12 bulan terakhir
$labels_penjualan = [];
$data_omset_per_bulan = [];
$data_laba_per_bulan = [];
for ($i = 11; $i >= 0; $i--) {
    $bulan = date('m', strtotime("-$i month"));
    $tahun = date('Y', strtotime("-$i month"));
    $label = date('M Y', strtotime("-$i month"));
    $labels_penjualan[] = $label;

    $res_omset = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT SUM(total) AS omset
        FROM transaksi
        WHERE MONTH(tanggal) = '$bulan' AND YEAR(tanggal) = '$tahun' and id_bengkel = '$id_bengkel'
    "));
    $omset = $res_omset['omset'] ?? 0;
    $data_omset_per_bulan[] = (float)$omset;

    $res_laba = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT SUM(td.subtotal - (td.qty * s.hpp_per_pcs)) AS laba
        FROM transaksi_detail_sparepart td
        JOIN spareparts s ON td.kode_sparepart = s.kode_sparepart
        JOIN transaksi t ON td.no_faktur = t.no_faktur
        WHERE MONTH(t.tanggal) = '$bulan' AND YEAR(t.tanggal) = '$tahun' and bengkel_id = '$id_bengkel'
    "));
    $laba = $res_laba['laba'] ?? 0;
    $data_laba_per_bulan[] = (float)$laba;
}

// Data transaksi harian bulan ini
$labels_harian = [];
$data_transaksi_harian = [];
$days_in_month = date('t');
for ($d = 1; $d <= $days_in_month; $d++) {
    $labels_harian[] = $d;
    $res3 = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(*) AS count_tr
        FROM transaksi
        WHERE DAY(tanggal) = '$d' AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE()) and id_bengkel = '$id_bengkel'
    "));
    $cnt = $res3['count_tr'] ?? 0;
    $data_transaksi_harian[] = (int)$cnt;
}

// --- 3. Data stok limit ---
$stok_limit = 5;
$query_stok_limit = mysqli_query($conn, "
    SELECT kode_sparepart, nama_sparepart, stok_pcs
    FROM spareparts
    WHERE stok_pcs <= $stok_limit and bengkel_id = '$id_bengkel'
    ORDER BY stok_pcs ASC
    LIMIT 10
");

// --- 4. Data barang terlaris ---
$query_barang_terlaris = mysqli_query($conn, "
    SELECT s.kode_sparepart, s.nama_sparepart, SUM(td.qty) AS total_terjual
    FROM transaksi_detail_sparepart td
    JOIN spareparts s ON td.kode_sparepart = s.kode_sparepart where bengkel_id = '$id_bengkel'
    GROUP BY s.kode_sparepart, s.nama_sparepart
    ORDER BY total_terjual DESC
    LIMIT 10
");
?>

<!-- FontAwesome sudah ada di AdminLTE2, jika belum bisa tambahkan -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Statistik Boxes -->
<div class="row">
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3><?= number_format($total_spareparts,0,',','.'); ?></h3>
                <p>Total Sparepart</p>
            </div>
            <div class="icon"><i class="fa fa-cogs"></i></div>
            <a href="?page=spareparts" class="small-box-footer">Selengkapnya <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3><?= number_format($total_pelanggan,0,',','.'); ?></h3>
                <p>Total Pelanggan</p>
            </div>
            <div class="icon"><i class="fa fa-users"></i></div>
            <a href="?page=pelanggan" class="small-box-footer">Selengkapnya <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-green">
            <div class="inner">
                <h3>Rp <?= number_format($omset_bulan_ini,0,',','.'); ?></h3>
                <p>Omset Bulan Ini</p>
            </div>
            <div class="icon"><i class="fa fa-line-chart"></i></div>
            <a href="?page=transaksi" class="small-box-footer">Selengkapnya <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-red">
            <div class="inner">
                <h3>Rp <?= number_format($laba_bulan_ini,0,',','.'); ?></h3>
                <p>Laba Bulan Ini</p>
            </div>
            <div class="icon"><i class="fa fa-money"></i></div>
            <a href="?page=laporan" class="small-box-footer">Selengkapnya <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<!-- Layout 2 kolom kiri kanan -->
<div class="row">
    <!-- Kiri -->
    <div class="col-md-6">
        <!-- Grafik Omset Bulanan -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Grafik Omset Bulanan (12 Bulan Terakhir)</h3>
            </div>
            <div class="box-body">
                <canvas id="chartOmsetBulanan" style="height:250px;"></canvas>
            </div>
        </div>

        <!-- Grafik Transaksi Harian -->
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Grafik Transaksi Harian (Bulan Ini)</h3>
            </div>
            <div class="box-body">
                <canvas id="chartTransaksiHarian" style="height:250px;"></canvas>
            </div>
        </div>

        <!-- Barang Terlaris -->
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Barang Terlaris (Top 10)</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Sparepart</th>
                            <th>Nama Sparepart</th>
                            <th>Total Terjual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no2 = 1;
                        while ($row2 = mysqli_fetch_assoc($query_barang_terlaris)) {
                            echo "<tr>
                                <td>{$no2}</td>
                                <td>" . htmlspecialchars($row2['kode_sparepart']) . "</td>
                                <td>" . htmlspecialchars($row2['nama_sparepart']) . "</td>
                                <td>" . number_format($row2['total_terjual'],0,',','.'). "</td>
                            </tr>";
                            $no2++;
                        }
                        if (mysqli_num_rows($query_barang_terlaris) == 0) {
                            echo "<tr><td colspan='4' class='text-center'>Tidak ada data barang terlaris.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Kanan -->
    <div class="col-md-6">
        <!-- Grafik Laba Bulanan -->
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Grafik Laba Bulanan (12 Bulan Terakhir)</h3>
            </div>
            <div class="box-body">
                <canvas id="chartLabaBulanan" style="height:250px;"></canvas>
            </div>
        </div>

        <!-- Stok Limit -->
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title">Sparepart dengan Stok Limit (&le; <?= $stok_limit; ?>)</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Sparepart</th>
                            <th>Nama Sparepart</th>
                            <th>Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($query_stok_limit)) {
                            echo "<tr>
                                <td>{$no}</td>
                                <td>" . htmlspecialchars($row['kode_sparepart']) . "</td>
                                <td>" . htmlspecialchars($row['nama_sparepart']) . "</td>
                                <td>" . number_format($row['stok_pcs'],0,',','.'). "</td>
                            </tr>";
                            $no++;
                        }
                        if (mysqli_num_rows($query_stok_limit) == 0) {
                            echo "<tr><td colspan='4' class='text-center'>Tidak ada sparepart dengan stok limit.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Grafik Omset Bulanan
const ctxOmset = document.getElementById('chartOmsetBulanan').getContext('2d');
const chartOmsetBulanan = new Chart(ctxOmset, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels_penjualan); ?>,
        datasets: [{
            label: 'Omset (Rp)',
            data: <?= json_encode($data_omset_per_bulan); ?>,
            backgroundColor: 'rgba(60,141,188,0.9)',
            borderColor: 'rgba(60,141,188,1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Grafik Laba Bulanan
const ctxLaba = document.getElementById('chartLabaBulanan').getContext('2d');
const chartLabaBulanan = new Chart(ctxLaba, {
    type: 'line',
    data: {
        labels: <?= json_encode($labels_penjualan); ?>,
        datasets: [{
            label: 'Laba (Rp)',
            data: <?= json_encode($data_laba_per_bulan); ?>,
            backgroundColor: 'rgba(0,255,0,0.3)',
            borderColor: 'rgba(0,128,0,1)',
            borderWidth: 2,
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Grafik Transaksi Harian Bulan Ini
const ctxTransaksi = document.getElementById('chartTransaksiHarian').getContext('2d');
const chartTransaksiHarian = new Chart(ctxTransaksi, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels_harian); ?>,
        datasets: [{
            label: 'Jumlah Transaksi',
            data: <?= json_encode($data_transaksi_harian); ?>,
            backgroundColor: 'rgba(255,165,0,0.8)',
            borderColor: 'rgba(255,140,0,1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true, stepSize: 1 }
        }
    }
});
</script>
