<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php
// Pastikan session_start() sudah aktif di index.php (INI PENTING SEKALI!)

// Cek hak akses: hanya admin dan guru yang bisa mengakses halaman ini
if (function_exists('get_user_role')) {
    $user_role = get_user_role();
    if ($user_role !== 'admin' && $user_role !== 'guru') {
        echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat halaman ini.</div>";
        exit();
    }
} else {
    echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan. Pastikan file fungsi.php ter-include.</div>";
    exit();
}

$current_user_id = $_SESSION['user_id'] ?? null;
$guru_id_loggedin = null;

if ($user_role === 'guru' && $current_user_id) {
    $query_guru_id = mysqli_query($conn, "SELECT guru_id FROM Guru WHERE user_id = '$current_user_id'");
    if ($row_guru_id = mysqli_fetch_assoc($query_guru_id)) {
        $guru_id_loggedin = $row_guru_id['guru_id'];
    }
}

$kuis_id = $_GET['kuis_id'] ?? null;
if (!$kuis_id || !is_numeric($kuis_id)) {
    echo "<div class='alert alert-danger'>ID Kuis tidak valid atau tidak ditemukan.</div></div>";
    exit();
}

$sql_kuis_detail = "SELECT K.kuis_id, K.judul_kuis, K.deskripsi, K.waktu_mulai, K.waktu_selesai, K.durasi_menit,
                           U.nama_lengkap AS nama_guru, G.guru_id
                    FROM Kuis K
                    JOIN Guru G ON K.guru_id = G.guru_id
                    JOIN Users U ON G.user_id = U.user_id
                    WHERE K.kuis_id = '" . mysqli_real_escape_string($conn, $kuis_id) . "'";

if ($user_role === 'guru') {
    $sql_kuis_detail .= " AND K.guru_id = '" . mysqli_real_escape_string($conn, $guru_id_loggedin) . "'";
}

$result_kuis_detail = mysqli_query($conn, $sql_kuis_detail);
$kuis_detail = null;

if ($result_kuis_detail && mysqli_num_rows($result_kuis_detail) > 0) {
    $kuis_detail = mysqli_fetch_assoc($result_kuis_detail);
} else {
    echo "<div class='alert alert-danger'>Kuis tidak ditemukan atau Anda tidak memiliki akses ke kuis ini.</div></div>";
    exit();
}


// --- LOGIKA TAMBAH PERTANYAAN ---
if (isset($_POST['add_question'])) {
    $teks_pertanyaan = mysqli_real_escape_string($conn, $_POST['teks_pertanyaan'] ?? '');
    $tipe_pertanyaan = mysqli_real_escape_string($conn, $_POST['tipe_pertanyaan'] ?? '');
    $poin = mysqli_real_escape_string($conn, $_POST['poin'] ?? 0);
    $opsi_teks = $_POST['opsi_teks'] ?? [];
    $opsi_benar_index = $_POST['opsi_benar'] ?? null;

    if (empty($teks_pertanyaan) || empty($tipe_pertanyaan) || empty($poin)) {
        echo "<div class='alert alert-danger'>Teks pertanyaan, tipe, dan poin tidak boleh kosong.</div>";
    } elseif ($poin <= 0) {
        echo "<div class='alert alert-danger'>Poin pertanyaan harus lebih dari 0.</div>";
    } else {
        mysqli_begin_transaction($conn);
        try {
            $insert_q_sql = "INSERT INTO PertanyaanKuis (kuis_id, teks_pertanyaan, tipe_pertanyaan, poin) 
                             VALUES ('$kuis_id', '$teks_pertanyaan', '$tipe_pertanyaan', '$poin')";

            if (!mysqli_query($conn, $insert_q_sql)) {
                throw new Exception("Gagal menambahkan pertanyaan: " . mysqli_error($conn));
            }
            $pertanyaan_id = mysqli_insert_id($conn);

            // Perbaikan: Cek nilai tipe_pertanyaan yang benar
            if ($tipe_pertanyaan === 'pilihan_ganda') {
                if (empty($opsi_teks) || !is_array($opsi_teks) || count($opsi_teks) < 2) {
                    throw new Exception("Pertanyaan Pilihan Ganda harus memiliki minimal 2 opsi.");
                }
                if ($opsi_benar_index === null || !isset($opsi_teks[$opsi_benar_index])) {
                    throw new Exception("Anda harus memilih satu opsi jawaban yang benar.");
                }

                foreach ($opsi_teks as $index => $teks) {
                    $is_benar = ($index == $opsi_benar_index) ? 1 : 0;
                    $insert_opsi_sql = "INSERT INTO OpsiPilihanGanda (pertanyaan_id, teks_opsi, is_benar) 
                                         VALUES ('$pertanyaan_id', '" . mysqli_real_escape_string($conn, $teks) . "', '$is_benar')";

                    if (!mysqli_query($conn, $insert_opsi_sql)) {
                        throw new Exception("Gagal menambahkan opsi: " . mysqli_error($conn));
                    }
                }
            }
            
            mysqli_commit($conn);
            echo "<script>alert('Pertanyaan berhasil ditambahkan!');window.location.href='?page=quiz_questions&kuis_id=$kuis_id';</script>";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "<div class='alert alert-danger'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
            error_log("Add Question Error: " . $e->getMessage());
        }
    }
}

// --- LOGIKA EDIT PERTANYAAN ---
if (isset($_POST['edit_question'])) {
    $pertanyaan_id = mysqli_real_escape_string($conn, $_POST['pertanyaan_id'] ?? '');
    $teks_pertanyaan = mysqli_real_escape_string($conn, $_POST['teks_pertanyaan_edit'] ?? '');
    $tipe_pertanyaan = mysqli_real_escape_string($conn, $_POST['tipe_pertanyaan_edit'] ?? '');
    $poin = mysqli_real_escape_string($conn, $_POST['poin_edit'] ?? 0);
    $opsi_teks_edit = $_POST['opsi_teks_edit'] ?? [];
    $opsi_benar_index_edit = $_POST['opsi_benar_edit'] ?? null;

    if (empty($teks_pertanyaan) || empty($tipe_pertanyaan) || empty($poin)) {
        echo "<div class='alert alert-danger'>Teks pertanyaan, tipe, dan poin tidak boleh kosong.</div>";
    } elseif ($poin <= 0) {
        echo "<div class='alert alert-danger'>Poin pertanyaan harus lebih dari 0.</div>";
    } else {
        mysqli_begin_transaction($conn);
        try {
            $update_q_sql = "UPDATE PertanyaanKuis SET 
                             teks_pertanyaan = '$teks_pertanyaan', 
                             tipe_pertanyaan = '$tipe_pertanyaan', 
                             poin = '$poin'
                             WHERE pertanyaan_id = '$pertanyaan_id' AND kuis_id = '$kuis_id'";

            if (!mysqli_query($conn, $update_q_sql)) {
                throw new Exception("Gagal memperbarui pertanyaan: " . mysqli_error($conn));
            }

            mysqli_query($conn, "DELETE FROM OpsiPilihanGanda WHERE pertanyaan_id = '$pertanyaan_id'");

            // Perbaikan: Cek nilai tipe_pertanyaan yang benar
            if ($tipe_pertanyaan === 'pilihan_ganda') {
                if (empty($opsi_teks_edit) || !is_array($opsi_teks_edit) || count($opsi_teks_edit) < 2) {
                    throw new Exception("Pertanyaan Pilihan Ganda harus memiliki minimal 2 opsi.");
                }
                if ($opsi_benar_index_edit === null || !isset($opsi_teks_edit[$opsi_benar_index_edit])) {
                    throw new Exception("Anda harus memilih satu opsi jawaban yang benar.");
                }

                foreach ($opsi_teks_edit as $index => $teks) {
                    $is_benar = ($index == $opsi_benar_index_edit) ? 1 : 0;
                    $insert_opsi_sql = "INSERT INTO OpsiPilihanGanda (pertanyaan_id, teks_opsi, is_benar) 
                                         VALUES ('$pertanyaan_id', '" . mysqli_real_escape_string($conn, $teks) . "', '$is_benar')";

                    if (!mysqli_query($conn, $insert_opsi_sql)) {
                        throw new Exception("Gagal menambahkan/memperbarui opsi: " . mysqli_error($conn));
                    }
                }
            }
            
            mysqli_commit($conn);
            echo "<script>alert('Pertanyaan berhasil diperbarui!');window.location.href='?page=quiz_questions&kuis_id=$kuis_id';</script>";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "<div class='alert alert-danger'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
            error_log("Edit Question Error: " . $e->getMessage());
        }
    }
}

// --- LOGIKA HAPUS PERTANYAAN ---
if (isset($_GET['action']) && $_GET['action'] == 'delete_question') {
    $pertanyaan_id_delete = mysqli_real_escape_string($conn, $_GET['pertanyaan_id']);
    
    $delete_q_sql = "DELETE FROM PertanyaanKuis WHERE pertanyaan_id = '$pertanyaan_id_delete' AND kuis_id = '$kuis_id'";
    
    if (mysqli_query($conn, $delete_q_sql)) {
        echo "<script>alert('Pertanyaan berhasil dihapus!');window.location.href='?page=quiz_questions&kuis_id=$kuis_id';</script>";
    } else {
        echo "<div class='alert alert-danger'>Gagal menghapus pertanyaan: " . mysqli_error($conn) . "</div>";
        error_log("Delete Question Error: " . mysqli_error($conn));
    }
}
?>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Detail Kuis: <?= htmlspecialchars($kuis_detail['judul_kuis']); ?></h3>
                <div class="box-tools pull-right">
                    <a href="?page=quizzes_overview" class="btn btn-default btn-sm">
                        <i class="fa fa-arrow-left"></i> Kembali ke Daftar Kuis
                    </a>
                </div>
            </div>
            <div class="box-body">
                <div class="callout callout-info">
                    <h4>Informasi Kuis</h4>
                    <p>
                        <strong>Judul:</strong> <?= htmlspecialchars($kuis_detail['judul_kuis']); ?><br>
                        <strong>Guru Pembuat:</strong> <?= htmlspecialchars($kuis_detail['nama_guru']); ?><br>
                        <strong>Deskripsi:</strong> <?= nl2br(htmlspecialchars($kuis_detail['deskripsi'] ?? 'Tidak ada deskripsi.')); ?><br>
                        <strong>Waktu Pelaksanaan:</strong> <?= date('d-m-Y H:i', strtotime($kuis_detail['waktu_mulai'])); ?> s/d <?= date('d-m-Y H:i', strtotime($kuis_detail['waktu_selesai'])); ?><br>
                        <strong>Durasi:</strong> <?= htmlspecialchars($kuis_detail['durasi_menit']); ?> menit
                    </p>
                </div>

                <h4>Daftar Pertanyaan</h4>
                <div class="mb-3">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addQuestionModal">
                        <i class="fa fa-plus"></i> Tambah Pertanyaan Baru
                    </button>
                </div>
                <div class="table-responsive">
                    <table id="data" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Pertanyaan</th>
                                <th>Tipe</th>
                                <th>Poin</th>
                                <th>Opsi Jawaban</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Ambil semua pertanyaan dan opsinya sekaligus
                            $questions_and_options = [];
                            $no = 1;
                            $sql_questions = "SELECT pertanyaan_id, teks_pertanyaan, tipe_pertanyaan, poin 
                                              FROM PertanyaanKuis 
                                              WHERE kuis_id = '" . mysqli_real_escape_string($conn, $kuis_id) . "'
                                              ORDER BY pertanyaan_id ASC";
                            $result_questions = mysqli_query($conn, $sql_questions);

                            if ($result_questions && mysqli_num_rows($result_questions) > 0) {
                                while ($pertanyaan = mysqli_fetch_assoc($result_questions)) {
                                    $pertanyaan_id = $pertanyaan['pertanyaan_id'];
                                    $questions_and_options[$pertanyaan_id] = $pertanyaan;
                                    $opsi_html = '';

                                    // Perbaikan: Cek nilai tipe_pertanyaan yang benar
                                    if ($pertanyaan['tipe_pertanyaan'] == 'pilihan_ganda') {
                                        $sql_opsi = "SELECT teks_opsi, is_benar FROM OpsiPilihanGanda WHERE pertanyaan_id = '" . $pertanyaan_id . "' ORDER BY opsi_id ASC";
                                        $result_opsi = mysqli_query($conn, $sql_opsi);
                                        $opsi_data = [];
                                        if ($result_opsi && mysqli_num_rows($result_opsi) > 0) {
                                            $opsi_html .= '<ul>';
                                            while ($opsi = mysqli_fetch_assoc($result_opsi)) {
                                                $opsi_data[] = $opsi;
                                                $opsi_html .= '<li>' . htmlspecialchars($opsi['teks_opsi']);
                                                if ($opsi['is_benar']) {
                                                    $opsi_html .= ' <span class="label label-success"><i class="fa fa-check"></i> Benar</span>';
                                                }
                                                $opsi_html .= '</li>';
                                            }
                                            $opsi_html .= '</ul>';
                                        } else {
                                            $opsi_html = '<span class="text-danger">Belum ada opsi</span>';
                                        }
                                        $questions_and_options[$pertanyaan_id]['opsi'] = $opsi_data;
                                    } else {
                                        $opsi_html = 'N/A (Isian)';
                                        $questions_and_options[$pertanyaan_id]['opsi'] = [];
                                    }
                            ?>
                                    <tr>
                                        <td style="vertical-align: top;"><?= $no++; ?></td>
                                        <td style="vertical-align: top;"><?= nl2br(htmlspecialchars($pertanyaan['teks_pertanyaan'])); ?></td>
                                        <td style="vertical-align: top;"><?= htmlspecialchars($pertanyaan['tipe_pertanyaan']); ?></td>
                                        <td style="vertical-align: top;"><?= htmlspecialchars($pertanyaan['poin']); ?></td>
                                        <td style="vertical-align: top;"><?= $opsi_html; ?></td>
                                        <td style="vertical-align: top;">
                                            <button type="button" class="btn btn-warning btn-xs edit-question-btn" 
                                                     data-toggle="modal" data-target="#editQuestionModal"
                                                     data-pertanyaan_id="<?= $pertanyaan_id; ?>"
                                                     title="Edit Pertanyaan">
                                                <i class="fa fa-edit"></i> Edit
                                            </button>
                                            <a href="?page=quiz_questions&action=delete_question&kuis_id=<?= $kuis_id; ?>&pertanyaan_id=<?= $pertanyaan['pertanyaan_id']; ?>" 
                                               onclick="return confirm('Apakah Anda yakin ingin menghapus pertanyaan ini dan semua opsi/jawaban siswanya?')" 
                                               class="btn btn-danger btn-xs" title="Hapus Pertanyaan">
                                                <i class="fa fa-trash"></i> Hapus
                                            </a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
        </div>
</div>

<div class="modal fade" id="addQuestionModal" tabindex="-1" role="dialog" aria-labelledby="addQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="addQuestionModalLabel">Tambah Pertanyaan Baru</h4>
            </div>
            <form method="POST" action="?page=quiz_questions&kuis_id=<?= $kuis_id; ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="teks_pertanyaan_add">Teks Pertanyaan</label>
                        <textarea class="form-control" id="teks_pertanyaan_add" name="teks_pertanyaan" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="tipe_pertanyaan_add">Tipe Pertanyaan</label>
                        <select class="form-control" id="tipe_pertanyaan_add" name="tipe_pertanyaan" required>
                            <option value="pilihan_ganda">Pilihan Ganda</option>
                            <option value="isian">Isian</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="poin_add">Poin Pertanyaan</label>
                        <input type="number" class="form-control" id="poin_add" name="poin" min="1" required>
                    </div>

                    <div id="opsi_pg_container_add" style="display:none;">
                        <h5>Opsi Pilihan Ganda:</h5>
                        <div id="opsi_list_add">
                            <div class="input-group mb-2">
                                <span class="input-group-addon"><input type="radio" name="opsi_benar" value="0"></span>
                                <input type="text" class="form-control" name="opsi_teks[]" placeholder="Teks Opsi 1" required>
                                <span class="input-group-btn"><button type="button" class="btn btn-danger remove-opsi" style="display:none;"><i class="fa fa-times"></i></button></span>
                            </div>
                            <div class="input-group mb-2">
                                <span class="input-group-addon"><input type="radio" name="opsi_benar" value="1"></span>
                                <input type="text" class="form-control" name="opsi_teks[]" placeholder="Teks Opsi 2" required>
                                <span class="input-group-btn"><button type="button" class="btn btn-danger remove-opsi" style="display:none;"><i class="fa fa-times"></i></button></span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-success btn-xs mt-2" id="add_opsi_btn_add"><i class="fa fa-plus"></i> Tambah Opsi</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="add_question" class="btn btn-primary">Simpan Pertanyaan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editQuestionModal" tabindex="-1" role="dialog" aria-labelledby="editQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="editQuestionModalLabel">Edit Pertanyaan</h4>
            </div>
            <form method="POST" action="?page=quiz_questions&kuis_id=<?= $kuis_id; ?>">
                <div class="modal-body">
                    <input type="hidden" name="pertanyaan_id" id="pertanyaan_id_edit">
                    <div class="form-group">
                        <label for="teks_pertanyaan_edit">Teks Pertanyaan</label>
                        <textarea class="form-control" id="teks_pertanyaan_edit" name="teks_pertanyaan_edit" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="tipe_pertanyaan_edit">Tipe Pertanyaan</label>
                        <select class="form-control" id="tipe_pertanyaan_edit" name="tipe_pertanyaan_edit" required>
                            <option value="pilihan_ganda">Pilihan Ganda</option>
                            <option value="isian">Isian</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="poin_edit">Poin Pertanyaan</label>
                        <input type="number" class="form-control" id="poin_edit" name="poin_edit" min="1" required>
                    </div>

                    <div id="opsi_pg_container_edit" style="display:none;">
                        <h5>Opsi Pilihan Ganda:</h5>
                        <div id="opsi_list_edit">
                            </div>
                        <button type="button" class="btn btn-success btn-xs mt-2" id="add_opsi_btn_edit"><i class="fa fa-plus"></i> Tambah Opsi</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_question" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const questionData = <?= json_encode($questions_and_options); ?>;
    
    $('#data').DataTable({
        "paging": true, "lengthChange": true, "searching": true,
        "ordering": true, "info": true, "autoWidth": true, "scrollX": true,
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json"
        }
    });
    
    $('#data tbody td').css('vertical-align', 'top');

    // --- Logika untuk Modal Tambah Pertanyaan (Add) ---
    function toggleOpsiPGAdd() {
        // Perbaikan: Cek nilai `pilihan_ganda` yang benar
        if ($('#tipe_pertanyaan_add').val() === 'pilihan_ganda') {
            $('#opsi_pg_container_add').show();
            $('#opsi_list_add input[type="text"]').prop('required', true);
            $('#opsi_list_add .input-group:lt(2) input[type="text"]').prop('required', true);
        } else {
            $('#opsi_pg_container_add').hide();
            $('#opsi_list_add input[type="text"]').prop('required', false);
            $('#opsi_list_add input[type="radio"]').prop('checked', false);
        }
    }
    $('#tipe_pertanyaan_add').on('change', toggleOpsiPGAdd);
    toggleOpsiPGAdd();

    let opsiCountAdd = $('#opsi_list_add .input-group').length;
    $('#add_opsi_btn_add').on('click', function() {
        const newOpsi = `
            <div class="input-group mb-2">
                <span class="input-group-addon"><input type="radio" name="opsi_benar" value="${opsiCountAdd}"></span>
                <input type="text" class="form-control" name="opsi_teks[]" placeholder="Teks Opsi ${opsiCountAdd + 1}" required>
                <span class="input-group-btn"><button type="button" class="btn btn-danger remove-opsi"><i class="fa fa-times"></i></button></span>
            </div>
        `;
        $('#opsi_list_add').append(newOpsi);
        opsiCountAdd++;
        updateRemoveButtonsAdd();
        updateOpsiIndicesAdd();
    });

    $('#opsi_list_add').on('click', '.remove-opsi', function() {
        if ($('#opsi_list_add .input-group').length > 2) {
            $(this).closest('.input-group').remove();
            opsiCountAdd--;
            updateOpsiIndicesAdd();
            updateRemoveButtonsAdd();
        } else {
            alert('Pertanyaan pilihan ganda harus memiliki minimal 2 opsi.');
        }
    });

    function updateOpsiIndicesAdd() {
        $('#opsi_list_add .input-group').each(function(index) {
            $(this).find('input[type="radio"]').val(index);
            $(this).find('input[type="text"]').attr('placeholder', `Teks Opsi ${index + 1}`);
        });
    }

    function updateRemoveButtonsAdd() {
        if ($('#opsi_list_add .input-group').length > 2) {
            $('#opsi_list_add .remove-opsi').show();
        } else {
            $('#opsi_list_add .remove-opsi').hide();
        }
    }
    updateRemoveButtonsAdd();


    // --- Logika untuk Modal Edit Pertanyaan (Edit) ---
    function toggleOpsiPGEdit() {
        // Perbaikan: Cek nilai `pilihan_ganda` yang benar
        if ($('#tipe_pertanyaan_edit').val() === 'pilihan_ganda') {
            $('#opsi_pg_container_edit').show();
            $('#opsi_list_edit input[type="text"]').prop('required', true);
            if ($('#opsi_list_edit .input-group').length === 0) {
                 for (let i = 0; i < 2; i++) {
                    $('#add_opsi_btn_edit').click();
                }
            }
        } else {
            $('#opsi_pg_container_edit').hide();
            $('#opsi_list_edit').empty();
            $('#opsi_list_edit input[type="text"]').prop('required', false);
        }
    }
    
    $('#tipe_pertanyaan_edit').on('change', function() {
        toggleOpsiPGEdit();
        // Perbaikan: Cek nilai `isian` yang benar
        if ($(this).val() === 'isian') {
            $('#opsi_list_edit input[type="radio"]').prop('checked', false);
        }
    });

    let opsiCountEdit = 0;
    $('#add_opsi_btn_edit').on('click', function() {
        const newOpsi = `
            <div class="input-group mb-2">
                <span class="input-group-addon"><input type="radio" name="opsi_benar_edit" value="${opsiCountEdit}"></span>
                <input type="text" class="form-control" name="opsi_teks_edit[]" placeholder="Teks Opsi ${opsiCountEdit + 1}" required>
                <span class="input-group-btn"><button type="button" class="btn btn-danger remove-opsi-edit"><i class="fa fa-times"></i></button></span>
            </div>
        `;
        $('#opsi_list_edit').append(newOpsi);
        opsiCountEdit++;
        updateRemoveButtonsEdit();
        updateOpsiIndicesEdit();
    });

    $('#opsi_list_edit').on('click', '.remove-opsi-edit', function() {
        if ($('#opsi_list_edit .input-group').length > 2) {
            $(this).closest('.input-group').remove();
            opsiCountEdit--;
            updateOpsiIndicesEdit();
            updateRemoveButtonsEdit();
        } else {
            alert('Pertanyaan pilihan ganda harus memiliki minimal 2 opsi.');
        }
    });

    function updateOpsiIndicesEdit() {
        $('#opsi_list_edit .input-group').each(function(index) {
            $(this).find('input[type="radio"]').val(index);
            $(this).find('input[type="text"]').attr('placeholder', `Teks Opsi ${index + 1}`);
        });
    }
    
    function updateRemoveButtonsEdit() {
        if ($('#opsi_list_edit .input-group').length > 2) {
            $('#opsi_list_edit .remove-opsi-edit').show();
        } else {
            $('#opsi_list_edit .remove-opsi-edit').hide();
        }
    }

    $('#editQuestionModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var pertanyaan_id = button.data('pertanyaan_id');
        const question = questionData[pertanyaan_id];

        var modal = $(this);
        modal.find('#pertanyaan_id_edit').val(pertanyaan_id);
        modal.find('#teks_pertanyaan_edit').val(question.teks_pertanyaan);
        // Perbaikan: Set nilai `pilihan_ganda` atau `isian` yang benar
        modal.find('#tipe_pertanyaan_edit').val(question.tipe_pertanyaan);
        modal.find('#poin_edit').val(question.poin);
        
        // Perbaikan: Cek nilai `pilihan_ganda` yang benar
        if (question.tipe_pertanyaan === 'pilihan_ganda') {
            $('#opsi_pg_container_edit').show();
            let opsiHtml = '';
            opsiCountEdit = 0;
            question.opsi.forEach(function(opsi, index) {
                const isChecked = opsi.is_benar == 1 ? 'checked' : '';
                const removeBtnStyle = (question.opsi.length > 2) ? '' : 'style="display:none;"';
                opsiHtml += `
                    <div class="input-group mb-2">
                        <span class="input-group-addon"><input type="radio" name="opsi_benar_edit" value="${opsiCountEdit}" ${isChecked}></span>
                        <input type="text" class="form-control" name="opsi_teks_edit[]" value="${opsi.teks_opsi}" placeholder="Teks Opsi ${opsiCountEdit + 1}" required>
                        <span class="input-group-btn"><button type="button" class="btn btn-danger remove-opsi-edit" ${removeBtnStyle}><i class="fa fa-times"></i></button></span>
                    </div>
                `;
                 opsiCountEdit++;
            });
            $('#opsi_list_edit').html(opsiHtml);
            updateRemoveButtonsEdit();
        } else {
            $('#opsi_pg_container_edit').hide();
            $('#opsi_list_edit').empty();
        }
    });
});
</script>