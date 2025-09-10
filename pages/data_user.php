<div class="row">
  <div class="col-lg-12">
    <div class="box box-info">
      <div class="box-header with-border">
        <a href="" class="btn btn-primary" data-toggle="modal" data-target="#myModal" id="tambah"><i class="fa fa-plus"></i> User</a>
      </div>
      <div class="box-body">
      <?php
        if (isset($_POST['simpan'])) {
          if ($_POST['id_users'] == "") {
            $password = md5($_POST['username']);
            $sql_simpan = mysqli_query($conn,"INSERT INTO tb_users values('','$_POST[nama_user]','$_POST[username]','$password','$_POST[level]')");
            if ($sql_simpan) {
              ?>
              <script type="text/javascript">
                alert("Success, Data baru berhasil ditambahkan !");
              </script>
              <?php
            
            }
          }else {
            $sql_simpan = mysqli_query($conn,"UPDATE tb_users set nama_users = '$_POST[nama_user]', username = '$_POST[username]', level = '$_POST[level]' where id_users = '$_POST[id_users]'");
            if ($sql_simpan) {
              ?>
              <script type="text/javascript">
                alert("Success, Data berhasil diubah !");
              </script>
              <?php
            
            }
          }
        }
      ?>
        <table class="table table-bordered table-hover" id="data">
          <thead>
            <tr>
              <th>No</th>
              <th>Nama User</th>
              <th>Username</th>
              <th>Password</th>
              <th>Level</th>
              <th>Opsi</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $i = 1;
              $sql = mysqli_query($conn,"SELECT * FROM tb_users");
              while ($data = mysqli_fetch_array($sql)) {
                ?>

            <tr>
              <td><?= $i; ?></td>
              <td><?= $data['nama_users']; ?></td>
              <td><?= $data['username']; ?></td>
              <td>*******************</td>
              <td><?= $data['level']; ?></td>
              <td>
                <a href="#" class="btn btn-success" data-toggle="modal" data-target="#myModal" id="kategori" data-id="<?= $data['id_users']; ?>">Edit</a>&nbsp;
                <a href="?page=reset password&id_users=<?= $data['id_users'] ?>" class="btn btn-warning">Reset Password</a>&nbsp;
                <a href="?page=hapus User&id=<?= $data['id_users']; ?>" class="btn btn-danger" onclick="return confirm('Yakin menghapus data ?')">Hapus</a></td>
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
        <h4 class="modal-title">Data User</h4>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label class="control-label col-sm-12">Nama User</label>
          <div class="col-sm-12">
            <input type="hidden" name="id_users" id="id_users">
            <input type="text" name="nama_user" placeholder="Masukkan Nama User..." class="form-control" required id="nama_user">
          </div>
        </div>
        <div class="form-group row">
          <label class="control-label col-sm-12">Username</label>
          <div class="col-sm-12">
            <input type="text" name="username" placeholder="Masukkan Username..." class="form-control" required id="username">
          </div>
        </div>
        <div class="form-group row">
          <label class="control-label col-sm-12">Level</label>
          <div class="col-sm-12">
            <select name="level" id="level" class="form-control">
              <option value="">--Pilih Level--</option>
              <option value="Administrator">Administrator</option>
              <option value="Wali Kelas">Wali Kelas</option>
              <option value="Guru Mapel">Guru Mapel</option>
              <option value="Guru BK">Guru BK</option>
              <option value="Siswa">Siswa</option>
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
    $("#tambah").click(function () {
      $("#id_users").val("");
      $("#nama_user").val("");
      $("#username").val("");
      $("#level").val("");
    });
    $("#myModal").on("show.bs.modal", function (e) {
      var data_kategori = $(e.relatedTarget).data('id');
      $.ajax({
        type:"POST",
        url:"ajax/users_show.php",
        data:"id_users="+data_kategori,
        success:function (data) {
          var obj = JSON.parse(data);
          $("#id_users").val(obj.id_users);
          $("#nama_user").val(obj.nama_users);
          $("#username").val(obj.username);
          $("#level").val(obj.level);
        }
      });
    })
  });
</script>