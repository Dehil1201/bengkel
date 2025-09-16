<?php
// Pastikan koneksi dan session sudah start di index utama
// fungsi generateNoFakturPembelian sama seperti sebelumnya:
function generateNoFakturPembelian($conn) {
    $id_user = $_SESSION['id_user'];

    $q_user = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
    $d_user = mysqli_fetch_assoc($q_user);
    $id_bengkel = $d_user['bengkel_id'];

    $prefix = "PB." . date("Ymd") . "." . $id_user . "." . $id_bengkel;

    $today = date("Y-m-d");
    $q = mysqli_query($conn, "SELECT COUNT(*) as total 
                              FROM transaksi 
                              WHERE DATE(tanggal)='$today' 
                              AND id_user='$id_user' 
                              AND id_bengkel='$id_bengkel'
                              AND jenis='pembelian'");
    $row = mysqli_fetch_assoc($q);

    $no_urut = str_pad($row['total'] + 1, 4, "0", STR_PAD_LEFT);

    return $prefix . "." . $no_urut;
}

$id_user = $_SESSION['id_user'];
$q_user = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
$d_user = mysqli_fetch_assoc($q_user);
$id_bengkel = $d_user['bengkel_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_faktur   = $_POST['no_faktur'];
    $id_user     = $_POST['id_user'];
    $id_supplier = $_POST['id_supplier'];
    $id_bengkel  = $_POST['id_bengkel'];
    $jenis       = "pembelian";

    $spareparts  = json_decode($_POST['spareparts'], true);

    mysqli_begin_transaction($conn);
    try {
        $sql = "INSERT INTO transaksi (no_faktur, jenis, id_user, id_supplier, id_bengkel, total) 
                VALUES ('$no_faktur', '$jenis', '$id_user', '$id_supplier', '$id_bengkel', 0)";
        mysqli_query($conn, $sql);
        $id_transaksi = mysqli_insert_id($conn);

        $total = 0;

        foreach ($spareparts as $sp) {
            $kode    = $sp['kode'];
            $nama    = $sp['nama'];
            $harga   = $sp['harga'];
            $qty     = $sp['qty'];
            $satuan  = $sp['satuan'];
            $subtotal= $harga * $qty;

            $sql_detail = "INSERT INTO transaksi_detail_sparepart 
                           (id_transaksi, kode_sparepart, nama_sparepart, harga, qty, satuan, subtotal)
                           VALUES ('$id_transaksi', '$kode', '$nama', '$harga', '$qty', '$satuan', '$subtotal')";
            mysqli_query($conn, $sql_detail);

            // Update stok sparepart bertambah saat pembelian
            mysqli_query($conn, "UPDATE sparepart SET stok = stok + $qty WHERE kode_sparepart='$kode'");

            $total += $subtotal;
        }

        mysqli_query($conn, "UPDATE transaksi SET total='$total' WHERE id_transaksi='$id_transaksi'");

        mysqli_commit($conn);
        echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: 'Transaksi pembelian berhasil disimpan (No Faktur: $no_faktur)'
                }).then(() => { window.location = 'index.php?page=transaksi_pembelian'; });
              </script>";
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Transaksi gagal: " . $e->getMessage() . "'
                });
              </script>";
    }
}
?>

    <div class="row">
        <!-- Form Transaksi -->
        <div class="col-md-4">
            <form id="form-transaksi" method="POST" autocomplete="off">
                <input type="hidden" name="id_user" value="<?= $id_user; ?>">
                <input type="hidden" name="id_bengkel" value="<?= $id_bengkel; ?>">
                <input type="hidden" name="jenis" value="pembelian">

                <div class="form-group">
                    <label>No Faktur</label>
                    <input type="text" class="form-control" name="no_faktur" id="noFakturText" value="<?= generateNoFakturPembelian($conn); ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Pilih Supplier</label>
                    <select name="id_supplier" id="supplier-select" class="form-control" required>
                        <option value="">-- Pilih Supplier --</option>
                        <?php
                        $q_sup = mysqli_query($conn, "SELECT id_supplier, nama_supplier FROM suppliers ORDER BY nama_supplier ASC");
                        while($row = mysqli_fetch_assoc($q_sup)){
                            echo '<option value="'.$row['id_supplier'].'">'.$row['nama_supplier'].'</option>';
                        }
                        ?>
                    </select>
                </div>

                <h4><b>Daftar Sparepart</b></h4>
                <div class="form-group">
                    <label>Pilih Sparepart</label>
                    <select class="form-control" id="sparepart-select" style="width:100%;">
                        <option value="">-- Pilih Sparepart --</option>
                        <?php
                        $qsp = mysqli_query($conn, "SELECT 
                            sp.kode_sparepart, 
                            sp.nama_sparepart, 
                            sp.hpp_per_pcs, 
                            st.nama_satuan as satuan
                            FROM spareparts sp
                            JOIN satuan st ON sp.satuan_id = st.id_satuan
                            WHERE sp.bengkel_id = '$id_bengkel'
                            ORDER BY sp.nama_sparepart ASC");
                        while($row = mysqli_fetch_assoc($qsp)) {
                            echo '<option 
                            value="'.$row['kode_sparepart'].'" 
                            data-harga="'.$row['hpp_per_pcs'].'" 
                            data-nama_sparepart="'.$row['nama_sparepart'].'" 
                            data-satuan="'.$row['satuan'].'">'
                            .$row['nama_sparepart'].' - '.number_format($row['hpp_per_pcs']).'/'.$row['satuan'].'</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Jumlah</label>
                    <input type="number" class="form-control" id="jumlah-barang-input" value="1" min="1">
                </div>
                <button type="button" class="btn btn-warning btn-block" id="btn-add-sparepart"><i class="fa fa-plus"></i> Tambah Sparepart</button>

                <input type="hidden" name="spareparts" id="input-spareparts">

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:20px;">
                    <i class="fa fa-save"></i> Simpan Transaksi Pembelian
                </button>
            </form>
        </div>

        <!-- Tabel Keranjang -->
        <div class="col-md-8">
            <div class="box box-info">
                <div class="box-header with-border" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="box-title">Keranjang Sparepart</h3>
                    <h3 id="total-display" style="margin: 0; font-weight: bold;">Rp 0</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-striped" id="table-sparepart">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>Harga (HPP)</th>
                                <th>Qty</th>
                                <th>Satuan</th>
                                <th>Subtotal</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="cart-barang-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script>
$(function(){
    $('#sparepart-select').select2({ placeholder: "Cari sparepart...", allowClear: true });
    $('#supplier-select').select2({ placeholder: "Pilih supplier...", allowClear: true });

    let cart = [];

    function renderCart() {
        let tbody = $("#cart-barang-body");
        tbody.empty();
        let total = 0;
        cart.forEach((item, index) => {
            let subtotal = item.harga * item.qty;
            total += subtotal;
            tbody.append(`
                <tr>
                    <td>${item.kode}</td>
                    <td>${item.nama}</td>
                    <td>${formatRupiah(item.harga)}</td>
                    <td>
                        <input type="number" class="form-control input-qty" data-index="${index}" value="${item.qty}" min="1" style="width:80px;">
                    </td>
                    <td>${item.satuan}</td>
                    <td>${formatRupiah(subtotal)}</td>
                    <td><button class="btn btn-danger btn-xs btn-remove" data-index="${index}"><i class="fa fa-trash"></i></button></td>
                </tr>
            `);
        });
        $("#total-display").text(formatRupiah(total));
        $("#input-spareparts").val(JSON.stringify(cart));
    }

    function formatRupiah(angka){
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    $("#btn-add-sparepart").click(function(){
        let kode = $("#sparepart-select").val();
        let nama = $("#sparepart-select option:selected").data("nama_sparepart");
        let harga = parseInt($("#sparepart-select option:selected").data("harga"));
        let satuan = $("#sparepart-select option:selected").data("satuan");
        let qty = parseInt($("#jumlah-barang-input").val());

        if (!kode) {
            Swal.fire('Pilih sparepart dulu!');
            return;
        }
        if (qty < 1) {
            Swal.fire('Jumlah harus minimal 1!');
            return;
        }

        let idx = cart.findIndex(i => i.kode === kode);
        if (idx !== -1) {
            cart[idx].qty += qty;
        } else {
            cart.push({ kode, nama, harga, qty, satuan });
        }
        renderCart();
    });

    $(document).on('input', '.input-qty', function(){
        let index = $(this).data('index');
        let val = parseInt($(this).val());
        if(val < 1) val = 1;
        cart[index].qty = val;
        renderCart();
    });

    $(document).on('click', '.btn-remove', function(){
        let index = $(this).data('index');
        cart.splice(index, 1);
        renderCart();
    });

    $("#form-transaksi").submit(function(e){
        if(cart.length == 0) {
            e.preventDefault();
            Swal.fire('Keranjang masih kosong!');
            return false;
        }
        if (!$("#supplier-select").val()) {
            e.preventDefault();
            Swal.fire('Pilih supplier terlebih dahulu!');
            return false;
        }
        $("#input-spareparts").val(JSON.stringify(cart));
        // biarkan form submit normal
    });

    renderCart();
});
</script>
