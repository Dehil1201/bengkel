<?php
    $sql_presensi = mysqli_query($conn,"SELECT * FROM tb_presensi group by tanggal order by tanggal desc");
?>
<div class="row">
  <div class="col-lg-12">
    <div class="box box-info">
      <div class="box-header with-border">
          
      </div>
      <div class="box-body" style="overflow-x:auto;">
        <table class="table table-bordered table-hover" width="100%" id="data">
          <thead>
            <tr>
              <th>No</th>
              <th>Tanggal</th>
              <th>Status Presensi</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $i = 1;
              $data_siswa = mysqli_fetch_array(mysqli_query($conn,"SELECT * FROM tb_siswa where nis = '$data_users[username]'"));
              $sql = mysqli_query($conn,"SELECT * FROM tb_det_presensi join tb_presensi on tb_det_presensi.id_presensi = tb_presensi.id_presensi where id_siswa = '$data_siswa[id_siswa]' order by tanggal desc") or die(mysqli_error($conn));
              while ($data = mysqli_fetch_array($sql)) {
                  
                ?>

            <tr>
              <td><?= $i; ?></td>
              <td><?= $data['tanggal']; ?></td>
              <td class="text-center"><?= $data['presensi']; ?></td>
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

<script type="text/javascript">
  $(window).ready(function () {
    var jumlah_siswa = <?= $jumlah_siswa ?>;
    console.log(jumlah_siswa);
    for (var i = 1; i <= jumlah_siswa; i++) {
      $("#h_"+i).click(function () {
        $("#ubah_presensi_"+i).html("Loading...");
        $.ajax({
          type : "POST",
          url : "ajax/update_presensi.php",
          data : {id_presensi:<?= $_GET['id_presensi'] ?>,id_siswa:$(this).attr('id_siswa'),status:$(this).attr('status')},
          success : function (data) {
            var obj = JSON.parse(data);
            console.log(obj);
            $("#ubah_presensi_"+obj.id_siswa).html(obj.presensi);
          }
        });
      });
      $("#s_"+i).click(function () {
        console.log($(this).attr('id_siswa'));
        $("#ubah_presensi_"+i).html("Loading...");
        $.ajax({
          type : "POST",
          url : "ajax/update_presensi.php",
          data : {id_presensi:<?= $_GET['id_presensi'] ?>,id_siswa:$(this).attr('id_siswa'),status:$(this).attr('status')},
          success : function (data) {
            var obj = JSON.parse(data);
            console.log(obj);
            $("#ubah_presensi_"+obj.id_siswa).html(obj.presensi);
          }
        });     });
      $("#i_"+i).click(function () {
        console.log($(this).attr('id_siswa'));
        $("#ubah_presensi_"+i).html("Loading...");
        $.ajax({
          type : "POST",
          url : "ajax/update_presensi.php",
          data : {id_presensi:<?= $_GET['id_presensi'] ?>,id_siswa:$(this).attr('id_siswa'),status:$(this).attr('status')},
          success : function (data) {
            var obj = JSON.parse(data);
            console.log(obj);
            $("#ubah_presensi_"+obj.id_siswa).html(obj.presensi);
          }
        });
      });
      $("#a_"+i).click(function () {
        console.log($(this).attr('id_siswa'));
        $("#ubah_presensi_"+i).html("Loading...");
        $.ajax({
          type : "POST",
          url : "ajax/update_presensi.php",
          data : {id_presensi:<?= $_GET['id_presensi'] ?>,id_siswa:$(this).attr('id_siswa'),status:$(this).attr('status')},
          success : function (data) {
            var obj = JSON.parse(data);
            console.log(obj);
            $("#ubah_presensi_"+obj.id_siswa).html(obj.presensi);
          }
        });
      });
    }
    // $.ajax({
    //   type:"POST",
    //   url:"ajax/jurusan_show.php",
    //   data:"id_presensi="+data_kategori,
    //   success:function (data) {
    //     var obj = JSON.parse(data);

    //     $("#id_presensi").val(obj.id_presensi);
    //     $("#nama_presensi").val(obj.nama_presensi);
    //   }
    // });
  });
</script>