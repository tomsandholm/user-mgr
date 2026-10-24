-- user-mgr SQL dump
-- Generated: 2026-10-24T21:45:17+00:00
-- Tables: users, hosts

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

--
-- Table: `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `uid` int(11) NOT NULL,
  `gid` int(11) NOT NULL,
  `email` varchar(32) NOT NULL,
  `shell` varchar(100) DEFAULT '/bin/bash',
  `home` varchar(255) NOT NULL,
  `public_key` text DEFAULT NULL,
  `access_host` varchar(255) DEFAULT 'localhost',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `name`, `uid`, `gid`, `email`, `shell`, `home`, `public_key`, `access_host`) VALUES (1, 'stu01', 5000, 5000, 'tom.sandholm@gmail.com', '/bin/bash', '/share/home/stu01', 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABgQDKqXZ+SLI5aH0aYMfoIygApu28cmVH06GU91Ko6hpfBblNntam4Mi6tWolCeZLeaDmZ+/LdXWykbmOyoKq42S2SStjimkeUTqQWHt6wojPfIZwovk7zN5Vn7V8w54e0/v97fX/j9gCvDToHzx6ReJyR5uRoMJ9Cs82DlJwOE6sVpbOPnpYwlCCB7zZmi82RiXZ7pVO6C3ezcJvl3565gxqHbmcqK5uDbXx2mTFC08TUxyxWOfEeAJbhM5RD7OtmbYTL59HlzuhPhgG8jCSUq5nJvOKqhqNvDF0MzVbV2hxmiJsfvQ84fxvCTh4CWOGxUMzEv57uLdTgQeXm1Gl01FNUIPvwPaRVYUzPXyFzPrXkN19f7fTJ6pCJpw/4O35RGIL57fK4jYV0y0xPrDtRESiwColvqubIi27NAqGJwAwJzNNe696COoj8ORsEMxn5uwB68xMALK6IIJZcTk4FO13JQqD/hlIb8+JkkmqToTFmymbjPiTTiIKA6soWOxeteM= sandholm@beast4', 'localhost');
INSERT INTO `users` (`id`, `name`, `uid`, `gid`, `email`, `shell`, `home`, `public_key`, `access_host`) VALUES (2, 'stu02', 5001, 5001, 'tom.sandholm@gmail.com', '/bin/bash', '/share/home/stu02', 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABgQDKqXZ+SLI5aH0aYMfoIygApu28cmVH06GU91Ko6hpfBblNntam4Mi6tWolCeZLeaDmZ+/LdXWykbmOyoKq42S2SStjimkeUTqQWHt6wojPfIZwovk7zN5Vn7V8w54e0/v97fX/j9gCvDToHzx6ReJyR5uRoMJ9Cs82DlJwOE6sVpbOPnpYwlCCB7zZmi82RiXZ7pVO6C3ezcJvl3565gxqHbmcqK5uDbXx2mTFC08TUxyxWOfEeAJbhM5RD7OtmbYTL59HlzuhPhgG8jCSUq5nJvOKqhqNvDF0MzVbV2hxmiJsfvQ84fxvCTh4CWOGxUMzEv57uLdTgQeXm1Gl01FNUIPvwPaRVYUzPXyFzPrXkN19f7fTJ6pCJpw/4O35RGIL57fK4jYV0y0xPrDtRESiwColvqubIi27NAqGJwAwJzNNe696COoj8ORsEMxn5uwB68xMALK6IIJZcTk4FO13JQqD/hlIb8+JkkmqToTFmymbjPiTTiIKA6soWOxeteM= sandholm@beast4', 'localhost');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table: `hosts`
--

DROP TABLE IF EXISTS `hosts`;
CREATE TABLE `hosts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fqdn` varchar(255) NOT NULL,
  `groupname` varchar(255) NOT NULL,
  `gid` int(11) NOT NULL,
  `members` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fqdn` (`fqdn`),
  UNIQUE KEY `groupname` (`groupname`),
  UNIQUE KEY `gid` (`gid`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `hosts` WRITE;
/*!40000 ALTER TABLE `hosts` DISABLE KEYS */;
INSERT INTO `hosts` (`id`, `fqdn`, `groupname`, `gid`, `members`) VALUES (1, 'sv-quantum-lx25.infinera.com', 'sv-quantum-lx25-infinera-com', 6000, 'ansible,sudo,wheel');
INSERT INTO `hosts` (`id`, `fqdn`, `groupname`, `gid`, `members`) VALUES (2, 'sv-quantum-lx26.infinera.com', 'sv-quantum-lx26-infinera-com', 6001, 'ansible,sudo,wheel');
INSERT INTO `hosts` (`id`, `fqdn`, `groupname`, `gid`, `members`) VALUES (3, 'sv-quantum-lx27.infinera.com', 'sv-quantum-lx27-infinera-com', 6002, 'ansible,sudo,wheel');
INSERT INTO `hosts` (`id`, `fqdn`, `groupname`, `gid`, `members`) VALUES (4, 'sv-quantum-lx28.infinera.com', 'sv-quantum-lx28-infinera-com', 6003, 'ansible,sudo,wheel');
/*!40000 ALTER TABLE `hosts` ENABLE KEYS */;
UNLOCK TABLES;

COMMIT;
