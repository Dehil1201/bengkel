<?php

// Fungsi sanitize
function sanitize($data){
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

// ==========================
// Cek role dan akses bengkel
// ==========================
$user_role = get_user_role();
$allowed_roles = ['owner_bengkel','admin_bengkel'];

if(!in_array($user_role, $allowed_roles)){
    echo "<div class='alert alert-danger'>Anda tidak memiliki akses ke halaman ini.</div>";
    exit();
}

// Ambil bengkel yang bisa diakses
$accessible_bengkel_ids = [];
if($user_role === 'owner_bengkel'){
    $owner_id = $_SESSION['id_user'];
    $q = mysqli_query($conn,"SELECT id_bengkel FROM bengkels WHERE owner_id='$owner_id'");
    while($r = mysqli_fetch_assoc($q)){
        $accessible_bengkel_ids[] = $r['id_bengkel'];
    }
}elseif($user_role === 'admin_bengkel'){
    $user_id = $_SESSION['id_user'];
    $q = mysqli_query($conn,"SELECT bengkel_id FROM users WHERE id_user='$user_id'");
    if($r = mysqli_fetch_assoc($q)){
        $accessible_bengkel_ids[] = $r['bengkel_id'];
    }
}
if(empty($accessible_bengkel_ids)){
    echo "<div class='alert alert-danger'>Anda tidak terdaftar di bengkel manapun.</div>";
    exit();
}
$bengkel_ids_string = "'".implode("','",$accessible_bengkel_ids)."'";

// ==========================
// PROSES ASSET
// ==========================
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $action = $_POST['action'] ?? '';
    $current_page = '?page=asset';

    if($action === 'tambah'){
        $kode = sanitize($_POST['kode_asset']);
        $nama = sanitize($_POST['nama_asset']);
        $harga = (float)sanitize($_POST['harga_beli']);
        $stok  = (int)sanitize($_POST['stok']);
        $id_kategori = sanitize($_POST['id_kategori']);
        $bengkel_id = sanitize($_POST['bengkel_id'] ?? $accessible_bengkel_ids[0]);

        if(!in_array($bengkel_id,$accessible_bengkel_ids)){
            header("Location:$current_page&status=error&message=Bengkel tidak valid"); exit();
        }

        $q = mysqli_query($conn,"INSERT INTO asset(kode_asset,nama_asset,harga_beli,stok,id_kategori,bengkel_id)
                                 VALUES('$kode','$nama','$harga','$stok','$id_kategori','$bengkel_id')");
        if($q) header("Location:$current_page&status=success&message=Asset berhasil ditambahkan");
        else header("Location:$current_page&status=error&message=".mysqli_error($conn));
        exit();

    }elseif($action === 'ubah'){
        $id_asset = sanitize($_POST['id_asset']);
        $nama = sanitize($_POST['nama_asset']);
        $harga = (float)sanitize($_POST['harga_beli']);
        $stok = (int)sanitize($_POST['stok']);
        $id_kategori = sanitize($_POST['id_kategori']);

        $qCheck = mysqli_query($conn,"SELECT bengkel_id FROM asset WHERE id_asset='$id_asset'");
        if($r = mysqli_fetch_assoc($qCheck)){
            if(!in_array($r['bengkel_id'],$accessible_bengkel_ids)){
                header("Location:$current_page&status=error&message=Akses ditolak"); exit();
            }
        }else{
            header("Location:$current_page&status=error&message=Asset tidak ditemukan"); exit();
        }

        $q = mysqli_query($conn,"UPDATE asset SET nama_asset='$nama',harga_beli='$harga',stok='$stok',id_kategori='$id_kategori' WHERE id_asset='$id_asset'");
        if($q) header("Location:$current_page&status=success&message=Asset berhasil diubah");
        else header("Location:$current_page&status=error&message=".mysqli_error($conn));
        exit();

    }elseif($action === 'hapus'){
        $id_asset = sanitize($_POST['id_asset']);
        $qCheck = mysqli_query($conn,"SELECT bengkel_id FROM asset WHERE id_asset='$id_asset'");
        if($r = mysqli_fetch_assoc($qCheck)){
            if(!in_array($r['bengkel_id'],$accessible_bengkel_ids)){
                header("Location:$current_page&status=error&message=Akses ditolak"); exit();
            }
        }else{
            header("Location:$current_page&status=error&message=Asset tidak ditemukan"); exit();
        }

        $q = mysqli_query($conn,"DELETE FROM asset WHERE id_asset='$id_asset'");
        if($q) header("Location:$current_page&status=success&message=Asset berhasil dihapus");
        else header("Location:$current_page&status=error&message=".mysqli_error($conn));
        exit();
    }
}
?>

<!-- ========================== -->
<!-- TABEL ASSET -->
<!-- ========================== -->
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Daftar Asset</h3>
                <div class="box-tools pull-right">
                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalTambahAsset">
                        <i class="fa fa-plus"></i> Tambah Asset
                    </button>
                </div>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped" id="dataTable">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Asset</th>
                            <th>Nama Asset</th>
                            <th>Harga Beli</th>
                            <th>Stok</th>
                            <th>Total Harga Beli</th>
                            <th>Kategori</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        $qAsset = mysqli_query($conn,"SELECT a.*,ka.nama_kategori 
                            FROM asset a 
                            JOIN kategori_asset ka ON a.id_kategori=ka.id_kategori 
                            WHERE a.bengkel_id IN ($bengkel_ids_string)
                            ORDER BY a.nama_asset ASC");
                        if(mysqli_num_rows($qAsset) > 0){
                            while($row = mysqli_fetch_assoc($qAsset)){
                                $total_harga = $row['harga_beli'] * $row['stok'];
                                ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($row['kode_asset']); ?></td>
                                    <td><?= htmlspecialchars($row['nama_asset']); ?></td>
                                    <td><?= number_format($row['harga_beli'],0,',','.'); ?></td>
                                    <td><?= $row['stok']; ?></td>
                                    <td><?= number_format($total_harga,0,',','.'); ?></td>
                                    <td><?= htmlspecialchars($row['nama_kategori']); ?></td>
                                    <td>
                                        <button class="btn btn-info btn-xs btn-ubah-asset"
                                            data-id="<?= $row['id_asset']; ?>"
                                            data-nama="<?= htmlspecialchars($row['nama_asset']); ?>"
                                            data-harga="<?= $row['harga_beli']; ?>"
                                            data-stok="<?= $row['stok']; ?>"
                                            data-id_kategori="<?= $row['id_kategori']; ?>">
                                            <i class="fa fa-edit"></i> Ubah
                                        </button>
                                        <button class="btn btn-danger btn-xs btn-hapus-asset"
                                            data-id="<?= $row['id_asset']; ?>"
                                            data-nama="<?= htmlspecialchars($row['nama_asset']); ?>">
                                            <i class="fa fa-trash"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ========================== -->
<!-- MODAL TAMBAH ASSET -->
<!-- ========================== -->
<div class="modal fade" id="modalTambahAsset" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formTambahAsset" method="POST" action="?page=asset">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-header">
                    <h4 class="modal-title">Tambah Asset</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Kode Asset</label>
                        <input type="text" class="form-control" name="kode_asset" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Asset</label>
                        <input type="text" class="form-control" name="nama_asset" required>
                    </div>
                    <div class="form-group">
                        <label>Harga Beli</label>
                        <input type="number" class="form-control" name="harga_beli" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Stok</label>
                        <input type="number" class="form-control" name="stok" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <div class="input-group">
                            <select class="form-control" name="id_kategori" id="id_kategori" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php
                                $qK = mysqli_query($conn,"SELECT id_kategori,nama_kategori FROM kategori_asset ORDER BY nama_kategori");
                                while($k = mysqli_fetch_assoc($qK)){
                                    echo "<option value='{$k['id_kategori']}'>{$k['nama_kategori']}</option>";
                                }
                                ?>
                            </select>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-success" id="btnKelolaKategori">
                                    <i class="fa fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ========================== -->
<!-- MODAL UBAH ASSET -->
<!-- ========================== -->
<div class="modal fade" id="modalUbahAsset" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formUbahAsset" method="POST" action="?page=asset">
                <input type="hidden" name="action" value="ubah">
                <input type="hidden" name="id_asset" id="edit_id_asset">

                <div class="modal-header">
                    <h4 class="modal-title">Ubah Asset</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Asset</label>
                        <input type="text" class="form-control" name="nama_asset" id="edit_nama_asset" required>
                    </div>

                    <div class="form-group">
                        <label>Harga Beli</label>
                        <input type="number" class="form-control" name="harga_beli" id="edit_harga_beli" min="0" required>
                    </div>

                    <div class="form-group">
                        <label>Stok</label>
                        <input type="number" class="form-control" name="stok" id="edit_stok" min="0" required>
                    </div>

                    <div class="form-group">
                        <label>Kategori</label>
                        <div class="input-group">
                            <select class="form-control" name="id_kategori" id="id_kategori" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php
                                $qK = mysqli_query($conn,"SELECT id_kategori,nama_kategori FROM kategori_asset ORDER BY nama_kategori");
                                while($k = mysqli_fetch_assoc($qK)){
                                    echo "<option value='{$k['id_kategori']}'>{$k['nama_kategori']}</option>";
                                }
                                ?>
                            </select>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-success" id="btnKelolaKategoriEdit">
                                    <i class="fa fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- ========================== -->
<!-- MODAL KELOLA KATEGORI -->
<!-- ========================== -->
<div class="modal fade" id="modalKelolaKategori" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Kelola Kategori Asset</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <button class="btn btn-primary mb-2" id="btnTambahKategoriModal">
                    <i class="fa fa-plus"></i> Tambah Kategori
                </button>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTableKelolaKategori">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Kategori</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================== -->
<!-- MODAL TAMBAH/UBAH KATEGORI -->
<!-- ========================== -->
<div class="modal fade" id="modalTambahUbahKategori" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formTambahUbahKategori">
                <input type="hidden" name="action" id="kategori_action" value="tambah">
                <input type="hidden" name="id_kategori" id="id_kategori_modal">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah / Ubah Kategori</h5>
                    <button class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Kategori</label>
                        <input type="text" class="form-control" name="nama_kategori" id="nama_kategori_modal" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- ========================== -->
<!-- SCRIPT JS -->
<!-- ========================== -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function(){
    $('#dataTable').DataTable();

    // Menangani status alert
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const message = urlParams.get('message');
    if(status && message){
        Swal.fire({
            icon: status==='success'?'success':'error',
            title: status==='success'?'Berhasil!':'Gagal!',
            text: message,
            showConfirmButton:false,
            timer:2000
        });
        window.history.replaceState({},document.title,window.location.pathname);
    }

    // Hapus asset
    $(document).on('click','.btn-hapus-asset', function(){
        const id = $(this).data('id');
        const nama = $(this).data('nama');
        Swal.fire({
            title:'Hapus asset '+nama+'?',
            icon:'warning',
            showCancelButton:true,
            confirmButtonText:'Ya, hapus'
        }).then(result=>{
            if(result.isConfirmed){
                $.post(window.location.href, {action:'hapus', id_asset:id}, function(res){
                    window.location.reload();
                });
            }
        });
    });

    // Buka modal kelola kategori
    $('#btnKelolaKategori').on('click', function(){
        $('#modalKelolaKategori').modal('show');
        loadKategori();
    });
    $('#btnKelolaKategoriEdit').on('click', function(){
        $('#modalKelolaKategori').modal('show');
        loadKategori();
    });

    // Tambah kategori modal
    $('#btnTambahKategoriModal').on('click', function(){
        $('#kategori_action').val('tambah');
        $('#id_kategori_modal').val('');
        $('#nama_kategori_modal').val('');
        $('#modalTambahUbahKategori').modal('show');
    });

    // Submit tambah/ubah kategori
    $('#formTambahUbahKategori').on('submit', function(e){
        e.preventDefault();
        $.post('pages/admin_bengkel/api_kategori_asset.php', $(this).serialize(), function(res){
            if(res.status==='success'){
                Swal.fire('Berhasil', res.message,'success');
                $('#modalTambahUbahKategori').modal('hide');
                loadKategori();
                if(res.id_kategori){
                    if(!$('#id_kategori option[value="'+res.id_kategori+'"]').length){
                        $('#id_kategori').append(`<option value="${res.id_kategori}" selected>${$('#nama_kategori_modal').val()}</option>`);
                    }
                }
            }else{
                Swal.fire('Gagal', res.message,'error');
            }
        },'json');
    });

    // Edit kategori
    $(document).on('click','.btn-edit', function(){
        $('#kategori_action').val('ubah');
        $('#id_kategori_modal').val($(this).data('id'));
        $('#nama_kategori_modal').val($(this).data('nama'));
        $('#modalTambahUbahKategori').modal('show');
    });

    // Hapus kategori
    $(document).on('click','.btn-hapus', function(){
        const id = $(this).data('id');
        Swal.fire({
            title:'Hapus kategori?',
            icon:'warning',
            showCancelButton:true,
            confirmButtonText:'Ya, hapus'
        }).then(result=>{
            if(result.isConfirmed){
                $.post('pages/admin_bengkel/api_kategori_asset.php',{action:'hapus',id_kategori:id},function(res){
                    if(res.status==='success'){
                        Swal.fire('Berhasil',res.message,'success');
                        loadKategori();
                        $('#id_kategori option[value="'+id+'"]').remove();
                    }else{
                        Swal.fire('Gagal',res.message,'error');
                    }
                },'json');
            }
        });
    });

    function loadKategori(){
        $.getJSON('pages/admin_bengkel/api_kategori_asset.php?action=list', function(res){
            let tbody='';
            res.data.forEach((k,i)=>{
                tbody += `<tr>
                    <td>${i+1}</td>
                    <td>${k.nama_kategori}</td>
                    <td>
                        <button class="btn btn-info btn-sm btn-edit" data-id="${k.id_kategori}" data-nama="${k.nama_kategori}">Edit</button>
                        <button class="btn btn-danger btn-sm btn-hapus" data-id="${k.id_kategori}">Hapus</button>
                    </td>
                </tr>`;
            });
            $('#dataTableKelolaKategori tbody').html(tbody);
        });
    }

    // Ubah asset
    $(document).on('click','.btn-ubah-asset', function(){
        const id = $(this).data('id');
        const nama = $(this).data('nama');
        const harga = $(this).data('harga');
        const stok = $(this).data('stok');
        const id_kategori = $(this).data('id_kategori');

        // Isi ke form
        $('#edit_id_asset').val(id);
        $('#edit_nama_asset').val(nama);
        $('#edit_harga_beli').val(harga);
        $('#edit_stok').val(stok);
        $('#edit_id_kategori').val(id_kategori);

        // Tampilkan modal
        $('#modalUbahAsset').modal('show');
    });


    

});
</script>
