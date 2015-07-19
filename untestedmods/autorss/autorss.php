<?php 

/*
+---------------------------------------------------------------------------
|   PHP-IRC v2.2.1 Module
|   ========================================================
|   by Hery
|   Contact: hery@serasera.org
+---------------------------------------------------------------------------
|   > autorss  module
|   > Module written by Hery
|   > Module Version Number: 0.9.0
|   > $Id: autorss.php 68 2008-09-28 06:29:30Z heriniaina.eugene $
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
|   Description
|   =======-------
|   > This module shows randomly the last item of a RSS into a defined channel
|   > within a defined interval
+---------------------------------------------------------------------------
*/

class autorss extends module
{

	public $title = "AutoRSS module";
	public $author = "hery@serasera.org";
	public $version = "0.9.0";
	
	/**
	* Configuration
	**/
	
	public $cachedir = "./modules/cache";
	public $inifile = "./modules/autorss.ini";
	
	
	public function init()
	{
		$this->rss = new ini($this->inifile);
		include_once('./modules/autorss/simplepie.inc');
		
		$this->timerClass->addTimer("autorss_show", $this, "show", "", 60*3, false);
	}
	
	public function destroy()
	{
		$this->timerClass->removeTimer("autorss_show");
	}
	
	public function reply($line, $args)
	{
		if ($line['to'] != $this->ircClass->getNick())
		{
			//didn't talk to bot, skip
			return;
		}
		
		if (!$this->rss->getVars("admins"))
		{
			//no admin yet so the first to talk to bot becomes admin
			$this->rss->setIniVal("admins", $line['fromNick'], mktime());
			$this->rss->writeIni();
			$this->ircClass->privMsg($line['fromNick'], "You are now admin. Please set the interval, channel and add links.");
			$this->printHelp($line, $args);
			return;
		}
		
		
		if (!$this->rss->getIniVal("admins", $line['fromNick']))
		{
			//not admin, skip
			$this->ircClass->privMsg($line['fromNick'], "You are not admin!");
			return;
		}
		
		if ($args['nargs'] == 0)
		{
			$this->printHelp($line, $args);
			
			return;
		}
		
		switch ($args['arg1'])
		{
			case "on":
				$this->timerClass->addTimer("autorss_show", $this, "show", "", 60*10, false);
			break;
			case "off":
				$this->timerClass->removeTimer("autorss_show");
				$this->ircClass->privMsg($line['fromNick'], "autorss is now off");
			break;
			case "set":
				if (!isset($args['arg2']))
				{
					$this->printHelp($line, $args);
					return;
				}
				switch ($args['arg2'])
				{
					case "timer":
					if (isset($args['arg3']) && intval($args['arg3']) > 300)
					{
						$this->timerClass->removeTimer("autorss_show");
						$this->timerClass->addTimer("autorss_show", $this, "show", "", intval($args['arg3']), false);
						$this->ircClass->privMsg($line['fromNick'], "Interval set to " . $args['arg3']);
					}
					else
					{
						$this->ircClass->privMsg($line['fromNick'], "Interval not valid, please use !autorss set timer {interval} where interval is a value in second bigger than 300");
					}
					break;
					case "channel":
					if (isset($args['arg3']) && (substr($args['arg3'], 0, 1) == "#"))
					{
						$this->rss->setIniVal("config", "channel", $args['arg3']);
						
						$this->ircClass->privMsg($line['fromNick'], "Channel is set to " . $args['arg3']);
						$this->rss->writeIni();
						return;
					}
					else
					{
						$this->ircClass->privMsg($line['fromNick'], "Channel not valid, please use !autorss set channel {#name} where name is the channel name.");
						return;
					}
					break;
				}
			break;
			case "admin":
				if (!isset($args['arg2']))
				{
					$this->printHelp($line, $args);
					return;
				}
				switch ($args['arg2'])
				{
					case "add":
						if (isset($args['arg3']))
						{
							$this->rss->setIniVal("admins", $args['arg3'], mktime());
							$this->ircClass->privMsg($line['fromNick'], $args['arg3'] . "is now admin");

							$this->rss->writeIni();

							return;
						}
						else
						{
							$this->ircClass->privMsg($line['fromNick'], "Please use !autorss admin add {nick}");
							return;
						}
					break;
					case "del":
						if (isset($args['arg3']))
						{
							if ($this->rss->getIniVal("admins", $args['arg3']))
							{
								$this->rss->deleteVar("admins", $args['arg3']);
								$this->ircClass->privMsg($line['fromNick'], "Admin removed");
								return;
							}
							else
							{
								$this->ircClass->privMsg($line['fromNick'], "Admin not found. Please use !autorss admin list to see the list of links");
								return;
							}
						}
						else
						{
							$this->ircClass->privMsg($line['fromNick'], "Please use !autorss admin del {nick} to delete the admin.");
						}
					break;
					case "list":
						if ($admins = $this->rss->getVars("admins"))
						{
							$this->ircClass->privMsg($line['fromNick'], "These are the admins. !autorss admin del {nick} to delete");
							$msg = join (", " , array_keys($admins));
							$this->ircClass->privMsg($line['fromNick'], $msg);
						}
						else
						{
							$this->ircClass->privMsg($line['fromNick'], "No admin found.");
						}
					break;
					default:
						$this->printHelp($line, $args);
						return;
					break;
				}
			
			break;
			case "link":
				if (!isset($args['arg2']))
				{
					$this->printHelp($line, $args);
					return;
				}
				switch ($args['arg2'])
				{
					case "add":
						if (isset($args['arg3']))
						{
							$this->rss->setIniVal("links", mktime(), $args['arg3']);
							$this->ircClass->privMsg($line['fromNick'], "Link added");
							$this->rss->writeIni();
							return;
						}
						else
						{
							$this->ircClass->privMsg($line['fromNick'], "Please use !autorss link add {rss link}");
							return;
						}
					break;
					case "del":
						if (isset($args['arg3']))
						{
							if ($this->rss->getIniVal("links", $args['arg3']))
							{
								$this->rss->deleteVar("links", $args['arg3']);
								$this->ircClass->privMsg($line['fromNick'], "Link removed");
								return;
							}
							else
							{
								$this->ircClass->privMsg($line['fromNick'], "Link not found. Please use !autorss link list to see the list of links");
								return;
							}
						}
						else
						{
							$this->ircClass->privMsg($line['fromNick'], "Please use !autorss link del {id} to delete a link. If you don't know the id, then use !autorss link list");
						}
					break;
					case "list":
						if ($links = $this->rss->getVars("links"))
						{
							$this->ircClass->privMsg($line['fromNick'], "These are the links with their ids. You can remove a link with !autorss link del {id}");
							foreach ($links as $name => $link)
							{
								if ($name)
								{
								$this->ircClass->privMsg($line['fromNick'], BOLD . $name . " : " . BOLD . $link);
								}
							}
						}
						else
						{
							$this->ircClass->privMsg($line['fromNick'], "No link found.");
						}
					break;
					default:
					
						$this->printHelp($line, $args);
						return;
					break;
				}
			break;
		}		
	}
	
	public function printHelp($line, $args)
	{
			$this->ircClass->privMsg($line['fromNick'], BOLD . "!autorss {on|off} " . BOLD . "To activate/deactivate autorss");
			$this->ircClass->privMsg($line['fromNick'], BOLD . "!autorss set channel #autorss " . BOLD . "Set channel to #autorss.");
			$this->ircClass->privMsg($line['fromNick'], BOLD . "!autorss set timer 300 " . BOLD . "Set timer to 300 sec.");
			$this->ircClass->privMsg($line['fromNick'], BOLD . "!autorss admin {add|del} hery " . BOLD . "To add or remove hery as admin (can add or remove rss link)");
			$this->ircClass->privMsg($line['fromNick'], BOLD . "!autorss link add {rss url} " . BOLD . "To add rss link defined by name");
			$this->ircClass->privMsg($line['fromNick'], BOLD . "!autorss link list " . BOLD . "To see saved links with their id");	

			$this->ircClass->privMsg($line['fromNick'], BOLD . "!autorss link del {id} " . BOLD . "To remove the rss link defined by name");	
	}
	
	
	public function show()
	{

		if (!$this->rss->getIniVal("config", "channel"))
		{
			
			return true;
		}
		$chan = $this->rss->getIniVal("config", "channel");
		
		if ($var = $this->rss->randomVar("links", 1))
		{
			
			if ($link = $this->rss->getIniVal("links", $var))
			{
				
				$feed = new SimplePie();
				$feed->enable_cache(true);
				$feed->set_cache_location($this->cachedir);
				$feed->set_feed_url($link);
				$feed->init();
				$items = $feed->get_items(0, 1);
				if ($feed_title = $feed->get_title())
				{
					$this->ircClass->privMsg($chan, BOLD . $feed_title);
				}
				foreach ($items as $item)
				{
				
					$desc = substr(strip_tags(html_entity_decode($item->get_description())), 0, 200);
					$desc = ereg_replace("\n", " ", $desc);
					$desc = ereg_replace("\r", " ", $desc);
					$this->ircClass->privMsg($chan,  $desc . "... (" . $item->get_date('d/m/Y') . ")");
					$this->ircClass->privMsg($chan, $item->get_link());
				}
			}
		}
		return true;
	}
	
}

?>