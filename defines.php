<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC v2.2.1 Service Release
|   ========================================================
|   by Manick
|   (c) 2001-2005 by http://www.phpbots.org/
|   Contact: manick@manekian.com
|   irc: #manekian@irc.rizon.net
|   ========================================
+---------------------------------------------------------------------------
|   > defines module
|   > Module written by Manick
|   > Module Version Number: 2.2.0
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
|   Changes
|   =======-------
|   > If you wish to suggest or submit an update/change to the source
|   > code, email me at manick@manekian.com with the change, and I
|   > will look to adding it in as soon as I can.
+---------------------------------------------------------------------------
*/

// Debug Mode
define('DEBUG', 1);

// PID file
define('PID', "bot.pid");

// OS Type (windows/unix/linux/freebsd/unknown/auto)
define('OS', 'auto');

//YOU SHOULD NOT HAVE TO EDIT BELOW THIS POINT UNLESS YOU SPECIFY "unknown" AS OS!

if (OS == "auto")
{
	switch (PHP_OS)
	{
		case "Windows NT":
			$OS = "windows";
			break;
		case "Linux":
			$OS = "linux";
			break;
		case "FreeBSD":
			$OS = "freebsd";
			break;
		case "Unix":
			$OS = "unix";
			break;
		//Thx OrochiTux for below
		case "Darwin":
			$OS = "freebsd";
			break;
		default:
			$OS = "windows";
			break;
	}
}
else
{
	$OS = OS;
}

if ($OS == 'unknown')
{
	define('EAGAIN', 		0); /* Try again */
	define('EISCONN', 		0);	/* Transport endpoint is already connected */
	define('EALREADY',		0);	/* Operation already in progress */
	define('EINPROGRESS',	0);	/* Operation now in progress */
}
else if ($OS == 'windows')
{
	//http://developer.novell.com/support/winsock/doc/appenda.htm
	define('EAGAIN', 		10035);	//EWOULDBLOCK.. kinda like EAGAIN in windows?
	define('EISCONN', 		10056);	/* Transport endpoint is already connected */
	define('EALREADY',		10037);	/* Operation already in progress */
	define('EINPROGRESS',	10036);	/* Operation now in progress */
}
else if ($OS == 'freebsd')
{
	//Thanks to ryguy@efnet
	///usr/include/errno.h (freebsd)
	define('EAGAIN', 		35); 	/* Try again */
	define('EISCONN', 		56); 	/* Transport endpoint is already connected */
	define('EALREADY', 		37); 	/* Operation already in progress */
	define('EINPROGRESS', 	36); 	/* Operation now in progress */
}
else if ($OS == 'linux')
{
	///usr/include/sys/errno.h (sparc)
	define('EAGAIN', 		11);	/* Try again */
	define('EISCONN', 		106);	/* Transport endpoint is already connected */
	define('EALREADY',		114);	/* Operation already in progress */
	define('EINPROGRESS',	115);	/* Operation now in progress */
}
else if ($OS == 'unix')
{
	///usr/include/asm/errno.h (mandrake 9.0)
	define('EAGAIN', 		11);	/* Try again */
	define('EISCONN', 		133);	/* Transport endpoint is already connected */
	define('EALREADY',		149);	/* Operation already in progress */
	define('EINPROGRESS',	150);	/* Operation now in progress */
}

// Version Definition
define('VERSION', '2.2.2');
define('VERSION_DATE', '19/07/2015');

// Timer declarations
define('NICK_CHECK_TIMEOUT', 120); //seconds
define('CHAN_CHECK_TIMEOUT', 60); //seconds
define('PING_TIMEOUT', 130); //seconds (check every 130 seconds if we're still connected)

// Parser definitions
define('MAX_ARGS', 4);

// Status definitions
define('STATUS_IDLE', 0);
define('STATUS_ERROR', 1);
define('STATUS_CONNECTING', 2);
define('STATUS_CONNECTED', 3);
define('STATUS_CONNECTED_SENTREGDATA', 4);
define('STATUS_CONNECTED_REGISTERED', 5);

// Constant Definitions
define('ERROR_TIMEOUT', 60);
define('CONNECT_TIMEOUT', 45);
define('REGISTRATION_TIMEOUT', 60);
define('TIMEOUT_CHECK_TIME', 85); //85

//Constants for Channel Modes
define('BY_MASK', 0);
define('BY_STRING', 1);
define('BY_INT', 2);
define('BY_NONE', 3);

//Used with $ircClass->parseMode
define('USER_MODE', 0);
define('CHANNEL_MODE', 1);

//Random Vars
define('STATUS_JUST_BANNED', 1);
define('STATUS_ALREADY_BANNED', 2);
define('STATUS_NOT_BANNED', 3);

//Socket Class defines
define('SOCK_DEAD', 1);
define('SOCK_CONNECTING', 2);
define('SOCK_LISTENING', 3);
define('SOCK_ACCEPTED', 4);
define('SOCK_ACCEPTING', 5);
define('SOCK_CONNECTED', 6);
define('HIGHEST_PORT', 1000);  // this is tcpRangeStart + HIGHEST_PORT

//DCC Class defines
define('FILE', 0);
define('CHAT', 1);
define('DCC_WAITING', 3);
define('DCC_REVERSE', 4);
define('DCC_CONNECTING', 0);
define('DCC_CONNECTED', 1);
define('DCC_LISTENING', 2);

//Connection class defines
define('CONN_READ', 0);
define('CONN_WRITE', 1);
define('CONN_ACCEPT', 2);
define('CONN_CONNECT', 3);
define('CONN_DEAD', 4);
define('CONN_CONNECT_TIMEOUT', 5);
define('CONN_TRANSFER_TIMEOUT', 6);

//Parser Class defines
define('BRIGHT', chr(3) . "13");
define('DARK', chr(3) . "03");
define('NORMAL', chr(16));
define('BOLD', chr(2));
define('UNDERLINE', chr(31));
define('PRIV', 1);
define('DCC', 2);

//File Class defines
define('UPLOAD', 0);
define('DOWNLOAD', 1);

//Used with $ircClass->addQuery
define('QUERY_SUCCESS', 0);
define('QUERY_ERROR', 1);

//Used in ini
define('EXACT_MATCH', 0);
define('AND_MATCH', 1);
define('OR_MATCH', 2);
define('CONTAINS_MATCH', 3);

//Used in socket class to keep track of sockets

class socketInfo {
	public $socket;
	public $status;
	public $readQueue;
	public $readLength;
	public $writeQueue;
	public $writeLength;
	public $host;
	public $port;
	public $newSockInt;
	public $listener;
	public $owner;
	public $class;
	public $func;
	public $readScheduled; //Used so we don't add infinite queues to the process queue.
	public $writeScheduled;
}

//Channel and Username Linked List (Links) Definitions

class channelLink {
	public $name;
	public $count;
	public $memberList = array();
	public $banList = array();
	public $whoComplete;
	public $banComplete;
	public $modes;
	public $created;
	public $topic;
	public $topicBy;
}

class memberLink {
	public $nick;
	public $realNick;
	public $host;
	public $ident;
	public $banned;
	public $bantime;
	public $status;
	public $ignored;
}

// Used in timer class

class timer {
	public $name;
	public $class;
	public $args;
	public $interval;
	public $lastTimeRun;
	public $nextRunTime;
	public $func;
}

class usageLink {
	public $isBanned;
	public $timeBanned;
	public $lastTimeUsed;
	public $timesUsed;
}


// Useful for sending arguments with timers
class argClass
{
	public $arg1;
	public $arg2;
	public $arg3;
	public $arg4;
	public $arg5;
	public $arg6;
	public $arg7;
	public $arg8;
	public $timer;
}

// Used to instantiate a bot
class botClass {
	public $timerClass;
	public $ircClass;
	public $dccClass;
	public $parserClass;
	public $socketClass;
	public $configFilename;
	public $db;
	public $config;
}

// Used with processQueue
class queueItem {
	public $owner; //IRC Class of owner
	public $callBack_class;  //CALL BACK class/function to use
	public $callBack_function;
	public $nextRunTime; //The next getMicroTime() time to run
	public $removed;
	public $next;
	public $prev;
}

?>
