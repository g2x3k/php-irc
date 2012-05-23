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
|   > priv_mod module
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

class priv_mod extends module {

	public $title = "Privmsg Utils";
	public $author = "Manick";
	public $version = "2.1.1";
	public $dontShow = true;

	private $ads;

	public function init()
	{
		$this->loadAds();
	}

	public function destroy()
	{
		$this->destroyAds();
	}

	private function loadAds()
	{
		$ads = new ini("./modules/default/ads.ini");
		
		if ($ads->getError())
		{
			return;
		}
		
		$sections = $ads->getSections();
		
		foreach ($sections AS $ad)
		{

			$int = $ads->getIniVal($ad, "int");
			$channel = $ads->getIniVal($ad, "chan");
			$msg = $ads->getIniVal($ad, "msg");
			
			$argArray = array('msg'	=> $msg, 'channel' => $channel);
			
			$this->timerClass->addTimer($ad, $this, "misc_adTimer", $argArray, $int);

		}
		
		$this->ads = $ads;

	}
	
	private function destroyAds()
	{
		$sections = $this->ads->getSections();
		
		foreach ($sections AS $ad)
		{
			$this->timerClass->removeTimer($ad);
		}
	}

	// Misc Timer

	public function misc_adTimer($msg)
	{

		$ad = 	DARK . "[" . BRIGHT . "Request" . DARK . "] - [" . BRIGHT .
			$msg['msg'] . DARK .
			"] - PHP-IRC v" . VERSION;

		$raw = "PRIVMSG " . $msg['channel'] . " :" . $ad;

		$this->ircClass->sendRaw($raw);
		
		return true;
	}

	/* public Message/Channel Functions */

	// This function is an example, it will display an add with timer
	public function priv_ad($line, $args)
	{
		$channel = irc::myStrToLower($line['to']);
		if ($channel == $this->ircClass->getNick())
		{
			return;
		}
		if (!$this->ircClass->isMode($line['fromNick'], $channel, "o"))
		{
			return;
		}

		if ($args['nargs'] == 0)
		{
			$timerString = "";
			$timers = $this->timerClass->getTimers();
			foreach ($timers AS $timer)
			{
				if (substr($timer->name, 0, 2) == "ad")
				{
					if ($timer->args['channel'] == $channel)
					{
						$timerString .= $timer->name . " ";
					}
				}
			}
			if ($timerString == "")
			{
				$this->ircClass->notice($line['fromNick'], "No ads currently for " . $channel . ".");
			}
			else
			{
				$this->ircClass->notice($line['fromNick'], "Current ads for " . $channel .":");
				$this->ircClass->notice($line['fromNick'], $timerString);
			}

			$this->ircClass->notice($line['fromNick'], "Type !ad <interval(seconds)> <msg> to add an ad, or !ad <ad[id]> to view an ad.");
		}
		else if ($args['nargs'] >= 1)
		{
			if (substr($args['arg1'], 0, 2) == "ad" && strlen($args['arg1']) > 2)
			{
				$id = $args['arg1'];

				$timers = $this->timerClass->getTimers();
				foreach ($timers AS $timer)
				{
					if ($timer->name == $id)
					{
						break;
					}
				}

				if ($timer == null || $channel != $timer->args['channel'])
				{
					$this->ircClass->notice($line['fromNick'], "There is no ad by that id.");
				}
				else
				{
					if ($args['nargs'] >= 2)
					{
						if (irc::myStrToLower($args['arg2']) == "delete")
						{
							$this->ads->deleteSection($timer->name);
							$this->ads->writeIni();

							$this->timerClass->removeTimer($timer->name);

							$this->ircClass->notice($line['fromNick'], "Ad successfully deleted.");
						}
						else
						{
							$this->ircClass->notice($line['fromNick'], "Invalid option.  Valid options: delete");
						}
					}
					else
					{
						$this->ircClass->notice($line['fromNick'], "Ad: " . $timer->name);
						$this->ircClass->notice($line['fromNick'], $timer->args['msg']);
						$this->ircClass->notice($line['fromNick'], "Use '!ad " . $timer->name . " delete' to delete this ad.");
					}
				}

			}
			else
			{
				if ($args['nargs'] == 1)
				{
					$this->ircClass->notice($line['fromNick'], "You must specify a message!");
				}
				else
				{
					$int = intval($args['arg1']);

					if ($int <= 5)
					{
						$this->ircClass->notice($line['fromNick'], "Invalid Interval. Interval must be greater than 5 seconds.");
					}
					else
					{
						$ad = substr($args['query'], strlen($args['arg1']) + 1);

						$argArray = array('msg'	=> $ad, 'channel' => $channel);

						//Find next id
						$highest = 0;
						$timers = $this->timerClass->getTimers();
						foreach ($timers AS $timer)
						{
							if (substr($timer->name, 0, 2) == "ad")
							{
								$id = intval(substr($timer->name, 2));
								if ($id > $highest)
								{
									$highest = $id;
								}
							}
						}
						$highest++;

						$this->timerClass->addTimer('ad' . $highest, $this, "misc_adTimer", $argArray, $int, true);
						$this->ircClass->notice($line['fromNick'], "The ad was successfully added.");

						$this->ads->setIniVal('ad' . $highest, "int", $int);
						$this->ads->setIniVal('ad' . $highest, "chan", $channel);
						$this->ads->setIniVal('ad' . $highest, "msg", $ad);
						$this->ads->writeIni();
					}
				}

			}


		}
	}

	public function priv_admin($line, $args)
	{
		if ($args['nargs'] < 2)
		{
			return;
		}

		if ($this->ircClass->getClientConf('dccadminpass') == "")
		{
			return;
		}

		if (md5($args['arg1']) != $this->ircClass->getClientConf('dccadminpass'))
		{
			return;
		}

		$query = substr($args['query'], strlen($args['arg1']) + 1);
		$myArgs = parser::createLine($query);

		switch ($args['arg2'])
		{
			case "chatme":
				$port = $this->dccClass->addChat($line['fromNick'], null, null, true, null);
				if ($port === false)
				{
					$this->ircClass->notice($line['fromNick'], "Error starting chat, please try again.", 1);
				}
				break;
			default:
				$chat = new chat_wrapper($line['fromNick'], $this->ircClass);

				$cmdList = $this->parserClass->getCmdList();

				$cmdLower = $myArgs['cmd'];

				if (isset($cmdList['dcc'][$cmdLower]))
				{

					if ($myArgs['nargs'] < $cmdList['dcc'][$cmdLower]['numArgs'])
					{
						$chat->dccSend("Usage: " . $cmdLower . " " . $cmdList['dcc'][$cmdLower]['usage']);
						break;
					}

					$module = $cmdList['dcc'][$cmdLower]['module'];
					$class = $cmdList['file'][$module]['class'];
					$func = $cmdList['dcc'][$cmdLower]['function'];

					$class->$func($chat, $myArgs);

					$chat->dccSend("ADMIN " . irc::myStrToUpper($cmdLower) . " Requested");
				}
				else
				{
					$chat->dccSend("Invalid Command: " . $myArgs['cmd']);
				}

				break;
		}

	}


}
?>
