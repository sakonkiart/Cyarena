<?php
session_start();

// ถ้าล็อกอินอยู่แล้ว -> ไปหน้า dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
} else {
    // ถ้ายังไม่ล็อกอิน -> ไปหน้า login
    header("Location: login.php");
    exit;
}
?>
