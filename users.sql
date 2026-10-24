-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Generation Time: Oct 24, 2026 at 06:56 PM
-- Server version: 10.11.19-MariaDB-ubu2204
-- PHP Version: 8.3.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `users`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(32) NOT NULL,
  `uid` int(11) NOT NULL,
  `gid` int(11) NOT NULL,
  `email` varchar(32) NOT NULL,
  `shell` varchar(100) DEFAULT '/bin/bash',
  `home` varchar(255) NOT NULL,
  `public_key` text DEFAULT NULL,
  `access_host` varchar(255) DEFAULT 'localhost'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `uid`, `gid`, `email`, `shell`, `home`, `public_key`, `access_host`) VALUES
(1, 'stu01', 5000, 5000, 'tom.sandholm@gmail.com', '/bin/bash', '/share/home/stu01', 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABgQDKqXZ+SLI5aH0aYMfoIygApu28cmVH06GU91Ko6hpfBblNntam4Mi6tWolCeZLeaDmZ+/LdXWykbmOyoKq42S2SStjimkeUTqQWHt6wojPfIZwovk7zN5Vn7V8w54e0/v97fX/j9gCvDToHzx6ReJyR5uRoMJ9Cs82DlJwOE6sVpbOPnpYwlCCB7zZmi82RiXZ7pVO6C3ezcJvl3565gxqHbmcqK5uDbXx2mTFC08TUxyxWOfEeAJbhM5RD7OtmbYTL59HlzuhPhgG8jCSUq5nJvOKqhqNvDF0MzVbV2hxmiJsfvQ84fxvCTh4CWOGxUMzEv57uLdTgQeXm1Gl01FNUIPvwPaRVYUzPXyFzPrXkN19f7fTJ6pCJpw/4O35RGIL57fK4jYV0y0xPrDtRESiwColvqubIi27NAqGJwAwJzNNe696COoj8ORsEMxn5uwB68xMALK6IIJZcTk4FO13JQqD/hlIb8+JkkmqToTFmymbjPiTTiIKA6soWOxeteM= sandholm@beast4', 'localhost'),
(2, 'stu02', 5001, 5001, 'tom.sandholm@gmail.com', '/bin/bash', '/share/home/stu02', 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABgQDKqXZ+SLI5aH0aYMfoIygApu28cmVH06GU91Ko6hpfBblNntam4Mi6tWolCeZLeaDmZ+/LdXWykbmOyoKq42S2SStjimkeUTqQWHt6wojPfIZwovk7zN5Vn7V8w54e0/v97fX/j9gCvDToHzx6ReJyR5uRoMJ9Cs82DlJwOE6sVpbOPnpYwlCCB7zZmi82RiXZ7pVO6C3ezcJvl3565gxqHbmcqK5uDbXx2mTFC08TUxyxWOfEeAJbhM5RD7OtmbYTL59HlzuhPhgG8jCSUq5nJvOKqhqNvDF0MzVbV2hxmiJsfvQ84fxvCTh4CWOGxUMzEv57uLdTgQeXm1Gl01FNUIPvwPaRVYUzPXyFzPrXkN19f7fTJ6pCJpw/4O35RGIL57fK4jYV0y0xPrDtRESiwColvqubIi27NAqGJwAwJzNNe696COoj8ORsEMxn5uwB68xMALK6IIJZcTk4FO13JQqD/hlIb8+JkkmqToTFmymbjPiTTiIKA6soWOxeteM= sandholm@beast4', 'localhost');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `uid` (`uid`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
