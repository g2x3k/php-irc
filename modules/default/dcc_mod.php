<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC v2.2.0
|   ========================================================
|   by Manick
|   (c) 2001-2005 by http://phpbots.sf.net/
|   Contact: manick@manekian.com
|   irc: #manekian@irc.rizon.net
|   ========================================
+---------------------------------------------------------------------------
|   > dcc_mod module
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

class dcc_mod extends module {

	public $title = "DCC Chat Utils";
	public $author = "Manick";
	public $version = "2.1.1";
	public $dontShow = true;

	public function init()
	{
		$this->timerClass->addTimer("dccstatus", $this, "sendStatus", "", 5*60);
	}
	
	public function destroy()
	{
		$this->timerClass->removeTimer("dccstatus");
	}

	/* DCC Functions */

	public $monitorList = array();

	public function monitor_check($line, $args)
	{
		switch($line['cmd'])
		{
			case "PRIVMSG":
				if (isset($this->monitorList[irc::myStrToLower($line['to'])]))
				{
					if (preg_match("/\1ACTION (.+?)\1/", $line['text'], $match))
					{
						$this->dccClass->dccInform("CHAN: " . $line['to'] . ": * " . $line['fromNick'] . " " . $match[1]);
					}
					else
					{
						$this->dccClass->dccInform("CHAN: " . $line['to'] . ": <" . $line['fromNick'] . "> " . $line['text']);
					}
				}
				break;

			case "MODE":
				if ($line['fromNick'] != $this->ircClass->getNick())
				{
					if (isset($this->monitorList[irc::myStrToLower($line['to'])]))
					{
						$this->dccClass->dccInform("CHAN: " . $line['to'] . ": *** " . $line['fromNick'] . " sets mode: ".$line['params']);
					}
				}
				break;

			case "JOIN":
				if ($line['fromNick'] != $this->ircClass->getNick())
				{
					if (isset($this->monitorList[irc::myStrToLower($line['text'])]))
					{
						$this->dccClass->dccInform("CHAN: " . $line['text'] . ": *** " . $line['fromNick'] . " joined channel.");
					}
				}
				break;

			case "PART":
				if ($line['fromNick'] != $this->ircClass->getNick())
				{
					if (isset($this->monitorList[irc::myStrToLower($line['to'])]))
					{
						$this->dccClass->dccInform("CHAN: " . $line['to'] . ": *** " . $line['fromNick'] . " parted channel.");
					}
				}
				break;

			case "KICK":
				if ($line['params'] != $this->ircClass->getNick())
				{
					if (isset($this->monitorList[irc::myStrToLower($line['to'])]))
					{
						$this->dccClass->dccInform("CHAN: " . $line['to'] . ": *** " . $line['params'] . " was kicked by ".$line['fromNick']." (".$line['text'].").");
					}
				}

				break;

			default:
				break;

		}
	}
	

	public function dcc_botinfo($chat, $args)
	{

		$chat->dccSend("PHP-IRC v" . VERSION . " [".VERSION_DATE."] by Manick (visit http://phpbots.sf.net/ to download)");
		$chat->dccSend("total running time of " . $this->ircClass->timeFormat($this->ircClass->getRunTime(), "%d days, %h hours, %m minutes, and %s seconds."));

		$fd = @fopen("/proc/" . $this->ircClass->pid() . "/stat", "r");
		if ($fd !== false)
		{
			$stat = fread($fd, 1024);
			fclose($fd);

			$stat_array = explode(" ", $stat);

			$pid = $stat_array[0];
			$comm = $stat_array[1];
			$utime = $stat_array[13];
			$stime = $stat_array[14];
			$vsize = $stat_array[22];
			$meminfo = number_format($vsize, 0, '.',',');
			$u_time = number_format($utime / 100, 2,'.',',');
			$s_time = number_format($stime / 100, 2,'.',',');
			
			$fd = @fopen("/proc/stat", "r");
			if ($fd !== false)
			{
				$stat = fread($fd, 1024);
				fclose($fd);

				$stat = str_replace("  ", " ", $stat);
				$stat_array_2 = explode(" ", $stat);
				$totalutime = $stat_array_2[1];
				$totalstime = $stat_array_2[3];
				$u_percent = number_format($utime / $totalutime, 6,'.',',');
				$s_percent = number_format($stime / $totalstime, 6,'.',',');

				$chat->dccSend("cpu usage: " . $u_time . "s user (" . $u_percent . "%), " . $s_time . "s system (" . $s_percent . "%)");
			}

			$chat->dccSend("memory usage: " . $meminfo . " bytes");

		}

		$fd = @fopen("/proc/loadavg", "r");
		if ($fd !== false)
		{
			$loadavg = fread($fd, 1024);
			$loadavg_array = explode(" ", $loadavg);
			$loadavgs = $loadavg_array[0] . " " . $loadavg_array[1] . " " .$loadavg_array[2];
			fclose($fd);

			$chat->dccSend("cpu load averages: " . $loadavgs);
		}

		$realname = $this->ircClass->getClientConf('realname') == "" ? "n/a" : $this->ircClass->getClientConf('realname');
		$upload = $this->ircClass->getClientConf('upload') == "yes" ? "yes" : "no";

		$chat->dccSend("configured nick: " . $this->ircClass->getClientConf('nick') . ", " .
						"actual nick: " . $this->ircClass->getNick() . ", realname: " . $realname);

		$chat->dccSend("upload is currently set to " . $upload);

		if ($this->ircClass->getStatusRaw() == STATUS_CONNECTED_REGISTERED)
		{
			$network = $this->ircClass->getServerConf('Network') == "" ? $this->ircClass->getClientConf('server') : $this->ircClass->getServerConf('Network');
			$chat->dccSend("current server: " . $network . "(" . $this->ircClass->getClientConf('server') . ") port: " . $this->ircClass->getClientConf('port'));
		}
		else
		{
			$chat->dccSend("current server: none");
		}

		$maintain = $this->ircClass->getMaintainedChannels();

		$maintained = "n/a";

		if (isset($maintain[0]))
		{
			$maintained = "";
			foreach ($maintain AS $chan)
			{
				$maintained .= $chan['CHANNEL'] . " ";
			}
			$maintained = trim($maintained);
			$chat->dccSend("configured channels: " . $maintained);
		}

		$channels = $this->ircClass->getChannelData();

		foreach($channels AS $chanPtr)
		{
			$chat->dccSend("in channel " . strtoupper($chanPtr->name) . ": users: " . $chanPtr->count);
		}

		$chat->dccSend("status line: " . $this->getStatus());
		$chat->dccSend("config file: " . $this->ircClass->getConfigFilename());

		return;

	}

	public function dcc_connect($chat, $args)
	{
		$status = $this->ircClass->getStatusRaw();

		if ($status == STATUS_ERROR)
		{
			$this->ircClass->reconnect();
		}
	}


	public function dcc_monitor($chat, $args)
	{
		if ($args['nargs'] == 0)
		{
			$chat->dccSend("The following channels are being monitored:");
			foreach ($this->monitorList AS $channel => $random)
			{
				$chat->dccSend($channel);
			}
		}
		else
		{

			$chan = irc::myStrToLower($args['arg1']);

			if (!isset($this->monitorList[$chan]))
			{
				$this->monitorList[$chan] = 1;
				$chat->dccSend("The channel '" . $chan . "' is now being monitored.");
			}
			else
			{
				$chat->dccSend("The channel '" . $chan . "' was removed from the monitored list.");
				unset($this->monitorList[$chan]);
			}
		}

	}


	
	public function dcc_who($chat, $args)
	{
		$dccList = $this->dccClass->getDccList();

		$chat->dccSend("--- Users Online ---");
		$chat->dccSend("[id] <nick> <admin>");
		foreach ($dccList AS $chatBox)
		{
			if ($chatBox->type == CHAT)
			{
				$chat->dccSend("[" . $chatBox->id . "] " . $chatBox->nick . ($chatBox->isAdmin ? " (admin)" : ""));
			}
		}
	}

	public function dcc_timers($chat, $args)
	{
		$timers = $this->timerClass->getTimers();

		$chat->dccSend("Active Timers:");

		if (count($timers) > 0)
		{
			$time = timers::getMicroTime();
			foreach ($timers AS $timer)
			{
				$interval = "interval(" . $timer->interval . " sec)";

				$timeTillNext = round(($timer->nextRunTime - $time < 0 ? 0 : $timer->nextRunTime - $time), 0);

				$ttnext = irc::timeFormat($timeTillNext, "%m min, %s sec");

				if (is_object($timer->class))
				{
					$cName = get_class($timer->class);
				}
				else
				{
					$cName = "";
				}
				
				$tName = preg_replace("/([a-z0-9]){32}/", "", $timer->name);
				if ($tName == "")
				{
					$tName = "(random hash)";
				}

				$cName = preg_replace("/_([a-z0-9]){32}/", "", $cName);

				$chat->dccSend("Timer " . BOLD . $tName . BOLD . ": func(" . $cName . "::" . $timer->func . ") " . $interval . ", Time till next run: " . $ttnext);
			}
		}
		else
		{
			$chat->dccSend("There are currently no timers.");
		}

	}

	public function dcc_reloadfunc($chat, $args)
	{
		if ($this->ircClass->getClientConf('functionfile') != "")
		{
			$stat = $this->parserClass->loadFuncs($this->ircClass->getClientConf('functionfile'));
			$chat->dccSend("Function reload complete");
			if ($stat == true)
			{
				$chat->dccSend("There were errors loading a function file!  Cached version may still be in use!");
			}
		}
		else
		{
			$chat->dccSend("No function file defined in config file.");
		}
	}
	
	public function dcc_rehash($chat, $args)
	{

		$chat->dccSend("Rehashing main config file, please wait...");

		$currConfig = $this->ircClass->getClientConf();
		$newConfig = bot::parseConfig($this->ircClass->getConfigFilename());

		if ($newConfig == false)
		{
			$chat->dccSend("Could not find config file or IO error.");
			return;
		}

		$this->ircClass->setConfig($newConfig, $this->ircClass->getConfigFilename());

		if ($currConfig['nick'] != $newConfig['nick'])
		{
			$chat->dccSend("Changing nick...");
			$this->ircClass->changeNick($newConfig['nick']);
		}

		if ($currConfig['server'] != $newConfig['server'])
		{
			$chat->dccSend("Connecting to new server...");
			$this->ircClass->disconnect();
			$this->ircClass->reconnect();
		}
		else
		{
			if (isset($currConfig['channel']))
			{
				if (!is_array($currConfig['channel']))
				{
					$currConfig['channel'] = array($currConfig['channel']);
				}
				if (!is_array($newConfig['channel']))
				{
					$newConfig['channel'] = array($newConfig['channel']);
				}

				foreach($currConfig['channel'] AS $chan)
				{
					if (!in_array($chan, $newConfig['channel']))
					{
						$chan = trim($chan) . " ";
						$chan = trim(substr($chan, 0, strpos($chan, chr(32)) + 1));
						$this->ircClass->sendRaw("PART " . $chan);
					}

				}
			}
		}
		
		$this->ircClass->purgeMaintainList();

		$chat->dccSend("Rehashing channel list...");
		bot::createChannelArray($this->ircClass);

		$chat->dccSend("Rehashing IP address...");
		if (isset($newConfig['natip']))
		{
			if (isset($currConfig['natip']))
			{
				if ($currConfig['natip'] != $newConfig['natip'])
				{
					$this->ircClass->setClientIP($newConfig['natip']);
				}
			}
			else
			{
				$this->ircClass->setClientIP($newConfig['natip']);
			}
		}
		else
		{
			if ($this->ircClass->getStatusRaw() != STATUS_CONNECTED_REGISTERED)
			{
				$chat->dccSend("NOTICE: Cannot reset IP address unless connected to server. No change made.");
			}
			else
			{
				$this->ircClass->setClientIP();
			}
		}

		if (isset($newConfig['dccrangestart']) && $newConfig['dccrangestart'] != $currConfig['dccrangestart'])
		{
			$chat->dccSend("Updating TCP Range...");
			$this->socketClass->setTcpRange($newConfig['dccrangestart']);
		}

		if (isset($newConfig['logfile']) && $newConfig['logfile'] != $currConfig['logfile'])
		{
			$chat->dccSend("Changing log file...");
			$this->ircClass->closeLog();
		}

		if (isset($newConfig['functionfile']))
		{
			if ($newConfig['functionfile'] != $currConfig['functionfile'])
			{
				$this->parserClass->loadFuncs($newConfig['functionfile']);
			}
		}
		else
		{
			$chat->dccSend("Fatal Error, functionfile directive not set.  The performance of this bot is no longer guaranteed (please restart and fix your error)");
			return;
		}

		$chat->dccSend("Main config rehash complete.");

	}
	
	public function dcc_join($chat, $args)
	{
		$chat->dccSend("Joining: " . $args['query']);
		$this->ircClass->sendRaw("JOIN " . $args['query']);
	}

	public function dcc_part($chat, $args)
	{
		$this->ircClass->sendRaw("PART " . $args['query']);
	}

	public function dcc_rejoin($chat, $args)
	{
		$chanPtr = $this->ircClass->getChannelData($args['arg1']);
		
		if ($chanPtr == NULL)
		{
			$chat->dccSend("You are not on channel '" . $args['arg1'] . "'");
		}
		else
		{
			$chat->dccSend("Rejoining: " . $args['arg1']);
			$this->ircClass->sendRaw("JOIN " . $args['arg1']);
			$this->ircClass->sendRaw("PART " . $args['arg1']);
		}
	}


	public function dcc_shutdown($chat, $args)
	{
		$chat->dccSend("Shutting down, sending kill command to irc class.");

		if ($this->ircClass->getStatusRaw() == STATUS_CONNECTED_REGISTERED)
		{
			$time = $this->ircClass->timeFormat($this->ircClass->getRunTime(), "%dd%hh%mm%ss");
			$msg = "php-irc v" . VERSION . " by Manick, running ".$time;
			$this->ircClass->sendRaw("QUIT :" . $msg);
		}

		$chat->dccSend("Waiting for server queue to flush...");

		$this->timerClass->addTimer("shutdown", $this->ircClass, "shutdown", "", 1);
	}

	public function dcc_ignore($chat, $args)
	{
		$usageList = $this->ircClass->getUsageList();

		$chat->dccSend("--- Usage List (* denotes active ignore) ---");

		foreach($usageList AS $host => $user)
		{
			if (trim($host) != "")
			{
				if (intval($user->timeBanned) > 5)
				{
					$chat->dccSend(($user->isBanned == true ? "*" : "") . $host . ": " . (intval($user->timeBanned) > 5 ? date("m-d-y h:i:s a", $user->timeBanned) : "never banned"));
				}
			}
		}


	}

	public function dcc_rignore($chat, $args)
	{
		$usageList = $this->ircClass->getUsageList();
		
		if (array_key_exists($args['arg1'], $usageList))
		{
			$usageList[$args['arg1']]->isBanned = false;
			$chat->dccSend("Ignore for " . $args['arg1'] . " successfully removed.");
		}
		else
		{
			$chat->dccSend("No such ignore.");
		}

	}

	public function dcc_clearqueue($chat, $args)
	{
		if ($args['nargs'] > 0)
		{
			$this->ircClass->removeQueues($args['arg1']);
			$chat->dccSend("All text queues for " . $args['arg1'] . " removed.");
		}
		else
		{
			$this->ircClass->purgeTextQueue();
			$chat->dccSend("All text queues purged.");
		}
	}


	public function dcc_status($chat, $args)
	{
		$chat->dccSend($this->getStatus());
	}

	public function dcc_server($chat, $args)
	{
		$server = $args['arg1'];
		$port = 6667;

		if ($args['nargs'] > 1)
		{
			$port = intval($args['arg2']);
			if ($port == 0)
			{
				$port = 6667;
			}
		}

		$chat->dccSend("Changing server to: " . $server . ":" . $port);
		$this->ircClass->setClientConfigVar('server', $server);
		$this->ircClass->setClientConfigVar('port', $port);
		$this->ircClass->disconnect();
		$this->ircClass->reconnect();
	}

	public function dcc_exit($chat, $args)
	{
		$this->dccClass->dccInform("DCC: " . $chat->nick . " logged off", $chat);
		$chat->disconnect("User quit");
	}

	public function dcc_action($chat, $args)
	{
		$this->ircClass->action($args['arg1'], substr($args['query'], strlen($args['arg1']) + 1));
	}

	public function dcc_restart($chat, $args)
	{
		$this->ircClass->disconnect();
	}

	public function dcc_chat($chat, $args)
	{
		$this->dccClass->dccInform("CHAT: (" . $chat->nick . ")> " . $args['query'], $chat);
	}

	public function dcc_raw($chat, $args)
	{
		$this->ircClass->sendRaw($args['query']);
	}

	public function dcc_say($chat, $args)
	{
		$this->ircClass->privMsg($args['arg1'], substr($args['query'], strlen($args['arg1']) + 1));
	}
	
	public function dcc_users($chat, $args)
	{
		$chat->dccSend($this->ircClass->displayUsers());
	}
	
	public function dcc_upload($chat, $args)
	{
		$args['arg1'] = irc::myStrToLower($args['arg1']);

		if ($args['arg1'] == "yes")
		{
			$this->ircClass->setClientConfigVar('upload', 'yes');
			$chat->dccSend("Upload is now set to allow.");
		}
		else if ($args['arg1'] == "no")
		{
			$this->ircClass->setClientConfigVar('upload', 'no');
			$chat->dccSend("Upload is now set to deny.");
		}
		else
		{
			$chat->dccSend("Upload is currently set to: " . $this->ircClass->getClientConf('upload'));
			$chat->dccSend("Valid syntax is 'yes' or 'no'.");
		}
	}


	public function dcc_maintain($chat, $args)
	{
		if ($args['nargs'] == 0)
		{
		
			$chat->dccSend("-- Maintained Channels --");
			$chans = $this->ircClass->getMaintainedChannels();

			$num = 0;
			foreach($chans AS $chan)
			{
				$chat->dccSend($chan['CHANNEL'] .
										($chan['KEY'] != "" ? " with key " . $chan['KEY'] : ""));
				$num++;
			}

			$chat->dccSend($num . " total channels");

		}
		else
		{
			$chanArg = irc::myStrToLower($args['arg1']);

			$chans = $this->ircClass->getMaintainedChannels();

			$found = false;
			foreach($chans AS $chan)
			{
				if ($chan['CHANNEL'] == $chanArg)
				{
					$found = true;
					break;
				}
			}
			
			if ($found == true)
			{
				$this->ircClass->removeMaintain($chanArg);
				$chat->dccSend("Channel " . $chanArg . " successfully removed from maintain list.");
			}
			else
			{
				$this->ircClass->maintainChannel($chanArg, ($args['nargs'] >= 2 ? $args['arg2'] : ""));
				$chat->dccSend("Channel " . $chanArg . " is now being maintained"
												 . ($args['nargs'] >= 2 ? " with key " . $args['arg2'] : "."));

				if ($this->ircClass->isOnline($this->ircClass->getNick(), $chanArg) == false)
				{
					$this->ircClass->joinChannel($chanArg . ($args['nargs'] >= 2 ? " " . $args['arg2'] : ""));
				}

			}

		}


	}

	public function dcc_help($chat, $args)
	{
		$cmdList = $this->parserClass->getCmdList('dcc');
		$sectionList = $this->parserClass->getCmdList('section');

		if ($args['nargs'] > 0)
		{
			$cmd = $args['arg1'];

			if (isset($cmdList[$cmd]))
			{
				$chat->dccSend("Usage: " . $cmd . " " . $cmdList[$cmd]['usage']);
				$chat->dccSend("Section: " . $sectionList[$cmdList[$cmd]['section']]['longname']);
				$chat->dccSend("Description: " . $cmdList[$cmd]['help']);
			}
			else
			{
				$chat->dccSend("Invalid Command: " . $line['arg1']);
			}

			return;
		}

		$chat->dccSend("Commands:");

		$sections = array();

		foreach ($cmdList AS $cmd => $cmdData)
		{
			if (!$chat->isAdmin && $cmdData['admin'] == true)
			{
				continue;
			}
			
			$sections[$cmdData['section']][] = strtoupper($cmd) . " - " . $cmdData['help'];
		}

		foreach ($sections AS $section => $data)
		{
			$chat->dccSend($sectionList[$section]['longname']);

			foreach ($data AS $cmd)
			{
				$chat->dccSend("-- " . $cmd);
			}
		}

		$chat->dccSend("Use HELP <command> for a list of arguments");


	}
	
	public function dcc_modules($chat, $args)
	{
		$cmdList = $this->parserClass->getCmdList();

		if (isset($cmdList['file']))
		{
			$chat->dccSend("Installed Modules:");

			foreach($cmdList['file'] AS $module)
			{
				$class = $module['class'];

				$chat->dccSend("-- " . $class->title . " " . $class->version . " by " . $class->author);
			}
		}
		else
		{
			$chat->dccSend("There are no installed modules.");
		}
	}


	public function dcc_close($chat, $args)
	{
		$dccList = $this->dccClass->getDccList();

		foreach ($dccList AS $sockInt => $dcc)
		{
			if ($args['arg1'] == $dcc->id)
			{
				if ($dcc->type == CHAT && $dcc->isAdmin == true)
				{
					$chat->dccSend("Cannot close admin session!");
					return;
				}

				$dcc->disconnect("Owner Requested Close");
				break;
			}
		}
	}

	public function dcc_listul($chat, $args)
	{

		$uldir = $this->ircClass->getClientConf('uploaddir');

		if ($uldir != "")
		{
			$chat->dccSend("Directory Contents, " . $uldir);

			$dir = @scandir($uldir);

			$dirs = 0;
			$files = 0;

			if (count($dir))
			{

				foreach ($dir AS $file)
				{
					if (is_dir($uldir . "/" . $file))
					{
						$dirs++;
						$chat->dccSend(BOLD . "dir: " . BOLD . $file);
					}
					else if (is_file($uldir . "/" . $file))
					{
						$files++;
						$chat->dccSend($file);
					}
				}
			}
			$chat->dccSend($dirs . " directories, " . $files . " files");
			
		}
		else
		{
			$chat->dccSend("Error, no uploaddir configuration directive set in config file.");
		}
	}


	public function dcc_dccs($chat, $args)
	{
		$dccList = $this->dccClass->getDccList();
		$currDcc = false;

		foreach ($dccList AS $sockInt => $dcc)
		{
			if ($dcc->type == FILE)
			{
				$currDcc = true;

				if ($dcc->speed_lastavg == 0)
				{
					$percent = "0%";
					$eta = "n/a";
					$speed = 0.0;
				}
				else
				{
					$percent = round(($dcc->bytesTransfered/$dcc->filesize)*100, 1) . "%";
					$eta = $this->ircClass->timeFormat(round(($dcc->filesize - $dcc->bytesTransfered)/$dcc->speed_lastavg, 0), "%hh,%mm,%ss");
					$speed = irc::intToSizeString($dcc->speed_lastavg);
				}

				if ($dcc->transferType == UPLOAD)
				{
					$chat->dccSend("Upload[{$dcc->id}]: " . $dcc->nick . " " . $dcc->filenameNoDir . " ".$percent." " . $speed . "/s eta:" . $eta);
				}
				else
				{
					$chat->dccSend("Download[{$dcc->id}]: " . $dcc->nick . " " . $dcc->filenameNoDir . " ".$percent." " . $speed . "/s eta:" . $eta);
				}
			}
		}

		if ($currDcc == false)
		{
			$chat->dccSend("No dcc transfers in progress");
		}
	}

	
	public function dcc_send($chat, $args)
	{
		$filename = substr($args['query'], strlen($args['arg1']) + 1);

		$this->dccClass->addFile($args['arg1'], null, null, UPLOAD, $filename, null);
	}

	public function dcc_function($chat, $args)
	{
		$cmdList = $this->parserClass->getCmdList();

		if ($args['nargs'] == 0)
		{
			$chat->dccSend("-- All user-defined function status --");

			$num = 0;
			foreach ($cmdList['priv'] AS $cmd => $data)
			{
				$chat->dccSend($cmd . " => " . ($data['active'] == true ? "active" : "inactive") . ", Usage: " . $data['usage']);
				$num++;
			}
			$chat->dccSend($num . " total functions.");

		}
		else if ($args['nargs'] == 2)
		{
			if ($args['arg1'] == "activate")
			{
				if ($args['arg2'] == "all")
				{
					foreach($cmdList['priv'] AS $cmd => $data)
					{
						//$cmdList['priv'][$cmd]['active'] = true;
						$this->parserClass->setCmdListValue('priv', $cmd, 'active', true);
					}
					$chat->dccSend("All functions activated.");
				}
				else
				{
					$cmdLower = irc::myStrToLower($args['arg2']);

					if (isset($cmdList['priv'][$cmdLower]))
					{
						$this->parserClass->setCmdListValue('priv', $cmdLower, 'active', true);
						//$cmdList['priv'][$cmdLower]['active'] = true;
						$chat->dccSend("Function " . $cmdLower . " activated.");
					}
					else
					{
						$chat->dccSend("Invalid function specified.");
					}
				}
			}
			else if ($args['arg1'] == "deactivate")
			{
				if ($args['arg2'] == "all")
				{
					foreach($cmdList['priv'] AS $cmd => $data)
					{
						if ($data['canDeactivate'] != false)
						{
							$this->parserClass->setCmdListValue('priv', $cmd, 'active', false);
							//$cmdList['priv'][$cmd]['active'] = false;
						}
					}
					$chat->dccSend("All functions deactivated.");
				}
				else
				{
					$cmdLower = irc::myStrToLower($args['arg2']);

					if (isset($cmdList['priv'][$cmdLower]))
					{
						if ($cmdList['priv'][$cmdLower]['canDeactivate'] != false)
						{
							$this->parserClass->setCmdListValue('priv', $cmdLower, 'active', false);
							//$cmdList['priv'][$cmdLower]['active'] = false;
							$chat->dccSend("Function " . $cmdLower . " deactivated.");
						}
						else
						{
							$chat->dccSend("Cannot modify read-only function " . $cmdLower . ".");
						}
					}
					else
					{
						$chat->dccSend("Invalid function specified.");
					}
				}
			}
			else
			{
				$chat->dccSend("Invalid Syntax, use 'all', or specify a function name.");
			}

		}
		else
		{
			$chat->dccSend("Invalid Syntax, use 'activate' or 'deactivate'.");
		}
	}

	public function dcc_spawn($chat, $args)
	{
		$chat->dccSend("Spawning " . $args['query'] . "...");
		$result= bot::addBot($args['query']);
		if($result === true)
			$chat->dccSend($args['query'] . " successfully spawned");	
		else
			$chat->dccSend($args['query'] . " was not spawned");
	}

	private function getStatus()
	{
		$sqlCount = 0;
		$bwStats = $this->ircClass->getStats();

		if (is_object($this->db))
		{
			$sqlCount = $this->db->numQueries();
		}

		$bwUp = irc::intToSizeString($bwStats['BYTESUP']);
		$bwDown = irc::intToSizeString($bwStats['BYTESDOWN']);

		$fileBwUp = irc::intToSizeString($this->dccClass->getBytesUp());
		$fileBwDown = irc::intToSizeString($this->dccClass->getBytesDown());

		$txtQueue = $this->ircClass->getTextQueueLength() + 1;

		$ircStat = $this->ircClass->getStatusString($this->ircClass->getStatusRaw());

		$status = 	"Status: [" . $ircStat . "] ". $sqlCount .
					" SQL, " . $txtQueue . " SrQ, (irc BW: " . $bwUp . " up, " . $bwDown . " down, file BW: " . $fileBwUp . " up, " . $fileBwDown . " down)";
		return $status;
	}

	public function sendStatus()
	{
		$this->dccClass->dccInform($this->getStatus());
		return true;
	}

/*
	NOTE: If you're reading this, you're really bored.  Anyway, this is just something I'm keeping in here
	in case I ever need it again.  It was a debug mechanism for a hash class I wrote to replace the associative arrays
	that the bot currently uses.

	dcc	debug		1	"<chan/mem> [<channel>]"		"Debug channel/member hash tables"					true	dcc_mod	dcc_debug	info
	public function dcc_debug($chat, $args)
	{
		if ($args['arg1'] == "chan")
		{
			$chanData = $this->ircClass->getChannelData();
			
			$chat->dccSend($chanData->getCount() . " total buckets");

			foreach ($chanData->debug() AS $debug)
			{
				$chat->dccSend($debug);
			}
		}
		else if ($args['arg1'] == "mem")
		{
			if ($args['nargs'] > 1)
			{
				$chanData = $this->ircClass->getChannelData();

				$chanPtr = $chanData->find($args['arg2']);

				if ($chanPtr !== false)
				{
					$chat->dccSend($chanPtr->memberList->getCount() . " total buckets");
		
					foreach ($chanPtr->memberList->debug() AS $debug)
					{
						$chat->dccSend($debug);
					}
				}
			}
		}
	}
*/
}
?>
