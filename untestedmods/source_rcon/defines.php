<?php

if(!function_exists('cDefine')){
  function cDefine($const, $value){
		if (!defined($const))
			define($const, $value);
  }
}

// Source defines
cDefine('SERVERDATA_EXECCOMMAND',2);
cDefine('SERVERDATA_AUTH',3);
cDefine('SERVERDATA_RESPONSE_VALUE',0);
cDefine('SERVERDATA_AUTH_RESPONSE',2);

// Pattern for a full player string
cDefine('PATTERN_PLAYER_FULL', 		'(.{1,35})<(\d{1,9})><([a-z0-9:_]{3,35})><([\040\#a-z0-9_-]{0,35})>');

// Log level defines
cDefine('LOG_SAY', 1);
cDefine('LOG_TEAMSAY', 2);
cDefine('LOG_SERVERSAY', 4);
cDefine('LOG_KILL', 8);
cDefine('LOG_CONNECT', 16);
cDefine('LOG_DISCONNECT', 32);
cDefine('LOG_TEAMJOIN', 64);
cDefine('LOG_NAMECHANGE', 128);
cDefine('LOG_MAPCHANGE', 256);

// User level defines
cDefine('LVL_SAY', 1);
cDefine('LVL_CHANGELEVEL', 2);
cDefine('LVL_KICK', 4);
cDefine('LVL_BAN', 8);
cDefine('LVL_REHASH', 16);
cDefine('LVL_RCON', 32);

?>