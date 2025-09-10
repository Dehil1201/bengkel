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
// P R O S E S   A K S I
// ==========================================================
// Logika PHP hanya untuk menampilkan halaman, semua CRUD di api_sparepart.php
$query_bengkels = mysqli_query($conn, "SELECT id_bengkel, nama_bengkel FROM bengkels WHERE id_bengkel IN ($bengkel_ids_string) ORDER BY nama_bengkel ASC");
$bengkels = [];
while ($row = mysqli_fetch_assoc($query_bengkels)) { $bengkels[] = $row; }
$selected_bengkel_id = $_GET['id_bengkel'] ?? ($bengkels[0]['id_bengkel'] ?? null);
$selected_bengkel_name = "Semua Bengkel";
if ($selected_bengkel_id) {
    $query_selected_bengkel = mysqli_query($conn, "SELECT nama_bengkel FROM bengkels WHERE id_bengkel = '$selected_bengkel_id'");
    if (mysqli_num_rows($query_selected_bengkel) > 0) { $data_selected_bengkel = mysqli_fetch_assoc($query_selected_bengkel); $selected_bengkel_name = $data_selected_bengkel['nama_bengkel']; }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Manajemen Spare Part untuk Bengkel: **<?= htmlspecialchars($selected_bengkel_name); ?>**</h3>
                <div class="box-tools pull-right">
                    <a href="#" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambahSparepart" id="btn-tambah-sparepart"><i class="fa fa-plus"></i> Tambah Spare Part</a>
                </div>
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
                                <th>Harga Beli</th>
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
                            $query_spareparts = mysqli_query($conn, "SELECT sp.*, b.nama_bengkel, k.nama_kategori, m.nama_merk, s.nama_satuan AS nama_satuan_beli FROM spareparts sp JOIN bengkels b ON sp.bengkel_id = b.id_bengkel JOIN kategori_sparepart k ON sp.kategori_id = k.id_kategori JOIN merk_sparepart m ON sp.merk_id = m.id_merk JOIN satuan s ON sp.satuan_beli_id = s.id_satuan $sql_where ORDER BY sp.nama_sparepart ASC");
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
                                        <td><?= "Rp " . number_format($data_part['harga_beli'], 0, ',', '.'); ?></td>
                                        <td>
                                            <?php foreach ($harga_jual_data as $hj): ?>
                                                <p><strong><?= htmlspecialchars($hj['nama_satuan']); ?>:</strong> Rp <?= number_format($hj['harga_jual'], 0, ',', '.'); ?></p>
                                            <?php endforeach; ?>
                                        </td>
                                        <td><?= htmlspecialchars($data_part['nama_bengkel']); ?></td>
                                        <td>
                                            <a href="#" class="btn btn-warning btn-xs btn-edit"
                                                data-id="<?= $data_part['id_sparepart']; ?>"
                                                data-kode="<?= htmlspecialchars($data_part['kode_sparepart']); ?>"
                                                data-nama="<?= htmlspecialchars($data_part['nama_sparepart']); ?>"
                                                data-kategori-id="<?= htmlspecialchars($data_part['kategori_id']); ?>"
                                                data-merk-id="<?= htmlspecialchars($data_part['merk_id']); ?>"
                                                data-lokasi-rak="<?= htmlspecialchars($data_part['lokasi_rak']); ?>"
                                                data-harga-beli="<?= htmlspecialchars($data_part['harga_beli']); ?>"
                                                data-satuan-beli-id="<?= htmlspecialchars($data_part['satuan_beli_id']); ?>"
                                                data-isi-per-pcs-beli="<?= htmlspecialchars($data_part['isi_per_pcs_beli']); ?>"
                                                data-stok-pcs="<?= htmlspecialchars($data_part['stok_pcs']); ?>"
                                                data-stok-minimal="<?= htmlspecialchars($data_part['stok_minimal']); ?>"
                                                data-bengkel-id="<?= htmlspecialchars($data_part['bengkel_id']); ?>"
                                                data-harga-jual='<?= json_encode($harga_jual_data); ?>'>
                                                <i class="fa fa-pencil"></i> Edit
                                            </a>
                                            <button type="button" class="btn btn-danger btn-xs btn-hapus" data-id="<?= $data_part['id_sparepart']; ?>">
                                                <i class="fa fa-trash"></i> Hapus
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

<div class="modal fade" id="modalTambahSparepart" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Tambah Spare Part Baru</h4>
            </div>
            <form id="formSparepart" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_sparepart" id="id_sparepart_edit">
                    <input type="hidden" name="bengkel_id" id="form_bengkel_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="kode_sparepart">Kode Spare Part (Opsional)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="kode_sparepart" name="kode_sparepart">
                                    <span class="input-group-btn">
                                        <button class="btn btn-info" type="button" id="btn-auto-kode">Auto</button>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="nama_sparepart">Nama Spare Part</label>
                                <input type="text" class="form-control" id="nama_sparepart" name="nama_sparepart" required>
                            </div>
                            <div class="form-group">
                                <label for="kategori_id">Kategori Spare Part</label>
                                <div class="input-group">
                                    <select class="form-control" id="kategori_id" name="kategori_id" required>
                                        <option value="">-- Pilih --</option>
                                    </select>
                                    <span class="input-group-btn">
                                        <button class="btn btn-default" type="button" data-toggle="modal" data-target="#modalManajemenKategori"><i class="fa fa-plus"></i></button>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="merk_id">Merek Part</label>
                                <div class="input-group">
                                    <select class="form-control" id="merk_id" name="merk_id" required>
                                        <option value="">-- Pilih --</option>
                                    </select>
                                    <span class="input-group-btn">
                                        <button class="btn btn-default" type="button" data-toggle="modal" data-target="#modalManajemenMerk"><i class="fa fa-plus"></i></button>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="lokasi_rak">Lokasi Rak</label>
                                <input type="text" class="form-control" id="lokasi_rak" name="lokasi_rak">
                            </div>
                        </div>
                        <div class="col-md-6">
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <h4 class="text-bold">Data Pembelian</h4>
                            <div class="form-group">
                                <label for="harga_beli">Harga Beli</label>
                                <input type="number" step="0.01" class="form-control" id="harga_beli" name="harga_beli" required>
                            </div>
                            <div class="form-group">
                                <label for="satuan_beli_id">Satuan Beli</label>
                                <div class="input-group">
                                    <select class="form-control" id="satuan_beli_id" name="satuan_beli_id" required>
                                        <option value="">-- Pilih --</option>
                                    </select>
                                    <span class="input-group-btn">
                                        <button class="btn btn-default" type="button" data-toggle="modal" data-target="#modalManajemenSatuan"><i class="fa fa-plus"></i></button>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="isi_per_pcs_beli">Isi/Pcs</label>
                                <input type="number" class="form-control" id="isi_per_pcs_beli" name="isi_per_pcs_beli" required>
                            </div>
                            <div class="form-group">
                                <label>HPP Per Pcs</label>
                                <input type="text" class="form-control" id="hpp_per_pcs" value="0" disabled>
                            </div>
                            <div class="form-group">
                                <label>Stok Sekarang (dalam Pcs)</label>
                                <input type="number" class="form-control" id="stok_pcs" name="stok_pcs" required>
                            </div>
                            <div class="form-group">
                                <label>Stok Minimal (dalam Pcs)</label>
                                <input type="number" class="form-control" id="stok_minimal" name="stok_minimal" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h4 class="text-bold">Data Penjualan</h4>
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                            <hr class="mt-1 mb-1">
                            <p>Harga Jual <?= $i; ?></p>
                            <div class="row">
                                <div class="col-xs-3">
                                    <div class="form-group">
                                        <label for="persentase_jual_<?= $i ?>">Jual (%)</label>
                                        <input type="number" step="0.01" class="form-control persentase-jual" data-index="<?= $i ?>" name="persentase_jual_<?= $i ?>">
                                    </div>
                                </div>
                                <div class="col-xs-5">
                                    <div class="form-group">
                                        <label for="harga_jual_<?= $i ?>">Harga Jual</label>
                                        <input type="number" step="0.01" class="form-control harga-jual" data-index="<?= $i ?>" name="harga_jual_<?= $i ?>">
                                    </div>
                                </div>
                                <div class="col-xs-4">
                                    <div class="form-group">
                                        <label for="satuan_jual_<?= $i ?>">Satuan Jual</label>
                                        <select class="form-control" name="satuan_jual_<?= $i ?>">
                                            <option value="">-- Pilih --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-xs-12">
                                    <div class="form-group">
                                        <label for="isi_per_pcs_jual_<?= $i ?>">Isi/Pcs</label>
                                        <input type="number" class="form-control" name="isi_per_pcs_jual_<?= $i ?>">
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                    <button type="submit" name="tambah_sparepart" class="btn btn-primary">Simpan</button>
                    <button type="submit" name="edit_sparepart" class="btn btn-success" style="display: none;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$master_data_config = [
    'kategori' => ['id' => 'modalManajemenKategori', 'title' => 'Manajemen Kategori', 'name' => 'Kategori'],
    'merk' => ['id' => 'modalManajemenMerk', 'title' => 'Manajemen Merek', 'name' => 'Merek'],
    'satuan' => ['id' => 'modalManajemenSatuan', 'title' => 'Manajemen Satuan', 'name' => 'Satuan'],
];
foreach ($master_data_config as $type => $config): ?>
<div class="modal fade" id="<?= $config['id']; ?>" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?= $config['title']; ?></h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nama <?= $config['name']; ?> Baru</label>
                    <input type="text" class="form-control" id="nama_<?= $type; ?>_baru">
                </div>
                <button type="button" class="btn btn-primary btn-tambah-master" data-master="<?= $type; ?>">Tambah <?= $config['name']; ?></button>
                <hr>
                <table class="table table-bordered tabel-master" id="tabel<?= ucfirst($type); ?>">
                    <thead>
                        <tr>
                            <th>Nama <?= $config['name']; ?></th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $('#dataTable').DataTable();
    
    // Fungsi untuk memuat ulang tabel spare part
    function loadSpareParts() {
        window.location.reload();
    }

    // Set nilai hidden input bengkel saat modal dibuka
    $('#modalTambahSparepart').on('show.bs.modal', function() {
        $('#form_bengkel_id').val($('#filter_bengkel').val());
    });
    
    // Tangani event klik tombol tambah spare part
    $('#btn-tambah-sparepart').on('click', function() {
        $('#formSparepart').trigger('reset');
        $('#myModalLabel').text('Tambah Spare Part Baru');
        $('#id_sparepart_edit').val('');
        $('button[name="tambah_sparepart"]').show();
        $('button[name="edit_sparepart"]').hide();
        $('#kode_sparepart').val('');
    });

    // Tangani event klik tombol edit
    $(document).on('click', '.btn-edit', function() {
        const data = $(this).data();
        $('#myModalLabel').text('Edit Spare Part');
        $('#id_sparepart_edit').val(data.id);
        $('#kode_sparepart').val(data.kode);
        $('#nama_sparepart').val(data.nama);
        $('#kategori_id').val(data.kategoriId);
        $('#merk_id').val(data.merkId);
        $('#lokasi_rak').val(data.lokasiRak);
        $('#harga_beli').val(data.hargaBeli);
        $('#satuan_beli_id').val(data.satuanBeliId);
        $('#isi_per_pcs_beli').val(data.isiPerPcsBeli);
        $('#stok_pcs').val(data.stokPcs);
        $('#stok_minimal').val(data.stokMinimal);
        $('#form_bengkel_id').val(data.bengkelId);
        $('button[name="tambah_sparepart"]').hide();
        $('button[name="edit_sparepart"]').show();
        const hargaJualData = data.hargaJual || [];
        for (let i = 1; i <= 4; i++) {
            const hj = hargaJualData.find(item => item.tipe_harga == i);
            $(`[name="persentase_jual_${i}"]`).val(hj ? hj.persentase_jual : '');
            $(`[name="harga_jual_${i}"]`).val(hj ? hj.harga_jual : '');
            $(`[name="satuan_jual_${i}"]`).val(hj ? hj.satuan_jual_id : '');
            $(`[name="isi_per_pcs_jual_${i}"]`).val(hj ? hj.isi_per_pcs_jual : '');
        }
        $('#modalTambahSparepart').modal('show');
    });

    // --- Logika Form Spare Part (AJAX) ---
    $('#formSparepart').on('submit', function(e) {
        e.preventDefault();
        const aksi = $('#id_sparepart_edit').val() ? 'edit' : 'tambah';
        
        Swal.fire({
            title: aksi === 'tambah' ? 'Menyimpan data...' : 'Memperbarui data...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'pages/admin_bengkel/api_sparepart.php',
            method: 'POST',
            data: $(this).serialize() + '&aksi=' + aksi,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire('Berhasil!', response.message, 'success').then(() => {
                        window.location.href = `?page=sparepart&id_bengkel=${$('#filter_bengkel').val()}`;
                    });
                } else {
                    Swal.fire('Gagal!', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Gagal!', 'Terjadi kesalahan pada server. Silakan coba lagi.', 'error');
            }
        });
    });

    // --- Logika Hapus Spare Part (AJAX) ---
    $(document).on('click', '.btn-hapus', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Yakin?',
            text: "Anda tidak bisa mengembalikan ini!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'pages/admin_bengkel/api_sparepart.php',
                    method: 'POST',
                    data: { aksi: 'hapus', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire('Terhapus!', response.message, 'success').then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Gagal!', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Gagal!', 'Terjadi kesalahan pada server.', 'error');
                    }
                });
            }
        });
    });

    // --- Logika Manajemen Master Data via AJAX ---
    function loadMasterData() {
        const masterTables = ['kategori', 'merk', 'satuan'];
        masterTables.forEach(function(master) {
            $.ajax({
                url: 'pages/admin_bengkel/api_manajemen.php',
                method: 'POST',
                data: { aksi: 'get_all_' + master },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        var data = response.data;
                        var dropdowns = master === 'satuan' ? ['#satuan_beli_id', 'select[name^="satuan_jual_"]'] : [`#${master}_id`];
                        dropdowns.forEach(function(dropdownSelector) {
                            $(dropdownSelector).html('<option value="">-- Pilih --</option>');
                            data.forEach(function(item) {
                                $(dropdownSelector).append(`<option value="${item.id}">${item.nama}</option>`);
                            });
                        });
                        
                        var tableBody = $(`#tabel${master.charAt(0).toUpperCase() + master.slice(1)} tbody`);
                        tableBody.html('');
                        data.forEach(function(item) {
                            tableBody.append(`
                                <tr>
                                    <td>${item.nama}</td>
                                    <td><button type="button" class="btn btn-danger btn-xs btn-hapus-master" data-id="${item.id}" data-master="${master}">Hapus</button></td>
                                </tr>
                            `);
                        });
                    }
                }
            });
        });
    }

    loadMasterData();

    $('.btn-tambah-master').on('click', function() {
        const masterType = $(this).data('master');
        const masterName = $(`#nama_${masterType}_baru`).val();
        if (masterName) {
            $.ajax({
                url: 'pages/admin_bengkel/api_manajemen.php',
                method: 'POST',
                data: { aksi: 'tambah', tipe: masterType, nama: masterName },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Berhasil!', response.message, 'success');
                        $(`#nama_${masterType}_baru`).val('');
                        loadMasterData();
                    } else {
                        Swal.fire('Gagal!', response.message, 'error');
                    }
                }
            });
        }
    });

    $(document).on('click', '.btn-hapus-master', function() {
        const id = $(this).data('id');
        const masterType = $(this).data('master');
        Swal.fire({
            title: 'Yakin?',
            text: "Anda tidak bisa mengembalikan ini!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'pages/admin_bengkel/api_manajemen.php',
                    method: 'POST',
                    data: { aksi: 'hapus', tipe: masterType, id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire('Terhapus!', response.message, 'success');
                            loadMasterData();
                        } else {
                            Swal.fire('Gagal!', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // Logika tombol "Auto"
    $('#btn-auto-kode').on('click', function() {
        const bengkelId = $('#filter_bengkel').val();
        if (bengkelId) {
            // Format: ID_BENGKEL-SPART-RANDOM_9_DIGITS
            const randomDigits = Math.floor(100000000 + Math.random() * 900000000);
            const autoCode = `${bengkelId}-SPART-${randomDigits}`;
            $('#kode_sparepart').val(autoCode);
        } else {
            Swal.fire('Perhatian', 'Pilih bengkel terlebih dahulu untuk membuat kode otomatis.', 'info');
        }
    });
});
</script>