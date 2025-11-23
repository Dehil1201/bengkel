<?php
include "../../inc/koneksi.php";
session_start();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// --- GET DATA SERVER SIDE DATATABLE ---
if ($action == "table") {

    $start  = $_POST['start'];
    $length = $_POST['length'];
    $search = $_POST['search']['value'];

    // akses bengkel
    $user_id = $_SESSION['id_user'];
    $q_bengkel = mysqli_query($conn,
        "SELECT bengkel_id FROM users WHERE id_user='$user_id'"
    );
    $row = mysqli_fetch_assoc($q_bengkel);
    $bengkel_id = $row['bengkel_id'];

    $where = "WHERE js.bengkel_id='$bengkel_id'";
    if (!empty($search)) {
        $where .= " AND (js.nama_servis LIKE '%$search%' OR b.nama_bengkel LIKE '%$search%')";
    }

    // hitung total
    $q_total = mysqli_query($conn,
        "SELECT COUNT(*) AS total FROM jenis_servis js $where"
    );
    $total = mysqli_fetch_assoc($q_total)['total'];

    // ambil data
    $q_data = mysqli_query($conn,
        "SELECT js.*, b.nama_bengkel
         FROM jenis_servis js 
         JOIN bengkels b ON js.bengkel_id=b.id_bengkel
         $where
         ORDER BY js.id_servis DESC
         LIMIT $start, $length"
    );

    $data = [];
    $no = $start + 1;

    while ($row = mysqli_fetch_assoc($q_data)) {
        $data[] = [
            "no" => $no++,
            "nama_servis" => $row['nama_servis'],
            "biaya_format" => "Rp " . number_format($row['biaya'], 0, ',', '.'),
            "nama_bengkel" => $row['nama_bengkel'],
            "aksi" => '
                <button class="btn btn-info btn-xs btn-ubah"
                    data-id="'.$row['id_servis'].'"
                    data-nama="'.$row['nama_servis'].'"
                    data-biaya="'.$row['biaya'].'">
                    <i class="fa fa-edit"></i> Ubah
                </button>

                <button class="btn btn-danger btn-xs btn-hapus"
                    data-id="'.$row['id_servis'].'"
                    data-nama="'.$row['nama_servis'].'">
                    <i class="fa fa-trash"></i> Hapus
                </button>
            '
        ];
    }

    echo json_encode([
        "draw" => intval($_POST['draw']),
        "recordsTotal" => $total,
        "recordsFiltered" => $total,
        "data" => $data
    ]);

    exit();
}
