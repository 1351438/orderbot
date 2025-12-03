-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: fr.apexteam.net
-- Generation Time: Dec 03, 2025 at 11:21 AM
-- Server version: 11.7.2-MariaDB-ubu2404
-- PHP Version: 8.4.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `admin_test`
--

-- --------------------------------------------------------

--
-- Table structure for table `balances`
--

CREATE TABLE `balances` (
                            `user_id` int(11) NOT NULL,
                            `balance` decimal(26,9) NOT NULL,
                            `update_on` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `balance_history`
--

CREATE TABLE `balance_history` (
                                   `id` int(11) NOT NULL,
                                   `user_id` varchar(20) NOT NULL,
                                   `amount` decimal(26,9) NOT NULL,
                                   `before_balance` decimal(26,9) NOT NULL,
                                   `reference` varchar(32) NOT NULL,
                                   `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blockchain_events`
--

CREATE TABLE `blockchain_events` (
                                     `id` char(64) NOT NULL,
                                     `action_index` smallint(5) UNSIGNED NOT NULL,
                                     `status` enum('PENDING','VERIFIED') NOT NULL DEFAULT 'PENDING',
                                     `type` enum('NFT_ITEM_TRANSFER','JETTON_TRANSFER','TON_TRANSFER','OTHER','CONTRACT_DEPLOY','JETTON_BURN','SWAP_TOKENS') NOT NULL,
                                     `detect_date` timestamp NOT NULL DEFAULT current_timestamp(),
                                     `blockchain_date` int(10) UNSIGNED NOT NULL,
                                     `account` char(66) NOT NULL,
                                     `sender` char(66) DEFAULT NULL,
                                     `is_sender_wallet` enum('YES','NO') NOT NULL DEFAULT 'YES',
                                     `recipient` char(66) DEFAULT NULL,
                                     `is_recipient_wallet` enum('YES','NO') NOT NULL DEFAULT 'YES',
                                     `amount` decimal(46,9) UNSIGNED NOT NULL,
                                     `currency` varchar(10) DEFAULT NULL,
                                     `currency_master` char(66) DEFAULT NULL,
                                     `comment` varchar(300) DEFAULT NULL,
                                     `item_address` varchar(200) DEFAULT NULL,
                                     `base_transactions` text NOT NULL,
                                     `lt` bigint(20) UNSIGNED NOT NULL,
                                     `raw_data` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blockchain_events_swap`
--

CREATE TABLE `blockchain_events_swap` (
                                          `id` char(64) NOT NULL,
                                          `action_index` smallint(5) UNSIGNED NOT NULL,
                                          `status` enum('PENDING','VERIFIED') NOT NULL DEFAULT 'PENDING',
                                          `dex` varchar(100) NOT NULL,
                                          `detect_date` timestamp NOT NULL DEFAULT current_timestamp(),
                                          `blockchain_date` int(10) UNSIGNED NOT NULL,
                                          `account` char(66) NOT NULL,
                                          `sender` char(66) DEFAULT NULL,
                                          `amount_in` decimal(21,9) UNSIGNED NOT NULL DEFAULT 0.000000000,
                                          `currency_in` varchar(10) DEFAULT NULL,
                                          `currency_in_master` char(66) DEFAULT NULL,
                                          `amount_out` decimal(21,9) UNSIGNED NOT NULL DEFAULT 0.000000000,
                                          `currency_out` varchar(10) DEFAULT NULL,
                                          `currency_out_master` char(66) DEFAULT NULL,
                                          `ton_out` decimal(21,9) UNSIGNED NOT NULL DEFAULT 0.000000000,
                                          `router_address` varchar(200) NOT NULL,
                                          `base_transactions` text NOT NULL,
                                          `lt` bigint(20) UNSIGNED NOT NULL,
                                          `raw_data` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `city`
--

CREATE TABLE `city` (
                        `tag` varchar(32) NOT NULL,
                        `name` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
                           `user_id` varchar(20) NOT NULL,
                           `name` varchar(64) NOT NULL,
                           `phone_number` varchar(16) NOT NULL,
                           `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jettons`
--

CREATE TABLE `jettons` (
                           `symbol` varchar(20) NOT NULL,
                           `decimals` int(11) NOT NULL,
                           `name` varchar(200) NOT NULL,
                           `description` text DEFAULT NULL,
                           `image` text NOT NULL,
                           `master` varchar(66) NOT NULL,
                           `json` longtext NOT NULL,
                           `network` enum('MAINNET','TESTNET') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jettons__price`
--

CREATE TABLE `jettons__price` (
                                  `jetton_master` varchar(66) NOT NULL,
                                  `price_in_usd` decimal(46,9) NOT NULL,
                                  `price_in_ton` decimal(46,9) NOT NULL,
                                  `update_on` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                                  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
                            `id` int(11) NOT NULL,
                            `user_id` varchar(20) NOT NULL,
                            `message_id` varchar(100) NOT NULL,
                            `data` text NOT NULL,
                            `status` enum('ACTIVE','REMOVED') NOT NULL,
                            `update_on` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                            `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
                          `id` int(11) NOT NULL,
                          `user_id` varchar(20) NOT NULL,
                          `product_id` varchar(20) NOT NULL,
                          `count` int(11) NOT NULL,
                          `amount` decimal(26,9) NOT NULL,
                          `transaction_code` varchar(32) GENERATED ALWAYS AS (md5(concat(`id`,`user_id`))) VIRTUAL,
                          `status` enum('WAITING','CANCELED','EXPIRED','ACCEPTED','SENT','DONE','DRIVER','DELIVERED') NOT NULL,
                          `driver` varchar(20) DEFAULT NULL,
                          `update_on` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                          `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
                            `id` int(11) NOT NULL,
                            `name` varchar(100) NOT NULL,
                            `file_id` varchar(100) DEFAULT NULL,
                            `description` varchar(200) NOT NULL,
                            `region` int(11) NOT NULL,
                            `variants` set('1','2','3','4','5','10','15','20','25','50','100') NOT NULL,
                            `unit_price` decimal(29,9) NOT NULL COMMENT 'unit price in ton',
                            `driver_note` varchar(300) NOT NULL,
                            `manager` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `region`
--

CREATE TABLE `region` (
                          `id` int(11) NOT NULL,
                          `city_tag` varchar(32) NOT NULL,
                          `region` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `secret_tokens`
--

CREATE TABLE `secret_tokens` (
                                 `id` int(11) NOT NULL,
                                 `secret` varchar(100) NOT NULL,
                                 `note` varchar(200) NOT NULL,
                                 `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
                            `id` int(11) NOT NULL,
                            `user_id` varchar(20) NOT NULL,
                            `secret_id` int(11) NOT NULL,
                            `expire_in` timestamp GENERATED ALWAYS AS (`update_on` + interval 1 hour) VIRTUAL,
  `status` enum('INACTIVE','ACTIVE') GENERATED ALWAYS AS (if(current_timestamp() > `expire_in`,'INACTIVE','ACTIVE')) VIRTUAL,
  `update_on` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
                            `user_id` varchar(20) NOT NULL,
                            `name` varchar(100) NOT NULL,
                            `value` longtext NOT NULL,
                            `update_on` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                            `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
                         `user_id` varchar(20) NOT NULL,
                         `first_name` varchar(100) NOT NULL,
                         `last_name` varchar(100) NOT NULL,
                         `username` varchar(64) NOT NULL,
                         `type` enum('USER','ADMIN','SELLER','DRIVER') NOT NULL,
                         `update_on` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                         `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallets`
--

CREATE TABLE `wallets` (
                           `address` varchar(66) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whitelist_jettons`
--

CREATE TABLE `whitelist_jettons` (
                                     `address` varchar(66) NOT NULL,
                                     `name` varchar(100) NOT NULL,
                                     `min_deposit` decimal(46,9) DEFAULT 0.000000000,
                                     `min_withdraw` decimal(46,9) NOT NULL DEFAULT 0.000000000,
                                     `status` enum('ENABLE','DISABLE') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `balances`
--
ALTER TABLE `balances`
    ADD PRIMARY KEY (`user_id`),
  ADD KEY `balance` (`balance`);

--
-- Indexes for table `balance_history`
--
ALTER TABLE `balance_history`
    ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `blockchain_events`
--
ALTER TABLE `blockchain_events`
    ADD PRIMARY KEY (`id`,`action_index`),
  ADD KEY `detect_date` (`detect_date`),
  ADD KEY `action_index` (`action_index`),
  ADD KEY `type` (`type`),
  ADD KEY `sender` (`sender`),
  ADD KEY `recipient` (`recipient`),
  ADD KEY `account` (`account`),
  ADD KEY `blockchain_date` (`blockchain_date`),
  ADD KEY `comment` (`comment`),
  ADD KEY `currency` (`currency`),
  ADD KEY `status` (`status`),
  ADD KEY `is_sender_wallet` (`is_sender_wallet`),
  ADD KEY `is_recipient_wallet` (`is_recipient_wallet`);

--
-- Indexes for table `blockchain_events_swap`
--
ALTER TABLE `blockchain_events_swap`
    ADD PRIMARY KEY (`id`,`action_index`),
  ADD KEY `detect_date` (`detect_date`),
  ADD KEY `action_index` (`action_index`),
  ADD KEY `sender` (`sender`),
  ADD KEY `account` (`account`),
  ADD KEY `blockchain_date` (`blockchain_date`),
  ADD KEY `currency` (`currency_in`),
  ADD KEY `status` (`status`),
  ADD KEY `currency_out` (`currency_out`),
  ADD KEY `currency_out_master` (`currency_out_master`),
  ADD KEY `currency_in_master` (`currency_in_master`);

--
-- Indexes for table `city`
--
ALTER TABLE `city`
    ADD PRIMARY KEY (`tag`),
  ADD KEY `tag` (`tag`,`name`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
    ADD PRIMARY KEY (`user_id`),
  ADD KEY `user_id` (`user_id`,`name`,`phone_number`);

--
-- Indexes for table `jettons`
--
ALTER TABLE `jettons`
    ADD PRIMARY KEY (`master`),
  ADD UNIQUE KEY `symbol` (`symbol`,`master`,`network`);

--
-- Indexes for table `jettons__price`
--
ALTER TABLE `jettons__price`
    ADD PRIMARY KEY (`jetton_master`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`message_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
    ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`product_id`,`status`,`driver`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
    ADD PRIMARY KEY (`id`),
  ADD KEY `region` (`region`),
  ADD KEY `unit_price` (`unit_price`);

--
-- Indexes for table `region`
--
ALTER TABLE `region`
    ADD PRIMARY KEY (`id`),
  ADD KEY `city` (`city_tag`);

--
-- Indexes for table `secret_tokens`
--
ALTER TABLE `secret_tokens`
    ADD PRIMARY KEY (`id`),
  ADD KEY `secret` (`secret`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
    ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`secret_id`),
  ADD KEY `secret` (`secret_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
    ADD PRIMARY KEY (`user_id`,`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
    ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `wallets`
--
ALTER TABLE `wallets`
    ADD PRIMARY KEY (`address`);

--
-- Indexes for table `whitelist_jettons`
--
ALTER TABLE `whitelist_jettons`
    ADD PRIMARY KEY (`address`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `balance_history`
--
ALTER TABLE `balance_history`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `region`
--
ALTER TABLE `region`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `secret_tokens`
--
ALTER TABLE `secret_tokens`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
    ADD CONSTRAINT `uid` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
    ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`region`) REFERENCES `region` (`id`);

--
-- Constraints for table `region`
--
ALTER TABLE `region`
    ADD CONSTRAINT `city` FOREIGN KEY (`city_tag`) REFERENCES `city` (`tag`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
    ADD CONSTRAINT `secret` FOREIGN KEY (`secret_id`) REFERENCES `secret_tokens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
