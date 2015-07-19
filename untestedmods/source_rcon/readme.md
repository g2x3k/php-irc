#Source RCON

Monitor and interact with a Source based game server (CS:S, TF2, etc). Also allows players to interact with the server based on their Steam ID.
For more details please check the Official Support Thread.

##readme.txt
--------------------------------------------------------------------------
|   PHP-IRC Source RCON
|   ========================================================
|     by Mad Clog
|   (c) 2007-2008 by http://www.madclog.nl
|   Contact:
|    email: phpirc@madclog.com
|    msn:   gertjuhh@hotmail.com
|    irc:   #madclog@irc.quakenet.org
+---------------------------------------------------------------------------
|   > This program is free software; you can redistribute it and/or
|   > modify it under the terms of the GNU General Public License
|   > as published by the Free Software Foundation; either version 2
|   > of the License, or (at your option) any later version.
|   >
|   > This program is distributed in the hope that it will be useful,
|   > but WITHOUT ANY WARRANTY; without even the implied warranty of
|   > MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
|   > GNU General Public License for more details.
|   >
|   > You should have received a copy of the GNU General Public License
|   > along with this program; if not, write to the Free Software
|   > Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
+---------------------------------------------------------------------------

Table of Contents
-----------------

1.	Introduction
1.1		Features
1.2		Support
1.3		Note to HLSW users
2.	Installation
3.	Configuration
3.1		Custom Levels
3.1.1		Log level options
3.1.2		Admin level options
3.2		settings.php
3.3		admins.php
4.	Expanding functionality
4.1		Adding triggers
4.2		Adding RCON response parsers
4.3		Adding UDP log parsers
4.3.1		Adding the trigger in 'registerHandlers'
4.3.2		Creating the function


================
1. Introduction
================
This module was written to monitor and interact with a Source based game server (CS:S, TF2, etc) from IRC.
It also allows players to interact with the server based on their Steam ID.
The module has been tested on, and confirmed to work with, Fortress Forever, Counter-Strike:Source and Team Fortress 2

And now a word from our sponsor:
If you don't know Fortress Forever please go check it out at http://www.fortress-forever.com/ and download your FREE copy.
"Fortress Forever should please those who have stuck with or been a part Half-life: TFC over the years while at the same time doing more for new TF players than any prior Fortress attempt."
(This module is in no way related to Fortress Forever other then that I used the game to test the module on)

=============
1.1 Features
=============
For a full list of features please visit http://www.phpbots.org/showtopic.php?tid=253

============
1.2 Support
============
You can get support at the following places
	PHP-IRC forums: Source RCON support thread at http://www.phpbots.org/showtopic.php?tid=253
	Rizon IRC: #manekian
	Quakenet IRC: #madclog
Keep in mind I do have a job and a personal life, when you don't get a response within (insert random timeframe here), please be patient.

=======================
1.3 Note to HLSW users
=======================
If you use HLSW on the same computer as the bot is running on, close it prior to running the bot when using this module.
I have noticed problems with the script when HLSW was running before i started the bot.
Once the bot is started you can use HLSW safely.

================
2. Installation
================
Extract the contents of this zip to <bot_dir>/modules/source_rcon/
Edit you bot's function.conf file to include the following line:
	include modules/source_rcon/source_rcon.conf
If you plan on saving log files locally make sure the following directory exists and is writeable
	<bot_dir>/modules/source_rcon/logs
At this point you're ready to configure the module

=================
3. Configuration
=================
There are 2 files you need to edit to make this module function properly.
settings.php  -  This is the main configuration file
admins.php    -  Defines which people are considered admins, if any

==================
3.1 Custom levels
==================
Some settings require a level (log level, admin level), these levels are the sum of the options you want enabled.
These levels are defined in defines.php but it will explain them in the following 2 subsections.

========================
3.1.1 Log level options
========================
Constant        | Level | Explanation
--------------------------------------
LOG_SAY         | 1     | Global messages send in chat
LOG_TEAMSAY     | 2     | Team messages send in chat
LOG_SERVERSAY   | 4     | Messages send via rcon (console)
LOG_KILL        | 8     | Kills made on the server
LOG_CONNECT     | 16    | Players who connect to the server
LOG_DISCONNECT  | 32    | Players who disconnect from the server
LOG_TEAMJOIN    | 64    | Players who join a team / change teams
LOG_NAMECHANGE  | 128   | Logs all name changes from players
LOG_MAPCHANGE   | 256   | When the server changes maps

Example:
We want to log all global messages (LOG_SAY), name changes (LOG_NAMECHANGE) and map changes (LOG_MAPCHANGE)
The log level would be: 1 + 128 + 256 = 385

==========================
3.1.2 Admin level options
==========================
Constant        | Level | Explanation
--------------------------------------
LVL_SAY         | 1     | Send messages to the server (IRC only)
LVL_CHANGELEVEL | 2     | Change the current map on the server
LVL_KICK        | 4     | Kick a player from the server
LVL_BAN         | 8     | Permanently ban a player from the server
LVL_REHASH      | 16    | Restart the logging process (IRC only)
LVL_RCON        | 32    | Send custom RCON commands, this basically gives a user full control over your server so use it wisely

Example:
We want to allow someone to change the level (LVL_CHANGELEVEL) and kick players (LVL_KICK)
The admin level would be: 2 + 4 = 6

=================
3.2 settings.php
=================
$setup['channel'] (string)
	The IRC channel that is to be used as our IRC portal to the game server.
	Any messages send to irc will be send here.
	Admins can also send administrative commands to the game server from within this channel.
$setup['commandPrefix'] (string)
	The prefix which indicates we're sending a command instead of regular text.
	This needs to be filled with one character only (no more, no less).
$setup['opLevel'] (integer)
	The default admin level (see 3.1.2) channel operators have when there is no mathcing hostname in admins.php
$setup['loglevel'] (integer)
	This will determine the output on IRC, please refer to 3.1.1
$setup['logtofile'] (boolean)
	If you want to write the UDP logs to file.
$setup['prefixsay'] (boolean)
	If this is enabled, messages from IRC will be prefixed as such: (IRC|Nick)
$setup['textMarkup'] (boolean)
	If this is disabled, no text markup (colors etc.) will be used in messages send to IRC
$setup['badnames'] (array)
	An array containing regular expression patterns which match bad names.
	Any player with a nick matched against one of these patterns will be kicked from the server automatically.
$setup['debug'] (boolean)
	When this is enabled all kinds of debug information will be send to the console.

$reconnect['enabled'] (boolean)
	If enabled, the bot will automaticly reconnect with the server on disconnect
$reconnect['numTries'] (integer)
	The maximum number of reconnects, 0 (zero) is unlimmited
$reconnect['delay'] (integer)
	The time in seconds in between reconnects

$server['ip'] (string)
	Ip address of the game server
$server['port'] (integer)
	Port number the server is running on
$server['password'] (string)
	RCON password

$local['ip'] (string)
	The WAN ip address (internet address (www.whatsmyip.org)) of the computer the bot is running on
	Leave empty if you put your address in the server config already, you still need to specify the port though
$local['port'] (integer)
	Port to which the UDP log is streamed, make sure this port is forwarded properly

$colors[...] (integer)
	The colors array is an array with team names as indexes.
	The values of these array elements represent IRC color codes which will be used to display on which team a player is

===============
3.3 admins.php
===============
$admins
	First of, if you don't want any admins, or want to use irc OPs only, just leave the $admins array empty (don't remove it, this will cause php notices/warnings!)
	Each entry of $admins is a new array on itself which has 4 elements:
		name (string) Name of the admin, can be used to display who executed a command (currently implemented in kicks/bans)
		level (integer) The administrative level (as explained earlier)
		steamid (string) The admin's Steam ID, used to identify him/her while on the server
		host (string) The admin's host, used to identify him/her while on irc, for security reasons you should only add static hosts
	Steam ID and host are optional, however each entry without either Steam ID or host is useless as the bot then has no way to identify the admin.

===========================
4. Expanding functionality
===========================
While working on the module I tried to make it as flexible as I could in regards to custom expansions.
However you do need to follow a few simple guidelines.

====================
4.1 Adding triggers
====================
If you want to set up new triggers for IRC (or the game server) you don't need to edit source_rcon.conf, all you have to do is add a function to the module and reload the bot.
The function name should be func<Trigger>, so all lower case 'func' and the trigger text starting with a capital, followed by all lower case letters.
it should ALWAYS have the following 3 parameters:
	$p_aLine = null
	$p_sSteamId = null
	$p_sArguments = null

In the following examples I'll assume $setup['commandPrefix'] is set to '!'

Example 1:
Trigger !irc which is accessible only from irc

private function funcIrc($p_aLine, $p_sSteamId, $p_sArguments) {
	if (!empty($p_sSteamId)) {
		return; // if we call this from the server it will stop here
	}

	return 'This text will be send to IRC';
}

Example 2:
Trigger !server which is only accessible from the game server

private function funcIrc($p_aLine, $p_sSteamId, $p_sArguments) {
	if (empty($p_sSteamId)) {
		return; // if we don't have a Steam ID, we're not calling it from within the game server
	}

	return 'This text will be send to the game server';
}

Example 3:
Trigger !both which can be accessed from IRC aswell as the game server

private function funcBoth($p_aLine, $p_sSteamId, $p_sArguments) {
	// the bot will automaticly determine where the command was send from
	return 'This text will be send to either IRC or the game server';
}

=================================
4.2 Adding RCON response parsers
=================================
If you want to parse the result of an RCON command, all you need to do is add a function to the module and reload the bot.
The function name should be sLog_<RCON command>, sLog_ followed by the rcon command in lower case.
It should ALWAYS have the following 2 parameters
	$p_sArgs
	$p_sResponse
$p_sArgs will hold any parameters send along with the RCON command
$p_sResponse will hold the actuall response send by the game server

Example:
Add a parser for the RCON command 'status' (implemented code stripped down)

private function sLog_status($p_sArgs, $p_sResponse) {
	return 'Someone on IRC or the game server executed a RCON status command';
}

===========================
4.3 Adding UDP log parsers
===========================
Fetching the UDP log is as exciting as watching grass grow if we don't have any parsers for it.
Adding a parser is done in 2 steps:
	- Add a trigger in the function called 'registerHandlers'
	- Create a function to parse the result
The result of these triggers will be send to IRC only.
If you want to send output to the game server as well you'll need to build this into your function.

===============================================
4.3.1 Adding the trigger in 'registerHandlers'
===============================================
Within the 'registerHandlers' function you'll see a bunch of triggers being setup already.
Adding triggers is done by calling the function 'addHandler' which has 2 parameters:
	Name of the function which is to be called
	The regular expression pattern which indicates when a function should be called
The matches made within the regular expression are passed onto the function which will be called.

Example:
Trigger a rLog_kill every team a player kills someone

$this->addHandler('rLog_kill', '"(.{1,35})<(\d{1,9})><([a-z0-9:_]{3,35})><([\040\#a-z0-9_-]{0,35})>" killed "(.{1,35})<(\d{1,9})><([a-z0-9:_]{3,35})><([\040\#a-z0-9_-]{0,35})>" with "(.*)"');

============================
4.3.2 Creating the function
============================
The naming on this one doesn't really matter, however I would advise on keeping my 'rLog_' prefix to easily distinguish the UDP parsers.
It should ALWAYS have the following parameter
	$p_sMatches

Example:
A function rLog_kill from our example in 4.3.1

private function rLog_kill($p_aMatches) {
	// ATTACKER 1:name || 2:uid || 3:Steam ID || 4:team
	// VICTOM 5:name || 6:uid || 7:Steam ID || 8:team
	// 9:weapon
	return $p_aMatches[1].' killed '.$p_aMatches[5];
}