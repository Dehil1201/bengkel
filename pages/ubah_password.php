 
  <form method="POST">
  <div class="row">
    <div class="col-lg-12"  style="margin : 0 auto">
      <div class="box box-info"  style="margin : 0 auto">
        <div class="box-header with-border">
          <h3 class="box-title">Ubah Password</h3>
           <?php
  if (isset($_POST['simpan'])) {
    $password_valid = $data_users['password'];
    $pass = md5($_POST['password_lama']);
    if ($password_valid != $pass) {
      ?>
        <div class="col-lg-12 bg-red text-white">
          Password sebelumnya salah !, Silakan coba lagi !
        </div>
      <?php 
    }else {
      $password_baru = md5($_POST['password_baru']);
      $konfirmasi_password_baru = md5($_POST['konfirmasi_password_baru']);
      if ($password_baru != $konfirmasi_password_baru) {
        ?>
        <div class="col-sm-12 bg-red text-white">
          Konfirmasi password salah salah !, Silakan coba lagi !
        </div>
      <?php 
      }else {
        mysqli_query($conn,"UPDATE tb_users set password = '$password_baru' where id_users = '$data_users[id_users]'");
        ?>
        <script type="text/javascript">
          alert("Selamat password anda berhasil di ubah !");
          // window.location.href = "logout.php";
        </script>
        <?php
      }
    }
  }
  ?>
        </div>
        <div class="box-body" style="margin : 0 auto">
          <div class="form-group row">
            <label class="control-label col-sm-12">Password Sebelumnya</label>
            <div class="col-sm-12">
              <input type="text" name="password_lama" placeholder="Masukkan Password Sebelumnya..." class="form-control" required id="password_lama">
            </div>
          </div>
          <div class="form-group row">
            <label class="control-label col-sm-12">Password Baru</label>
            <div class="col-sm-12">
              <input type="text" name="password_baru" placeholder="Masukkan Password Baru..." class="form-control" required id="password_baru">
            </div>
          </div>
          <div class="form-group row">
            <label class="control-label col-sm-12">Konfirmasi Password Baru</label>
            <div class="col-sm-12">
              <input type="text" name="konfirmasi_password_baru" placeholder="Konfirmasi Password Baru..." class="form-control" required id="password_baru">
            </div>
          </div>
          <div class="form-group row">
            <div class="col-sm-12">
              <input type="submit" name="simpan" class="form-control btn btn-primary" value="Simpan">
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>