  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nick` varchar(32) CHARACTER SET latin1 NOT NULL,
  `address` varchar(255) CHARACTER SET latin1 NOT NULL,
  `type` varchar(255) CHARACTER SET latin1 NOT NULL,
  `last` decimal(11,0) NOT NULL,
  `today` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `channel` varchar(255) CHARACTER SET latin1 NOT NULL,
  `network` varchar(255) CHARACTER SET latin1 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;