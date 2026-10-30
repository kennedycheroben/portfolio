-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 30, 2026 at 08:29 AM
-- Server version: 8.0.41
-- PHP Version: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `xqtrqexj_portfolio_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` int NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contacts`
--

INSERT INTO `contacts` (`id`, `name`, `email`, `subject`, `message`, `is_read`, `created_at`) VALUES
(10, '', '', '', '', 1, '2026-08-13 05:23:26'),
(11, 'Davidbok', 'no.reply.LaurentMoore@gmail.com', 'The best advertising for your products and services!', 'Hi there! kennedycheroben.co.ke, \r\nI was browsing websites and came across kennedycheroben.co.ke. \r\nOur platform businesses deliver outreach messages through website contact forms. \r\nMany teams use platforms like this to connect with website owners online. \r\nPricing for the service is kept straightforward and accessible. \r\n  \r\nFeel free to test our service without cost. \r\n \r\nHope you have a great day. \r\nContact us. \r\nTelegram - https://t.me/FeedbackFormEU \r\nWhatsApp - +375259112693 \r\nWhatsApp  https://wa.me/+375259112693 \r\nWe only use chat for communication.', 1, '2026-08-13 18:25:32');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'web-design',
  `image_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `project_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `title`, `description`, `category`, `image_url`, `video_url`, `project_url`, `client`, `date`, `featured`, `created_at`) VALUES
(1, 'JDM Kenya', 'A full church website built with a robust backend and responsive frontend.', 'web-design', 'uploads/projects/project_1782802071_757db404.webp', NULL, 'https://jdmkenya.com', 'JDM Kenya', '2025-06-01', 1, '2026-06-28 17:59:16'),
(4, 'Graphic Designer Portfolio', 'A proffesional graphic design portfolio that showcases her great work she does in design world. It has an admin panel where she can upload her work easily.', 'web-design', 'uploads/projects/project_1782758792_9e1fcf75.png', NULL, 'https://idahmercy.jdmkenya.com/', 'Mercy Idaya', '2026-06-29', 1, '2026-06-29 18:46:32'),
(6, 'Media Fusion', 'A software that enable users to upload their posts/contents to their social media platforms with one click.', 'software-development', 'uploads/projects/1de335a782ee382c998066201aae1688.webp', '', 'https://fusionmedia.top/', 'Kennedy Cheroben', '2026-06-30', 0, '2026-06-30 06:20:55'),
(7, 'Benchero', 'Benchero helps sports clubs organize their teams, players, coaches, managers, fixtures, match results, and club news all in one centralized location.', 'software-development', 'uploads/projects/0dae8920abea23b063748a5cfddf8038.png', '', 'https://benchero.co.ke/', 'Personal Project', '2026-09-09', 1, '2026-09-09 09:15:11'),
(8, 'MediCare HMS : Hospital Management System', 'MediCare HMS is a web-based hospital management system designed to streamline healthcare operations through a centralized digital platform. Built with Laravel, PHP, and a responsive multi-page interface, the system brings together patient-facing services and staff management workflows.\r\n\r\nKey features include role-based staff authentication and access control, electronic health records, doctor consultations, pharmacy workflows, online appointment booking, doctor and department directories, and public-facing hospital information pages.\r\n\r\nThe project focuses on secure access, organized healthcare workflows, maintainable application architecture, and a responsive user experience. It demonstrates full-stack development skills, database-driven application design, and deployment of a Laravel application to a live hosting environment.', 'web-design', 'uploads/projects/239063f70c9c8e2bd96a30da403f7578.png', '', 'https://medicare.kennedycheroben.co.ke', 'Kennedy Cheroben', '2026-10-27', 1, '2026-10-27 18:18:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `created_at`) VALUES
(1, 'kennedy', 'kennedycheroben001@gmail.com', '$2y$10$9o1vf6M6NsLGQ.eLFiIAf.qqo26WYN5pK6TVvdqgavfKpicnEUkoS', 'admin', '2026-06-28 22:11:17'),
(2, 'pyeghtukvl', 'egttezzf@immenseignite.info', '$2y$10$NYexOIYv7o9OWKYzhd6u2u30haSRZU9Ct5XmsF72EhFL9SrwGz0mi', 'user', '2026-07-24 07:09:34'),
(3, 'zlkkhufqhv', 'ugryfxxf@immenseignite.info', '$2y$10$/T8fxrYBjufLJubjRp2s6OQG7HsMD0a8eB9FxkcIAYResaqpHjB5O', 'user', '2026-07-24 07:09:41'),
(4, 'fgjeezotkw', 'htqnqhrn@immenseignite.info', '$2y$10$1BLJEGVLx7FK5gz6hE.gN.1RHs4XJX4azi1gLdpgsZrg4.O8lQ3Mu', 'user', '2026-07-27 19:54:13'),
(5, 'xqxsurxysj', 'ipirdfpw@immenseignite.info', '$2y$10$NPdkQTVB5NVJpzRNTJaWee0RobKKa5w0Gx03WHKakQtGDZy.gM2yu', 'user', '2026-08-09 11:45:13'),
(6, 'dimgfveyhe', 'ueqskjwn@immenseignite.info', '$2y$10$XwI5Yiq3CCIZ8JRczPOqK.AidYdsqjq/qoBXU1SzZvK5NXWY3PjJ.', 'user', '2026-08-09 11:45:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
