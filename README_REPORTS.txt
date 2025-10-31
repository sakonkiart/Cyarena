# CY Arena - Reports Module (10 SQL Views)
- โฟลเดอร์นี้รวมไฟล์ที่คุณอัปโหลด + โมดูลรายงาน (report.php, includes/table_helpers.php, reports/*)
- เข้าถึงที่ /report.php หลังจากตั้งค่า VIEW ทั้ง 10 อันในฐานข้อมูล `cy_arena_db`

Security notes:
- อินพุตจาก $_GET ถูก bind ผ่าน prepared statement แล้วในจุดที่ใช้กรอง
- หากต้องการจำกัดสิทธิ์ ให้เพิ่มเช็ค login/role ก่อนแสดงรายงาน
