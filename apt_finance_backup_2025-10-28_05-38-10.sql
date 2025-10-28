-- Apt Finance Manager Database Backup
-- Generated on: 2025-10-28 05:38:10

-- Table structure for `bank_accounts`
DROP TABLE IF EXISTS `bank_accounts`;
CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('opay','kuda','moniepoint','gtbank_personal','gtbank_corporate','access_corporate','palmpay','cash') NOT NULL,
  `balance` decimal(15,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for table `bank_accounts`
INSERT INTO `bank_accounts` VALUES ('1', 'Opay', 'opay', '0.00', '1', '2025-10-24 19:47:48');
INSERT INTO `bank_accounts` VALUES ('2', 'Kuda Bank', 'kuda', '0.00', '1', '2025-10-24 19:47:48');
INSERT INTO `bank_accounts` VALUES ('3', 'MoniePoint', 'moniepoint', '0.00', '1', '2025-10-24 19:47:48');
INSERT INTO `bank_accounts` VALUES ('4', 'GTBank Personal', 'gtbank_personal', '0.00', '1', '2025-10-24 19:47:48');
INSERT INTO `bank_accounts` VALUES ('5', 'GTBank Corporate', 'gtbank_corporate', '0.00', '1', '2025-10-24 19:47:48');
INSERT INTO `bank_accounts` VALUES ('6', 'Access Bank Corporate', 'access_corporate', '0.00', '1', '2025-10-24 19:47:48');
INSERT INTO `bank_accounts` VALUES ('7', 'PalmPay', 'palmpay', '0.00', '1', '2025-10-24 19:47:48');
INSERT INTO `bank_accounts` VALUES ('8', 'Cash', 'cash', '0.00', '1', '2025-10-24 19:47:48');

-- Table structure for `clients`
DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for table `clients`
INSERT INTO `clients` VALUES ('1', 'Lanre Awolokun', 'bukolalla1@gmail.com', '234567890-', 'rtryuiop[', '2025-10-24 19:48:24', '2025-10-24 19:48:24');
INSERT INTO `clients` VALUES ('2', 'Spiro Mobility Nigeria', '', '', '', '2025-10-24 20:25:56', '2025-10-24 20:25:56');
INSERT INTO `clients` VALUES ('3', 'Mr. Jide yesufu', 'oyesufu@apostolicfaithweca.org', '', 'Gbagada, Lagos', '2025-10-25 12:34:16', '2025-10-25 12:34:16');
INSERT INTO `clients` VALUES ('4', 'Sezuor Daniels Ademoh', '', '', '', '2025-10-25 14:41:23', '2025-10-25 14:41:23');
INSERT INTO `clients` VALUES ('5', 'Apostolic Faith Secondary School', '', '', 'Lagos', '2025-10-25 22:03:15', '2025-10-25 22:03:15');
INSERT INTO `clients` VALUES ('6', 'Lanre Akinnouye', '', '', '', '2025-10-27 16:49:10', '2025-10-27 16:49:10');

-- Table structure for `company_settings`
DROP TABLE IF EXISTS `company_settings`;
CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `contact_info` text DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'NGN',
  `tax_enabled` tinyint(1) DEFAULT 0,
  `tax_rate` decimal(5,2) DEFAULT 7.50,
  `tithe_rate` decimal(5,2) DEFAULT 10.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `vat_threshold` decimal(15,2) DEFAULT 25000000.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for table `company_settings`
INSERT INTO `company_settings` VALUES ('1', 'Cyberrose Systems Limited', '', '', 'Nigeria', '₦', '1', '7.50', '10.00', '2025-10-24 13:34:05', '2025-10-24 18:59:23', '25000000.00');

-- Table structure for `generated_invoices`
DROP TABLE IF EXISTS `generated_invoices`;
CREATE TABLE `generated_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `job_order_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_name` varchar(255) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `vat_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `document_type` enum('invoice','receipt') DEFAULT 'invoice',
  `status` enum('sent','paid','overdue') DEFAULT 'sent',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `job_order_id` (`job_order_id`),
  CONSTRAINT `generated_invoices_ibfk_1` FOREIGN KEY (`job_order_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for table `generated_invoices`
INSERT INTO `generated_invoices` VALUES ('1', 'INV-20251025-001', '7', '3', 'Mr. Jide yesufu', '15000.00', '0.00', '15000.00', 'invoice', 'sent', '2025-11-24', '2025-10-25 17:03:49');

-- Table structure for `inventory`
DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `purchase_cost` decimal(15,2) NOT NULL,
  `current_value` decimal(15,2) NOT NULL,
  `purchase_date` date NOT NULL,
  `depreciation_rate` decimal(5,2) DEFAULT 0.00,
  `depreciation_method` enum('straight_line','declining_balance') DEFAULT 'straight_line',
  `useful_life_years` int(11) DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for `invoices`
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` varchar(50) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `service_description` text NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('open','completed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `client_id` int(11) DEFAULT NULL,
  `vat_amount` decimal(15,2) DEFAULT 0.00,
  `total_with_vat` decimal(15,2) DEFAULT 0.00,
  `payment_status` enum('unpaid','partly_paid','fully_paid') DEFAULT 'unpaid',
  `has_line_items` tinyint(1) DEFAULT 0,
  `line_items_total` decimal(15,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_id` (`invoice_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for table `invoices`
INSERT INTO `invoices` VALUES ('7', '251025-JOB-MRJ-001', 'Mr. Jide yesufu', 'Install Stand-Alone PTZ Camera at Gbagada residence.', '15000.00', '2025-10-25', '', 'open', '2025-10-25 12:35:47', '2025-10-25 12:35:47', '3', '0.00', '15000.00', 'unpaid', '0', '0.00');
INSERT INTO `invoices` VALUES ('10', '251025-JOB-SPI-001', 'Spiro Mobility Nigeria', 'Purchase and Installation of CCTV Surveillance Cameras, Access Controls and LAN + WIFI.', '11977343.00', '2025-10-25', 'We gave a Discount of 1,130,557', 'open', '2025-10-25 16:15:32', '2025-10-25 17:33:22', '2', '0.00', '11977343.00', 'partly_paid', '1', '11977343.00');
INSERT INTO `invoices` VALUES ('13', '010725-JOB-APO-001', 'Apostolic Faith Secondary School', 'Mikrotik Router Reconfiguration and Setup -Service restoration.', '45000.00', '2025-07-01', '', 'completed', '2025-10-25 22:13:08', '2025-10-25 22:14:34', '5', '0.00', '45000.00', 'fully_paid', '1', '45000.00');
INSERT INTO `invoices` VALUES ('14', '220825-JOB-LAN-001', 'Lanre Akinnouye', '₦324,500.00', '324500.00', '2025-08-22', '', 'open', '2025-10-27 16:50:09', '2025-10-27 16:50:09', '6', '0.00', '324500.00', 'unpaid', '1', '324500.00');
INSERT INTO `invoices` VALUES ('15', '140825-JOB-MRR-001', 'Mr. Rotimi LAGGPAD', 'Reset Smart Door Lock and Train Staff.', '10000.00', '2025-08-14', '', 'completed', '2025-10-27 22:06:24', '2025-10-27 22:07:32', NULL, '0.00', '10000.00', 'fully_paid', '1', '10000.00');

-- Table structure for `job_order_line_items`
DROP TABLE IF EXISTS `job_order_line_items`;
CREATE TABLE `job_order_line_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_order_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `total` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `job_order_id` (`job_order_id`),
  CONSTRAINT `job_order_line_items_ibfk_1` FOREIGN KEY (`job_order_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for table `job_order_line_items`
INSERT INTO `job_order_line_items` VALUES ('4', '10', 'Access Control System Hardware', '1547100.00', '1.00', '1547100.00', '2025-10-25 16:15:32');
INSERT INTO `job_order_line_items` VALUES ('5', '10', 'IP Surveillance System Hardware [CCTV]', '3628800.00', '1.00', '3628800.00', '2025-10-25 16:15:32');
INSERT INTO `job_order_line_items` VALUES ('6', '10', 'LAN (Local Area Network/Structured Cabling Active Componenets', '2122200.00', '1.00', '2122200.00', '2025-10-25 16:15:32');
INSERT INTO `job_order_line_items` VALUES ('7', '10', 'Other Data Cabling, Accessories, Labour and Professional Services/Charges', '4679243.00', '1.00', '4679243.00', '2025-10-25 16:15:32');
INSERT INTO `job_order_line_items` VALUES ('10', '13', 'Mikrotik Router Reconfiguration and Setup -Service restoration.', '45000.00', '1.00', '45000.00', '2025-10-25 22:13:08');
INSERT INTO `job_order_line_items` VALUES ('11', '14', 'TPlink In-wall supply &amp;  WIFI AP Installation', '324500.00', '1.00', '324500.00', '2025-10-27 16:50:09');
INSERT INTO `job_order_line_items` VALUES ('12', '15', 'Reset Smart Door Lock and Train Staff.', '10000.00', '1.00', '10000.00', '2025-10-27 22:06:24');

-- Table structure for `tithes`
DROP TABLE IF EXISTS `tithes`;
CREATE TABLE `tithes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` enum('owed','paid') DEFAULT 'owed',
  `date_generated` date NOT NULL,
  `date_paid` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_tithes_invoice` (`invoice_id`),
  CONSTRAINT `fk_tithes_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for table `tithes`
INSERT INTO `tithes` VALUES ('4', '13', '9000.00', 'owed', '2025-10-25', NULL, '2025-10-25 22:14:30');
INSERT INTO `tithes` VALUES ('5', '15', '1000.00', 'owed', '2025-10-27', NULL, '2025-10-27 22:07:28');

-- Table structure for `transactions`
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('inflow','outflow') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text NOT NULL,
  `category` enum('internal','invoice_linked') NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bank_account_id` int(11) DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT 0,
  `recurring_frequency` enum('monthly','quarterly','yearly') DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `seller_details` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_transactions_invoice` (`invoice_id`),
  CONSTRAINT `fk_transactions_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for table `transactions`
INSERT INTO `transactions` VALUES ('10', 'outflow', '10000.00', 'Steel Pole Fabrication', 'invoice_linked', '7', '2025-10-25', '2025-10-25 12:37:01', '8', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('18', 'inflow', '7052705.00', '60% Down Payment', 'invoice_linked', '10', '2025-08-30', '2025-10-25 16:52:34', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('20', 'outflow', '250000.00', 'CSR - Sammy', 'invoice_linked', '10', '2025-09-01', '2025-10-25 17:13:47', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('23', 'outflow', '200000.00', 'Kolade - Part Payment 1', 'invoice_linked', '10', '2025-10-25', '2025-10-25 17:15:46', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('24', 'outflow', '60000.00', 'Freight / Dispatch of Materials to Shagamu.', 'invoice_linked', '10', '2025-09-02', '2025-10-25 17:16:32', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('25', 'outflow', '1636000.00', 'ANEKWE - Purchase 01 - Lan Items', 'invoice_linked', '10', '2025-09-02', '2025-10-25 17:17:08', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('26', 'outflow', '3992000.00', 'SGS - Purchase 03 - ACS Items', 'invoice_linked', '10', '2025-09-02', '2025-10-25 17:17:42', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('27', 'outflow', '20000.00', 'Part 1 Freight / Dispatch of Materials From Lagos to Anthony.', 'invoice_linked', '10', '2025-09-04', '2025-10-25 17:18:25', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('28', 'outflow', '121800.00', 'Pipes, Flexible Pipes, Fiber Patch Panels, Cable Mnager', 'invoice_linked', '10', '2025-10-25', '2025-10-25 17:19:41', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('29', 'outflow', '465900.00', 'Emmy Success - Purchase 02 - Cabling Items', 'invoice_linked', '10', '2025-09-04', '2025-10-25 17:20:10', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('30', 'outflow', '5000.00', 'Shagamu - industrial Ladder rental', 'invoice_linked', '10', '2025-10-25', '2025-10-25 17:20:45', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('31', 'outflow', '5000.00', 'Shagamu - Cable tie at Shagamu', 'invoice_linked', '10', '2025-09-22', '2025-10-25 17:21:22', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('32', 'outflow', '3000.00', 'Kolade Shagamu transport', 'invoice_linked', '10', '2025-10-25', '2025-10-25 17:22:00', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('33', 'outflow', '300000.00', 'Inventory Purchase - Industrial 6m Ladder', 'internal', NULL, '2025-09-22', '2025-10-25 17:23:02', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('34', 'outflow', '50000.00', 'Fuel for Trips to Shagamu', 'invoice_linked', '10', '2025-10-02', '2025-10-25 17:24:00', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('35', 'outflow', '2500.00', 'Tip for Spiro - Security Man', 'invoice_linked', '10', '2025-10-01', '2025-10-25 17:24:46', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('36', 'outflow', '250000.00', 'CSR - Tegbe', 'invoice_linked', '10', '2025-10-01', '2025-10-25 17:26:57', '3', '0', NULL, '', NULL, NULL);
INSERT INTO `transactions` VALUES ('38', 'outflow', '29800.00', 'Kolade - Boxes', 'invoice_linked', '10', '2025-10-25', '2025-10-25 20:34:56', '1', '0', NULL, '', 'receipt_1761420896_68fd2660b5c6d.jpg', NULL);
INSERT INTO `transactions` VALUES ('41', 'inflow', '45000.00', 'Workmanship Payment', 'invoice_linked', '13', '2025-07-01', '2025-10-25 22:14:12', '8', '0', NULL, '', NULL, 'AFSS');
INSERT INTO `transactions` VALUES ('42', 'outflow', '25000.00', 'Workmanship Payment for Kolade', 'invoice_linked', '14', '2025-08-22', '2025-10-27 16:56:18', '8', '0', NULL, '', NULL, '');
INSERT INTO `transactions` VALUES ('43', 'inflow', '324500.00', 'Project Payment -', 'invoice_linked', '14', '2025-08-27', '2025-10-27 16:59:54', '8', '0', NULL, '', NULL, '');
INSERT INTO `transactions` VALUES ('44', 'outflow', '250000.00', 'Tplink In-Wall Wifi Access Point x2', 'invoice_linked', '14', '2025-08-28', '2025-10-27 17:01:27', '8', '0', NULL, '', NULL, '');
INSERT INTO `transactions` VALUES ('45', 'inflow', '10000.00', 'Workmanship Payment', 'invoice_linked', '15', '2025-08-15', '2025-10-27 22:07:01', '8', '0', NULL, '', NULL, '');

-- Table structure for `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','accountant','viewer') DEFAULT 'viewer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for table `users`
INSERT INTO `users` VALUES ('1', 'admin', '$2y$10$0Oowi9UlvP1ds1ynowHsI.OK1Tl/yOkjGgiDj3FAUHitB1Nko2AR6', 'admin', '2025-10-24 13:33:44');

-- Table structure for `vat_records`
DROP TABLE IF EXISTS `vat_records`;
CREATE TABLE `vat_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) DEFAULT NULL,
  `vat_amount` decimal(15,2) NOT NULL,
  `vat_rate` decimal(5,2) NOT NULL,
  `period_month` varchar(7) DEFAULT NULL,
  `status` enum('collected','paid','pending') DEFAULT 'collected',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `vat_records_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

