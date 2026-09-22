mysqldump: [Warning] Using a password on the command line interface can be insecure.
-- Warning: column statistics not supported by the server.
-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: myBot
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_actions`
--

DROP TABLE IF EXISTS `admin_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint(20) unsigned NOT NULL,
  `action` varchar(100) NOT NULL COMMENT 'user.activate, user.deactivate, balance.add, wheel.grant_spin, ...',
  `target_type` varchar(100) DEFAULT NULL COMMENT 'Model class أو نوع الهدف',
  `target_id` bigint(20) unsigned DEFAULT NULL COMMENT 'ID الهدف',
  `changes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{field: {old, new}}' CHECK (json_valid(`changes`)),
  `reason` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_act_admin_date` (`admin_id`,`created_at`),
  KEY `admin_act_action_date` (`action`,`created_at`),
  KEY `admin_act_target` (`target_type`,`target_id`),
  KEY `admin_actions_action_index` (`action`),
  CONSTRAINT `admin_actions_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_actions`
--

LOCK TABLES `admin_actions` WRITE;
/*!40000 ALTER TABLE `admin_actions` DISABLE KEYS */;
INSERT INTO `admin_actions` VALUES (1,1,'user.promote','App\\Models\\User',3,'{\"role\":{\"old\":\"user\",\"new\":\"admin\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 02:11:39'),(2,1,'user.demote_to_user','App\\Models\\User',3,'{\"role\":{\"old\":\"admin\",\"new\":\"user\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 02:12:54'),(3,1,'user.promote','App\\Models\\User',3,'{\"role\":{\"old\":\"user\",\"new\":\"admin\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 02:13:18'),(4,1,'user.demote_to_user','App\\Models\\User',3,'{\"role\":{\"old\":\"admin\",\"new\":\"user\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 02:13:49'),(5,1,'user.promote','App\\Models\\User',4,'{\"role\":{\"old\":\"user\",\"new\":\"admin\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 02:56:03'),(6,1,'user.promote','App\\Models\\User',3,'{\"role\":{\"old\":\"user\",\"new\":\"admin\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 08:57:54'),(7,1,'user.demote_to_user','App\\Models\\User',3,'{\"role\":{\"old\":\"admin\",\"new\":\"user\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 08:59:00'),(8,1,'user.promote','App\\Models\\User',5,'{\"role\":{\"old\":\"user\",\"new\":\"admin\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 09:15:02'),(9,1,'user.promote','App\\Models\\User',7,'{\"role\":{\"old\":\"user\",\"new\":\"admin\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 09:59:58'),(10,1,'user.demote_to_user','App\\Models\\User',7,'{\"role\":{\"old\":\"admin\",\"new\":\"user\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 10:00:57'),(11,1,'user.promote','App\\Models\\User',7,'{\"role\":{\"old\":\"user\",\"new\":\"admin\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 10:05:41'),(12,1,'user.demote_to_user','App\\Models\\User',7,'{\"role\":{\"old\":\"admin\",\"new\":\"user\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 10:12:27'),(13,1,'user.promote','App\\Models\\User',7,'{\"role\":{\"old\":\"user\",\"new\":\"admin\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 10:12:42'),(14,1,'user.demote_to_user','App\\Models\\User',7,'{\"role\":{\"old\":\"admin\",\"new\":\"user\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 10:16:32'),(15,1,'user.promote','App\\Models\\User',7,'{\"role\":{\"old\":\"user\",\"new\":\"admin\"}}',NULL,'127.0.0.1','Symfony','2026-09-21 10:17:18');
/*!40000 ALTER TABLE `admin_actions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deposit_methods`
--

DROP TABLE IF EXISTS `deposit_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deposit_methods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `icon` varchar(10) NOT NULL DEFAULT '?',
  `currency` varchar(3) NOT NULL DEFAULT 'SYP',
  `gateway_type` enum('syriatel','shamcash','manual') NOT NULL DEFAULT 'manual' COMMENT 'نوع البوابة للتحقق التلقائي',
  `account_number` varchar(100) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `receiver_gsm` varchar(20) DEFAULT NULL COMMENT 'رقم GSM المستقبل (سيرياتيل)',
  `receiver_address` varchar(100) DEFAULT NULL COMMENT 'عنوان المحفظة (شام كاش)',
  `instructions` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `min_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `max_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `commission_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `auto_verify` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'تفعيل التحقق التلقائي',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `deposit_methods_code_unique` (`code`),
  KEY `dm_active_sort` (`is_active`,`sort_order`),
  KEY `deposit_methods_gateway_type_index` (`gateway_type`),
  KEY `deposit_methods_is_active_index` (`is_active`),
  KEY `deposit_methods_sort_order_index` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deposit_methods`
--

LOCK TABLES `deposit_methods` WRITE;
/*!40000 ALTER TABLE `deposit_methods` DISABLE KEYS */;
/*!40000 ALTER TABLE `deposit_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exchange_rates`
--

DROP TABLE IF EXISTS `exchange_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchange_rates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `from_currency` varchar(3) NOT NULL COMMENT 'SYP أو USD',
  `to_currency` varchar(3) NOT NULL COMMENT 'SYP أو USD',
  `rate` decimal(18,6) NOT NULL COMMENT 'سعر الصرف',
  `buy_rate` decimal(18,6) DEFAULT NULL COMMENT 'سعر الشراء (اختياري)',
  `sell_rate` decimal(18,6) DEFAULT NULL COMMENT 'سعر البيع (اختياري)',
  `commission_percent` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'نسبة العمولة %',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exchange_rates_updated_by_foreign` (`updated_by`),
  KEY `exchange_rates_lookup` (`from_currency`,`to_currency`,`is_active`),
  KEY `exchange_rates_is_active_index` (`is_active`),
  CONSTRAINT `exchange_rates_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exchange_rates`
--

LOCK TABLES `exchange_rates` WRITE;
/*!40000 ALTER TABLE `exchange_rates` DISABLE KEYS */;
INSERT INTO `exchange_rates` VALUES (1,'USD','NSP',140.000000,NULL,NULL,0.00,1,1,'تعديل من لوحة الأدمن','2026-09-21 09:00:14','2026-09-21 09:00:14');
/*!40000 ALTER TABLE `exchange_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gem_balances`
--

DROP TABLE IF EXISTS `gem_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gem_balances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `balance` int(11) NOT NULL DEFAULT 0,
  `total_earned` int(11) NOT NULL DEFAULT 0,
  `total_spent` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gem_balances_user_id_unique` (`user_id`),
  KEY `gem_balances_balance_index` (`balance`),
  CONSTRAINT `gem_balances_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gem_balances`
--

LOCK TABLES `gem_balances` WRITE;
/*!40000 ALTER TABLE `gem_balances` DISABLE KEYS */;
/*!40000 ALTER TABLE `gem_balances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gem_transactions`
--

DROP TABLE IF EXISTS `gem_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gem_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `amount` int(11) NOT NULL,
  `type` varchar(20) NOT NULL,
  `source` varchar(30) NOT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gem_transactions_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `gem_transactions_type_index` (`type`),
  KEY `gem_transactions_source_index` (`source`),
  CONSTRAINT `gem_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gem_transactions`
--

LOCK TABLES `gem_transactions` WRITE;
/*!40000 ALTER TABLE `gem_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `gem_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gift_codes`
--

DROP TABLE IF EXISTS `gift_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gift_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL COMMENT 'الكود الفريد مثل GIFT_A3F9B2C1',
  `value` decimal(18,2) NOT NULL COMMENT 'قيمة الهدية',
  `currency` enum('NSP','USD') NOT NULL DEFAULT 'NSP' COMMENT 'العملة NSP ل.س أو USD',
  `max_uses` int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'الحد الأقصى للمستخدمين (1 = أول مستخدم فقط)',
  `used_count` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('active','used','expired','disabled') NOT NULL DEFAULT 'active' COMMENT 'حالة الكود',
  `created_by` bigint(20) unsigned NOT NULL,
  `channel_id` bigint(20) DEFAULT NULL COMMENT 'معرّف قناة النشر (قيمة سالبة)',
  `channel_msg_id` bigint(20) DEFAULT NULL COMMENT 'معرّف رسالة القناة لتعديلها بعد الاستخدام',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'تاريخ انتهاء الصلاحية',
  `note` text DEFAULT NULL COMMENT 'ملاحظة داخلية للأدمن',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gift_codes_code_unique` (`code`),
  KEY `gc_status_idx` (`status`),
  KEY `gc_status_expires_idx` (`status`,`expires_at`),
  KEY `gc_created_by_idx` (`created_by`),
  KEY `gc_currency_idx` (`currency`),
  CONSTRAINT `gift_codes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gift_codes`
--

LOCK TABLES `gift_codes` WRITE;
/*!40000 ALTER TABLE `gift_codes` DISABLE KEYS */;
INSERT INTO `gift_codes` VALUES (1,'GIFT_AMYZILWS',200.00,'NSP',1,1,'used',1,-1004392951392,NULL,'2026-09-21 08:50:51',NULL,'2026-09-21 08:45:51','2026-09-21 08:46:19');
/*!40000 ALTER TABLE `gift_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gift_redemptions`
--

DROP TABLE IF EXISTS `gift_redemptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gift_redemptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gift_code_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `value` decimal(18,2) NOT NULL COMMENT 'القيمة وقت الاستبدال',
  `currency` enum('NSP','USD') NOT NULL COMMENT 'العملة وقت الاستبدال',
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `redeemed_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'وقت الاستبدال',
  PRIMARY KEY (`id`),
  UNIQUE KEY `gr_code_user_unique` (`gift_code_id`,`user_id`),
  KEY `gr_user_idx` (`user_id`),
  KEY `gr_redeemed_idx` (`redeemed_at`),
  KEY `gr_transaction_idx` (`transaction_id`),
  CONSTRAINT `gift_redemptions_gift_code_id_foreign` FOREIGN KEY (`gift_code_id`) REFERENCES `gift_codes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `gift_redemptions_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `gift_redemptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gift_redemptions`
--

LOCK TABLES `gift_redemptions` WRITE;
/*!40000 ALTER TABLE `gift_redemptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `gift_redemptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ichancy_accounts`
--

DROP TABLE IF EXISTS `ichancy_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ichancy_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `ichancy_player_id` varchar(255) NOT NULL,
  `ichancy_username` varchar(255) NOT NULL,
  `ichancy_password_encrypted` varchar(255) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'NSP',
  `balance_cache` decimal(15,2) NOT NULL DEFAULT 0.00,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ichancy_accounts_ichancy_player_id_unique` (`ichancy_player_id`),
  KEY `ichancy_accounts_user_id_foreign` (`user_id`),
  KEY `ichancy_accounts_ichancy_username_index` (`ichancy_username`),
  CONSTRAINT `ichancy_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ichancy_accounts`
--

LOCK TABLES `ichancy_accounts` WRITE;
/*!40000 ALTER TABLE `ichancy_accounts` DISABLE KEYS */;
INSERT INTO `ichancy_accounts` VALUES (3,4,'492661267','Vexora_Ali23','eyJpdiI6Ink4QzJuZ0dxV21IQ3kwYnJPcHZ6bGc9PSIsInZhbHVlIjoibVo4ZlQzbVhYUlFjNzdiVGJ4dFY2dz09IiwibWFjIjoiMDM4MDkyMDIzZTcxZmI0ZWY0ZjkxYzljZTEwZjdhZjE5MTI1M2Y5MmJjY2Y0OGY3MTM1YzI4MTY0ZDg0NzZmNSIsInRhZyI6IiJ9','NSP',0.00,'2026-09-21 02:55:41',1,'2026-09-21 02:55:15','2026-09-21 02:55:41'),(6,7,'492707542','Vexora_djfndd','eyJpdiI6IjZzRGVhdUc2UThLb3p5UEpwQ09Benc9PSIsInZhbHVlIjoiZWgzc3pZZGpNc0JVTjZhRWQ3YnVCdz09IiwibWFjIjoiYWMwM2RlMWEzYzJiMzVkYWI3MzZlYWJlMmI5NDk3YmZiODUxZDNmYjhmZGNlMDc1ZjllYjg3NzJhMWJiMzFjMyIsInRhZyI6IiJ9','NSP',0.00,'2026-09-21 09:59:46',1,'2026-09-21 09:58:01','2026-09-21 09:59:46');
/*!40000 ALTER TABLE `ichancy_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_09_11_213631_create_wallets_table',1),(5,'2026_09_11_213640_create_deposit_methods_table',1),(6,'2026_09_11_213650_create_user_payment_accounts_table',1),(7,'2026_09_11_213700_create_exchange_rates_table',1),(8,'2026_09_11_213745_create_transactions_table',1),(9,'2026_09_12_092237_create_referral_settings_table',1),(10,'2026_09_12_092248_create_referrals_table',1),(11,'2026_09_12_092258_create_referral_rewards_table',1),(12,'2026_09_12_092310_create_referral_cycles_table',1),(13,'2026_09_12_092321_create_referral_cycle_rewards_table',1),(14,'2026_09_12_140312_create_settings_table',1),(15,'2026_09_12_202710_create_support_agents_table',1),(16,'2026_09_14_114249_create_wheels_table',1),(17,'2026_09_14_114330_create_wheel_prizes_table',1),(18,'2026_09_14_114347_create_wheel_user_states_table',1),(19,'2026_09_14_114403_create_wheel_spins_table',1),(20,'2026_09_14_214756_create_transaction_audits_table',1),(21,'2026_09_14_214828_create_admin_actions_table',1),(22,'2026_09_14_214857_create_sensitive_access_logs_table',1),(23,'2026_09_17_100000_create_ichancy_accounts_table',1),(24,'2026_09_17_130745_rename_balance_syp_to_nsp',1),(25,'2026_09_18_193733_create_gift_codes_table',1),(26,'2026_09_18_194309_create_gift_redemptions_table',1),(27,'2026_09_20_053419_add_ichancy_display_rate_setting',1),(28,'2026_09_20_171609_add_deposit_bonus_settings',1),(29,'2026_09_20_173646_add_deposit_bonus_to_transactions_type',1),(30,'2026_09_20_230256_add_signup_bonus_settings',1),(31,'2026_09_20_230420_add_signup_bonus_to_transactions_type',1),(32,'2026_09_20_234701_create_gems_tables',1),(33,'2026_09_20_234815_add_gems_settings',1),(34,'2026_09_20_235131_add_gems_exchange_to_transactions_type',1),(35,'2026_09_21_040000_add_telegram_id_to_support_agents',1),(36,'2026_09_21_050151_add_join_message_id_to_support_agents',2),(37,'2026_09_21_050452_add_admin_welcome_message_id_to_users',3);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referral_cycle_rewards`
--

DROP TABLE IF EXISTS `referral_cycle_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_cycle_rewards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cycle_id` bigint(20) unsigned NOT NULL,
  `referrer_id` bigint(20) unsigned NOT NULL,
  `total_burned_l1` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_burned_l2` decimal(18,2) NOT NULL DEFAULT 0.00,
  `reward_l1` decimal(18,2) NOT NULL DEFAULT 0.00,
  `reward_l2` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_reward` decimal(18,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'SYP',
  `percent_l1` decimal(5,2) NOT NULL DEFAULT 0.00,
  `percent_l2` decimal(5,2) NOT NULL DEFAULT 0.00,
  `referred_count_l1` int(10) unsigned NOT NULL DEFAULT 0,
  `referred_count_l2` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cycle_referrer_unique` (`cycle_id`,`referrer_id`),
  KEY `referral_cycle_rewards_referrer_id_index` (`referrer_id`),
  KEY `referral_cycle_rewards_status_index` (`status`),
  CONSTRAINT `referral_cycle_rewards_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `referral_cycles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referral_cycle_rewards_referrer_id_foreign` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referral_cycle_rewards`
--

LOCK TABLES `referral_cycle_rewards` WRITE;
/*!40000 ALTER TABLE `referral_cycle_rewards` DISABLE KEYS */;
/*!40000 ALTER TABLE `referral_cycle_rewards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referral_cycles`
--

DROP TABLE IF EXISTS `referral_cycles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_cycles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('open','processing','closed','cancelled') NOT NULL DEFAULT 'open',
  `total_burned` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_rewards` decimal(18,2) NOT NULL DEFAULT 0.00,
  `referrers_count` int(10) unsigned NOT NULL DEFAULT 0,
  `referred_count` int(10) unsigned NOT NULL DEFAULT 0,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cycle_dates_unique` (`start_date`,`end_date`),
  KEY `referral_cycles_start_date_index` (`start_date`),
  KEY `referral_cycles_end_date_index` (`end_date`),
  KEY `referral_cycles_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referral_cycles`
--

LOCK TABLES `referral_cycles` WRITE;
/*!40000 ALTER TABLE `referral_cycles` DISABLE KEYS */;
/*!40000 ALTER TABLE `referral_cycles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referral_rewards`
--

DROP TABLE IF EXISTS `referral_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_rewards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `referrer_id` bigint(20) unsigned NOT NULL,
  `referred_id` bigint(20) unsigned DEFAULT NULL,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `level` tinyint(4) NOT NULL DEFAULT 1,
  `type` enum('instant','cycle') NOT NULL DEFAULT 'instant',
  `basis` enum('deposit','burn') NOT NULL DEFAULT 'deposit',
  `amount` decimal(18,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'SYP',
  `commission_percent` decimal(5,2) NOT NULL,
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'paid',
  `notes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referral_rewards_referrer_id_index` (`referrer_id`),
  KEY `referral_rewards_referred_id_index` (`referred_id`),
  KEY `referral_rewards_transaction_id_index` (`transaction_id`),
  KEY `rr_type_basis` (`type`,`basis`),
  KEY `rr_status_created` (`status`,`created_at`),
  KEY `referral_rewards_status_index` (`status`),
  CONSTRAINT `referral_rewards_referred_id_foreign` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `referral_rewards_referrer_id_foreign` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referral_rewards_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referral_rewards`
--

LOCK TABLES `referral_rewards` WRITE;
/*!40000 ALTER TABLE `referral_rewards` DISABLE KEYS */;
/*!40000 ALTER TABLE `referral_rewards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referral_settings`
--

DROP TABLE IF EXISTS `referral_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referral_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `instant_level_1_percent` decimal(5,2) NOT NULL DEFAULT 5.00,
  `instant_level_2_percent` decimal(5,2) NOT NULL DEFAULT 2.00,
  `cycle_level_1_percent` decimal(5,2) NOT NULL DEFAULT 10.00,
  `cycle_level_2_percent` decimal(5,2) NOT NULL DEFAULT 3.00,
  `cycle_days` int(10) unsigned NOT NULL DEFAULT 10,
  `min_instant_reward` decimal(18,2) NOT NULL DEFAULT 0.00,
  `max_instant_reward` decimal(18,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referral_settings_updated_by_foreign` (`updated_by`),
  KEY `referral_settings_is_active_index` (`is_active`),
  CONSTRAINT `referral_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referral_settings`
--

LOCK TABLES `referral_settings` WRITE;
/*!40000 ALTER TABLE `referral_settings` DISABLE KEYS */;
INSERT INTO `referral_settings` VALUES (1,5.00,2.00,10.00,3.00,10,0.00,0.00,1,NULL,'2026-09-21 01:33:22','2026-09-21 01:33:22');
/*!40000 ALTER TABLE `referral_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referrals`
--

DROP TABLE IF EXISTS `referrals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referrals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `referrer_id` bigint(20) unsigned NOT NULL,
  `referred_id` bigint(20) unsigned NOT NULL,
  `level` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1 أو 2',
  `type` enum('instant','cycle') NOT NULL DEFAULT 'instant',
  `status` enum('active','inactive','blocked') NOT NULL DEFAULT 'active',
  `total_deposited` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_withdrawn` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_burned` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_earned` decimal(18,2) NOT NULL DEFAULT 0.00,
  `deposits_count` int(10) unsigned NOT NULL DEFAULT 0,
  `withdrawals_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_unique` (`referrer_id`,`referred_id`,`level`),
  KEY `referrals_referrer_id_index` (`referrer_id`),
  KEY `referrals_referred_id_index` (`referred_id`),
  KEY `ref_type_status` (`type`,`status`),
  KEY `referrals_status_index` (`status`),
  CONSTRAINT `referrals_referred_id_foreign` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referrals_referrer_id_foreign` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referrals`
--

LOCK TABLES `referrals` WRITE;
/*!40000 ALTER TABLE `referrals` DISABLE KEYS */;
/*!40000 ALTER TABLE `referrals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sensitive_access_logs`
--

DROP TABLE IF EXISTS `sensitive_access_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sensitive_access_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `accessed_by` bigint(20) unsigned DEFAULT NULL,
  `resource_type` enum('password','pin','api_key','token','personal_data') NOT NULL,
  `resource_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL DEFAULT 'read' COMMENT 'read, decrypt, update, delete',
  `reason` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sal_user_created` (`user_id`,`created_at`),
  KEY `sal_accessor_created` (`accessed_by`,`created_at`),
  KEY `sal_resource_action` (`resource_type`,`action`),
  KEY `sensitive_access_logs_resource_type_index` (`resource_type`),
  KEY `sensitive_access_logs_created_at_index` (`created_at`),
  CONSTRAINT `sensitive_access_logs_accessed_by_foreign` FOREIGN KEY (`accessed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sensitive_access_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sensitive_access_logs`
--

LOCK TABLES `sensitive_access_logs` WRITE;
/*!40000 ALTER TABLE `sensitive_access_logs` DISABLE KEYS */;
INSERT INTO `sensitive_access_logs` VALUES (1,NULL,NULL,'password',3,'read','User viewed own password','127.0.0.1','Symfony','2026-09-21 02:11:17'),(2,NULL,NULL,'password',5,'read','User viewed own password','127.0.0.1','Symfony','2026-09-21 09:12:57'),(3,7,7,'password',7,'read','User viewed own password','127.0.0.1','Symfony','2026-09-21 09:59:46');
/*!40000 ALTER TABLE `sensitive_access_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `type` enum('string','int','bool','json','decimal') NOT NULL DEFAULT 'string',
  `group` varchar(50) NOT NULL DEFAULT 'general',
  `label` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_editable` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`),
  KEY `settings_group_index` (`group`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'ichancy.display_rate','100','int','ichancy','سعر العرض (NSP → NPS)','سعر تحويل NSP إلى NPS في IChancy',1,'2026-09-21 01:33:20','2026-09-21 01:33:20'),(2,'ichancy.currency','NSP','string','ichancy','العملة','عملة التعامل مع IChancy',0,'2026-09-21 01:33:20','2026-09-21 01:33:22'),(3,'deposit_bonus.enabled','1','bool','deposit_bonus','تفعيل مكافآت الإيداع','تفعيل/تعطيل مكافآت الإيداع التلقائية',1,'2026-09-21 01:33:20','2026-09-21 08:48:16'),(4,'deposit_bonus.percent','10','int','deposit_bonus','نسبة مكافأة الإيداع (%)','النسبة المئوية المضافة كرصيد عند كل إيداع',1,'2026-09-21 01:33:20','2026-09-21 01:33:20'),(5,'signup_bonus.enabled','1','bool','signup_bonus','تفعيل مكافأة التسجيل','منح رصيد تلقائي عند التسجيل',1,'2026-09-21 01:33:20','2026-09-21 08:47:34'),(6,'signup_bonus.amount_nsp','100','int','signup_bonus','مبلغ NSP','مكافأة التسجيل بالـ NSP',1,'2026-09-21 01:33:20','2026-09-21 01:33:20'),(7,'signup_bonus.amount_usd','0','decimal','signup_bonus','مبلغ USD','مكافأة التسجيل بالـ USD',1,'2026-09-21 01:33:20','2026-09-21 01:33:20'),(8,'gems.enabled','1','bool','gems','تفعيل نظام الجواهر','تفعيل/تعطيل نظام الجواهر بالكامل',1,'2026-09-21 01:33:22','2026-09-21 09:02:31'),(9,'gems.min_deposit_nsp','100','int','gems','الحد الأدنى للاكتساب (NSP)','أقل مبلغ إيداع بالـ NSP لاكتساب جوهرة',1,'2026-09-21 01:33:22','2026-09-21 08:50:18'),(10,'gems.min_deposit_usd','5','decimal','gems','الحد الأدنى للاكتساب (USD)','أقل مبلغ إيداع بالـ USD لاكتساب جوهرة',1,'2026-09-21 01:33:22','2026-09-21 08:50:39'),(11,'gems.exchange_min_gems','50','int','gems','الحد الأدنى لاستبدال الرصيد','أقل عدد جواهر لاستبدالها برصيد',1,'2026-09-21 01:33:22','2026-09-21 08:51:12'),(12,'gems.exchange_value_nsp','600','int','gems','قيمة استبدال الرصيد (NSP)','قيمة الاستبدال بالـ NSP مقابل الحد الأدنى من الجواهر',1,'2026-09-21 01:33:22','2026-09-21 08:50:57'),(13,'gems.wheel_min_gems','600','int','gems','الحد الأدنى لفتح العجلة','عدد الجواهر المطلوبة لفتح لفة عجلة',1,'2026-09-21 01:33:22','2026-09-21 08:53:17'),(14,'gems.wheel_spins','1','int','gems','عدد لفات العجلة المكتسبة','عدد لفات العجلة المكتسبة مقابل الجواهر',1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(15,'bot_name_prefix','Vexora','string','general','بادئة اسم المستخدم','البادئة التي تُضاف لأسماء المستخدمين الجدد (مثال: Vexora → Vexora_ahmad)',1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(16,'general_channel','@VexoraChannel','string','general','القناة العامة','القناة العامة للبوت (تُستخدم للإشعارات العامة)',1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(17,'withdraw_fee_percent','2','decimal','finance','عمولة السحب %','نسبة العمولة على السحوبات',1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(18,'withdraws_enabled','1','bool','finance','تفعيل السحب','تفعيل/تعطيل خدمة السحب',1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(19,'maintenance_mode','0','bool','maintenance','وضع الصيانة','تفعيل وضع الصيانة',0,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(20,'maintenance_message','البوت تحت الصيانة، عد قريبًا','string','maintenance','رسالة الصيانة','الرسالة التي تظهر أثناء الصيانة',1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(21,'ichancy.enabled','1','bool','ichancy','تفعيل نظام IChancy','تفعيل/تعطيل ربط البوت بالكاشير',1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(22,'ichancy.min_deposit','100','int','ichancy','الحد الأدنى للإيداع','الحد الأدنى لشحن الرصيد في IChancy',1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(23,'ichancy.max_deposit','100000','int','ichancy','الحد الأقصى للإيداع','الحد الأقصى لشحن الرصيد',1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(24,'ichancy.min_withdraw','100','int','ichancy','الحد الأدنى للسحب','الحد الأدنى للسحب من IChancy',1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(25,'ichancy.max_withdraw','50000','int','ichancy','الحد الأقصى للسحب','الحد الأقصى للسحب',1,'2026-09-21 01:33:22','2026-09-21 01:33:22');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_agents`
--

DROP TABLE IF EXISTS `support_agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_agents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `telegram_id` bigint(20) DEFAULT NULL,
  `channels_joined_at` timestamp NULL DEFAULT NULL,
  `join_message_id` bigint(20) DEFAULT NULL,
  `icon` varchar(10) NOT NULL DEFAULT '?',
  `description` varchar(200) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sa_active_sort` (`is_active`,`sort_order`),
  KEY `support_agents_is_active_index` (`is_active`),
  KEY `support_agents_telegram_id_index` (`telegram_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_agents`
--

LOCK TABLES `support_agents` WRITE;
/*!40000 ALTER TABLE `support_agents` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_agents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaction_audits`
--

DROP TABLE IF EXISTS `transaction_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `actor_type` varchar(50) NOT NULL DEFAULT 'system' COMMENT 'system, admin, user, api, scheduler',
  `event` varchar(100) NOT NULL COMMENT 'created, approved, rejected, completed, failed, refunded, ...',
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) DEFAULT NULL,
  `amount_before` decimal(18,2) DEFAULT NULL,
  `amount_after` decimal(18,2) DEFAULT NULL,
  `balance_before` decimal(18,2) DEFAULT NULL,
  `balance_after` decimal(18,2) DEFAULT NULL,
  `currency` varchar(3) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `note` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `request_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `audit_tx_event` (`transaction_id`,`event`),
  KEY `audit_user_created` (`user_id`,`created_at`),
  KEY `audit_actor_created` (`actor_id`,`created_at`),
  KEY `transaction_audits_created_at_index` (`created_at`),
  KEY `transaction_audits_event_index` (`event`),
  KEY `transaction_audits_request_id_index` (`request_id`),
  CONSTRAINT `transaction_audits_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaction_audits_transaction_id_foreign` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transaction_audits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaction_audits`
--

LOCK TABLES `transaction_audits` WRITE;
/*!40000 ALTER TABLE `transaction_audits` DISABLE KEYS */;
/*!40000 ALTER TABLE `transaction_audits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `from_wallet_id` bigint(20) unsigned DEFAULT NULL,
  `to_wallet_id` bigint(20) unsigned DEFAULT NULL,
  `user_payment_account_id` bigint(20) unsigned DEFAULT NULL,
  `user_account_number` varchar(100) DEFAULT NULL,
  `type` enum('deposit','withdraw','deposit_usd','withdraw_usd','exchange','ichancy_deposit','ichancy_withdraw','commission','refund','admin_credit','admin_debit','deposit_bonus','signup_bonus','gems_exchange') NOT NULL,
  `from_currency` varchar(3) DEFAULT NULL,
  `to_currency` varchar(3) DEFAULT NULL,
  `amount_from` decimal(18,2) NOT NULL DEFAULT 0.00,
  `amount_to` decimal(18,2) NOT NULL DEFAULT 0.00,
  `exchange_rate` decimal(18,6) DEFAULT NULL,
  `commission_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','approved','completed','rejected','cancelled','failed') NOT NULL DEFAULT 'pending',
  `ichancy_transaction_id` varchar(255) DEFAULT NULL,
  `external_reference` varchar(255) DEFAULT NULL,
  `admin_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `proof_file` varchar(255) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transactions_reference_unique` (`reference`),
  KEY `transactions_from_wallet_id_foreign` (`from_wallet_id`),
  KEY `transactions_to_wallet_id_foreign` (`to_wallet_id`),
  KEY `transactions_user_payment_account_id_foreign` (`user_payment_account_id`),
  KEY `transactions_admin_id_foreign` (`admin_id`),
  KEY `tx_user_type_status` (`user_id`,`type`,`status`),
  KEY `tx_status_created` (`status`,`created_at`),
  KEY `tx_type_created` (`type`,`created_at`),
  KEY `tx_completed_status` (`completed_at`,`status`),
  KEY `transactions_deleted_at_index` (`deleted_at`),
  KEY `transactions_type_index` (`type`),
  KEY `transactions_status_index` (`status`),
  KEY `transactions_ichancy_transaction_id_index` (`ichancy_transaction_id`),
  CONSTRAINT `transactions_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_from_wallet_id_foreign` FOREIGN KEY (`from_wallet_id`) REFERENCES `wallets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_to_wallet_id_foreign` FOREIGN KEY (`to_wallet_id`) REFERENCES `wallets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_user_payment_account_id_foreign` FOREIGN KEY (`user_payment_account_id`) REFERENCES `user_payment_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (1,'GIFT_TO9QMTRDMLMO',NULL,NULL,NULL,NULL,NULL,'admin_credit',NULL,'NSP',0.00,200.00,NULL,0.00,'completed',NULL,NULL,NULL,'استبدال كود هدية: GIFT_AMYZILWS','{\"source\":\"gift_code\",\"gift_code_id\":1,\"gift_code\":\"GIFT_AMYZILWS\"}',NULL,NULL,'2026-09-21 08:46:19',NULL,NULL,'2026-09-21 08:46:19','2026-09-21 08:46:19',NULL),(2,'signup_bonus_nsp_5',NULL,NULL,NULL,NULL,NULL,'signup_bonus','NSP','NSP',100.00,100.00,NULL,0.00,'completed',NULL,NULL,NULL,'مكافأة تسجيل (NSP)',NULL,NULL,NULL,'2026-09-21 09:12:28',NULL,NULL,'2026-09-21 09:12:28','2026-09-21 09:12:28',NULL),(3,'signup_bonus_nsp_6',NULL,NULL,NULL,NULL,NULL,'signup_bonus','NSP','NSP',100.00,100.00,NULL,0.00,'completed',NULL,NULL,NULL,'مكافأة تسجيل (NSP)',NULL,NULL,NULL,'2026-09-21 09:35:50',NULL,NULL,'2026-09-21 09:35:50','2026-09-21 09:35:50',NULL),(4,'signup_bonus_nsp_7',7,NULL,7,NULL,NULL,'signup_bonus','NSP','NSP',100.00,100.00,NULL,0.00,'completed',NULL,NULL,NULL,'مكافأة تسجيل (NSP)',NULL,NULL,NULL,'2026-09-21 09:58:02',NULL,NULL,'2026-09-21 09:58:02','2026-09-21 09:58:02',NULL);
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_payment_accounts`
--

DROP TABLE IF EXISTS `user_payment_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_payment_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `deposit_method_id` bigint(20) unsigned NOT NULL,
  `account_number` varchar(100) NOT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `total_deposited` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_withdrawn` decimal(18,2) NOT NULL DEFAULT 0.00,
  `available_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
  `deposits_count` int(10) unsigned NOT NULL DEFAULT 0,
  `withdrawals_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_payment_accounts_unique` (`user_id`,`deposit_method_id`,`account_number`),
  KEY `upa_user_active` (`user_id`,`is_active`),
  KEY `user_payment_accounts_deposit_method_id_index` (`deposit_method_id`),
  KEY `user_payment_accounts_is_active_index` (`is_active`),
  CONSTRAINT `user_payment_accounts_deposit_method_id_foreign` FOREIGN KEY (`deposit_method_id`) REFERENCES `deposit_methods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_payment_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_payment_accounts`
--

LOCK TABLES `user_payment_accounts` WRITE;
/*!40000 ALTER TABLE `user_payment_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_payment_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `telegram_id` bigint(20) unsigned NOT NULL COMMENT 'معرّف Telegram الفريد',
  `telegram_username` varchar(64) DEFAULT NULL COMMENT 'اسم المستخدم في Telegram',
  `username` varchar(64) NOT NULL COMMENT 'اسم المستخدم في المنصة',
  `password_encrypted` text NOT NULL COMMENT 'كلمة المرور مشفرة بمفتاح منفصل',
  `password_key_id` varchar(32) NOT NULL DEFAULT 'v1' COMMENT 'معرّف مفتاح التشفير (للتناوب)',
  `ichancy_player_id` varchar(64) DEFAULT NULL COMMENT 'معرّف اللاعب في إيشانسي',
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `is_super_admin` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `admin_seen_at` timestamp NULL DEFAULT NULL,
  `admin_welcome_message_id` bigint(20) DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `referral_code` varchar(20) DEFAULT NULL COMMENT 'كود الإحالة الفريد',
  `referral_type` enum('instant','cycle') DEFAULT NULL COMMENT 'نوع الإحالة الذي اختاره',
  `referral_chosen_at` timestamp NULL DEFAULT NULL,
  `referred_by` bigint(20) unsigned DEFAULT NULL,
  `referred_by_level_2` bigint(20) unsigned DEFAULT NULL,
  `referrals_count` int(10) unsigned NOT NULL DEFAULT 0,
  `referral_earnings` decimal(18,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_telegram_id_unique` (`telegram_id`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_ichancy_player_id_unique` (`ichancy_player_id`),
  UNIQUE KEY `users_referral_code_unique` (`referral_code`),
  KEY `users_referred_by_level_2_foreign` (`referred_by_level_2`),
  KEY `users_admin_idx` (`is_admin`,`is_super_admin`),
  KEY `users_active_admin_idx` (`is_active`,`is_admin`),
  KEY `users_referrer_type_idx` (`referred_by`,`referral_type`),
  KEY `users_created_at_index` (`created_at`),
  KEY `users_deleted_at_index` (`deleted_at`),
  KEY `users_telegram_username_index` (`telegram_username`),
  KEY `users_is_active_index` (`is_active`),
  KEY `users_is_admin_index` (`is_admin`),
  KEY `users_is_super_admin_index` (`is_super_admin`),
  KEY `users_admin_seen_at_index` (`admin_seen_at`),
  CONSTRAINT `users_referred_by_foreign` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_referred_by_level_2_foreign` FOREIGN KEY (`referred_by_level_2`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,7453447854,'VexoraOwner','Vexora_Owner','eyJpdiI6InVvdXBlZjJwd1ArMS9uNnhlZHdVZmc9PSIsInZhbHVlIjoiLzM1dXhXNUNzZ0VPTDFadkZqam04Y2lhMmVScmVOVjR2MmdjcWtSSlVXOD0iLCJtYWMiOiJkZWYyMzNjM2Q4MmU1NjVmZTAxNTkwZDVjYzZmNTA0MTkyNGNkNmRkYWY4ZjU5OTM2N2FhOTdiMTc3MzZjYzc2IiwidGFnIjoiIn0=','v1',NULL,'Owner','VEXORA',1,1,1,NULL,'2026-09-21 09:39:34',NULL,'2026-09-21 01:33:22','VXB6EGMKDC',NULL,NULL,NULL,NULL,0,0.00,'2026-09-21 01:33:22','2026-09-21 09:39:34',NULL),(4,5956027786,NULL,'Vexora_Ali23','eyJpdiI6InZETUNlUkFIenJUdjZIZzc1R0FTQVE9PSIsInZhbHVlIjoiNzNxN09ySStOMnh2dFF6OSsrRGtNZz09IiwibWFjIjoiMGQzNzVmNjE3MDQ5NjkwZDYxMDYwZGFhNGJiZTU2MDYwZmIyMjY3YWFhYmFkYmM2NWI2NGQ0MWNiMWY0Mzk3ZiIsInRhZyI6IiJ9','v1',NULL,NULL,NULL,1,1,0,NULL,'2026-09-21 02:55:40',5349,'2026-09-21 02:55:10','VX628UMAKI',NULL,NULL,NULL,NULL,0,0.00,'2026-09-21 02:55:10','2026-09-21 02:56:03',NULL),(7,8335709957,'Vexora_Support12','Vexora_djfndd','eyJpdiI6ImtPcDMwZWhRdUFkaXZvSXRnVnUrd2c9PSIsInZhbHVlIjoieXVKVFlhWG5WazRoRm9ybXc1R3JNUT09IiwibWFjIjoiYTM2MDYxZDExOTAzMzE1YjU5NGFjZGUyOGNhZGIxMDZhMjczY2ExYTk2MWEyODZlNGRiZGYyMDM4NWUwNTk4MCIsInRhZyI6IiJ9','v1',NULL,NULL,NULL,1,1,0,NULL,NULL,NULL,'2026-09-21 09:57:56','VXCPXQ5WDO','instant','2026-09-21 09:58:11',NULL,NULL,0,0.00,'2026-09-21 09:57:56','2026-09-21 10:19:32',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wallets`
--

DROP TABLE IF EXISTS `wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wallets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('main','user','agent') NOT NULL DEFAULT 'user' COMMENT 'نوع المحفظة',
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `balance_nsp` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الرصيد بالليرة السورية',
  `balance_usd` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'الرصيد بالدولار الأمريكي',
  `is_locked` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'قفل المحفظة (منع العمليات المتزامنة)',
  `locked_at` timestamp NULL DEFAULT NULL,
  `total_deposit_nsp` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_deposit_usd` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_withdraw_nsp` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_withdraw_usd` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_commission_nsp` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_commission_usd` decimal(18,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_frozen` tinyint(1) NOT NULL DEFAULT 0,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallets_user_type_unique` (`user_id`,`type`),
  KEY `wallets_type_active_idx` (`type`,`is_active`),
  KEY `wallets_frozen_idx` (`is_frozen`,`is_active`),
  KEY `wallets_is_active_index` (`is_active`),
  KEY `wallets_is_frozen_index` (`is_frozen`),
  CONSTRAINT `wallets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wallets`
--

LOCK TABLES `wallets` WRITE;
/*!40000 ALTER TABLE `wallets` DISABLE KEYS */;
INSERT INTO `wallets` VALUES (1,'user',1,0.00,0.00,0,NULL,0.00,0.00,0.00,0.00,0.00,0.00,1,0,NULL,'2026-09-21 01:33:22','2026-09-21 01:33:22',NULL),(4,'user',4,0.00,0.00,0,NULL,0.00,0.00,0.00,0.00,0.00,0.00,1,0,NULL,'2026-09-21 02:55:10','2026-09-21 02:55:10',NULL),(7,'user',7,100.00,0.00,0,NULL,0.00,0.00,0.00,0.00,0.00,0.00,1,0,NULL,'2026-09-21 09:57:56','2026-09-21 09:58:02',NULL);
/*!40000 ALTER TABLE `wallets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wheel_prizes`
--

DROP TABLE IF EXISTS `wheel_prizes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wheel_prizes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wheel_id` bigint(20) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(10) NOT NULL DEFAULT '?',
  `color` varchar(20) NOT NULL DEFAULT '#4CAF50',
  `value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'SYP',
  `type` enum('balance','empty','recycle') NOT NULL DEFAULT 'balance',
  `weight` int(10) unsigned NOT NULL DEFAULT 10 COMMENT 'النسبة من 0 إلى 100',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wp_wheel_active` (`wheel_id`,`is_active`),
  KEY `wheel_prizes_type_index` (`type`),
  KEY `wheel_prizes_is_active_index` (`is_active`),
  CONSTRAINT `wheel_prizes_wheel_id_foreign` FOREIGN KEY (`wheel_id`) REFERENCES `wheels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wheel_prizes`
--

LOCK TABLES `wheel_prizes` WRITE;
/*!40000 ALTER TABLE `wheel_prizes` DISABLE KEYS */;
INSERT INTO `wheel_prizes` VALUES (1,1,'50 ل.س','💵','#4CAF50',50.00,'SYP','balance',30,0,1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(2,1,'100 ل.س','💵','#4CAF50',100.00,'SYP','balance',25,1,1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(3,1,'200 ل.س','💰','#4CAF50',200.00,'SYP','balance',15,2,1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(4,1,'500 ل.س','💰','#4CAF50',500.00,'SYP','balance',10,3,1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(5,1,'1000 ل.س','💎','#4CAF50',1000.00,'SYP','balance',5,4,1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(6,1,'فارغة','😢','#4CAF50',0.00,'SYP','empty',10,5,1,'2026-09-21 01:33:22','2026-09-21 01:33:22'),(7,1,'لفة إضافية','♻️','#4CAF50',0.00,'SYP','recycle',5,6,1,'2026-09-21 01:33:22','2026-09-21 01:33:22');
/*!40000 ALTER TABLE `wheel_prizes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wheel_spins`
--

DROP TABLE IF EXISTS `wheel_spins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wheel_spins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `wheel_id` bigint(20) unsigned NOT NULL,
  `prize_id` bigint(20) unsigned DEFAULT NULL,
  `won_value` decimal(18,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'SYP',
  `source` enum('deposit_syp','deposit_usd','referral','admin','manual') NOT NULL DEFAULT 'deposit_syp',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wheel_spins_prize_id_foreign` (`prize_id`),
  KEY `ws_user_created` (`user_id`,`created_at`),
  KEY `ws_wheel_created` (`wheel_id`,`created_at`),
  KEY `wheel_spins_created_at_index` (`created_at`),
  KEY `wheel_spins_source_index` (`source`),
  CONSTRAINT `wheel_spins_prize_id_foreign` FOREIGN KEY (`prize_id`) REFERENCES `wheel_prizes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wheel_spins_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wheel_spins_wheel_id_foreign` FOREIGN KEY (`wheel_id`) REFERENCES `wheels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wheel_spins`
--

LOCK TABLES `wheel_spins` WRITE;
/*!40000 ALTER TABLE `wheel_spins` DISABLE KEYS */;
/*!40000 ALTER TABLE `wheel_spins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wheel_user_states`
--

DROP TABLE IF EXISTS `wheel_user_states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wheel_user_states` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `wheel_id` bigint(20) unsigned NOT NULL,
  `available_spins` int(10) unsigned NOT NULL DEFAULT 0,
  `total_spins_used` int(10) unsigned NOT NULL DEFAULT 0,
  `total_won_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `spins_today` int(10) unsigned NOT NULL DEFAULT 0,
  `last_spin_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wheel_user_unique` (`user_id`,`wheel_id`),
  KEY `wheel_user_states_wheel_id_foreign` (`wheel_id`),
  KEY `wus_user_spins` (`user_id`,`available_spins`),
  CONSTRAINT `wheel_user_states_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wheel_user_states_wheel_id_foreign` FOREIGN KEY (`wheel_id`) REFERENCES `wheels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wheel_user_states`
--

LOCK TABLES `wheel_user_states` WRITE;
/*!40000 ALTER TABLE `wheel_user_states` DISABLE KEYS */;
/*!40000 ALTER TABLE `wheel_user_states` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wheels`
--

DROP TABLE IF EXISTS `wheels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wheels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT 'عجلة الحظ',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deposit_syp_threshold` decimal(18,2) NOT NULL DEFAULT 50000.00,
  `min_deposit_syp_threshold` decimal(18,2) NOT NULL DEFAULT 1000.00,
  `max_deposit_syp_threshold` decimal(18,2) NOT NULL DEFAULT 1000000.00,
  `deposit_usd_threshold` decimal(18,2) NOT NULL DEFAULT 1.00,
  `min_deposit_usd_threshold` decimal(18,2) NOT NULL DEFAULT 0.50,
  `max_deposit_usd_threshold` decimal(18,2) NOT NULL DEFAULT 100.00,
  `referral_threshold` int(10) unsigned NOT NULL DEFAULT 5,
  `min_referral_threshold` int(10) unsigned NOT NULL DEFAULT 1,
  `max_referral_threshold` int(10) unsigned NOT NULL DEFAULT 50,
  `daily_limit` int(10) unsigned NOT NULL DEFAULT 10,
  `min_daily_limit` int(10) unsigned NOT NULL DEFAULT 1,
  `max_daily_limit` int(10) unsigned NOT NULL DEFAULT 100,
  `max_stored_spins` int(10) unsigned NOT NULL DEFAULT 100,
  `auto_grant_on_deposit` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wheels_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wheels`
--

LOCK TABLES `wheels` WRITE;
/*!40000 ALTER TABLE `wheels` DISABLE KEYS */;
INSERT INTO `wheels` VALUES (1,'عجلة الحظ','عجلة حظ VEXORA',1,50000.00,1000.00,1000000.00,1.00,0.50,100.00,5,1,50,10,1,100,100,1,'2026-09-21 01:33:22','2026-09-21 01:33:22');
/*!40000 ALTER TABLE `wheels` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-22  1:00:02
