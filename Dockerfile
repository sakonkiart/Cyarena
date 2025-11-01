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

# 5. [แก้ไขสิทธิ์สำคัญ] ตั้งค่าความเป็นเจ้าของ (Owner) และสิทธิ์การเขียน (Permission)
# Base Image 'php:8.2-apache' รัน Service ภายใต้ User:Group ที่ชื่อ 'www-data:www-data'
# เราต้องเปลี่ยนเจ้าของของ Folder 'uploads' (ซึ่งรวมถึง avatars และ payment_slips)
# ให้เป็น 'www-data' เพื่อให้ PHP มีสิทธิ์เขียนไฟล์ลงใน Folder ได้
RUN chown -R www-data:www-data /var/www/html/uploads
RUN chmod -R 755 /var/www/html/uploads
