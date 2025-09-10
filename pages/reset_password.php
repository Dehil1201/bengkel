<?php 
	$id_users = $_GET['id_users'];
	$data_users = mysqli_fetch_array(mysqli_query($conn,"SELECT * FROM tb_users where id_users = '$id_users'"));
	$password_default = md5($data_users['username']);
	$reset = mysqli_query($conn,"UPDATE tb_users set password = '$password_default' where id_users = '$id_users'");

	if ($reset) {
		?>
		<script type="text/javascript">
			alert("Reset Password Success !");
			window.location.href="?page=data user"
		</script>
		<?php
	}
?>