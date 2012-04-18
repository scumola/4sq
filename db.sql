CREATE TABLE `venues` (
  `id` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(255) DEFAULT NULL,
  `usersCount` int(11) DEFAULT NULL,
  `checkinsCount` int(11) DEFAULT NULL,
  `lat` double DEFAULT NULL,
  `lng` double DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `postalCode` int(11) DEFAULT NULL,
  `mayorId` int(11) DEFAULT NULL,
  `mayorCount` int(11) DEFAULT NULL,
  `m_lastCheckin` datetime DEFAULT NULL,
  `m_checkinPriority` int(11) DEFAULT '999',
  `m_lastVenueDetails` datetime DEFAULT NULL,
  `m_lastSearch` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;