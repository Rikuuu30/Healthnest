-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 09, 2026 at 07:54 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_healthnest`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `table_affected` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `details` varchar(255) DEFAULT NULL,
  `created_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `created_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(5) NOT NULL,
  `category_name` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'Weight Management & Metabolic Health', 'Products supporting weight and metabolism'),
(2, 'Recovery & Tissue Repair', 'Products supporting healing and recovery'),
(3, 'Anti-Aging & Wellness', 'Products supporting anti-aging and overall wellness'),
(4, 'Performance & Hormone Optimization', 'Products supporting performance and hormone balance');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total_amount` float DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `shipping_address` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `created_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` float DEFAULT NULL,
  `subtotal` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(5) NOT NULL,
  `category_id` int(5) DEFAULT NULL,
  `product_name` varchar(150) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `price` float DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT NULL,
  `image` varchar(150) NOT NULL,
  `status` varchar(20) DEFAULT NULL,
  `created_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `category_id`, `product_name`, `description`, `price`, `stock_quantity`, `image`, `status`, `created_at`) VALUES
(1, 1, 'Semaglutide', 'A GLP-1 receptor agonist commonly used to support weight loss by reducing appetite, slowing gastric emptying, and improving blood sugar regulation.', 945, 25, 'Semaglutide.png', 'active', NULL),
(2, 1, 'Tirzepatide', 'A dual GIP/GLP-1 receptor agonist designed to promote significant weight reduction while improving insulin sensitivity and metabolic function.', 945, 50, 'Tirzepatide.png', 'active', NULL),
(3, 1, 'Retatrutide', 'A next-generation triple hormone receptor agonist (GLP-1, GIP, and glucagon) being studied for advanced weight management and metabolic health.', 1635, 50, 'Retatrutide.png', 'active', NULL),
(4, 2, 'BPC-157', 'A synthetic peptide researched for supporting tendon, ligament, muscle, and gastrointestinal tissue repair while promoting faster recovery.', 1020, 10, 'BPC-157.png', 'active', NULL),
(5, 2, 'TB-500', 'A peptide that may enhance cell migration, tissue regeneration, flexibility, and recovery following injury or intense physical activity.', 2640, 5, 'TB-500.png', 'active', NULL),
(6, 2, 'BPC-157 + TB-500', 'A combination therapy intended to maximize healing by combining the regenerative benefits of both peptides for soft tissue recovery.', 3075, 32, 'BPC-157 + TB-500.png', 'active', NULL),
(7, 3, 'GHK-Cu (Copper Peptide)', 'A naturally occurring copper peptide studied for skin rejuvenation, collagen production, wound healing, and hair growth support. ', 975, 47, 'GHK-Cu.png', 'active', NULL),
(8, 3, 'NAD+', 'A coenzyme involved in cellular energy production that is commonly used in wellness protocols to support healthy aging and mitochondrial function.', 2640, 0, 'NAD+.png', 'active', NULL),
(9, 3, 'Glutathione', 'A powerful antioxidant that helps protect cells from oxidative stress, supports liver detoxification, and promotes brighter skin appearance.', 1470, 14, 'Glutathione.png', 'active', NULL),
(10, 4, 'Ipamorelin', 'A selective growth hormone secretagogue that stimulates natural growth hormone release with minimal effect on cortisol and prolactin levels.', 1215, 55, 'Ipamorelin.png', 'active', NULL),
(11, 4, 'CJC-1295 (with or without DAC)', 'A growth hormone-releasing hormone (GHRH) analog used to stimulate sustained growth hormone production for recovery and body composition goals.', 2775, 42, 'CJC-1295.png', 'active', NULL),
(12, 4, 'Sermorelin', 'A synthetic peptide that encourages the pituitary gland to naturally release growth hormone, supporting recovery, lean muscle development, and healthy aging.', 2355, 33, 'Sermorelin.png', 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tblaccount`
--

CREATE TABLE `tblaccount` (
  `id` int(11) NOT NULL,
  `firstname` varchar(50) DEFAULT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `lastname` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `level` varchar(20) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `image` varchar(100) DEFAULT NULL,
  `created_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tblaccount`
--

INSERT INTO `tblaccount` (`id`, `firstname`, `middlename`, `lastname`, `email`, `password`, `address`, `contact`, `birthdate`, `level`, `status`, `image`, `created_at`) VALUES
(1, 'System', NULL, 'Seller', 'seller@healthnest.local', 'seller123', 'HealthNest Office', '09000000001', '1995-01-01', 'seller', 'active', 'uploads/profiles/user-1-20260715185437-d88ce5d866.jpg', CURDATE()),
(2, 'Sample', NULL, 'Buyer', 'buyer@healthnest.local', 'buyer1234', 'HealthNest Customer Address', '09000000002', '1998-01-01', 'buyer', 'active', '', CURDATE());

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `tblaccount`
--
ALTER TABLE `tblaccount`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tblaccount`
--
ALTER TABLE `tblaccount`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


-- =========================================
-- Order Tracking Update
-- =========================================

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS status_updated_at datetime DEFAULT NULL AFTER status;

CREATE TABLE IF NOT EXISTS order_status_history (
  history_id int(11) NOT NULL AUTO_INCREMENT,
  order_id int(11) DEFAULT NULL,
  status varchar(20) DEFAULT NULL,
  note varchar(255) DEFAULT NULL,
  updated_by int(11) DEFAULT NULL,
  created_at datetime DEFAULT NULL,
  PRIMARY KEY (history_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

UPDATE orders
SET status = 'paid'
WHERE status IS NULL OR status = '';

UPDATE orders
SET status_updated_at = NOW()
WHERE status_updated_at IS NULL;

INSERT INTO order_status_history (order_id, status, note, updated_by, created_at)
SELECT o.order_id, o.status, 'Existing order status imported.', o.user_id, o.status_updated_at
FROM orders o
WHERE NOT EXISTS (
  SELECT 1
  FROM order_status_history h
  WHERE h.order_id = o.order_id
);
