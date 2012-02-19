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
|   > Main module
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

error_reporting(E_ALL);
set_time_limit(0);

require('./defines.php');
require('./queue.php');
require('./module.php');
require('./irc.php');
require('./socket.php');
require('./timers.php');
require('./dcc.php');
require('./chat.php');
require('./file.php');
require('./parser.php');
require('./databases/ini.php');
require('./error.php');
require('./connection.php');
require('./remote.php');

final class bot {

	/* Global socket class used by all bots */
	private $socketClass;

	/* Global process Queue used by all bots, timers, dcc classes */
	private $procQueue;
	
	/* Whether we are running in background mode or not.  (not sure if this is used anymore */
	private $background = 0;

	//contain all the bots
	private $bots = array();
	
	// save the only one instance of bot (singleton)
	private static $_instance;

	public static function getInstance()
	{
		if (!isset (self :: $_instance))
		{
			self :: $_instance = new bot();
		}
		return self :: $_instance;
	}


	//Main Method
	private function __construct()
	{

		$this->socketClass = new socket();
		$this->procQueue = new processQueue();
		
		$this->socketClass->setProcQueue($this->procQueue);

		$this->readConfig();
	}
	
	public function launch(){

		foreach($this->bots as $bot)
		{
			$this->createBot($bot);
		}

		try
		{

			/* Main program loop */

			while (1)
			{
				//Get data from sockets, and trigger procQueue's for new data to be read
				$this->socketClass->handle();

				//The bots main process loop.  Run everything we need to!
				$timeout = $this->procQueue->handle();

				//Okay, set the socketclass timeout based on the next applicable process
				if ($timeout !== true)
				{
					$this->socketClass->setTimeout($timeout);
				}

				//echo $this->procQueue->getNumQueued() . "\n";
				//$this->procQueue->displayQueue();

				//Aight, if we don't have any sockets open/in use, and
				//we have no processes in the process queue, then there are
				//obviously no bots running, so just exit!
				if ($this->socketClass->getNumSockets() == 0 && $this->procQueue->getNumQueued() == 0)
				{
					break;
				}
			}

		}
		catch (Exception $e)
		{
			$this->ircClass->log($e->_toString());
		}

	}


	public static function addBot($filename){
		$bot = bot::getInstance();
		$config = bot::parseConfig($filename);
			if ($config == false)
			{
				return false;
			}

			$newBot = new botClass();
			$newBot->config = $config;
			$newBot->configFilename = $filename;

			$bot->bots[] = $newBot;
			$bot->createBot($newBot);
			return true;
	}


	private function createBot($bot)
	{
		$this->connectToDatabase($bot);

		$bot->socketClass = $this->socketClass;
		$bot->timerClass = new timers();
		$bot->parserClass = new parser();

		$bot->dccClass = new dcc();
		$bot->ircClass = new irc();

		$bot->ircClass->setConfig($bot->config, $bot->configFilename);
		$bot->ircClass->setSocketClass($this->socketClass);
		$bot->ircClass->setParserClass($bot->parserClass);
		$bot->ircClass->setDccClass($bot->dccClass);
		$bot->ircClass->setTimerClass($bot->timerClass);
		$bot->ircClass->setProcQueue($this->procQueue);

		$bot->dccClass->setSocketClass($this->socketClass);
		$bot->dccClass->setTimerClass($bot->timerClass);
		$bot->dccClass->setParserClass($bot->parserClass);
		$bot->dccClass->setProcQueue($this->procQueue);

		$bot->parserClass->setTimerClass($bot->timerClass);
		$bot->parserClass->setSocketClass($this->socketClass);
		$bot->parserClass->setDatabase($bot->db);

		$bot->timerClass->setIrcClass($bot->ircClass);
		$bot->timerClass->setSocketClass($this->socketClass);
		$bot->timerClass->setProcQueue($this->procQueue);

		$bot->parserClass->init();
		
		//Okay, this function adds the connect timer and starts up this bot class.
		$bot->ircClass->init();

		bot::createChannelArray($bot->ircClass);

	}


	private function readConfig()
	{
		global $argc, $argv;

		if ($argc < 2)
		{
			echo "You must specify a config file.\n";
			die();
		}

		$isPasswordEncrypt = false;

		array_shift($argv);

		foreach ($argv AS $filename)
		{
			if ($filename == "")
			{
				continue;
			}

			if ($isPasswordEncrypt == true)
			{
				die("Encrypted Password: " . md5($filename) . "\nReplace this as 'dccadminpass' in bot.conf!");
			}

			if ($filename == "-c")
			{
				$isPasswordEncrypt = true;
				continue;
			}

			if ($filename == "-b" && $this->background != 1)
			{
				$this->background == 1;
				$this->doBackground();
				continue;
			}

			$config = bot::parseConfig($filename);
			
			if ($config == false)
			{
				echo "Could not spawn bot $filename";
				die();
			}

			$bot = new botClass();
			$bot->config = $config;
			$bot->configFilename = $filename;

			$this->bots[] = $bot;
		}
		
		if ($isPasswordEncrypt == true)
		{
			die("No password submitted on command line! Syntax: bot.php -c <new admin password>\n");
		}

	}




	private function connectToDatabase($bot)
	{
		if (isset($bot->config['usedatabase']))
		{
			if (!file_exists("./databases/" . $bot->config['usedatabase']. ".php"))
			{
				die("Couldn't find the database file! Make sure it exists!");
			}
			
			require_once("./databases/" . $bot->config['usedatabase']. ".php");

			$dbType = $bot->config['usedatabase'];

			if (!isset($bot->config['dbhost']))
				$bot->config['dbhost'] = "localhost";
			if (!isset($bot->config['dbuser']))
				$bot->config['dbuser'] = "root";
			if (!isset($bot->config['dbpass']))
				$bot->config['dbpass'] = "";
			if (!isset($bot->config['db']))
				$bot->config['db'] = "test";
			if (!isset($bot->config['dbprefix']))
				$bot->config['dbprefix'] = "";
			if (!isset($bot->config['dbport']))
			{
				$bot->db = new $dbType($bot->config['dbhost'],
								$bot->config['db'],
								$bot->config['dbuser'],
								$bot->config['dbpass'],
								$bot->config['dbprefix']);
			}
			else
			{
				$bot->db = new $dbType($bot->config['dbhost'],
								$bot->config['db'],
								$bot->config['dbuser'],
								$bot->config['dbpass'],
								$bot->config['dbprefix'],
								$bot->config['dbport']);
			}

			if (!$bot->db->isConnected())
			{
				die("Couldn't connect to database...");
			}

		}
	}


	public static function createChannelArray($ircClass)
	{
		$channels = $ircClass->getClientConf('channel');

		if ($channels != "")
		{
			if (!is_array($channels))
			{
				$channels = array($channels);
			}
			
			foreach ($channels AS $channel)
			{
				$chan = $channel;
				$key = "";

				if (strpos($channel, chr(32)) !== false)
				{
					$channelVars = explode(chr(32), $channel);
					$chan = $channelVars[0];
					$key = $channelVars[1];
				}

				$ircClass->maintainChannel($chan, $key);
			}
		}
	}

	public static function parseConfig($filename)
	{
		$configFPtr = @fopen($filename, "rt");

		if ($configFPtr == null)
		{
		//	echo "Could not find config file '".$filename."'\n";
			return false;
		}

		$configRaw = "";

		try
		{

			while (!feof($configFPtr))
			{
				$configRaw .= fgets($configFPtr, 1024);
			}
			
			fclose($configFPtr);

		}
		catch (Exception $e)
		{
		//	echo "A fatal IO Exception occured.";
			return false;
		}

		$config = array();

		$configRaw = str_replace("\r", "", $configRaw);

		$confLines = explode("\n", $configRaw);

		foreach ($confLines AS $line)
		{
			$line = trim($line);

			if ($line == "" || substr($line, 0, 1) == ";")
			{
				continue;
			}

			$offsetA = strpos($line, chr(32));

			if ($offsetA != false)
			{
				$confVar = substr($line, 0, $offsetA);
				$confParams = substr($line, $offsetA + 1);
			}
			else
			{
				$confVar = $line;
				$confParams = "";
			}

			if (isset($config[$confVar]))
			{
				if (!is_array($config[$confVar]))
				{
					$prevParam = $config[$confVar];
					$config[$confVar] = array();
					$config[$confVar][] = $prevParam;
				}

				$config[$confVar][] = $confParams;

			}
			else
			{
				$config[$confVar] = $confParams;
			}
		}

		return $config;
	}


	private function doBackground()
	{
		$pid = pcntl_fork();
		if ($pid == -1)
		{
			die("Error: could not fork\n");
		}
		else if ($pid)
		{
			if (PID != "")
			{
				$file = fopen(PID, "w+");
				fwrite($file, $pid);
				fclose($file);
			}

			exit(); // Parent
		}

		if (!posix_setsid())
		{
			die("Error: Could not detach from terminal\n");
		}
		else
		{
			echo "PHP|IRC is now running in the background\n";
		}

		fclose(STDOUT);

	}

}

$ircBot = bot::getInstance();
$ircBot->launch();

?>
