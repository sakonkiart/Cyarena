<?php
// --- 🚀 Aiven & Render DB Connection File ---

// 1. อ่านค่าเชื่อมต่อจาก Environment Variables ของ Render
// (นี่คือค่า 5 ตัวที่เราตั้งใน Dashboard ของ Render)
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');

// 2. เริ่มต้นการเชื่อมต่อ
$conn = mysqli_init();
if (!$conn) {
    die("❌ Error: mysqli_init failed");
}

// 3. ✅ [สำคัญมาก] สั่งให้ MySQLi ใช้ SSL (Aiven บังคับ)
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

// 4. พยายามเชื่อมต่อจริง (โดยใช้ SSL)
$isConnected = mysqli_real_connect(
    $conn,
    $host,
    $user,
    $pass,
    $db,
    (int)$port, // แปลง Port เป็นตัวเลข
    NULL,
    MYSQLI_CLIENT_SSL // บังคับใช้ SSL
);

// 5. ตรวจสอบผลลัพธ์
if (!$isConnected) {
    // ถ้าเชื่อมต่อล้มเหลว ให้หยุดทำงานและแสดง Error
    die("❌ Connection Failed: " . mysqli_connect_error());
}

// ถ้ามาถึงตรงนี้ได้ แปลว่าเชื่อมต่อสำเร็จ
// ไฟล์อื่นๆ ในโปรเจกต์ (เช่น index.php) จะ include ไฟล์นี้
// และจะได้ตัวแปร $conn ไปใช้งานต่อ
?>