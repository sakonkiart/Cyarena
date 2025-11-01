<?php
// เปิด error แบบโยน Exception (อ่านง่ายเวลา debug)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
@$conn->query("SET time_zone = '+07:00'");

function db_connect() {
    $isCloud = getenv('DB_HOST') && getenv('DB_USER') && getenv('DB_NAME');

    // 💡 FIX: กำหนดชื่อฐานข้อมูลที่คุณใช้งานจริง
    $TARGET_DB_NAME = 'defaultdb'; // <<< ชื่อฐานข้อมูลจริงของคุณ

    if ($isCloud) {
        // ====== Cloud (Aiven/Render) with SSL ======
        $host = getenv('DB_HOST');
        $port = (int)(getenv('DB_PORT') ?: 3306);
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');
        
        // **ใช้ $TARGET_DB_NAME แทน getenv('DB_NAME')** เพื่อแก้ปัญหา Aiven/Render defaultdb
        $db = $TARGET_DB_NAME; 
        
        $ca_content = getenv('DB_CA_CERT');

        if (!$ca_content) {
            throw new RuntimeException('Missing DB_CA_CERT environment variable');
        }

        // โค้ดส่วนจัดการ CA (ถูกต้องแล้ว)
        if (strpos($ca_content, 'BEGIN CERTIFICATE') === false) {
            $decoded = base64_decode($ca_content, true);
            if ($decoded !== false) {
                $ca_content = $decoded;
            }
        }
        $ca_content = str_replace(["\\n", "\r\n"], ["\n", "\n"], $ca_content);

        $tmp_ca = tempnam(sys_get_temp_dir(), 'ca_');
        file_put_contents($tmp_ca, $ca_content);
        register_shutdown_function(function() use ($tmp_ca) {
            if (is_file($tmp_ca)) @unlink($tmp_ca);
        });

        $conn = mysqli_init();
        if (!$conn) throw new RuntimeException('mysqli_init failed');

        if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
            mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
        }

        mysqli_ssl_set($conn, null, null, $tmp_ca, null, null);

        if (!@mysqli_real_connect(
            $conn,
            $host,
            $user,
            $pass,
            $db, // 💡 ใช้ชื่อฐานข้อมูลที่ถูกต้องตรงนี้
            $port,
            null,
            MYSQLI_CLIENT_SSL
        )) {
            // โค้ดที่อาจทำให้เกิด HTTP 500
            throw new RuntimeException('Cloud DB connect failed: '.mysqli_connect_error());
        }
    } else {
        // ====== Local (XAMPP) ======
        $host = '127.0.0.1'; // หรือ 'localhost'
        $user = 'root';
        $pass = '';
        $db   = 'defaultdb'; // ✅ FIX 1: เพิ่มเครื่องหมาย quotes
        $port = 3306;         

        $conn = mysqli_init();
        if (!$conn) throw new RuntimeException('mysqli_init failed');

        if (!@mysqli_real_connect($conn, $host, $user, $pass, $db, $port)) {
            throw new RuntimeException('Local DB connect failed: '.mysqli_connect_error());
        }
    }

    mysqli_set_charset($conn, 'utf8mb4');

    return $conn;
}

try {
    $conn = db_connect();
} catch (Throwable $e) {
    http_response_code(500);
    error_log("DB CONNECTION FAILED: " . $e->getMessage()); 
    
    // ✅ FIX 2: แสดง Error แบบ User-friendly
    if (getenv('RENDER') || getenv('DB_HOST')) {
        // Production: แสดงข้อความทั่วไป
        die('Database connection error. Please contact support.');
    } else {
        // Development: แสดง Error detail
        die('DB CONNECTION FAILED: ' . htmlspecialchars($e->getMessage()));
    }
}
