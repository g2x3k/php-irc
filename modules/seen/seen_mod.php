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
|   > Seen Mod
|   > Module written by Manick
|   > Module Version Number: 0.1
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

class seen_mod extends module {

	public $title = "Seen Mod";
	public $author = "Manick";
	public $version = "0.1";
	
	private $seen;

	public function init()
	{
		$this->timerClass->addTimer("seen_mod_updateini", $this, "seen_update", "", 60*15, false);
		$this->seen = new ini("modules/seen/seen.ini");
	}

	public function destroy()
	{
		$this->timerClass->removeTimer("seen_mod_updateini");
	}

	// Write to file
	public function seen_update($args)
	{
		if ($this->seen->getError())
		{
			return;
		}

		$this->seen->writeIni();
		$this->dccClass->dccSend("Updated Seen Mod ini database file");

		return true;
	}

	// Update actions
	public function seen($line, $args)
	{
		if ($this->seen->getError())
		{
			if (DEBUG == 1)
			{
				echo "Seen error!\n";
			}
			return;
		}

		if (strtolower($line['cmd']) == "join")
		{
			$line['text'] = "";
		}

		if (strtolower($line['cmd']) == "kick")
		{
			$offsetA = strpos($line['params'], chr(32));

			$act = "kick";
			$user = substr($line['params'], $offsetA + 1);
			
			$this->addLast($user, $act, $line['text']);

		}
		else
		{
			$this->addLast($line['fromNick'], strtolower($line['cmd']), $line['text']);
		}
		
		$this->getLast($line['fromNick']);
	}

	private function getLast($user)
	{
		$user = irc::myStrToLower($user);

		if (!$this->seen->sectionExists("seen"))
		{
			return;
		}

		$var = $this->seen->getIniVal("seen", $user);

		if ($var == false)
		{
			return false;
		}

		$offsetA = strpos($var, "=");
		$offsetB = strpos($var, "=", $offsetA + 1);
		$offsetC = strpos($var, "=", $offsetB + 1);

		$info = array();

		$info['user'] = substr($var, 0, $offsetA);
		$info['time'] = substr($var, $offsetA + 1, $offsetB - $offsetA - 1);
		$info['act'] = substr($var, $offsetB + 1, $offsetC - $offsetB - 1);
		$info['txt'] = substr($var, $offsetC + 1);
		
		return $info;
	}

	private function addLast($user, $act, $txt)
	{
		$Suser = irc::myStrToLower($user);

		$tAction = $user . "=" . time() . "=" . irc::myStrToLower($act) . "=" . $txt;
		$this->seen->setIniVal("seen", $Suser, $tAction);
	}

	// User interface
	public function priv_seen($line, $args)
	{
		if ($this->seen->getError())
		{
			$this->ircClass->notice($line['fromNick'], "There was an error while attempting to access the seen database.");
			return;
		}

		if ($line['to'] == $this->ircClass->getNick())
		{
			return;
		}

		if ($args['nargs'] <= 0)
		{
			$this->ircClass->notice($line['fromNick'], "Usage: !seen <nick>");
			return;
		}

		$user = irc::myStrToLower($args['arg1']);

		if ($user == irc::myStrToLower($line['fromNick']))
		{
			$this->ircClass->privMsg($line['to'], $line['fromNick'] . ", umm...  O..kay...");
			$this->ircClass->action($line['to'], "points at " . $line['fromNick'] . "...");
			return;
		}

		$data = $this->getLast($user);

		if ($data === false)
		{
			$this->ircClass->privMsg($line['to'], $line['fromNick'] . ", I have never seen " . $args['arg1'] . " before.");
			return;
		}

		$time = time() - $data['time'];
		
		if ($time > 3600*24)
		{
			$timeString = irc::timeFormat($time, "%d days %h hours %m min %s sec");
		}
		else if ($time > 3600)
		{
			$timeString = irc::timeFormat($time, "%h hours %m min %s sec");
		}
		else if ($time > 60)
		{
			$timeString = irc::timeFormat($time, "%m min %s sec");
		}
		else
		{
			$timeString = irc::timeFormat($time, "%s sec");
		}

		$action = "";

		switch ($data['act'])
		{
			case "privmsg":
				$action = "saying in a channel";
				break;
			case "notice":
				$action = "noticing a channel";
				break;
			case "join":
				$action = "joining a channel";
				break;
			case "kick":
				$action = "being kicked from a channel";
				break;
			case "part":
				$action = "parting a channel";
				break;
			case "quit":
				$action = "quitting";
				break;
		}

		if ($data['txt'] != "")
		{
			$action .= ": " . $data['txt'];
		}

		$this->ircClass->privMsg($line['to'], $line['fromNick'] . ", I last saw " . $data['user'] . " " . $timeString . " ago " . $action . ".");

	}
}

?>
