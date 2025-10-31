-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 29, 2025 at 09:14 PM
-- Server version: 8.0.42-cll-lve
-- PHP Version: 8.3.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cyberros_Vehicletrack`
--

-- --------------------------------------------------------

--
-- Table structure for table `cooking_gas_logs`
--

CREATE TABLE `cooking_gas_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `purchase_date` date NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `electricity_logs`
--

CREATE TABLE `electricity_logs` (
  `id` int NOT NULL,
  `meter_id` int NOT NULL,
  `purchase_date` date NOT NULL,
  `units_purchased` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `electricity_logs`
--

INSERT INTO `electricity_logs` (`id`, `meter_id`, `purchase_date`, `units_purchased`, `cost`, `notes`, `created_at`) VALUES
(2, 1, '2025-06-24', 52.40, 4000.00, '', '2025-06-24 08:30:32');

-- --------------------------------------------------------

--
-- Table structure for table `electricity_meters`
--

CREATE TABLE `electricity_meters` (
  `id` int NOT NULL,
  `meter_number` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `vehicle_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `electricity_meters`
--

INSERT INTO `electricity_meters` (`id`, `meter_number`, `description`, `vehicle_id`, `created_at`, `user_id`) VALUES
(1, '45701798832', 'Home meter', NULL, '2025-06-24 00:55:25', NULL),
(2, '4567890', 'dfghjik', NULL, '2025-07-01 16:57:09', 1);

-- --------------------------------------------------------

--
-- Table structure for table `fuel_logs`
--

CREATE TABLE `fuel_logs` (
  `id` int NOT NULL COMMENT 'Primary key for the fuel log entry',
  `purchase_date` date NOT NULL COMMENT 'Date of the fuel purchase',
  `volume` decimal(10,2) NOT NULL COMMENT 'Volume of fuel purchased (e.g., in liters)',
  `cost` decimal(10,2) NOT NULL COMMENT 'Total cost of the fuel purchase',
  `odometer_reading` int DEFAULT NULL COMMENT 'Odometer reading at the time of purchase (optional)',
  `vehicle_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Logs fuel purchases';

--
-- Dumping data for table `fuel_logs`
--

INSERT INTO `fuel_logs` (`id`, `purchase_date`, `volume`, `cost`, `odometer_reading`, `vehicle_id`, `user_id`) VALUES
(1, '2025-05-30', 10.00, 36000.00, 266122, NULL, NULL),
(2, '2025-05-31', 80.00, 500000.00, 5455855, NULL, NULL),
(3, '2025-06-23', 333.00, 3333.00, 333, NULL, NULL),
(7, '2025-06-28', 10.81, 10000.00, 250695, 1, NULL),
(8, '2025-07-05', 16.12, 15000.00, 250825, 1, 1),
(9, '2025-07-11', 34.70, 30000.00, 251227, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int NOT NULL COMMENT 'Primary key for the item',
  `name` varchar(255) NOT NULL COMMENT 'Name of the item (e.g., Car Insurance, Road Worthiness)',
  `item_type` varchar(50) DEFAULT 'general',
  `expiry_date` date DEFAULT NULL COMMENT 'Current expiry date of the item',
  `last_renewal_date` date DEFAULT NULL COMMENT 'Date of the last renewal',
  `last_renewal_cost` decimal(10,2) DEFAULT NULL COMMENT 'Cost of the last renewal',
  `vehicle_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Stores details of renewable vehicle items';

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `name`, `item_type`, `expiry_date`, `last_renewal_date`, `last_renewal_cost`, `vehicle_id`, `user_id`) VALUES
(1, 'Car Insurance', 'general', NULL, NULL, 0.00, NULL, NULL),
(2, 'Road Worthiness', 'general', NULL, NULL, 0.00, NULL, NULL),
(3, 'Vehicle Licence', 'general', NULL, NULL, 0.00, NULL, NULL),
(11, 'Car Insurance', 'insurance', '2025-06-05', '2025-05-15', 5677.00, 1, NULL),
(12, 'Road Worthiness', 'roadworthiness', '2025-09-25', '2025-06-24', 20220.00, 1, NULL),
(13, 'Vehicle Licence', 'license', '2025-08-23', '2025-06-24', 10202.00, 1, NULL),
(16, 'Car Insurance', 'insurance', NULL, NULL, 0.00, 1, 2),
(17, 'Road Worthiness', 'roadworthiness', NULL, NULL, 0.00, 1, 2),
(18, 'Vehicle Licence', 'license', NULL, NULL, 0.00, 1, 2),
(19, 'Driver\'s License', 'driver_license', NULL, NULL, 0.00, 1, 2),
(20, 'Car Ownership Certificate', 'ownership_certificate', NULL, NULL, 0.00, 1, 2),
(21, 'Car Insurance', 'insurance', '2026-07-04', '2025-07-01', 20000.00, 1, 1),
(22, 'Road Worthiness', 'roadworthiness', '2025-12-27', '2025-06-27', 10000.00, 1, 1),
(23, 'Vehicle Licence', 'license', '2026-06-30', '2025-06-27', 8000.00, 1, 1),
(24, 'Driver\'s License', 'driver_license', '2028-02-09', '2023-02-10', 20000.00, 1, 1),
(25, 'Car Ownership Certificate', 'ownership_certificate', '2025-10-10', '2024-10-10', 5000.00, 1, 1),
(31, 'Car Insurance', 'insurance', NULL, NULL, 0.00, 5, 4),
(32, 'Road Worthiness', 'roadworthiness', NULL, NULL, 0.00, 5, 4),
(33, 'Vehicle Licence', 'license', NULL, NULL, 0.00, 5, 4),
(34, 'Driver\'s License', 'driver_license', NULL, NULL, 0.00, 5, 4),
(35, 'Car Ownership Certificate', 'ownership_certificate', NULL, NULL, 0.00, 5, 4);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_logs`
--

CREATE TABLE `maintenance_logs` (
  `id` int NOT NULL COMMENT 'Primary key for the maintenance log entry',
  `maintenance_date` date NOT NULL COMMENT 'Date when the maintenance was performed',
  `work_done` text NOT NULL COMMENT 'Description of the maintenance work done',
  `cost` decimal(10,2) DEFAULT NULL COMMENT 'Cost of the maintenance (optional)',
  `vehicle_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Logs vehicle maintenance activities';

--
-- Dumping data for table `maintenance_logs`
--

INSERT INTO `maintenance_logs` (`id`, `maintenance_date`, `work_done`, `cost`, `vehicle_id`, `user_id`) VALUES
(1, '2025-05-30', 'edwe', 100000.00, NULL, NULL),
(2, '2025-06-23', 'kjhbekl', 5000.00, NULL, NULL),
(6, '2025-07-05', 'Total service', 300000.00, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `mileage_logs`
--

CREATE TABLE `mileage_logs` (
  `id` int NOT NULL COMMENT 'Primary key for the mileage log entry',
  `log_date` date NOT NULL COMMENT 'Date when the mileage was logged',
  `mileage_reading` int NOT NULL COMMENT 'The mileage reading',
  `comment` text COMMENT 'Optional comment for the mileage log',
  `vehicle_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Logs vehicle mileage readings';

--
-- Dumping data for table `mileage_logs`
--

INSERT INTO `mileage_logs` (`id`, `log_date`, `mileage_reading`, `comment`, `vehicle_id`, `user_id`) VALUES
(1, '2025-06-23', 200366666, '', NULL, NULL),
(4, '2025-06-25', 250600, '', 1, NULL),
(5, '2025-06-28', 250695, 'At the Total filling station Gbagada', 1, NULL),
(6, '2025-07-10', 251171, 'After trip to Pwu, daddy Majors place', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '7WSdZDH!M3Q&%cJN', '2025-06-24 00:02:25'),
(2, 'Dapoa', 'Aduragba2025$', '2025-06-24 01:24:37'),
(3, 'Tope', 'Admin4tope', '2025-06-24 08:12:21'),
(4, 'demoaccount', 'DemoPa$$word007@', '2025-07-01 16:24:01');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int NOT NULL,
  `make` varchar(100) NOT NULL,
  `model` varchar(100) NOT NULL,
  `plate_number` varchar(50) NOT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `make`, `model`, `plate_number`, `vehicle_type`, `created_at`, `user_id`) VALUES
(1, 'Toyota', 'Matrix 2004', 'KTU805GF', 'Sedan', '2025-06-24 00:17:55', NULL),
(5, 'Toyota', 'Matrix 2004 x2n', 'KTU805GFn', 'Sedan', '2025-07-01 16:49:57', 4);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cooking_gas_logs`
--
ALTER TABLE `cooking_gas_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `electricity_logs`
--
ALTER TABLE `electricity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_electricity_log_meter` (`meter_id`);

--
-- Indexes for table `electricity_meters`
--
ALTER TABLE `electricity_meters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `meter_number` (`meter_number`),
  ADD KEY `fk_electricity_meter_vehicle` (`vehicle_id`);

--
-- Indexes for table `fuel_logs`
--
ALTER TABLE `fuel_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_date_idx` (`purchase_date`) COMMENT 'Index on purchase_date for faster sorting/filtering',
  ADD KEY `fk_vehicle_fuel` (`vehicle_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_items_name_vehicle_user` (`name`,`vehicle_id`,`user_id`),
  ADD KEY `fk_vehicle_item` (`vehicle_id`);

--
-- Indexes for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `maintenance_date_idx` (`maintenance_date`) COMMENT 'Index on maintenance_date for faster sorting/filtering',
  ADD KEY `fk_vehicle_maintenance` (`vehicle_id`);

--
-- Indexes for table `mileage_logs`
--
ALTER TABLE `mileage_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `log_date_idx` (`log_date`) COMMENT 'Index on log_date for faster sorting/filtering',
  ADD KEY `fk_vehicle_mileage` (`vehicle_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vehicles_plate_user` (`plate_number`,`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cooking_gas_logs`
--
ALTER TABLE `cooking_gas_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `electricity_logs`
--
ALTER TABLE `electricity_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `electricity_meters`
--
ALTER TABLE `electricity_meters`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `fuel_logs`
--
ALTER TABLE `fuel_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary key for the fuel log entry', AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary key for the item', AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary key for the maintenance log entry', AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `mileage_logs`
--
ALTER TABLE `mileage_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary key for the mileage log entry', AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cooking_gas_logs`
--
ALTER TABLE `cooking_gas_logs`
  ADD CONSTRAINT `cooking_gas_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `electricity_logs`
--
ALTER TABLE `electricity_logs`
  ADD CONSTRAINT `fk_electricity_log_meter` FOREIGN KEY (`meter_id`) REFERENCES `electricity_meters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `electricity_meters`
--
ALTER TABLE `electricity_meters`
  ADD CONSTRAINT `fk_electricity_meter_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `fuel_logs`
--
ALTER TABLE `fuel_logs`
  ADD CONSTRAINT `fk_vehicle_fuel` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `fk_vehicle_item` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD CONSTRAINT `fk_vehicle_maintenance` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `mileage_logs`
--
ALTER TABLE `mileage_logs`
  ADD CONSTRAINT `fk_vehicle_mileage` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
