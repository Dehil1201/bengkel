<?php
function get_user_role() {
    // Implementasikan fungsi ini sesuai dengan struktur otorisasi Anda
    return $_SESSION['role'] ?? null;
}
?>