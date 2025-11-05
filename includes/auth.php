<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ฟังก์ชันตรวจว่ามีการเข้าสู่ระบบ */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

/* ฟังก์ชันตรวจว่าต้องเป็น super_admin */
function require_super_admin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
        http_response_code(403);
        echo "403 Forbidden – ต้องเป็น super_admin";
        exit;
    }
}

/* >>> ADD: ฟังก์ชันบังคับ scope บริษัทสำหรับ admin/employee <<< */
function require_company_scope() {
    if (!isset($_SESSION['role'])) return; // เงียบไว้ถ้าไม่ทราบ role (ปล่อยหน้า public)
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'employee') {
        if (empty($_SESSION['company_id'])) {
            http_response_code(403);
            echo "403 Forbidden – ยังไม่ได้กำหนดบริษัทให้ผู้ใช้งานนี้";
            exit;
        }
    }
}
/* >>> END ADD <<< */
