<?php
// Pastikan koneksi database sudah tersedia dari inc/koneksi.php
// Pastikan session_start() sudah aktif di index.php (INI PENTING SEKALI!)

if (function_exists('get_user_role')) {
    $user_role = get_user_role();
    if ($user_role !== 'siswa') {
        echo "<div class='alert alert-danger'>Anda tidak memiliki akses untuk melihat halaman ini.</div>";
        exit();
    }
} else {
    echo "<div class='alert alert-danger'>Error: Fungsi get_user_role() tidak ditemukan. Pastikan file fungsi.php ter-include.</div>";
    exit();
}

$current_user_id = $_SESSION['user_id'] ?? null;
$siswa_id_loggedin = null;

if ($current_user_id) {
    $query_siswa_id = mysqli_query($conn, "SELECT siswa_id FROM Siswa WHERE user_id = '$current_user_id'");
    if ($row_siswa_id = mysqli_fetch_assoc($query_siswa_id)) {
        $siswa_id_loggedin = $row_siswa_id['siswa_id'];
    }
}

$kuis_id = $_GET['kuis_id'] ?? null;
if (!$kuis_id || !is_numeric($kuis_id)) {
    echo "<div class='alert alert-danger'>ID Kuis tidak valid atau tidak ditemukan.</div>";
    exit();
}

$sql_kuis_detail = "SELECT K.kuis_id, K.judul_kuis, K.deskripsi, K.durasi_menit,
                           U.nama_lengkap AS nama_guru
                    FROM Kuis K
                    JOIN Guru G ON K.guru_id = G.guru_id
                    JOIN Users U ON G.user_id = U.user_id
                    WHERE K.kuis_id = '" . mysqli_real_escape_string($conn, $kuis_id) . "'";

$result_kuis_detail = mysqli_query($conn, $sql_kuis_detail);
$kuis_detail = null;

if ($result_kuis_detail && mysqli_num_rows($result_kuis_detail) > 0) {
    $kuis_detail = mysqli_fetch_assoc($result_kuis_detail);
} else {
    echo "<div class='alert alert-danger'>Kuis tidak ditemukan.</div>";
    exit();
}

// Cek apakah siswa sudah mengerjakan kuis ini (berdasarkan tabel HasilKuis)
$sql_check_score = "SELECT total_skor FROM HasilKuis WHERE siswa_id = '$siswa_id_loggedin' AND kuis_id = '$kuis_id'";
$result_check_score = mysqli_query($conn, $sql_check_score);

if ($result_check_score && mysqli_num_rows($result_check_score) > 0) {
    $hasil_kuis = mysqli_fetch_assoc($result_check_score);
    echo "<div class='alert alert-info'>Anda sudah menyelesaikan kuis ini.</div>";
    echo "<h3>Skor Anda: <span class='label label-primary'>" . htmlspecialchars($hasil_kuis['total_skor']) . "</span></h3>";
    echo "<p>Catatan: Skor ini hanya mencakup pertanyaan pilihan ganda. Jawaban isian Anda akan dinilai secara manual oleh guru.</p>";
    echo "<a href='?page=siswa_quizzes' class='btn btn-primary'>Kembali ke Daftar Kuis</a>";
    exit();
}

// --- LOGIKA PENYIMPANAN JAWABAN SISWA DAN PENILAIAN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    mysqli_begin_transaction($conn);
    try {
        $jawaban_siswa_isian = $_POST['jawaban_isian'] ?? [];
        $jawaban_siswa_pg = $_POST['jawaban_pg'] ?? [];
        $total_skor = 0;
        
        // Dapatkan semua pertanyaan untuk kuis ini beserta poinnya
        $sql_all_questions = "SELECT pertanyaan_id, tipe_pertanyaan, poin FROM PertanyaanKuis WHERE kuis_id = '$kuis_id'";
        $result_all_questions = mysqli_query($conn, $sql_all_questions);
        $questions_data = [];
        while ($row = mysqli_fetch_assoc($result_all_questions)) {
            $questions_data[$row['pertanyaan_id']] = $row;
        }

        // Loop melalui semua pertanyaan dan simpan jawaban
        foreach ($questions_data as $pertanyaan_id => $pertanyaan) {
            $pertanyaan_id_esc = mysqli_real_escape_string($conn, $pertanyaan_id);
            $kuis_id_esc = mysqli_real_escape_string($conn, $kuis_id);

            if ($pertanyaan['tipe_pertanyaan'] == 'pilihan_ganda' && isset($jawaban_siswa_pg[$pertanyaan_id])) {
                $jawaban_opsi_id = $jawaban_siswa_pg[$pertanyaan_id];
                $opsi_id_esc = mysqli_real_escape_string($conn, $jawaban_opsi_id);
                
                // Simpan jawaban siswa
                $sql_insert_jawaban_pg = "INSERT INTO JawabanSiswa (siswa_id, kuis_id, pertanyaan_id, opsi_id, tanggal_submit)
                                          VALUES ('$siswa_id_loggedin', '$kuis_id_esc', '$pertanyaan_id_esc', '$opsi_id_esc', NOW())";
                if (!mysqli_query($conn, $sql_insert_jawaban_pg)) {
                    throw new Exception("Gagal menyimpan jawaban PG untuk pertanyaan ID $pertanyaan_id: " . mysqli_error($conn));
                }
                
                // Cek apakah jawaban pilihan ganda benar
                $sql_check_correct = "SELECT is_benar FROM OpsiPilihanGanda WHERE opsi_id = '$opsi_id_esc'";
                $result_check_correct = mysqli_query($conn, $sql_check_correct);
                if ($result_check_correct && $row = mysqli_fetch_assoc($result_check_correct)) {
                    if ($row['is_benar'] == 1) {
                        $total_skor += $pertanyaan['poin'];
                    }
                }
            } elseif ($pertanyaan['tipe_pertanyaan'] == 'isian' && isset($jawaban_siswa_isian[$pertanyaan_id])) {
                $jawaban_isian = $jawaban_siswa_isian[$pertanyaan_id];
                $jawaban_text = mysqli_real_escape_string($conn, $jawaban_isian);
                
                // Simpan jawaban siswa
                $sql_insert_jawaban_isian = "INSERT INTO JawabanSiswa (siswa_id, kuis_id, pertanyaan_id, jawaban_isian, tanggal_submit)
                                             VALUES ('$siswa_id_loggedin', '$kuis_id_esc', '$pertanyaan_id_esc', '$jawaban_text', NOW())";
                if (!mysqli_query($conn, $sql_insert_jawaban_isian)) {
                    throw new Exception("Gagal menyimpan jawaban isian untuk pertanyaan ID $pertanyaan_id: " . mysqli_error($conn));
                }
                // Catatan: Skor untuk isian akan dinilai manual oleh guru
            }
        }
        
        // Simpan skor total ke tabel HasilKuis
        $sql_insert_hasil = "INSERT INTO HasilKuis (kuis_id, siswa_id, total_skor, tanggal_selesai)
                             VALUES ('$kuis_id_esc', '$siswa_id_loggedin', '$total_skor', NOW())";
        if (!mysqli_query($conn, $sql_insert_hasil)) {
            throw new Exception("Gagal menyimpan hasil kuis: " . mysqli_error($conn));
        }

        mysqli_commit($conn);
        echo "<script>alert('Jawaban berhasil dikirim! Kuis telah selesai. Skor Anda adalah: $total_skor. Skor isian akan ditambahkan setelah dinilai oleh guru.');window.location.href='?page=siswa_quizzes';</script>";
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<div class='alert alert-danger'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
        error_log("Quiz Submission Error: " . $e->getMessage());
    }
}
?>

<style>
    .question-list-container {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-bottom: 20px;
    }
    .question-list-item {
        width: 40px;
        height: 40px;
        border: 1px solid #ddd;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #333;
        font-weight: bold;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .question-list-item:hover, .question-list-item.active {
        background-color: #f0f0f0;
    }
    .question-list-item.answered {
        background-color: #5cb85c;
        color: white;
        border-color: #5cb85c;
    }
    .question-list-item.answered:hover {
        background-color: #4cae4c;
    }
    .tab-content .tab-pane {
        animation: fadeIn 0.5s;
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
</style>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Kuis: <?= htmlspecialchars($kuis_detail['judul_kuis']); ?></h3>
                <div class="box-tools pull-right">
                    <h3 class="box-title">Waktu Tersisa: <span id="timer" class="text-red"></span></h3>
                </div>
            </div>
            <div class="box-body">
                <div class="callout callout-info">
                    <h4>Informasi Kuis</h4>
                    <p>
                        <strong>Dibuat oleh:</strong> <?= htmlspecialchars($kuis_detail['nama_guru']); ?><br>
                        <strong>Deskripsi:</strong> <?= nl2br(htmlspecialchars($kuis_detail['deskripsi'] ?? 'Tidak ada deskripsi.')); ?><br>
                        <strong>Durasi:</strong> <?= htmlspecialchars($kuis_detail['durasi_menit']); ?> menit
                    </p>
                </div>
                
                <form method="POST" action="?page=take_quiz&kuis_id=<?= $kuis_id; ?>" id="quiz-form">
                    <input type="hidden" name="submit_quiz" value="1">
                    <?php
                    $sql_questions = "SELECT pertanyaan_id, teks_pertanyaan, tipe_pertanyaan
                                      FROM PertanyaanKuis 
                                      WHERE kuis_id = '" . mysqli_real_escape_string($conn, $kuis_id) . "'
                                      ORDER BY pertanyaan_id ASC";
                    $result_questions = mysqli_query($conn, $sql_questions);
                    
                    $questions = [];
                    if ($result_questions && mysqli_num_rows($result_questions) > 0) {
                        while ($pertanyaan = mysqli_fetch_assoc($result_questions)) {
                            $questions[] = $pertanyaan;
                        }
                    } else {
                        echo "<div class='alert alert-warning'>Belum ada pertanyaan untuk kuis ini.</div>";
                    }

                    if (!empty($questions)) :
                    ?>
                    
                    <div class="question-list-container">
                        <?php foreach ($questions as $index => $pertanyaan) : ?>
                            <a href="#question_<?= $index + 1; ?>" class="question-list-item <?= $index == 0 ? 'active' : ''; ?>" data-toggle="tab" data-question-id="<?= $pertanyaan['pertanyaan_id']; ?>">
                                <?= $index + 1; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="tab-content">
                        <?php foreach ($questions as $index => $pertanyaan) : ?>
                            <div class="tab-pane fade in <?= $index == 0 ? 'active' : ''; ?>" id="question_<?= $index + 1; ?>">
                                <div class="well">
                                    <p><strong>Soal <?= $index + 1; ?>.</strong></p>
                                    <p><?= nl2br(htmlspecialchars($pertanyaan['teks_pertanyaan'])); ?></p>
                                    
                                    <?php if ($pertanyaan['tipe_pertanyaan'] == 'pilihan_ganda') : ?>
                                        <div class="form-group">
                                            <?php
                                            $sql_opsi = "SELECT opsi_id, teks_opsi FROM OpsiPilihanGanda WHERE pertanyaan_id = '" . $pertanyaan['pertanyaan_id'] . "' ORDER BY opsi_id ASC";
                                            $result_opsi = mysqli_query($conn, $sql_opsi);
                                            if ($result_opsi && mysqli_num_rows($result_opsi) > 0) :
                                                while ($opsi = mysqli_fetch_assoc($result_opsi)) :
                                            ?>
                                                    <div class="radio">
                                                        <label>
                                                            <input type="radio" name="jawaban_pg[<?= $pertanyaan['pertanyaan_id']; ?>]" value="<?= $opsi['opsi_id']; ?>">
                                                            <?= htmlspecialchars($opsi['teks_opsi']); ?>
                                                        </label>
                                                    </div>
                                            <?php
                                                endwhile;
                                            endif;
                                            ?>
                                        </div>
                                    <?php else : // isian ?>
                                        <div class="form-group">
                                            <label for="jawaban_isian_<?= $pertanyaan['pertanyaan_id']; ?>">Jawaban Anda:</label>
                                            <textarea class="form-control" id="jawaban_isian_<?= $pertanyaan['pertanyaan_id']; ?>" name="jawaban_isian[<?= $pertanyaan['pertanyaan_id']; ?>]" rows="3" placeholder="Masukkan jawaban Anda di sini"></textarea>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-3">
                                    <?php if ($index > 0) : ?>
                                        <button type="button" class="btn btn-primary prev-btn">Sebelumnya</button>
                                    <?php endif; ?>
                                    <?php if ($index < count($questions) - 1) : ?>
                                        <button type="button" class="btn btn-primary next-btn pull-right">Selanjutnya</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <hr>
                    <button type="button" class="btn btn-success btn-lg" id="finish-quiz-btn" style="display:none;" disabled>
                        <i class="fa fa-check"></i> Selesai
                    </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="finishQuizModal" tabindex="-1" role="dialog" aria-labelledby="finishQuizModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="finishQuizModalLabel">Konfirmasi Selesai Kuis</h4>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin menyelesaikan kuis ini? Jawaban tidak bisa diubah setelah dikirim.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" id="confirm-submit-btn">Setuju</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let durasiMenit = <?= $kuis_detail['durasi_menit']; ?>;
    let waktuSelesai = new Date().getTime() + durasiMenit * 60 * 1000;
    const totalQuestions = <?= count($questions); ?>;
    let answeredQuestions = new Set();
    
    function updateQuestionStatus() {
        answeredQuestions.clear();
        
        $('input[type="radio"]:checked').each(function() {
            const name = $(this).attr('name');
            const questionId = name.match(/\[(\d+)\]/)[1];
            answeredQuestions.add(questionId);
        });

        $('textarea').each(function() {
            const name = $(this).attr('name');
            const questionId = name.match(/\[(\d+)\]/)[1];
            if ($(this).val().trim() !== '') {
                answeredQuestions.add(questionId);
            }
        });

        $('.question-list-item').each(function() {
            const questionId = $(this).data('question-id').toString();
            if (answeredQuestions.has(questionId)) {
                $(this).addClass('answered');
            } else {
                $(this).removeClass('answered');
            }
        });
        
        if (answeredQuestions.size === totalQuestions) {
            $('#finish-quiz-btn').show().prop('disabled', false);
        } else {
            $('#finish-quiz-btn').hide().prop('disabled', true);
        }
    }

    $('#quiz-form').on('change keyup', 'input[type="radio"], textarea', function() {
        updateQuestionStatus();
    });
    
    $('a[data-toggle="tab"]').on('shown.bs.tab', function() {
        updateQuestionStatus();
    });

    updateQuestionStatus();

    let countdown = setInterval(function() {
        let sekarang = new Date().getTime();
        let jarak = waktuSelesai - sekarang;

        if (jarak < 0) {
            clearInterval(countdown);
            $('#timer').html("WAKTU HABIS!");
            alert("Waktu pengerjaan kuis telah habis. Jawaban Anda akan dikirim secara otomatis.");
            
            $('#quiz-form input, #quiz-form textarea, #finish-quiz-btn, #confirm-submit-btn').prop('disabled', true);
            $('#quiz-form').submit();
        } else {
            let menit = Math.floor((jarak % (1000 * 60 * 60)) / (1000 * 60));
            let detik = Math.floor((jarak % (1000 * 60)) / 1000);
            $('#timer').html(menit + "m " + detik + "s");
        }
    }, 1000);

    $('.next-btn').on('click', function() {
        const currentTab = $(this).closest('.tab-pane');
        const nextTab = currentTab.next('.tab-pane');
        if (nextTab.length) {
            $('a[href="#' + nextTab.attr('id') + '"]').tab('show');
        }
    });

    $('.prev-btn').on('click', function() {
        const currentTab = $(this).closest('.tab-pane');
        const prevTab = currentTab.prev('.tab-pane');
        if (prevTab.length) {
            $('a[href="#' + prevTab.attr('id') + '"]').tab('show');
        }
    });
    
    $('#finish-quiz-btn').on('click', function() {
        $('#finishQuizModal').modal('show');
    });

    $('#confirm-submit-btn').on('click', function() {
        $('#finish-quiz-btn').prop('disabled', true).text('Mengirim...');
        $('#finishQuizModal').modal('hide');
        $('#quiz-form').submit();
    });
});
</script>