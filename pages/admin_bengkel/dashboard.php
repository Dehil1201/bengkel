<!-- FontAwesome sudah ada di AdminLTE2, jika belum bisa tambahkan -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* Perbaikan responsivitas angka dashboard */
.small-box h3 {
    font-size: clamp(16px, 3vw, 28px); /* auto menyesuaikan layar */
    white-space: nowrap;             /* cegah turun baris */
    overflow: hidden;                /* cegah tembus box */
    text-overflow: ellipsis;         /* tampilkan ... jika terlalu panjang */
}

/* Ikon tetap proporsional */
.small-box .icon {
    font-size: 60px;
}
@media (max-width: 768px) {
    .small-box .icon {
        font-size: 45px;
    }
}
</style>


<!-- Statistik Boxes -->

<!-- Loading Overlay -->
<div id="dashboardLoading" style="
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(255,255,255,0.9);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
">
    <i class="fa fa-spinner fa-spin fa-3x"></i>
    <p style="margin-top:10px;font-size:16px;">Memuat data dashboard...</p>
</div>

<div class="row">
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3 id="totalSparepart"></h3>
                <p>Total Sparepart</p>
            </div>
            <div class="icon"><i class="fa fa-cogs"></i></div>
            <a href="?page=spareparts" class="small-box-footer">Selengkapnya <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3 id="totalPelanggan"></h3>
                <p>Total Pelanggan</p>
            </div>
            <div class="icon"><i class="fa fa-users"></i></div>
            <a href="?page=pelanggan" class="small-box-footer">Selengkapnya <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-green">
            <div class="inner">
                <h3 id="omsetBulanIni"></h3>
                <p>Omset Bulan Ini</p>
            </div>
            <div class="icon"><i class="fa fa-line-chart"></i></div>
            <a href="?page=transaksi" class="small-box-footer">Selengkapnya <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-xs-6">
        <div class="small-box bg-red">
            <div class="inner">
                <h3 id="labaBulanIni"></h3>
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
                    <tbody id="barangTerlarisBody"></tbody>

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
                <h3 class="box-title">Sparepart dengan Stok Limit</h3>
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
                    <tbody id="stokLimitBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
let chartOmsetBulanan = null;
let chartLabaBulanan = null;
let chartTransaksiHarian = null;

// ✅ FUNCTION WAJIB DI LUAR AJAX
function formatRupiahShort(n) {
    n = Number(n) || 0;
    if (n >= 1000000000) return (n/1000000000).toFixed(1) + " M";
    if (n >= 1000000)    return (n/1000000).toFixed(1) + " Jt";
    if (n >= 1000)       return (n/1000).toFixed(0) + " Rb";
    return n.toString();
}

$(document).ready(function () {

    $("#dashboardLoading").show();

    $.getJSON("pages/admin_bengkel/api_dashboard.php")

    .done(function(res) {

        if (!res || res.status !== 200) {
            console.error("Response tidak valid:", res);
            alert("Gagal memuat data dashboard");
            return;
        }

        // =========================
        // ✅ STATISTIK
        // =========================
        $("#totalSparepart").text(parseInt(res.summary.total_spareparts).toLocaleString('id-ID'));
        $("#totalPelanggan").text(parseInt(res.summary.total_pelanggan).toLocaleString('id-ID'));
        $("#omsetBulanIni").text("Rp " + parseInt(res.summary.omset_bulan_ini).toLocaleString('id-ID'));
        $("#labaBulanIni").text("Rp " + parseInt(res.summary.laba_bulan_ini).toLocaleString('id-ID'));


        // =========================
        // ✅ CHART OMSET BULANAN
        // =========================
        const ctxOmset = document.getElementById('chartOmsetBulanan');

        if (chartOmsetBulanan) chartOmsetBulanan.destroy();

        chartOmsetBulanan = new Chart(ctxOmset, {
            type: 'bar',
            data: {
                labels: res.grafik_bulanan.labels,
                datasets: [{
                    label: 'Omset (Rp)',
                    data: res.grafik_bulanan.omset,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });

        // =========================
        // ✅ CHART LABA BULANAN
        // =========================
        const ctxLaba = document.getElementById('chartLabaBulanan');

        if (chartLabaBulanan) chartLabaBulanan.destroy();

        chartLabaBulanan = new Chart(ctxLaba, {
            type: 'line',
            data: {
                labels: res.grafik_bulanan.labels,
                datasets: [{
                    label: 'Laba (Rp)',
                    data: res.grafik_bulanan.laba,
                    borderWidth: 2,
                    fill: false,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });

        // =========================
        // ✅ CHART TRANSAKSI HARIAN
        // =========================
        const ctxHarian = document.getElementById('chartTransaksiHarian');

        if (chartTransaksiHarian) chartTransaksiHarian.destroy();

        chartTransaksiHarian = new Chart(ctxHarian, {
            type: 'line',
            data: {
                labels: res.grafik_harian.labels,
                datasets: [{
                    label: 'Total Nominal Harian',
                    data: res.grafik_harian.data,
                    borderWidth: 2,
                    fill: false,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });

        // =========================
        // ✅ BARANG TERLARIS
        // =========================
        let terlarisHtml = "";

        if (res.barang_terlaris?.length > 0) {
            res.barang_terlaris.forEach((item, i) => {
                terlarisHtml += `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${item.kode_sparepart}</td>
                        <td>${item.nama_sparepart}</td>
                        <td>${Number(item.total_terjual).toLocaleString('id-ID')}</td>
                    </tr>
                `;
            });
        } else {
            terlarisHtml = `
                <tr>
                    <td colspan="4" class="text-center text-muted">
                        Data barang terlaris kosong
                    </td>
                </tr>
            `;
        }

        $("#barangTerlarisBody").html(terlarisHtml);

        // =========================
        // ✅ STOK LIMIT
        // =========================
        let stokHtml = "";

        if (res.stok_limit?.length > 0) {
            res.stok_limit.forEach((item, i) => {
                stokHtml += `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${item.kode_sparepart}</td>
                        <td>${item.nama_sparepart}</td>
                        <td class="text-danger fw-bold">${item.stok_pcs}</td>
                    </tr>
                `;
            });
        } else {
            stokHtml = `
                <tr>
                    <td colspan="4" class="text-center text-muted">
                        Tidak ada stok limit
                    </td>
                </tr>
            `;
        }

        $("#stokLimitBody").html(stokHtml);

    })

    // ✅ SEKARANG CHAINING NORMAL LAGI
    .fail(function(xhr, status, error){
        console.error("AJAX Error:", error);
        alert("Gagal mengambil data dari server");
    })

    .always(function(){
        $("#dashboardLoading").fadeOut(300);
    });

});
</script>

