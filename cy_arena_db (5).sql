-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 29, 2025 at 05:32 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


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
  `BookingID` int(11) NOT NULL,
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
  `Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_booking`
--

INSERT INTO `tbl_booking` (`BookingID`, `CustomerID`, `VenueID`, `BookingStatusID`, `PaymentStatusID`, `PromotionID`, `EmployeeID`, `BookingDate`, `StartTime`, `EndTime`, `HoursBooked`, `TotalPrice`, `Discount`, `NetPrice`, `PaymentMethod`, `Notes`) VALUES
(41, 5, 13, 2, 2, NULL, NULL, '2025-10-29 15:48:02', '2025-10-29 16:00:00', '2025-10-29 17:00:00', 1.00, 350.00, 0.00, 350.00, NULL, NULL),
(42, 5, 13, 1, 1, NULL, NULL, '2025-10-29 22:06:28', '2025-10-30 10:00:00', '2025-10-30 11:00:00', 1.00, 350.00, 0.00, 350.00, NULL, NULL),
(43, 5, 12, 1, 1, NULL, NULL, '2025-10-29 22:07:06', '2025-10-30 09:00:00', '2025-10-30 10:00:00', 1.00, 100.00, 0.00, 100.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_booking_status`
--

CREATE TABLE `tbl_booking_status` (
  `BookingStatusID` int(11) NOT NULL,
  `StatusName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_booking_status`
--

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
  `CustomerID` int(11) NOT NULL,
  `FirstName` varchar(255) NOT NULL,
  `LastName` varchar(255) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Phone` varchar(20) NOT NULL,
  `AvatarPath` varchar(255) DEFAULT NULL,
  `Username` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `Status` varchar(20) NOT NULL DEFAULT 'active',
  `DateCreated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_customer`
--

INSERT INTO `tbl_customer` (`CustomerID`, `FirstName`, `LastName`, `Email`, `Phone`, `AvatarPath`, `Username`, `Password`, `Status`, `DateCreated`) VALUES
(1, 'สมชาย', 'ใจดี', 'somchai@email.com', '0812345678', NULL, 'somchai', '1234', 'active', '2025-10-21 06:04:34'),
(2, 'สมหญิง', 'รักไทย', 'somying@email.com', '0898765432', NULL, 'somying', '1234', 'active', '2025-10-21 06:04:34'),
(3, 'fafafa', 'afafafa', 'fasfafaf@hfjgfs', '2421542513', NULL, 'kannaja', '$2y$10$zmzZpjXmduqOBuzWsavEpeEi97r/6niglbMFXekk4ldSsKQzbDdYW', 'active', '2025-10-21 06:27:15'),
(4, 'sese', 'werwe', 'dadad@fafa', '14214251', NULL, 'gg', '$2y$10$2riSRuqTh/o890FHPjtIjOPNOa0Ab1BR4GVNs561ceWm7dDvvmhhy', 'active', '2025-10-21 08:56:38'),
(5, '123', '123', '123@gmail.com', '09347447474', 'uploads/avatars/123-123-5-1761742816.jpg', '123', '$2y$10$4Jz.S/Egau5zhvLGD2IhIezna0EZuirl1sSBy5cjAGnWQClc8d9k2', 'active', '2025-10-28 17:54:18'),
(8, '1', '1', '1@gmai.com', '1', NULL, '1', '$2y$10$DB5vLZL13qjjBTMbvaGEEOlSQhcR8y3hr5St0S5ctoOJp1dn9kGSW', 'active', '2025-10-28 17:55:22');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_employee`
--

CREATE TABLE `tbl_employee` (
  `EmployeeID` int(11) NOT NULL,
  `FirstName` varchar(255) NOT NULL,
  `Phone` varchar(20) NOT NULL,
  `RoleID` int(11) NOT NULL,
  `Username` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_employee`
--

INSERT INTO `tbl_employee` (`EmployeeID`, `FirstName`, `Phone`, `RoleID`, `Username`, `Password`) VALUES
(1, 'ผู้ดูแล', '0999999999', 1, 'admin', 'admin1234');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_payment_status`
--

CREATE TABLE `tbl_payment_status` (
  `PaymentStatusID` int(11) NOT NULL,
  `StatusName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_payment_status`
--

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
  `PromotionID` int(11) NOT NULL,
  `PromoCode` varchar(50) NOT NULL,
  `PromoName` varchar(255) NOT NULL,
  `Description` text DEFAULT NULL,
  `DiscountType` enum('percent','fixed') NOT NULL,
  `DiscountValue` decimal(10,2) NOT NULL,
  `StartDate` datetime NOT NULL,
  `EndDate` datetime NOT NULL,
  `Conditions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_review`
--

CREATE TABLE `tbl_review` (
  `ReviewID` int(11) NOT NULL,
  `CustomerID` int(11) NOT NULL,
  `VenueID` int(11) NOT NULL,
  `BookingID` int(11) NOT NULL,
  `Rating` int(11) NOT NULL,
  `Comment` text DEFAULT NULL,
  `ReviewDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `CreatedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_role`
--

CREATE TABLE `tbl_role` (
  `RoleID` int(11) NOT NULL,
  `RoleName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_role`
--

INSERT INTO `tbl_role` (`RoleID`, `RoleName`) VALUES
(1, 'Admin'),
(2, 'Staff');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_venue`
--

CREATE TABLE `tbl_venue` (
  `VenueID` int(11) NOT NULL,
  `VenueName` varchar(255) NOT NULL,
  `VenueTypeID` int(11) NOT NULL,
  `Description` text DEFAULT NULL,
  `Address` text DEFAULT NULL,
  `PricePerHour` decimal(10,2) NOT NULL,
  `TimeOpen` time DEFAULT NULL,
  `TimeClose` time DEFAULT NULL,
  `Status` varchar(50) NOT NULL DEFAULT 'available',
  `ImageURL` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_venue`
--

INSERT INTO `tbl_venue` (`VenueID`, `VenueName`, `VenueTypeID`, `Description`, `Address`, `PricePerHour`, `TimeOpen`, `TimeClose`, `Status`, `ImageURL`) VALUES
(1, 'สนามแบดมินตัน Green Court', 1, 'สนามแบดมินตันพื้นไม้ 4 สนาม พร้อมไฟส่องสว่าง', 'ถนนสุขภาพดี เขตเมือง', 150.00, '08:00:00', '22:00:00', 'available', 'images/badminton.jpg'),
(2, 'สนามเบสบอล SmartBase', 2, 'สนามเบสบอลมาตรฐานพร้อมอัฒจันทร์รองรับผู้ชม', 'ซอยนักกีฬากลาง 5', 400.00, '08:00:00', '21:00:00', 'available', 'images/baseball.jpg'),
(3, 'สนามเทนนิส Grand Sport', 3, 'พื้นสนามอะคริลิคมาตรฐาน ITF', 'ถนนนักกีฬา แขวงสนามกีฬา', 300.00, '07:00:00', '20:00:00', 'available', 'images/tennis.jpg'),
(4, 'สนามฮอกกี้พื้นสนาม BlueStick', 4, 'พื้นยางกันลื่นมาตรฐาน FIH', 'ซอยสนามกีฬากลาง', 350.00, '08:00:00', '20:00:00', 'available', 'images/hockey.jpg'),
(5, 'สนามอเมริกันฟุตบอล Titans Field', 5, 'สนามหญ้าเทียมขนาดใหญ่รองรับทีม 11 คน', 'หมู่บ้านสปอร์ตแลนด์', 800.00, '08:00:00', '23:00:00', 'available', 'images/americanfootball.jpg'),
(6, 'สนามวอลเลย์บอล City Court', 6, 'สนามในร่มพื้นยางรองรับการแข่งขัน', 'ถนนกีฬาไทย เขตสปอร์ตทาวน์', 250.00, '08:00:00', '21:00:00', 'available', 'images/volleyball.jpg'),
(7, 'สนามรักบี้ Rhino Arena', 7, 'สนามหญ้าธรรมชาติขนาดมาตรฐาน', 'ถนนกีฬาสากล แขวงนักกีฬา', 600.00, '07:00:00', '19:00:00', 'available', 'images/rugby.jpg'),
(8, 'สนามยิงธนู Arrow Zone', 8, 'สนามยิงธนูมาตรฐาน 30 เมตร พร้อมครูฝึก', 'ถนนสุขภาพ 9 เขตนักกีฬา', 200.00, '09:00:00', '18:00:00', 'available', 'images/archery.jpg'),
(9, 'สนามฟุตบอล Arena Five', 9, 'สนามฟุตบอลหญ้าเทียมขนาด 7 คน', 'หมู่บ้านสปอร์ตคอมเพล็กซ์', 500.00, '08:00:00', '22:00:00', 'available', 'images/football.jpg'),
(10, 'สนามฟุตซอล FastKick', 10, 'สนามฟุตซอลในร่มพื้นยางกันกระแทก', 'ถนนฟิตสปอร์ต', 400.00, '08:00:00', '22:00:00', 'available', 'images/futsal.jpg'),
(11, 'สนามปีนผา RockUp Center', 11, 'ผนังปีนสูง 12 เมตร พร้อมระบบเซฟตี้ครบ', 'ซอยแอดเวนเจอร์ เขตเมืองเหนือ', 300.00, '10:00:00', '20:00:00', 'available', 'images/climbing.jpg'),
(12, 'สนามปิงปอง PingZone', 12, 'สนามปิงปองในร่มมีโต๊ะ 5 โต๊ะ พร้อมแอร์เย็น', 'ถนนนักกีฬา ซอย 3', 100.00, '09:00:00', '21:00:00', 'available', 'images/pingpong.jpg'),
(13, 'สนามบาสเกตบอล Sport Hall', 13, 'สนามบาสในร่มพื้นไม้มาตรฐาน NBA', 'ศูนย์กีฬากลางเมือง', 350.00, '07:00:00', '22:00:00', 'available', 'images/basketball.jpg'),
(14, 'สนามฟุตบอล GreenField', 9, 'สนามหญ้าเทียมมาตรฐาน FIFA พร้อมไฟส่องสว่างเต็มสนาม', 'ถนนกีฬากลาง เขตเมือง', 500.00, '07:00:00', '22:00:00', 'available', 'images/football2.jpg'),
(15, 'สนามฟุตบอล Cygreen', 9, 'สนามหญ้าเทียมมาตรฐาน ขาดไฟแสงสว่าง ', 'ถนนกีฬากลาง เขตเมือง', 150.00, '07:00:00', '22:00:00', 'available', 'images/football3.jpg'),
(16, 'a', 13, '1', '1', 22222.00, '14:01:00', '15:01:00', 'available', 'uploads/venues/venue_20251028_200346_21c03d.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_venue_type`
--

CREATE TABLE `tbl_venue_type` (
  `VenueTypeID` int(11) NOT NULL,
  `TypeName` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_venue_type`
--

INSERT INTO `tbl_venue_type` (`VenueTypeID`, `TypeName`) VALUES
(13, 'บาสเกตบอล'),
(12, 'ปิงปอง'),
(11, 'ปีนผา'),
(10, 'ฟุตซอล'),
(9, 'ฟุตบอล'),
(8, 'ยิงธนู'),
(7, 'รักบี้'),
(6, 'วอลเลย์บอล'),
(5, 'อเมริกันฟุตบอล'),
(4, 'ฮอกกี้พื้นสนาม'),
(3, 'เทนนิส'),
(2, 'เบสบอล'),
(1, 'แบดมินตัน');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  ADD PRIMARY KEY (`BookingID`),
  ADD KEY `CustomerID` (`CustomerID`),
  ADD KEY `VenueID` (`VenueID`),
  ADD KEY `BookingStatusID` (`BookingStatusID`),
  ADD KEY `PaymentStatusID` (`PaymentStatusID`),
  ADD KEY `PromotionID` (`PromotionID`),
  ADD KEY `EmployeeID` (`EmployeeID`);

--
-- Indexes for table `tbl_booking_status`
--
ALTER TABLE `tbl_booking_status`
  ADD PRIMARY KEY (`BookingStatusID`),
  ADD UNIQUE KEY `StatusName` (`StatusName`);

--
-- Indexes for table `tbl_customer`
--
ALTER TABLE `tbl_customer`
  ADD PRIMARY KEY (`CustomerID`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD UNIQUE KEY `Phone` (`Phone`),
  ADD UNIQUE KEY `Username` (`Username`);

--
-- Indexes for table `tbl_employee`
--
ALTER TABLE `tbl_employee`
  ADD PRIMARY KEY (`EmployeeID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD KEY `RoleID` (`RoleID`);

--
-- Indexes for table `tbl_payment_status`
--
ALTER TABLE `tbl_payment_status`
  ADD PRIMARY KEY (`PaymentStatusID`),
  ADD UNIQUE KEY `StatusName` (`StatusName`);

--
-- Indexes for table `tbl_promotion`
--
ALTER TABLE `tbl_promotion`
  ADD PRIMARY KEY (`PromotionID`),
  ADD UNIQUE KEY `PromoCode` (`PromoCode`);

--
-- Indexes for table `tbl_review`
--
ALTER TABLE `tbl_review`
  ADD PRIMARY KEY (`ReviewID`),
  ADD UNIQUE KEY `BookingID` (`BookingID`),
  ADD KEY `CustomerID` (`CustomerID`),
  ADD KEY `VenueID` (`VenueID`);

--
-- Indexes for table `tbl_role`
--
ALTER TABLE `tbl_role`
  ADD PRIMARY KEY (`RoleID`),
  ADD UNIQUE KEY `RoleName` (`RoleName`);

--
-- Indexes for table `tbl_venue`
--
ALTER TABLE `tbl_venue`
  ADD PRIMARY KEY (`VenueID`),
  ADD KEY `VenueTypeID` (`VenueTypeID`);

--
-- Indexes for table `tbl_venue_type`
--
ALTER TABLE `tbl_venue_type`
  ADD PRIMARY KEY (`VenueTypeID`),
  ADD UNIQUE KEY `TypeName` (`TypeName`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  MODIFY `BookingID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `tbl_booking_status`
--
ALTER TABLE `tbl_booking_status`
  MODIFY `BookingStatusID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_customer`
--
ALTER TABLE `tbl_customer`
  MODIFY `CustomerID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tbl_employee`
--
ALTER TABLE `tbl_employee`
  MODIFY `EmployeeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_payment_status`
--
ALTER TABLE `tbl_payment_status`
  MODIFY `PaymentStatusID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_promotion`
--
ALTER TABLE `tbl_promotion`
  MODIFY `PromotionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_review`
--
ALTER TABLE `tbl_review`
  MODIFY `ReviewID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_role`
--
ALTER TABLE `tbl_role`
  MODIFY `RoleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_venue`
--
ALTER TABLE `tbl_venue`
  MODIFY `VenueID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `tbl_venue_type`
--
ALTER TABLE `tbl_venue_type`
  MODIFY `VenueTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=541113;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  ADD CONSTRAINT `tbl_booking_ibfk_1` FOREIGN KEY (`CustomerID`) REFERENCES `tbl_customer` (`CustomerID`),
  ADD CONSTRAINT `tbl_booking_ibfk_2` FOREIGN KEY (`VenueID`) REFERENCES `tbl_venue` (`VenueID`),
  ADD CONSTRAINT `tbl_booking_ibfk_3` FOREIGN KEY (`BookingStatusID`) REFERENCES `tbl_booking_status` (`BookingStatusID`),
  ADD CONSTRAINT `tbl_booking_ibfk_4` FOREIGN KEY (`PaymentStatusID`) REFERENCES `tbl_payment_status` (`PaymentStatusID`),
  ADD CONSTRAINT `tbl_booking_ibfk_5` FOREIGN KEY (`PromotionID`) REFERENCES `tbl_promotion` (`PromotionID`),
  ADD CONSTRAINT `tbl_booking_ibfk_6` FOREIGN KEY (`EmployeeID`) REFERENCES `tbl_employee` (`EmployeeID`);

--
-- Constraints for table `tbl_employee`
--
ALTER TABLE `tbl_employee`
  ADD CONSTRAINT `tbl_employee_ibfk_1` FOREIGN KEY (`RoleID`) REFERENCES `tbl_role` (`RoleID`);

--
-- Constraints for table `tbl_review`
--
ALTER TABLE `tbl_review`
  ADD CONSTRAINT `tbl_review_ibfk_1` FOREIGN KEY (`CustomerID`) REFERENCES `tbl_customer` (`CustomerID`),
  ADD CONSTRAINT `tbl_review_ibfk_2` FOREIGN KEY (`VenueID`) REFERENCES `tbl_venue` (`VenueID`),
  ADD CONSTRAINT `tbl_review_ibfk_3` FOREIGN KEY (`BookingID`) REFERENCES `tbl_booking` (`BookingID`);

--
-- Constraints for table `tbl_venue`
--
ALTER TABLE `tbl_venue`
  ADD CONSTRAINT `tbl_venue_ibfk_1` FOREIGN KEY (`VenueTypeID`) REFERENCES `tbl_venue_type` (`VenueTypeID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
