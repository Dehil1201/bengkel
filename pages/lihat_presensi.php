<div class="row">
  <div class="col-lg-12">
    <div class="box box-info">
      <div class="box-header with-border">
        <?php 
          if ($data_users['level'] == 'Administrator') {

          }else {
            $sekarang = date($_GET['tanggal']);
            $cek_presensi_harian = mysqli_num_rows(mysqli_query($conn,"SELECT * FROM tb_presensi where id_users = '$data_users[id_users]' and tanggal = '$sekarang'"));
            if ($cek_presensi_harian >= 1) {
              
            }else {
              ?>

            <a href="" class="btn btn-primary" data-toggle="modal" data-target="#myModal" id="tambah"><i class="fa fa-plus"></i> Presensi</a>
              <?php
            }
          }
        ?>
      </div>
      <div class="box-body">
      <?php
        if (isset($_POST['simpan'])) {
          $sql_tb_presensi = mysqli_query($conn,"SELECT * FROM tb_presensi where tanggal = '$_POST[tanggal]' and id_kelas = '$_POST[kelas]'") or die(mysqli_error($conn));
          $cek_tb_presensi = mysqli_num_rows($sql_tb_presensi);
          if ($cek_tb_presensi >= 1) {
            ?>
            <script type="text/javascript">
              alert("Absen kelas ini sudah di rekam !");
            </script>
            <?php
          }else {
          $sql_max_presensi = mysqli_query($conn,"SELECT MAX(id_presensi) as id_terakhir FROM tb_presensi") or die(mysqli_error($conn));
          $data_max_id_presensi = mysqli_fetch_array($sql_max_presensi);
          $id_presensi_asli = $data_max_id_presensi['id_terakhir']+1;
          $id_users = $data_users['id_users'];
          if ($_POST['id_presensi'] == "") {

            $sql_simpan = mysqli_query($conn,"INSERT INTO tb_presensi values('$id_presensi_asli','$_POST[tahun_ajaran]','$_POST[tanggal]','$_POST[kelas]','$id_users')");
            $sql_siswa = mysqli_query($conn,"SELECT * FROM tb_siswa where id_kelas = '$_POST[kelas]'");


            while($data_siswa = mysqli_fetch_array($sql_siswa)){
              mysqli_query($conn,"INSERT INTO tb_det_presensi values ('','$id_presensi_asli','$data_siswa[id_siswa]','H')") or die(mysqli_error($conn));
            }
            if ($sql_simpan) {
              ?>
              <script type="text/javascript">
                alert("Success, Data baru berhasil ditambahkan !");
              </script>
              <?php
            
            }
          }else {
            $sql_simpan = mysqli_query($conn,"UPDATE tb_presensi set nama_presensi = '$nama_presensi' where id_presensi = '$_POST[id_presensi]'");
            if ($sql_simpan) {
              ?>
              <script type="text/javascript">
                alert("Success, Data berhasil diubah !");
              </script>
              <?php
            
            }
            }
          }
        }
      ?>
        <table class="table table-bordered table-hover" id="data">
          <thead>
            <tr>
              <th rowspan="2">No</th>
              <th rowspan="2">Tahun Ajaran</th>
              <th rowspan="2">Tanggal</th>
              <th rowspan="2">Kelas</th>
              <th colspan="3">Kehadiran</th>
              <th rowspan="2">Opsi</th>
            </tr>
            <tr>
              <th>Sakit</th>
              <th>Ijin</th>
              <th>Tanpa Keterangan</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $i = 1;
              $sql = mysqli_query($conn,"SELECT * FROM tb_kelas");
              $tanggal = date('Y-m-d');
              while ($data = mysqli_fetch_array($sql)) {
                if ($data_users['level']=='Guru Mapel') {
                  $presensi = "SELECT * FROM tb_presensi join tb_tahun_ajaran on tb_presensi.id_tahun_ajaran = tb_tahun_ajaran.id_tahun_ajaran where tanggal = '$tanggal' and id_kelas = '$data[id_kelas]' and id_users = '$data_users[id_users]'";
                  $sql_presensi = mysqli_query($conn,$presensi);
                  $data_presensi2 = mysqli_fetch_array($sql_presensi);
                  $sql_jumlah_s = mysqli_query($conn,"SELECT count(presensi) as jml_presensi FROM tb_det_presensi where id_presensi = '$data_presensi2[id_presensi]' and presensi = 'S'");
                  $sql_jumlah_i = mysqli_query($conn,"SELECT count(presensi) as jml_presensi FROM tb_det_presensi where id_presensi = '$data_presensi2[id_presensi]' and presensi = 'I'");
                  $sql_jumlah_a = mysqli_query($conn,"SELECT count(presensi) as jml_presensi FROM tb_det_presensi where id_presensi = '$data_presensi2[id_presensi]' and presensi = 'A'");

                  $cek_presensi = mysqli_num_rows($sql_presensi);

                  $data_s = mysqli_fetch_array($sql_jumlah_s);
                  $data_i = mysqli_fetch_array($sql_jumlah_i);
                  $data_a = mysqli_fetch_array($sql_jumlah_a);

                  if ($cek_presensi == 0) {
                    continue;
                  }

                }elseif ($data_users['level']=='Wali Kelas') {
                  $presensi = "SELECT * FROM tb_presensi join tb_tahun_ajaran on tb_presensi.id_tahun_ajaran = tb_tahun_ajaran.id_tahun_ajaran where tanggal = '$tanggal' and id_kelas = '$data[id_kelas]' and id_users = '$data_users[id_users]'";
                  $sql_presensi = mysqli_query($conn,$presensi);
                  $data_presensi2 = mysqli_fetch_array($sql_presensi);
                  $sql_jumlah_s = mysqli_query($conn,"SELECT count(presensi) as jml_presensi FROM tb_det_presensi where id_presensi = '$data_presensi2[id_presensi]' and presensi = 'S'");
                  $sql_jumlah_i = mysqli_query($conn,"SELECT count(presensi) as jml_presensi FROM tb_det_presensi where id_presensi = '$data_presensi2[id_presensi]' and presensi = 'I'");
                  $sql_jumlah_a = mysqli_query($conn,"SELECT count(presensi) as jml_presensi FROM tb_det_presensi where id_presensi = '$data_presensi2[id_presensi]' and presensi = 'A'");

                  $cek_presensi = mysqli_num_rows($sql_presensi);

                  $data_s = mysqli_fetch_array($sql_jumlah_s);
                  $data_i = mysqli_fetch_array($sql_jumlah_i);
                  $data_a = mysqli_fetch_array($sql_jumlah_a);

                  if ($cek_presensi == 0) {
                    continue;
                  }

                }else {
                  $presensi = "SELECT * FROM tb_presensi join tb_tahun_ajaran on tb_presensi.id_tahun_ajaran = tb_tahun_ajaran.id_tahun_ajaran where tanggal = '$_GET[tanggal]' and id_kelas = '$data[id_kelas]'";
                  $sql_presensi = mysqli_query($conn,$presensi);
                  $data_presensi2 = mysqli_fetch_array($sql_presensi);
                  $sql_jumlah_s = mysqli_query($conn,"SELECT count(presensi) as jml_presensi FROM tb_det_presensi where id_presensi = '$data_presensi2[id_presensi]' and presensi = 'S'");
                  $sql_jumlah_i = mysqli_query($conn,"SELECT count(presensi) as jml_presensi FROM tb_det_presensi where id_presensi = '$data_presensi2[id_presensi]' and presensi = 'I'");
                  $sql_jumlah_a = mysqli_query($conn,"SELECT count(presensi) as jml_presensi FROM tb_det_presensi where id_presensi = '$data_presensi2[id_presensi]' and presensi = 'A'");

                  $cek_presensi = mysqli_num_rows($sql_presensi);

                  $data_s = mysqli_fetch_array($sql_jumlah_s);
                  $data_i = mysqli_fetch_array($sql_jumlah_i);
                  $data_a = mysqli_fetch_array($sql_jumlah_a);

                }
                ?>

            <tr>
              <td><?= $i; ?></td>
              <td><?= $data_presensi2['nama_tahun_ajaran']; ?></td>
              <td><?= $data_presensi2['tanggal']; ?></td>
              <td><?= $data['nama_kelas']; ?></td>
              <td><?= $data_s['jml_presensi']; ?></td>
              <td><?= $data_i['jml_presensi']; ?></td>
              <td><?= $data_a['jml_presensi']; ?></td>

          <?php 
          if ($data_users['level'] == 'Administrator') {
            if ($cek_presensi == 0) {
              $warna = 'btn-default';
              $href = '#';
            }else {
              $warna = 'btn-warning';
              $href = "?page=edit presensi&id_presensi=".$data_presensi2['id_presensi'];
            }
            ?>
            <td><a href="<?= $href; ?>" class="btn <?= $warna; ?>">Lihat Presensi</a></td>
            <?php
          }else {
            ?>
            <td><a href="?page=edit presensi&id_presensi=<?= $data_presensi2['id_presensi']; ?>" class="btn btn-warning">Mulai / Edit Presensi</a>&nbsp;<a href="?page=hapus presensi&id=<?= $data_presensi2['id_presensi']; ?>" class="btn btn-danger" onclick="return confirm('Yakin menghapus data ?')">Hapus</a></td>
            <?php
          }
          ?>
              
            </tr>

                <?php
                $i++;
              }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<form method="POST">
<div class="modal fade" id="myModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Presensi</h4>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label class="control-label col-sm-12">Tahun Ajaran</label>
          <div class="col-sm-12">
            <input type="hidden" name="id_presensi" id="id_presensi">
            <select name="tahun_ajaran" class="form-control" id="tahun_ajaran">
              <?php 
                $sql_tahun_ajaran = mysqli_query($conn,"SELECT * FROM tb_tahun_ajaran order by nama_tahun_ajaran desc");
                while($data_tahun_ajaran = mysqli_fetch_array($sql_tahun_ajaran)){
                  ?>
                  <option value="<?= $data_tahun_ajaran['id_tahun_ajaran'] ?>"><?= $data_tahun_ajaran['nama_tahun_ajaran'] ?></option>
                  <?php
                }
              ?>
            </select>
          </div>
        </div>
        <div class="form-group row">
          <label class="control-label col-sm-12">Tanggal</label>
          <div class="col-sm-12">
            <input type="date" name="tanggal" class="form-control" id="tahun_ajaran" value="<?= date($_GET['tanggal']); ?>">
          </div>
        </div>
        <div class="form-group row">
          <label class="control-label col-sm-12">Kelas</label>
          <div class="col-sm-12">
            <select name="kelas" class="form-control" id="kelas" style="width:100%;">
              <option value="">--Pilih Kelas--</option>
              <?php 
                $sql_tahun_ajaran = mysqli_query($conn,"SELECT * FROM tb_kelas order by nama_kelas asc");
                while($data_tahun_ajaran = mysqli_fetch_array($sql_tahun_ajaran)){
                  ?>
                  <option value="<?= $data_tahun_ajaran['id_kelas'] ?>"><?= $data_tahun_ajaran['nama_kelas'] ?></option>
                  <?php
                }
              ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Close</button>
        <input type="submit" class="btn btn-primary" name="simpan" value="Save changes">
      </div>
    </div>
    <!-- /.modal-content -->
  </div>
  <!-- /.modal-dialog -->
</div>
</form>
<script type="text/javascript">
  $(window).ready(function () {
    $("#data").DataTable();
    $("#kelas").select2();
    $("#tambah").click(function () {
      $("#id_presensi").val("");
      $("#nama_presensi").val("");
    });
    $("#myModal").on("show.bs.modal", function (e) {
      var data_kategori = $(e.relatedTarget).data('id');
      $.ajax({
        type:"POST",
        url:"ajax/jurusan_show.php",
        data:"id_presensi="+data_kategori,
        success:function (data) {
          var obj = JSON.parse(data);

          $("#id_presensi").val(obj.id_presensi);
          $("#nama_presensi").val(obj.nama_presensi);
        }
      });
    })
  });
</script>