<?php

$setup = array(	'channel' 			=> '#myPrivChan',
				'commandPrefix'		=> '!',
				'opLevel'			=> 19, // say, changelevel and rehash
				'loglevel' 			=> 439, // anything but kills and team joins
				'logtofile' 		=> true,
				'prefixsay' 		=> true,
				'textMarkup'		=> true,
				'badnames' 			=> array(	'/n[\!i1]gg[e3]r/i',
												'/fuck/i'
											),
				'debug' 			=> false
			);
$reconnect = array(	'enabled'	=> true,
					'numTries'	=> 5,
					'delay'		=> 60
				);
$server = array('ip' 		=> '1.2.3.4',
				'port' 		=> 27015,
				'password'	=> 'somepass'
				);
$local = array(	'ip'	=> '4.3.2.1', // leave empty if you put your address in the server config already, still specify the port though
				'port'	=> 7130
				);
$colors = array('console' 				=> 14,
				'#ff_team_blue' 		=> 12,
				'#ff_team_red' 			=> 4,
				'#ff_team_green' 		=> 3,
				'#ff_team_yellow' 		=> 8,
				'#ff_team_spectator'	=> 7,
				'ct' 					=> 12,
				'terrorist' 			=> 4,
				'spec' 					=> 7,
				'spectator' 			=> 7
				);

?>