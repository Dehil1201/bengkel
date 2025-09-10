<div class="row">
  <div class="col-lg-8 col-xs-12">
    <div class="box box-info">
      <div class="box-header with-border">
        <h3 class="box-title">Filter</h3>
      </div>
      <div class="box-body">
        <div class="form-group row">
            <label class="control-label col-sm-12">Periode</label>
            <label class="control-label col-sm-12">Tanggal Awal</label>
            <div class="col-sm-12">
              <input type="date" class="form-control" id="tanggal_awal">
            </div>
            <label class="control-label col-sm-12">Tanggal Akhir</label>
            <div class="col-sm-12">
              <input type="date" class="form-control" id="tanggal_akhir">
            </div>
            <?php
              if($data_users['level']=="Administrator"){
                ?>
                <label class="control-label col-sm-12">Jurusan</label>
                <div class="col-sm-12">
                <select name="jurusan" id="jurusan" class="form-control" width="100%">
                  <option value="">--Pilih Jurusan--</option>
                  <?php 
                    $sql_jurusan = mysqli_query($conn,"SELECT * FROM tb_jurusan");
                    while($data_jurusan = mysqli_fetch_array($sql_jurusan)){
    ?>
                    <option value="<?= $data_jurusan['id_jurusan']; ?>"><?= $data_jurusan['nama_jurusan']; ?></option>
    <?php
                    }
                  ?>
                  
                </select>
                </div>
                <?php
              }
            ?>
            
            <div class="col-sm-12">
              <input type="submit" class="btn btn-sm btn-primary form-control" id="btn_filter" value="Filter">
            </div>
          </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-xs-12">
    <div class="box box-info">
      <div class="box-header with-border">
        <h3 class="box-title">Rekap Siswa Tidak Hadir</h3>
      </div>
      <div class="box-body">
        <table class="table table-bordered table-hover">
          <thead>
            <tr>
              <th>Sakit</th>
              <th>Ijin</th>
              <th>Tanpa Keterangan</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td id="jumlah_sakit"><img src="dist/img/loading.gif" alt="" width="20px"></td>
              <td id="jumlah_ijin"><img src="dist/img/loading.gif" alt="" width="20px"></td>
              <td id="jumlah_alpa"><img src="dist/img/loading.gif" alt="" width="20px"></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-12 col-xs-12">
    <div class="box box-info">
      <div class="box-header with-border">
        <?php 
          if ($data_users['level'] == 'Wali Kelas') {
            $data_wali_kelas = mysqli_fetch_array(mysqli_query($conn,"SELECT * FROM tb_wali_kelas where id_users = '$data_users[id_users]'"));
            ?>
            <a class="btn btn-primary" id="download_presensi"><i class="fa fa-download"></i> Download Presensi</a>
            <?php
          }else {
            ?>
            
            <?php
          }
        ?>
      </div>
      <div class="box-body" id="ubah_data_detail">
        <table class="table table-bordered table-hover" id="data_rekap">
          <thead>
            <tr>
              <th rowspan="2">No</th>
              <th rowspan="2">NISN</th>
              <th rowspan="2">NIS</th>
              <th rowspan="2">Nama Lengkap</th>
              <th rowspan="2">Jenis Kelamin</th>
              <th rowspan="2">Kelas</th>
              <th colspan="3">Kehadiran</th>
            </tr>
            <tr>
              <th>Sakit</th>
              <th>Ijin</th>
              <th>Tanpa Keterangan</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">
  $(window).ready(function () {
    <?php
              $i = 1;
              if ($data_users['level'] == "Wali Kelas") {
                $sql = mysqli_query($conn,"SELECT * FROM tb_siswa join tb_kelas on tb_siswa.id_kelas = tb_kelas.id_kelas join tb_wali_kelas on tb_kelas.id_kelas = tb_wali_kelas.id_kelas where tb_wali_kelas.id_users = '$data_users[id_users]' and  id_tahun_ajaran = '$id_tahun_ajaran_aktif'") or die(mysqli_error($conn));
              }else {

                 $sql = mysqli_query($conn,"SELECT * FROM tb_siswa join tb_kelas on tb_siswa.id_kelas = tb_kelas.id_kelas where id_tahun_ajaran = '$id_tahun_ajaran_aktif' and id_jurusan = '0'");
              }
              $jumlah_sakit = 0;
              $jumlah_ijin = 0;
              $jumlah_alpa = 0;
              ?>
              
              
                var isi_data = [];
              <?php 
              while ($data = mysqli_fetch_array($sql)) {
                $rekap_sakit = mysqli_fetch_array(mysqli_query($conn,"SELECT count(presensi) as jml_presensi FROM tb_det_presensi where id_siswa = '$data[id_siswa]' and presensi = 'S'"));
                $rekap_ijin = mysqli_fetch_array(mysqli_query($conn,"SELECT count(presensi) as jml_presensi FROM tb_det_presensi where id_siswa = '$data[id_siswa]' and presensi = 'I'"));
                $rekap_alpa = mysqli_fetch_array(mysqli_query($conn,"SELECT count(presensi) as jml_presensi FROM tb_det_presensi where id_siswa = '$data[id_siswa]' and presensi = 'a'"));
                
                $jumlah_sakit = $jumlah_sakit+$rekap_sakit['jml_presensi'];
                $jumlah_ijin = $jumlah_ijin+$rekap_ijin['jml_presensi'];
                $jumlah_alpa = $jumlah_alpa+$rekap_alpa['jml_presensi'];
                
                
                ?>

        
          isi_data.push(["<?= $i; ?>","<?= $data['nisn']; ?>","<?= $data['nis']; ?>","<?= $data['nama_siswa']; ?>","<?= $data['jenis_kelamin']; ?>","<?= $data['nama_kelas']; ?>","<?= $rekap_sakit['jml_presensi']; ?>","<?= $rekap_ijin['jml_presensi']; ?>","<?= $rekap_alpa['jml_presensi']; ?>"]);
        

                <?php
                $i++;
              }
            ?>
    $("#data_rekap").DataTable({
      data:isi_data,
      deferRender : true,
      scrollY : 5000,
      scrollCollapse : true,
      scroller: true,
    });
    $("#kelas").select2();
    $("#jurusan").select2();

    
    $("#jumlah_sakit").html("<?= $jumlah_sakit ?>");
    $("#jumlah_ijin").html("<?= $jumlah_ijin ?>");
    $("#jumlah_alpa").html("<?= $jumlah_alpa ?>");
    $("#download_presensi").click(function () {
      
      var tanggal_awal = $("#tanggal_awal").val();
      var tanggal_akhir = $("#tanggal_akhir").val();
      window.open("pages/export_excel.php?id_kelas=<?= $data_wali_kelas['id_kelas']; ?>&tanggal_awal="+tanggal_awal+"&tanggal_akhir="+tanggal_akhir);
    });

    $("#btn_filter").click(function () {
      $("#ubah_data_detail").html("<center><img src='dist/img/loading.gif' alt='' width='50px'></center>");
      $("#jumlah_sakit").html("<img src='dist/img/loading.gif' alt='' width='20px'>");
      $("#jumlah_ijin").html("<img src='dist/img/loading.gif' alt='' width='20px'>");
      $("#jumlah_alpa").html("<img src='dist/img/loading.gif' alt='' width='20px'>");
      var tanggal_awal = $("#tanggal_awal").val();
      var tanggal_akhir = $("#tanggal_akhir").val();
      var jurusan = $("#jurusan").val();
      var level_users = '<?= $data_users['level'] ?>';
      var id_users = '<?= $data_users['id_users'] ?>';

      $.ajax({
        url:"ajax/filter_rekap_presensi.php",
        type: "POST",
        data:{tanggal_awal:tanggal_awal,tanggal_akhir:tanggal_akhir,jurusan:jurusan,level_users:level_users,id_users:id_users},
        success:function (data) {
           $("#ubah_data_detail").html(data);
           console.log(data);
        }
      });
    });
  });
</script>