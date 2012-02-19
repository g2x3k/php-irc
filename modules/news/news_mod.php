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
|   > News Mod
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

class news_mod extends module {

	public $title = "News Mod";
	public $author = "Manick";
	public $version = "0.1b";

	private $news = array();
	private $highest;

	public function priv_news($line, $args)
	{
		if ($line['to'] == $this->ircClass->getNick())
		{
			return;
		}

		$cmd = substr($args['cmd'], 1);

		if (!isset($this->news[$cmd]))
		{
			$this->news[$cmd] = new ini("modules/news/". $cmd .".ini");
		}

		if ($this->news[$cmd]->getError())
		{
			return;
		}
		
		$args['cmd'] = $cmd;

		if ($args['nargs'] == 0)
		{
			$this->nocmds($line, $args);
		}
		else
		{
			$this->cmds($line, $args);
		}
	}

	private function nocmds($line, $args)
	{
		$chan = irc::myStrToLower($line['to']);

		if (!$this->news[$args['cmd']]->sectionExists($chan))
		{
			$this->ircClass->notice($line['fromNick'], "There are no news items for this channel.");
			return;
		}

		$news = $this->news[$args['cmd']]->getSection($chan);
		ksort($news);

		$this->ircClass->notice($line['fromNick'], "Current News Items:");

		foreach ($news AS $index => $item)
		{
			if ($index != "highest")
			{
				$this->ircClass->notice($line['fromNick'], $item);
			}
		}
	}

	private function cmds($line, $args)
	{
		$cmd = $args['arg1'];

		switch($cmd)
		{
			case "add":
				$this->news_add($line, $args);
				break;
			case "count":
				$this->news_count($line, $args);
				break;
			case "del":
				$this->news_del($line, $args);
				break;
			case "show":
				$this->news_show($line, $args);
				break;
			case "clear":
				$this->news_clear($line, $args);
				break;
			default:
				$this->ircClass->notice($line['fromNick'], "I did not understand your query!  Try again!");
				break;
		}
	}
	
	private function news_count($line, $args)
	{
		$chan = irc::myStrToLower($line['to']);
		$nick = irc::myStrToLower($line['fromNick']);
		
		if (!$this->ircClass->hasModeSet($chan, $nick, "oa"))
		{
			return;
		}

		if (!$this->news[$args['cmd']]->sectionExists($chan))
		{
			$this->ircClass->notice($line['fromNick'], "There are no news items for this channel.");
			return;
		}
		
		$num = $this->news[$args['cmd']]->numVars($chan);
		
		$this->ircClass->notice($line['fromNick'], "There are " . $num . " items for this channel.");
	}

	private function news_show($line, $args)
	{
		$chan = irc::myStrToLower($line['to']);
		$nick = irc::myStrToLower($line['fromNick']);

		if (!$this->ircClass->hasModeSet($chan, $nick, "oa"))
		{
			return;
		}

		if (!$this->news[$args['cmd']]->sectionExists($chan))
		{
			$this->ircClass->notice($line['fromNick'], "There are no news items for this channel.");
			return;
		}

		$news = $this->news[$args['cmd']]->getSection($chan);
		ksort($news);

		$this->ircClass->notice($line['fromNick'], "Current News Items:");

		foreach ($news AS $index => $item)
		{
			if ($index != "highest")
			{
				$this->ircClass->notice($line['fromNick'], $index . ") " . $item);
			}
		}
	}

	private function news_del($line, $args)
	{
		$chan = irc::myStrToLower($line['to']);
		$nick = irc::myStrToLower($line['fromNick']);

		if (!$this->ircClass->hasModeSet($chan, $nick, "oa"))
		{
			return;
		}

		if ($args['nargs'] > 1)
		{
			$highest = $this->news[$args['cmd']]->getIniVal($chan, "highest");
			if ($highest == false)
			{
				$highest = 0;
			}

			$arg2 = $args['arg2'];
			$arg2low = strtolower($arg2);

			if ($arg2low == "low")
			{
				$vals = $this->news[$args['cmd']]->getSection($chan);
				unset($vals['highest']);
				$keys = array_keys($vals);
				ksort($keys);
				$arg2 = $keys[0];
			}
			else if ($arg2low == "high")
			{
				$arg2 = $highest;
			}

			$arg2intval = intval($arg2);

			if ($arg2intval == 0)
			{
				$this->ircClass->notice($line['fromNick'], "Error, you specified an invalid line number.");
			}
			else
			{
				$var = $this->news[$args['cmd']]->getIniVal($chan, $arg2);

				if ($var === false)
				{
					$this->ircClass->notice($line['fromNick'], "That line contains no information.");
				}
				else
				{

					$this->news[$args['cmd']]->deleteVar($chan, $arg2);
					
					if ($arg2intval == $highest)
					{
						$vals = $this->news[$args['cmd']]->getSection($chan);
						$keys = array_keys($vals);
						ksort($keys);
						$highest = $keys[count($keys)-1];
						$this->news[$args['cmd']]->setIniVal($chan, "highest", $highest);
					}

					$this->news[$args['cmd']]->writeIni();
					$this->ircClass->notice($line['fromNick'], "The line was successfully deleted.");
				}
			}
		}
		else
		{
			$this->ircClass->notice($line['fromNick'], "Error, you must specify a line number to delete.  Use !news show to see line numbers");
		}
	}

	private function news_add($line, $args)
	{
		$chan = irc::myStrToLower($line['to']);
		$nick = irc::myStrToLower($line['fromNick']);

		if (!$this->ircClass->hasModeSet($chan, $nick, "oa"))
		{
			return;
		}

		if ($this->news[$args['cmd']]->sectionExists($chan))
		{
			$next = intval($this->news[$args['cmd']]->getIniVal($chan, "highest")) + 1;
		}
		else
		{
			$next = 0 + 1;
		}
		
		if ($args['nargs'] > 1)
		{
			$arg2 = $args['arg2'];
			$arg2intval = intval($arg2);
			
			echo $arg2 . "-" . $arg2intval . "\n";

			//Arg 2 is not a line number, add to end of list
			if (strval($arg2intval) != $arg2 || $arg2intval == 0)
			{
				$query = substr($args['query'], strlen($args['arg1'])+1);

				$this->news[$args['cmd']]->setIniVal($chan, $next, $query);
				$this->news[$args['cmd']]->setIniVal($chan, "highest", $next);
				$this->news[$args['cmd']]->writeIni();
				$this->ircClass->notice($line['fromNick'], "Line added as " . $next . ".");
			}
			else //arg 2 is line number, add with that number if possible
			{
				$query = substr($args['query'], strlen($args['arg1'].$args['arg2'])+2);
				$var = $this->news[$args['cmd']]->getIniVal($chan, $arg2);

				if ($var !== false)
				{
					$this->ircClass->notice($line['fromNick'], "Error, that line number is already filled.  You must delete it first.");
				}
				else
				{
					if ($arg2 > $highest)
					{
						$next = $arg2;
					}
					$this->news[$args['cmd']]->setIniVal($chan, $arg2, $query);
					$this->news[$args['cmd']]->setIniVal($chan, "highest", $next);
					$this->news[$args['cmd']]->writeIni();
					$this->ircClass->notice($line['fromNick'], "Line added.");
				}
			}

		}
		else
		{
			$this->ircClass->notice($line['fromNick'], "You must specify some information to add.");
		}
	}

	private function news_clear($line, $args)
	{
		$chan = irc::myStrToLower($line['to']);
		$nick = irc::myStrToLower($line['fromNick']);

		if (!$this->ircClass->hasModeSet($chan, $nick, "oa"))
		{
			return;
		}

		if ($this->news[$args['cmd']]->sectionExists($chan))
		{
			$this->news[$args['cmd']]->deleteSection($chan);
			$this->news[$args['cmd']]->writeIni();
			$this->ircClass->notice($line['fromNick'], "All lines from this channel have been cleared.");
		}
		else
		{
			$this->ircClass->notice($line['fromNick'], "That channel does not have any news items set.");
		}
	}

}

?>
