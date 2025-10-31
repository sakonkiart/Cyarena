# 1. ใช้ Base Image ที่มี PHP 8.2 และ Apache
FROM php:8.2-apache

# 2. ติดตั้งส่วนขยาย (extension) "mysqli"
# นี่คือส่วนที่สำคัญที่สุด เพื่อให้ PHP คุยกับ MySQL ได้
RUN docker-php-ext-install mysqli

# 3. เปิดใช้งาน mod_rewrite (สำหรับ URL สวยๆ ในอนาคต)
RUN a2enmod rewrite

# 4. คัดลอกไฟล์โปรเจกต์ (index.php, db_connect.php ฯลฯ) 
# เข้าไปในเว็บเซิร์ฟเวอร์
COPY . /var/www/html/