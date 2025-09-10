<div class="row">
  <div class="col-lg-12">
    <div class="box box-info">
      <div class="box-header with-border">
         <?php
          if ($data_users['level'] == 'Administrator') {
            ?>
        <a href="?page=akumulasi point" class="btn btn-primary">Kembali</a>
            
            <?php
          }
          ?>
      </div>
      <div class="box-body">
        <table class="table table-bordered table-hover" id="data">
          <thead>
            <tr>
              <th rowspan="2">No</th>
              <th rowspan="2">Tanggal</th>
              <th rowspan="2">Pemberi Poin</th>
              <th rowspan="2">Indikator</th>
              <th colspan="2">Poin</th>
            </tr>
            <tr>
              <th>Positive</th>
              <th>Negative</th>
              <?php
                if($data_users['level'] == 'Administrator'){
                    
                    ?>
                <th>
                    Opsi
                </th>
                    <?php
                }else {
                }
              ?>
            </tr>
          </thead>
          <tbody>
            <?php 
              $i=1;
              
                $data_siswa = mysqli_fetch_array(mysqli_query($conn,"SELECT * FROM tb_siswa where nis = '$data_users[username]'"));
              if($data_users['level'] == 'Administrator' or $data_users['level'] == 'Wali Kelas'){
               $sql = mysqli_query($conn,"SELECT * FROM tb_point_siswa join tb_users on tb_users.id_users = tb_point_siswa.id_user where id_siswa = '$_GET[id_siswa]'") or die(mysqli_error($conn));
              }else{
                 $sql = mysqli_query($conn,"SELECT * FROM tb_point_siswa join tb_users on tb_users.id_users = tb_point_siswa.id_user where id_siswa = '$data_siswa[id_siswa]'") or die(mysqli_error($conn));
              }
              while ($data = mysqli_fetch_array($sql)){
            ?>

            <tr>
              <td><?= $i; ?></td>
              <td><?= $data['tanggal']; ?></td>
              <td><?= $data['nama_users']; ?></td>
              <td><?= $data['indikator']; ?></td>
              <td><?= $data['positive_point']; ?></td>
              <td><?= $data['negative_point']; ?></td>
              <?php
                if($data_users['level'] == 'Administrator'){
                    
                    ?>
                <td>
                    <a href="?page=hapus poin siswa&id=<?= $data['id_point_siswa'] ?>" class="btn btn-danger btn-sm">Hapus</a>
                </td>
                    <?php
                }else {
                    
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

<script type="text/javascript">
  $(window).ready(function () {
    $("#data").DataTable();
  });
</script>