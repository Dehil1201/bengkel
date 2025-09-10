<?php
// Pastikan sesi sudah dimulai dan file koneksi disertakan
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Fungsi untuk membersihkan dan mengamankan input
function sanitize_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

// ==========================================================
// Pengecekan Akses Berdasarkan Role
// ==========================================================
$user_role = get_user_role();
$allowed_roles = ['owner_bengkel', 'admin_bengkel'];

if (!in_array($user_role, $allowed_roles)) {
    echo "<div class='alert alert-danger'>Anda tidak memiliki akses ke halaman ini.</div>";
    exit();
}
// Tentukan ID bengkel yang bisa diakses oleh user
$accessible_bengkel_ids = [];
if ($user_role === 'owner_bengkel') {
    $owner_id = $_SESSION['id_user'];
    $query_bengkel_ids = mysqli_query($conn, "SELECT id_bengkel FROM bengkels WHERE owner_id = '$owner_id'");
    while ($row = mysqli_fetch_assoc($query_bengkel_ids)) {
        $accessible_bengkel_ids[] = $row['id_bengkel'];
    }
} else if ($user_role === 'admin_bengkel') {
    $user_id = $_SESSION['id_user'];
    $query_bengkel_admin = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user = '$user_id'");
    if ($row = mysqli_fetch_assoc($query_bengkel_admin)) {
        $accessible_bengkel_ids[] = $row['bengkel_id'];
    }
}
if (empty($accessible_bengkel_ids)) {
    echo "<div class='alert alert-danger'>Anda tidak terdaftar di bengkel manapun.</div>";
    exit();
}
$bengkel_ids_string = "'" . implode("','", $accessible_bengkel_ids) . "'";

// ==========================================================
// PROSES TAMPILAN
// ==========================================================
$query_bengkels = mysqli_query($conn, "SELECT id_bengkel, nama_bengkel FROM bengkels WHERE id_bengkel IN ($bengkel_ids_string) ORDER BY nama_bengkel ASC");
$bengkels = [];
while ($row = mysqli_fetch_assoc($query_bengkels)) {
    $bengkels[] = $row;
}
$selected_bengkel_id = $_GET['id_bengkel'] ?? ($bengkels[0]['id_bengkel'] ?? null);
$selected_bengkel_name = "Semua Bengkel";
if ($selected_bengkel_id) {
    $query_selected_bengkel = mysqli_query($conn, "SELECT nama_bengkel FROM bengkels WHERE id_bengkel = '$selected_bengkel_id'");
    if (mysqli_num_rows($query_selected_bengkel) > 0) {
        $data_selected_bengkel = mysqli_fetch_assoc($query_selected_bengkel);
        $selected_bengkel_name = $data_selected_bengkel['nama_bengkel'];
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Cetak Barcode Spare Part untuk Bengkel: **<?= htmlspecialchars($selected_bengkel_name); ?>**</h3>
            </div>
            <div class="box-body">
                <div class="form-group col-md-4">
                    <label for="filter_bengkel">Pilih Bengkel:</label>
                    <select id="filter_bengkel" class="form-control">
                        <?php foreach ($bengkels as $bengkel): ?>
                            <option value="<?= $bengkel['id_bengkel']; ?>" <?= ($bengkel['id_bengkel'] == $selected_bengkel_id) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($bengkel['nama_bengkel']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="clearfix"></div>
                <hr>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>Merk</th>
                                <th>Kategori</th>
                                <th>Stok</th>
                                <th>Harga Jual</th>
                                <th>Bengkel</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $sql_where = "WHERE sp.bengkel_id IN ($bengkel_ids_string)";
                            if ($selected_bengkel_id) {
                                $sql_where = "WHERE sp.bengkel_id = '$selected_bengkel_id'";
                            }
                            $query_spareparts = mysqli_query($conn, "SELECT sp.*, b.nama_bengkel, k.nama_kategori, m.nama_merk FROM spareparts sp JOIN bengkels b ON sp.bengkel_id = b.id_bengkel JOIN kategori_sparepart k ON sp.kategori_id = k.id_kategori JOIN merk_sparepart m ON sp.merk_id = m.id_merk $sql_where ORDER BY sp.nama_sparepart ASC");
                            if (mysqli_num_rows($query_spareparts) > 0) {
                                while ($data_part = mysqli_fetch_assoc($query_spareparts)) {
                                    $query_harga_jual = mysqli_query($conn, "SELECT hj.*, s.nama_satuan FROM harga_jual_sparepart hj JOIN satuan s ON hj.satuan_jual_id = s.id_satuan WHERE hj.sparepart_id = '{$data_part['id_sparepart']}' ORDER BY hj.tipe_harga ASC");
                                    $harga_jual_data = [];
                                    while ($hj_row = mysqli_fetch_assoc($query_harga_jual)) {
                                        $harga_jual_data[] = $hj_row;
                                    }
                            ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= htmlspecialchars($data_part['kode_sparepart']); ?></td>
                                        <td><?= htmlspecialchars($data_part['nama_sparepart']); ?></td>
                                        <td><?= htmlspecialchars($data_part['nama_merk']); ?></td>
                                        <td><?= htmlspecialchars($data_part['nama_kategori']); ?></td>
                                        <td><?= htmlspecialchars($data_part['stok_pcs']); ?></td>
                                        <td>
                                            <?php foreach ($harga_jual_data as $hj): ?>
                                                <p><strong><?= htmlspecialchars($hj['nama_satuan']); ?>:</strong> Rp <?= number_format($hj['harga_jual'], 0, ',', '.'); ?></p>
                                            <?php endforeach; ?>
                                        </td>
                                        <td><?= htmlspecialchars($data_part['nama_bengkel']); ?></td>
                                        <td>
                                            <button class="btn btn-info btn-xs btn-cetak-barcode"
                                                data-id="<?= $data_part['id_sparepart']; ?>"
                                                data-kode="<?= htmlspecialchars($data_part['kode_sparepart']); ?>"
                                                data-nama="<?= htmlspecialchars($data_part['nama_sparepart']); ?>"
                                                data-harga-jual='<?= json_encode($harga_jual_data); ?>'>
                                                <i class="fa fa-barcode"></i> Cetak Barcode
                                            </button>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCetakBarcode" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="cetakModalTitle">Cetak Barcode</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_sparepart" id="cetak_id_sparepart">
                
                <div id="barcode-container" style="text-align: center; padding: 20px;">
                    <svg id="barcodeCanvas"></svg>
                    <p class="text-bold" id="cetak_kode_sparepart" style="margin-top: 5px;"></p>
                </div>

                <div class="form-group">
                    <label>Nama Spare Part</label>
                    <p id="cetak_nama_sparepart" class="form-control-static"></p>
                </div>
                <div class="form-group">
                    <label>Harga Jual</label>
                    <div id="cetak_harga_jual" class="form-control-static"></div>
                </div>
                
                <p>Isi form ini untuk mencetak label.</p>
                <div class="form-group">
                    <label for="jumlah_cetak">Jumlah Label yang Ingin Dicetak</label>
                    <input type="number" class="form-control" id="jumlah_cetak" name="jumlah_cetak" value="1" min="1" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="btn-print"><i class="fa fa-print"></i> Cetak</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
$(document).ready(function() {
    $('#dataTable').DataTable();

    $('#filter_bengkel').on('change', function() {
        const bengkelId = $(this).val();
        window.location.href = `?page=barcode&id_bengkel=${bengkelId}`;
    });

    // Menangani klik tombol "Cetak Barcode" untuk menampilkan modal
    $(document).on('click', '.btn-cetak-barcode', function() {
        const data = $(this).data();
        $('#cetak_id_sparepart').val(data.id);
        $('#cetak_nama_sparepart').text(data.nama);
        $('#cetak_kode_sparepart').text(data.kode);

        let hargaHtml = '';
        if (data.hargaJual && data.hargaJual.length > 0) {
            data.hargaJual.forEach(hj => {
                hargaHtml += `<p><strong>${hj.nama_satuan}</strong>: Rp ${new Intl.NumberFormat('id-ID').format(hj.harga_jual)}</p>`;
            });
        } else {
            hargaHtml = '<p>Harga jual tidak tersedia.</p>';
        }
        $('#cetak_harga_jual').html(hargaHtml);
        
        // Sembunyikan barcode yang ada di modal sebelum cetak
        $('#barcode-container').hide();

        $('#modalCetakBarcode').modal('show');
    });

    // Menangani klik tombol "Cetak" di dalam modal untuk mencetak
    $('#btn-print').on('click', function() {
        const jumlahCetak = parseInt($('#jumlah_cetak').val(), 10) || 1;
        const kodePart = $('#cetak_kode_sparepart').text();
        const namaPart = $('#cetak_nama_sparepart').text();
        const hargaPart = $('#cetak_harga_jual').html();

        if (!kodePart) {
            alert("Kode spare part tidak ditemukan.");
            return;
        }

        const originalBody = $('body').html();
        
        // Buat konten cetak
        let printContent = `
            <style>
                @media print {
                    body {
                        margin: 0;
                        padding: 0;
                    }
                    .barcode-label {
                        page-break-after: always;
                        text-align: center;
                        padding: 10px;
                        border: 1px solid black;
                        margin: 5px;
                        display: inline-block;
                        width: 20%;
                    }
                    .no-print {
                        display: none;
                    }
                }
            </style>
            <div class="no-print">
                <p>Mencetak ${jumlahCetak} label...</p>
            </div>
            <div id="print-container"></div>
        `;
        
        $('body').html(printContent);
        const printContainer = $('#print-container');

        // Looping untuk membuat setiap barcode
        for (let i = 0; i < jumlahCetak; i++) {
            const labelHtml = `
                <div class="barcode-label">
                    <p class="text-bold" style="margin-bottom: 5px;">${namaPart}</p>
                    <svg class="barcode-svg"></svg>
                    <p class="text-bold" style="margin-top: 5px;">${kodePart}</p>
                    <div style="font-size: 12px; margin-top: 5px;">${hargaPart}</div>
                </div>
            `;
            printContainer.append(labelHtml);
            
            // Panggil JsBarcode untuk setiap SVG yang baru
            JsBarcode(printContainer.find('.barcode-svg').last()[0], kodePart, {
                format: "CODE128",
                displayValue: true,
                fontSize: 10,
                width: 1,
                height: 30,
                lineColor: "#000000",
                font: "system-ui"
            });
        }

        window.print();

        // Kembalikan tampilan ke kondisi semula
        setTimeout(() => {
            $('body').html(originalBody);
            location.reload(); // Reload halaman untuk memastikan semua event listener kembali normal
        }, 1000);
    });

    // Menangani modal disembunyikan
    $('#modalCetakBarcode').on('hidden.bs.modal', function () {
        // Kosongkan konten modal saat ditutup untuk menghindari masalah rendering
        $('#cetak_nama_sparepart').text('');
        $('#cetak_kode_sparepart').text('');
        $('#cetak_harga_jual').html('');
    });
});
</script>
<style>
@media print {
    body * {
        visibility: hidden;
    }
    #printable-content, #printable-content * {
        visibility: visible;
    }
    #printable-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 20px;
    }
    .modal-dialog, .modal-content {
        width: 100% !important;
        margin: 0 !important;
    }
    .modal-footer {
        display: none;
    }
}
</style>