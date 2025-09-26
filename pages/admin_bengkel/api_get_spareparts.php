<?php
session_start();
include "../../inc/koneksi.php";

$search = $_POST['search'] ?? '';
$page   = $_POST['page'] ?? 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

$id_user = $_SESSION['id_user'];
$q2 = mysqli_query($conn, "SELECT bengkel_id FROM users WHERE id_user='$id_user' LIMIT 1");
$d2 = mysqli_fetch_assoc($q2);
$id_bengkel = $d2['bengkel_id'];

$sql = "
  SELECT sp.id_sparepart, sp.kode_sparepart, sp.nama_sparepart,
         sp.hpp_per_pcs, st.nama_satuan as satuan, hjs.harga_jual
  FROM spareparts sp
  JOIN harga_jual_sparepart hjs ON sp.id_sparepart = hjs.sparepart_id
  JOIN satuan st ON hjs.satuan_jual_id = st.id_satuan
  WHERE sp.bengkel_id = ?
";

$params = [$id_bengkel];
$types  = "i";

if (!empty($search)) {
  $sql .= " AND (sp.nama_sparepart LIKE ? OR sp.kode_sparepart LIKE ?)";
  $searchLike = "%$search%";
  $params[] = $searchLike;
  $params[] = $searchLike;
  $types .= "ss";
}

$sql .= " ORDER BY sp.nama_sparepart ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
  $items[] = [
    "id" => $row['kode_sparepart'],
    "nama_sparepart" => $row['nama_sparepart'],
    "harga_jual" => $row['harga_jual'],
    "satuan" => $row['satuan']
  ];
}

$response = [
  "items" => $items,
  "more" => count($items) == $limit // kalau full berarti masih ada halaman berikutnya
];

header('Content-Type: application/json');
echo json_encode($response);
