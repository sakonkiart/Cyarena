-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 31, 2025 at 06:42 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30





/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cy_arena_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_booking`
--

CREATE TABLE `tbl_booking` (
  `BookingID` int(11) NOT NULL AUTO_INCREMENT, -- <<< แก้ไข: เพิ่ม AUTO_INCREMENT
  `CustomerID` int(11) NOT NULL,
  `VenueID` int(11) NOT NULL,
  `BookingStatusID` int(11) NOT NULL,
  `PaymentStatusID` int(11) NOT NULL,
  `PromotionID` int(11) DEFAULT NULL,
  `EmployeeID` int(11) DEFAULT NULL,
  `BookingDate` datetime NOT NULL DEFAULT current_timestamp(),
  `StartTime` datetime NOT NULL,
  `EndTime` datetime NOT NULL,
  `HoursBooked` decimal(4,2) NOT NULL,
  `TotalPrice` decimal(10,2) NOT NULL,
  `Discount` decimal(10,2) DEFAULT 0.00,
  `NetPrice` decimal(10,2) NOT NULL,
  `PaymentMethod` varchar(50) DEFAULT NULL,
  `Notes` text DEFAULT NULL, -- <<< แก้ไข: เพิ่มเครื่องหมายคอมม่า (,)
  PRIMARY KEY (`BookingID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Dumping data for table `tbl_booking`
--

INSERT INTO `tbl_booking` (`BookingID`, `CustomerID`, `VenueID`, `BookingStatusID`, `PaymentStatusID`, `PromotionID`, `EmployeeID`, `BookingDate`, `StartTime`, `EndTime`, `HoursBooked`, `TotalPrice`, `Discount`, `NetPrice`, `PaymentMethod`, `Notes`) VALUES
(66, 1, 13, 1, 1, NULL, 1, '2025-10-30 07:17:30', '2025-10-31 08:30:00', '2025-10-31 09:30:00', 1.00, 350.00, 0.00, 350.00, NULL, NULL),
(67, 0, 13, 5, 2, NULL, 1, '2025-10-30 07:49:55', '2025-11-01 08:00:00', '2025-11-01 09:00:00', 1.00, 350.00, 0.00, 350.00, NULL, NULL),
(68, 0, 13, 5, 2, NULL, 1, '2025-10-30 08:03:28', '2025-10-31 10:30:00', '2025-10-31 11:30:00', 1.00, 350.00, 0.00, 350.00, NULL, NULL),
(69, 1, 13, 1, 1, NULL, NULL, '2025-10-30 08:33:42', '2025-10-30 10:00:00', '2025-10-30 11:00:00', 1.00, 350.00, 0.00, 350.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_booking_status`
--

CREATE TABLE `tbl_booking_status` (
  `BookingStatusID` int(11) NOT NULL,
  `StatusName` varchar(100) NOT NULL,
  PRIMARY KEY (`BookingStatusID`) -- <<< แก้ไข: เพิ่ม PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_booking_status`
--
TRUNCATE TABLE `tbl_booking_status`;


INSERT INTO `tbl_booking_status` (`BookingStatusID`, `StatusName`) VALUES
(4, 'ยกเลิกโดยระบบ'),
(3, 'ยกเลิกโดยลูกค้า'),
(2, 'ยืนยันแล้ว'),
(1, 'รอยืนยัน'),
(5, 'เข้าใช้บริการแล้ว');


-- --------------------------------------------------------

--
-- Table structure for table `tbl_customer`
--

CREATE TABLE `tbl_customer` (
  `CustomerID` int(11) NOT NULL AUTO_INCREMENT, -- <<< แก้ไข: เพิ่ม AUTO_INCREMENT
  `FirstName` varchar(255) NOT NULL,
  `LastName` varchar(255) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Phone` varchar(20) NOT NULL,
  `AvatarPath` varchar(255) DEFAULT NULL,
  `Username` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Status` varchar(20) NOT NULL DEFAULT 'active',
  `DateCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`CustomerID`) -- <<< แก้ไข: เพิ่ม PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Dumping data for table `tbl_customer`
--
TRUNCATE TABLE `tbl_customer`;  -- ต้องมีเซมิโคลอน

INSERT INTO `tbl_customer` (`CustomerID`, `FirstName`, `LastName`, `Email`, `Phone`, `AvatarPath`, `Username`, `Password`, `Status`, `DateCreated`) VALUES
(1, 'สมชาย', 'ใจดี', 'somchai@email.com', '0812345678', 'uploads/avatars/--1-1761763308.jpg', 'somchai', '1234', 'active', '2025-10-21 06:04:34'),
(2, 'สมหญิง', 'รักไทย', 'somying@email.com', '0898765432', NULL, 'somying', '1234', 'active', '2025-10-21 06:04:34');


-- --------------------------------------------------------

--
-- Table structure for table `tbl_employee`
--

CREATE TABLE `tbl_employee` (
  `EmployeeID` int(11) NOT NULL AUTO_INCREMENT, -- <<< แก้ไข: เพิ่ม AUTO_INCREMENT
  `FirstName` varchar(255) NOT NULL,
  `Phone` varchar(20) NOT NULL,
  `RoleID` int(11) NOT NULL,
  `Username` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL,
  PRIMARY KEY (`EmployeeID`) -- <<< แก้ไข: เพิ่ม PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Dumping data for table `tbl_employee`
--
TRUNCATE TABLE `tbl_employee`;

INSERT INTO `tbl_employee` (`EmployeeID`, `FirstName`, `Phone`, `RoleID`, `Username`, `Password`) VALUES
(1, 'ผู้ดูแล', '0999999999', 1, 'admin', 'admin1234');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_payment_status`
--

CREATE TABLE `tbl_payment_status` (
  `PaymentStatusID` int(11) NOT NULL,
  `StatusName` varchar(100) NOT NULL,
  PRIMARY KEY (`PaymentStatusID`) -- <<< แก้ไข: เพิ่ม PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_payment_status`
--
TRUNCATE TABLE `tbl_payment_status`;

INSERT INTO `tbl_payment_status` (`PaymentStatusID`, `StatusName`) VALUES
(4, 'คืนเงินแล้ว'),
(2, 'ชำระเงินสำเร็จ'),
(3, 'รอคืนเงิน'),
(1, 'รอชำระเงิน');
-- --------------------------------------------------------

--
-- Table structure for table `tbl_promotion`
--

CREATE TABLE `tbl_promotion` (
  `PromotionID` int(11) NOT NULL AUTO_INCREMENT, -- <<< แก้ไข: เพิ่ม AUTO_INCREMENT
  `PromoCode` varchar(50) NOT NULL,
  `PromoName` varchar(255) NOT NULL,
  `Description` text DEFAULT NULL,
  `DiscountType` enum('percent','fixed') NOT NULL,
  `DiscountValue` decimal(10,2) NOT NULL,
  `StartDate` datetime NOT NULL,
  `EndDate` datetime NOT NULL,
  `Conditions` text DEFAULT NULL,
  PRIMARY KEY (`PromotionID`) -- <<< แก้ไข: เพิ่ม PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Dumping data for table `tbl_promotion`
--
TRUNCATE TABLE `tbl_promotion`;
INSERT INTO `tbl_promotion` (`PromotionID`, `PromoCode`, `PromoName`, `Description`, `DiscountType`, `DiscountValue`, `StartDate`, `EndDate`, `Conditions`) VALUES
(0, 'kan', 'kan', 'kan', 'fixed', 100.00, '2025-10-30 05:22:00', '2025-11-14 05:22:00', NULL);


-- --------------------------------------------------------

--
-- Table structure for table `tbl_review`
--

CREATE TABLE `tbl_review` (
  `ReviewID` int(11) NOT NULL AUTO_INCREMENT, -- <<< แก้ไข: เพิ่ม AUTO_INCREMENT
  `CustomerID` int(11) NOT NULL,
  `VenueID` int(11) NOT NULL,
  `BookingID` int(11) NOT NULL,
  `Rating` int(11) NOT NULL,
  `Comment` text DEFAULT NULL,
  `ReviewDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `CreatedAt` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`ReviewID`) -- <<< แก้ไข: เพิ่ม PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Dumping data for table `tbl_review`
--
TRUNCATE TABLE `tbl_review`;
INSERT INTO `tbl_review` (`ReviewID`, `CustomerID`, `VenueID`, `BookingID`, `Rating`, `Comment`, `ReviewDate`, `CreatedAt`) VALUES
(0, 0, 13, 68, 1, 'ห่วย', '2025-10-30 01:11:43', '2025-10-30 08:11:43'),
(3, 17, 8, 92, 4, 'ดีมากครับ', '2025-10-30 20:58:41', '2025-10-31 03:58:41'),
(4, 17, 18, 93, 3, 'กกกกกก', '2025-10-30 21:22:22', '2025-10-31 04:22:22');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_role`
--

CREATE TABLE `tbl_role` (
  `RoleID` int(11) NOT NULL AUTO_INCREMENT, -- <<< แก้ไข: เพิ่ม AUTO_INCREMENT
  `RoleName` varchar(100) NOT NULL,
  PRIMARY KEY (`RoleID`) -- <<< แก้ไข: เพิ่ม PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
-- Dumping data for table `tbl_role`
--
TRUNCATE TABLE `tbl_role`;
INSERT INTO `tbl_role` (`RoleID`, `RoleName`) VALUES
(1, 'Admin'),
(2, 'Staff');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_venue`
--

CREATE TABLE `tbl_venue` (
  `VenueID` int(11) NOT NULL AUTO_INCREMENT, -- <<< แก้ไข: เพิ่ม AUTO_INCREMENT
  `VenueName` varchar(255) NOT NULL,
  `VenueTypeID` int(11) NOT NULL,
  `Description` text DEFAULT NULL,
  `Address` text DEFAULT NULL,
  `PricePerHour` decimal(10,2) NOT NULL,
  `TimeOpen` time DEFAULT NULL,
  `TimeClose` time DEFAULT NULL,
  `Status` varchar(50) NOT NULL DEFAULT 'available',
  `ImageURL` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`VenueID`) -- <<< แก้ไข: เพิ่ม PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_venue`
--
--
TRUNCATE TABLE `tbl_venue`;
INSERT INTO `tbl_venue` (`VenueID`, `VenueName`, `VenueTypeID`, `Description`, `Address`, `PricePerHour`, `TimeOpen`, `TimeClose`, `Status`, `ImageURL`) VALUES


(1, 'สนามแบดมินตัน Green Court', 1, 'สนามแบดมินตันพื้นไม้ 4 สนาม พร้อมไฟส่องสว่าง', 'ถนนสุขภาพดี เขตเมือง', 150.00, '08:00:00', '22:00:00', 'available', 'images/badminton.jpg'),
(2, 'สนามเบสบอล SmartBase', 2, 'สนามเบสบอลมาตรฐานพร้อมอัฒจันทร์รองรับผู้ชม', 'ซอยนักกีฬากลาง 5', 400.00, '08:00:00', '21:00:00', 'available', 'images/baseball.jpg'),
(3, 'สนามเทนนิส Grand Sport', 3, 'พื้นสนามอะคริลิคมาตรฐาน ITF', 'ถนนนักกีฬา แขวงสนามกีฬา', 300.00, '07:00:00', '20:00:00', 'available', 'images/tennis.jpg'),
(4, 'สนามฮอกกี้พื้นสนาม BlueStick', 4, 'พื้นยางกันลื่นมาตรฐาน FIH', 'ซอยสนามกีฬากลาง', 350.00, '08:00:00', '20:00:00', 'available', 'images/hockey.jpg'),
(6, 'สนามวอลเลย์บอล City Court', 6, 'สนามในร่มพื้นยางรองรับการแข่งขัน', 'ถนนกีฬาไทย เขตสปอร์ตทาวน์', 250.00, '08:00:00', '21:00:00', 'available', 'images/volleyball.jpg'),
(7, 'สนามรักบี้ Rhino Arena', 7, 'สนามหญ้าธรรมชาติขนาดมาตรฐาน', 'ถนนกีฬาสากล แขวงนักกีฬา', 600.00, '07:00:00', '19:00:00', 'available', 'images/rugby.jpg'),
(8, 'สนามยิงธนู Arrow Zone', 8, 'สนามยิงธนูมาตรฐาน 30 เมตร พร้อมครูฝึก', 'ถนนสุขภาพ 9 เขตนักกีฬา', 200.00, '09:00:00', '18:00:00', 'available', 'images/archery.jpg'),
(9, 'สนามฟุตบอล Arena Five', 9, 'สนามฟุตบอลหญ้าเทียมขนาด 7 คน', 'หมู่บ้านสปอร์ตคอมเพล็กซ์', 500.00, '08:00:00', '22:00:00', 'available', 'images/football.jpg'),
(10, 'สนามฟุตซอล FastKick', 10, 'สนามฟุตซอลในร่มพื้นยางกันกระแทก', 'ถนนฟิตสปอร์ต', 400.00, '08:00:00', '22:00:00', 'available', 'images/futsal.jpg'),
(11, 'สนามปีนผา RockUp Center', 11, 'ผนังปีนสูง 12 เมตร พร้อมระบบเซฟตี้ครบ', 'ซอยแอดเวนเจอร์ เขตเมืองเหนือ', 300.00, '10:00:00', '20:00:00', 'available', 'images/climbing.jpg'),
(12, 'สนามปิงปอง PingZone', 12, 'สนามปิงปองในร่มมีโต๊ะ 5 โต๊ะ พร้อมแอร์เย็น', 'ถนนนักกีฬา ซอย 3', 100.00, '09:00:00', '21:00:00', 'available', 'images/pingpong.jpg'),
(13, 'สนามบาสเกตบอล Sport Hall', 13, 'สนามบาสในร่มพื้นไม้มาตรฐาน NBA มีที่นั่งสําหรับคนดูพร้อมในการเเข่งขัน', 'ศูนย์กีฬากลางเมือง', 500.00, '07:00:00', '22:00:00', 'available', 'images/basketball.jpg'),
(14, 'สนามฟุตบอล GreenField', 9, 'สนามหญ้าเทียมมาตรฐาน FIFA พร้อมไฟส่องสว่างเต็มสนาม', 'ถนนกีฬากลาง เขตเมือง', 500.00, '07:00:00', '22:00:00', 'available', 'images/football2.jpg'),
(15, 'สนามฟุตบอล Cygreen', 9, 'สนามหญ้าเทียมมาตรฐาน ขาดไฟแสงสว่าง ', 'ถนนกีฬากลาง เขตเมือง', 200.00, '07:00:00', '22:00:00', 'available', 'images/football3.jpg'),
(16, 'สนามฟุตซอล สนามเขียว', 10, 'สนามฟุตซอลในกลางเเจ้งคอนกรีตทาสี', 'ถนนหนองกุง', 300.00, '08:00:00', '21:00:00', 'available', 'images/futsal2.jpg'),
(17, 'สนามฟุตซอล89 ARENO', 10, 'สนามฟุตซอลกลางเเจ้งพื้นยางกันกระแทก', 'ถนนเเม่รําเพย', 400.00, '08:00:00', '22:00:00', 'available', 'images/futsal3.jpg'),
(18, 'สนามบาสเกตบอล Miami ', 13, 'สนามบาสในร่มพื้นไม้มาตรฐาน NBA', 'ศูนย์กีฬากลางเมือง', 400.00, '07:00:00', '22:00:00', 'available', 'images/basketball2.jpg'),
(19, 'สนามบาสเกตบอล Golden', 13, 'สนามบาสกลางเเจ้งคอนกรีต', 'ใกล้ตัวเมือง', 250.00, '07:00:00', '22:00:00', 'available', 'images/basketball3.jpg'),
(20, 'สนามแบดมินตัน Lebron Court', 1, 'สนามแบดมินตันในร่มพื้นไม้ 5 สนาม พร้อมไฟส่องสว่าง', 'ถนนอัสสัมชัญ เขตเมือง', 150.00, '08:00:00', '22:00:00', 'available', 'images/badminton2.jpg'),
(21, 'สนามแบดมินตัน Ming Court', 1, 'สนามแบดมินตันในร่มคอนกรีต 4 สนาม พร้อมไฟส่องสว่าง', 'ถนนสวนกุหลาบ เขตใกล้เมือง', 100.00, '08:00:00', '22:00:00', 'available', 'images/badminton3.jpg'),
(22, 'สนามเทนนิส Pumu sport', 3, 'พื้นสนามอะคริลิคมาตรฐาน ITF', 'ถนนราชดําเนิน ใกล้ตัวเมือง', 300.00, '07:00:00', '20:00:00', 'available', 'images/tennis2.jpg'),
(23, 'สนามเทนนิส Forrest Sport', 3, 'พื้นสนามอะคริลิคมาตรฐาน ITF สนามในร่ม มีไฟสว่าง', 'ถนนกันทรวิชัย เขตตัวเมือง', 350.00, '07:00:00', '20:00:00', 'available', 'images/tennis3.jpg'),
(24, 'สนามปิงปอง ปทุมวิไล', 12, 'สนามปิงปองในร่มมีโต๊ะ 5 โต๊ะ พร้อมแอร์เย็น', 'ถนนประชาชื่น ซอย 8 เขตเมืองเหนือ', 100.00, '09:00:00', '21:00:00', 'available', 'images/pingpong2.jpg'),
(25, 'สนามปิงปอง Nara Sport', 12, 'สนามปิงปองในร่มมีโต๊ะ 4 โต๊ะ พร้อมแอร์เย็น', 'ถนนศรีสุข ซอยกีฬา 2 เขตกลางเมือง', 100.00, '09:00:00', '21:00:00', 'available', 'images/pingpong3.jpg'),
(26, 'สนามวอลเลย์บอล คอร์ตเมืองเหนือ', 6, 'สนามในร่มพื้นยางรองรับการแข่งขัน', 'ถนนประชาราษฎร์ ซอย 10 เขตเมืองเหนือ', 250.00, '08:00:00', '21:00:00', 'available', 'images/volleyball2.jpg'),
(27, 'สนามวอลเลย์บอล ศรีนครคอร์ต', 6, 'สนามในร่มพื้นยางรองรับการแข่งขัน มีเเอร์ให้ในสนาม', 'ถนนศรีนคร ซอยกีฬา 1 เขตกลางเมือง', 300.00, '08:00:00', '21:00:00', 'available', 'images/volleyball3.jpg'),
(28, 'สนามเบสบอล ราชพฤกษ์โดม', 2, 'สนามเบสบอลมาตรฐานพร้อมอัฒจันทร์รองรับผู้ชม สนามสะอาดสามารถใช้ในการเล่นหรือการเเข่งได้', 'ถนนราชพฤกษ์ ซอย 12 เขตตะวันตก', 400.00, '08:00:00', '21:00:00', 'available', 'images/baseball2.jpg'),
(29, 'สนามเบสบอล เมืองทองพาร์ค', 2, 'สนามเบสบอลมาตรฐานในร่ม', 'ถนนเมืองทองธานี ซอยกีฬา 3 เขตกลางเมือง', 300.00, '08:00:00', '21:00:00', 'available', 'images/baseball3.jpg'),
(30, 'สนามยิงธนู ธนูสเตเดียม', 8, 'สนามยิงธนูมาตรฐาน ระยะ 30–50 เมตร มีครูฝึกดูแล เหมาะทั้งผู้เริ่มต้นและมือสมัครเล่น', 'ถนนนักกีฬากลาง เขตเมืองเหนือ', 250.00, '09:00:00', '18:00:00', 'available', 'images/archery2.jpg'),
(31, 'สนามยิงธนู ริเวอร์เรนจ์', 8, 'เลนยิง 10 ช่อง ระยะ 10–40 เมตร มีอุปกรณ์ให้เช่าและจุดพักผ่อน', 'ถนนริมน้ำ ซอย 5 เขตตะวันออก', 200.00, '09:00:00', '18:00:00', 'available', 'images/archery3.jpg'),
(32, 'สนามรักบี้ ช้างเผือกสเตเดียม', 7, 'สนามรักบี้หญ้าจริงขนาดมาตรฐาน มีเสาประตูและเส้นสนามครบ พร้อมห้องแต่งตัวและห้องอาบน้ำ', 'ถนนสนามกีฬาแห่งชาติ เขตเมืองเก่า', 600.00, '07:00:00', '19:00:00', 'available', 'images/rugby2.jpg'),
(33, 'สนามรักบี้ บางกอกรัคบี้พาร์ค', 7, 'พื้นหญ้าเทียมคุณภาพสูง รองรับการซ้อมและการแข่งขันระดับสมัครเล่นถึงกึ่งอาชีพ มีไฟส่องสว่างรอบสนาม', 'ถนนวิภาวดีรังสิต ซอย 10 เขตเหนือเมือง', 600.00, '07:00:00', '19:00:00', 'available', 'images/rugby3.jpg'),
(34, 'สนามปีนผา CliffHouse', 11, 'ผนังปีนหลากเส้นทาง ระดับเริ่มต้นถึงกลาง มีโซนบูลเดอริงและอุปกรณ์ให้เช่า', 'ถนนกีฬากลาง ซอย 4 เขตกลางเมือง', 300.00, '10:00:00', '20:00:00', 'available', 'images/climbing2.jpg'),
(35, 'สนามปีนผา Summit Gym', 11, 'ผนังสูง 15 เมตร มีระบบเซฟตี้อัตโนมัติและครูผู้ฝึกสอน ประสบการณ์ครบสำหรับทุกวัย', 'ถนนศรีกีฬา ซอย 3 เขตตะวันออก', 300.00, '10:00:00', '20:00:00', 'available', 'images/climbing3.jpg'),
(36, 'สนามฮอกกี้พื้นสนาม เมืองใหม่สปอร์ต', 4, 'พื้นสนามมาตรฐาน FIH พื้นยางสังเคราะห์กันลื่น มีไฟส่องสว่างรอบสนาม เหมาะสำหรับซ้อมและแข่งขัน', 'ถนนสุขสวัสดิ์ ซอยกีฬา 9 เขตเมืองใหม่', 350.00, '08:00:00', '20:00:00', 'available', 'images/hockey2.jpg'),
(37, 'สนามฮอกกี้พื้นสนาม ศรีนครอารีน่า', 4, 'เลย์เอาต์ขนาดมาตรฐาน มีกรอบประตูและเส้นสนามครบ พร้อมห้องแต่งตัวและอุปกรณ์ให้ยืม', 'ถนนศรีนคร ซอย 7 เขตตะวันออก', 350.00, '08:00:00', '20:00:00', 'available', 'images/hockey3.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_venue_type`
--

CREATE TABLE `tbl_venue_type` (
  `VenueTypeID` int(11) NOT NULL AUTO_INCREMENT, -- <<< แก้ไข: เพิ่ม AUTO_INCREMENT
  `TypeName` varchar(255) NOT NULL,
  PRIMARY KEY (`VenueTypeID`) -- <<< แก้ไข: เพิ่ม PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_venue_type`
--
TRUNCATE TABLE `tbl_venue_type`;
INSERT INTO `tbl_venue_type` (`VenueTypeID`, `TypeName`) VALUES


(1, 'แบดมินตัน'),
(2, 'เบสบอล'),
(3, 'เทนนิส'),
(4, 'ฮอกกี้พื้นสนาม'),
(5, 'อเมริกันฟุตบอล'),
(6, 'วอลเลย์บอล'),
(7, 'รักบี้'),
(8, 'ยิงธนู'),
(9, 'ฟุตบอล'),
(10, 'ฟุตซอล'),
(11, 'ปีนผา'),
(12, 'ปิงปอง'),
(13, 'บาสเกตบอล');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_booking_funnel`
-- (See below for the actual view)
--
--
-- Structure for view `vw_booking_funnel`
--
-- View แรก: vw_booking_funnel
-- View แรก: vw_booking_funnel
-- --------------------------------------------------------

--
-- Structure for view `vw_booking_funnel`
-- --------------------------------------------------------

--
-- Structure for view `vw_booking_funnel`
--DROP TABLE IF EXISTS `vw_booking_funnel`;

CREATE VIEW `vw_booking_funnel` AS SELECT `bs`.`StatusName` AS `booking_status`, `ps`.`StatusName` AS `payment_status`, count(0) AS `cnt` FROM ((`Tbl_Booking` `b` join `Tbl_Booking_Status` `bs` on(`bs`.`BookingStatusID` = `b`.`BookingStatusID`)) join `Tbl_Payment_Status` `ps` on(`ps`.`PaymentStatusID` = `b`.`PaymentStatusID`)) GROUP BY `bs`.`StatusName`, `ps`.`StatusName` ORDER BY `bs`.`StatusName` ASC, `ps`.`StatusName` ASC;

-- --------------------------------------------------------

--
-- Structure for view `vw_customer_ltv`
--
DROP TABLE IF EXISTS `vw_customer_ltv`;

CREATE VIEW `vw_customer_ltv` AS SELECT `c`.`CustomerID` AS `CustomerID`, concat(`c`.`FirstName`,' ',`c`.`LastName`) AS `customer_name`, count(`b`.`BookingID`) AS `total_bookings`, sum(`b`.`TotalPrice`) AS `total_revenue`, avg(`b`.`TotalPrice`) AS `avg_order_value`, min(`b`.`StartTime`) AS `first_booking_at`, max(`b`.`StartTime`) AS `last_booking_at`, to_days(curdate()) - to_days(max(`b`.`StartTime`)) AS `recency_days` FROM (((`Tbl_Customer` `c` left join `Tbl_Booking` `b` on(`b`.`CustomerID` = `c`.`CustomerID`)) left join `Tbl_Booking_Status` `bs` on(`bs`.`BookingStatusID` = `b`.`BookingStatusID`)) left join `Tbl_Payment_Status` `ps` on(`ps`.`PaymentStatusID` = `b`.`PaymentStatusID` and `bs`.`StatusName` in ('ยืนยันแล้ว','เข้าใช้บริการแล้ว') and `ps`.`StatusName` = 'ชำระเงินสำเร็จ')) GROUP BY `c`.`CustomerID`, concat(`c`.`FirstName`,' ',`c`.`LastName`) ORDER BY sum(`b`.`TotalPrice`) DESC;

-- --------------------------------------------------------

--
-- Structure for view `vw_employee_performance`
--
DROP TABLE IF EXISTS `vw_employee_performance`;

CREATE VIEW `vw_employee_performance` AS SELECT `e`.`EmployeeID` AS `EmployeeID`, `e`.`FirstName` AS `employee_name`, count(`b`.`BookingID`) AS `handled_bookings`, sum(case when `ps`.`StatusName` = 'ชำระเงินสำเร็จ' then `b`.`TotalPrice` else 0 end) AS `revenue_approved`, min(`b`.`StartTime`) AS `first_booking_at`, max(`b`.`StartTime`) AS `last_booking_at` FROM ((`Tbl_Employee` `e` left join `Tbl_Booking` `b` on(`b`.`EmployeeID` = `e`.`EmployeeID`)) left join `Tbl_Payment_Status` `ps` on(`ps`.`PaymentStatusID` = `b`.`PaymentStatusID`)) GROUP BY `e`.`EmployeeID`, `e`.`FirstName` ORDER BY sum(case when `ps`.`StatusName` = 'ชำระเงินสำเร็จ' then `b`.`TotalPrice` else 0 end) DESC;

-- --------------------------------------------------------

--
-- Structure for view `vw_monthly_cancellation_rate`
--
DROP TABLE IF EXISTS `vw_monthly_cancellation_rate`;

CREATE VIEW `vw_monthly_cancellation_rate` AS SELECT date_format(`b`.`StartTime`,'%Y-%m') AS `ym`, count(0) AS `total_bookings`, sum(case when `bs`.`StatusName` in ('ยกเลิกโดยลูกค้า','ยกเลิกโดยระบบ') then 1 else 0 end) AS `cancelled`, round(100.0 * sum(case when `bs`.`StatusName` in ('ยกเลิกโดยลูกค้า','ยกเลิกโดยระบบ') then 1 else 0 end) / count(0),2) AS `cancel_rate_pct` FROM (`Tbl_Booking` `b` join `Tbl_Booking_Status` `bs` on(`bs`.`BookingStatusID` = `b`.`BookingStatusID`)) GROUP BY date_format(`b`.`StartTime`,'%Y-%m') ORDER BY date_format(`b`.`StartTime`,'%Y-%m') ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_monthly_revenue`
--
DROP TABLE IF EXISTS `vw_monthly_revenue`;

CREATE VIEW `vw_monthly_revenue` AS SELECT date_format(`b`.`StartTime`,'%Y-%m') AS `ym`, sum(`b`.`TotalPrice`) AS `revenue`, count(0) AS `bookings`, avg(`b`.`TotalPrice`) AS `avg_order_value` FROM ((`Tbl_Booking` `b` join `Tbl_Booking_Status` `bs` on(`bs`.`BookingStatusID` = `b`.`BookingStatusID`)) join `Tbl_Payment_Status` `ps` on(`ps`.`PaymentStatusID` = `b`.`PaymentStatusID`)) WHERE `bs`.`StatusName` in ('ยืนยันแล้ว','เข้าใช้บริการแล้ว') AND `ps`.`StatusName` = 'ชำระเงินสำเร็จ' GROUP BY date_format(`b`.`StartTime`,'%Y-%m') ORDER BY date_format(`b`.`StartTime`,'%Y-%m') ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_peak_hours_by_type`
--
DROP TABLE IF EXISTS `vw_peak_hours_by_type`;

CREATE VIEW `vw_peak_hours_by_type` AS SELECT `vt`.`TypeName` AS `TypeName`, hour(`b`.`StartTime`) AS `hour_of_day`, count(0) AS `bookings`, row_number() over ( partition by `vt`.`TypeName` order by count(0) desc) AS `rn_in_type` FROM (((`Tbl_Booking` `b` join `Tbl_Venue` `v` on(`v`.`VenueID` = `b`.`VenueID`)) join `Tbl_Venue_Type` `vt` on(`vt`.`VenueTypeID` = `v`.`VenueTypeID`)) join `Tbl_Booking_Status` `bs` on(`bs`.`BookingStatusID` = `b`.`BookingStatusID`)) WHERE `bs`.`StatusName` in ('ยืนยันแล้ว','เข้าใช้บริการแล้ว') GROUP BY `vt`.`TypeName`, hour(`b`.`StartTime`) ORDER BY `vt`.`TypeName` ASC, count(0) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_promotion_performance`
--
DROP TABLE IF EXISTS `vw_promotion_performance`;

CREATE VIEW `vw_promotion_performance` AS SELECT `p`.`PromotionID` AS `PromotionID`, `p`.`PromoCode` AS `promo_code`, count(`b`.`BookingID`) AS `uses_count`, coalesce(sum(case when `bs`.`StatusName` in ('ยืนยันแล้ว','เข้าใช้บริการแล้ว') and `ps`.`StatusName` = 'ชำระเงินสำเร็จ' then `b`.`NetPrice` else 0 end),0) AS `revenue_from_promo`, min(`b`.`StartTime`) AS `first_used_at`, max(`b`.`StartTime`) AS `last_used_at` FROM (((`Tbl_Promotion` `p` left join `Tbl_Booking` `b` on(`b`.`PromotionID` = `p`.`PromotionID`)) left join `Tbl_Booking_Status` `bs` on(`bs`.`BookingStatusID` = `b`.`BookingStatusID`)) left join `Tbl_Payment_Status` `ps` on(`ps`.`PaymentStatusID` = `b`.`PaymentStatusID`)) GROUP BY `p`.`PromotionID`, `p`.`PromoCode` ORDER BY count(`b`.`BookingID`) DESC, coalesce(sum(case when `bs`.`StatusName` in ('ยืนยันแล้ว','เข้าใช้บริการแล้ว') and `ps`.`StatusName` = 'ชำระเงินสำเร็จ' then `b`.`NetPrice` else 0 end),0) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_review_scores_by_venue`
--
DROP TABLE IF EXISTS `vw_review_scores_by_venue`;

CREATE VIEW `vw_review_scores_by_venue` AS SELECT `v`.`VenueID` AS `VenueID`, `v`.`VenueName` AS `VenueName`, count(`r`.`ReviewID`) AS `reviews_count`, avg(`r`.`Rating`) AS `avg_rating`, min(`r`.`CreatedAt`) AS `first_review_at`, max(`r`.`CreatedAt`) AS `last_review_at` FROM (`Tbl_Venue` `v` left join `Tbl_Review` `r` on(`r`.`VenueID` = `v`.`VenueID`)) GROUP BY `v`.`VenueID`, `v`.`VenueName` ORDER BY avg(`r`.`Rating`) DESC, count(`r`.`ReviewID`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_top10_venues_by_revenue`
--
DROP TABLE IF EXISTS `vw_top10_venues_by_revenue`;

CREATE VIEW `vw_top10_venues_by_revenue` AS WITH last90 AS (SELECT `v`.`VenueID` AS `VenueID`, `v`.`VenueName` AS `VenueName`, sum(`b`.`TotalPrice`) AS `revenue_90d`, count(0) AS `bookings_90d` FROM (((`Tbl_Booking` `b` join `Tbl_Venue` `v` on(`v`.`VenueID` = `b`.`VenueID`)) join `Tbl_Booking_Status` `bs` on(`bs`.`BookingStatusID` = `b`.`BookingStatusID`)) join `Tbl_Payment_Status` `ps` on(`ps`.`PaymentStatusID` = `b`.`PaymentStatusID`)) WHERE `b`.`StartTime` >= curdate() - interval 90 day AND `bs`.`StatusName` in ('ยืนยันแล้ว','เข้าใช้บริการแล้ว') AND `ps`.`StatusName` = 'ชำระเงินสำเร็จ' GROUP BY `v`.`VenueID`, `v`.`VenueName`) SELECT `x`.`VenueID` AS `VenueID`, `x`.`VenueName` AS `VenueName`, `x`.`revenue_90d` AS `revenue_90d`, `x`.`bookings_90d` AS `bookings_90d`, `x`.`rn` AS `rn` FROM (select `last90`.`VenueID` AS `VenueID`,`last90`.`VenueName` AS `VenueName`,`last90`.`revenue_90d` AS `revenue_90d`,`last90`.`bookings_90d` AS `bookings_90d`,row_number() over ( order by `last90`.`revenue_90d` desc) AS `rn` from `last90`) AS `x` WHERE `x`.`rn` <= 10 ORDER BY `x`.`revenue_90d` DESC;
-- --------------------------------------------------------

--
-- Structure for view `vw_venue_utilization_daily`
--
DROP TABLE IF EXISTS `vw_venue_utilization_daily`;

CREATE VIEW `vw_venue_utilization_daily` AS SELECT `v`.`VenueID` AS `VenueID`, `v`.`VenueName` AS `VenueName`, cast(`b`.`StartTime` as date) AS `usage_date`, sum(timestampdiff(MINUTE,`b`.`StartTime`,`b`.`EndTime`)) / 60.0 AS `booked_hours`, greatest(timestampdiff(MINUTE,timestamp(cast(`b`.`StartTime` as date),`v`.`TimeOpen`),timestamp(cast(`b`.`StartTime` as date),`v`.`TimeClose`)) / 60.0,0) AS `open_hours`, round(sum(timestampdiff(MINUTE,`b`.`StartTime`,`b`.`EndTime`)) / 60.0 / nullif(greatest(timestampdiff(MINUTE,timestamp(cast(`b`.`StartTime` as date),`v`.`TimeOpen`),timestamp(cast(`b`.`StartTime` as date),`v`.`TimeClose`)) / 60.0,0),0) * 100,2) AS `utilization_pct` FROM ((`Tbl_Booking` `b` join `Tbl_Venue` `v` on(`v`.`VenueID` = `b`.`VenueID`)) join `Tbl_Booking_Status` `bs` on(`bs`.`BookingStatusID` = `b`.`BookingStatusID`)) WHERE `bs`.`StatusName` in ('ยืนยันแล้ว','เข้าใช้บริการแล้ว') GROUP BY `v`.`VenueID`, `v`.`VenueName`, cast(`b`.`StartTime` as date), `v`.`TimeOpen`, `v`.`TimeClose` ORDER BY cast(`b`.`StartTime` as date) DESC, `v`.`VenueName` ASC ;