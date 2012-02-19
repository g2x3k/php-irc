<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC IMDB Parsing Mod 0.1
|   ========================================================
|   by Manick
|   (c) 2001-2004 by http://phpbots.sf.net
|   Contact: manick@manekian.com
|   irc: #manekian@irc.rizon.net
|   ========================================
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
*/

class imdb_mod extends module {

	public $title = "IMDB Parser";
	public $author = "Manick";
	public $version = "0.1";

	public function init()
	{
		$this->cache = array();

		// Add your timer declarations and whatever
		// else here...
	}

	public function destroy()
	{
		$this->cache = array();
		// Put code here to destroy the timers that you created in init()
		// and whatever else cleanup code you want.
	}
	
	private $cache;
	
	public function parse_imdb($line, $args)
	{
		if ($args['nargs'] <= 0)
		{
			$this->ircClass->notice($line['fromNick'], "Usage: !imdb <movie>");
			return;
		}

		$this->ircClass->notice($line['fromNick'], "Processing, please wait...");

		$query = strtolower($args['query']);

		$lArgs = explode(chr(32), $query);
		$tCount = count($lArgs);
		if ($tCount > 0)
		{
			$date = $lArgs[count($lArgs)-1];
			$title = strtolower(trim(str_replace($date, "", $query)));
		}

		if (isset($this->cache[$query]))
		{
			foreach ($this->cache[$query] AS $date => $link)
				break;
			$line["link"] = $link;
			$line["title"] = $query;
			$line["date"] = $date;
			$search = socket::generateGetQuery("", "www.imdb.com", "/title/" . $link . "/", "1.0");
			$this->ircClass->addQuery("www.imdb.com", 80, $search, $line, $this, "title_response");
		}
		else if ($tCount > 0 && isset($this->cache[$title]) && isset($this->cache[$title][$date]))
		{
			$link = $this->cache[$title][$date];
			$line["link"] = $link;
			$line["title"] = $title;
			$line["date"] = $date;
			$search = socket::generateGetQuery("", "www.imdb.com", "/title/" . $link . "/", "1.0");
			$this->ircClass->addQuery("www.imdb.com", 80, $search, $line, $this, "title_response");
		}
		else
		{
			$tquery = "q=" . urlencode($args['query']) . "&tt=on&mx=20";
			$search = socket::generateGetQuery($tquery, "www.imdb.com", "/find", "1.0");
			$this->ircClass->addQuery("www.imdb.com", 80, $search, $line, $this, "search_response");
		}

	}
	
	public function search_response($line, $args, $result, $site)
	{
		if ($result == QUERY_ERROR)
		{
			$this->ircClass->notice($line['fromNick'], "Error: " . $site);
			return;
		}

		$site = str_replace("&#160;", ";", $site);
		$site = str_replace("\r", "", $site);
		$site = html_entity_decode($site);
		preg_match_all("/<li>\s*<a href=\"\/title\/(.+?)\/.+?>(.+?)<\/a>\s*(\(.+?\)){1}?(\s)?(.+?)*?<\/li>/i", $site, $matches, PREG_PATTERN_ORDER);

		$topTen = array();

		for ($i = 0; $i < count($matches[1]); $i++)
		{
			if ($matches[4][$i] == " ")
			{
				continue;
			}

			$link = trim($matches[1][$i]);

			$title = trim($matches[2][$i]);
			$date = trim($matches[3][$i]);

			$lTitle = strtolower($title);

			if (!isset($this->cache[$lTitle]))
			{
				$this->cache[$lTitle] = array();
				$this->cache[$lTitle][$date] = $link;
			}
			else
			{
				if (!isset($this->cache[$lTitle][$date]))
				{
					$this->cache[$lTitle][$date] = $link;
					krsort($this->cache[$lTitle]);
				}
			}

			$topTen[] = $title . " " . $date;

		}
		
		$tkey = strtolower($args["query"]);
		
		$lArgs = explode(chr(32), $tkey);
		$tCount = count($lArgs);
		if ($tCount > 0)
		{
			$date = $lArgs[count($lArgs)-1];
			$title = trim(str_replace($date, "", $tkey));
		}
		
		if (isset($this->cache[$tkey]))
		{
			foreach ($this->cache[$tkey] AS $date => $link)
				break;
			$line["link"] = $link;
			$line["title"] = $tkey;
			$line["date"] = $date;
			$search = socket::generateGetQuery("", "www.imdb.com", "/title/" . $link . "/", "1.0");
			$this->ircClass->addQuery("www.imdb.com", 80, $search, $line, $this, "title_response");
		}
		else if ($tCount > 0 && isset($this->cache[$title]) && isset($this->cache[$title][$date]))
		{
			$link = $this->cache[$title][$date];
			$line["link"] = $link;
			$line["title"] = $title;
			$line["date"] = $date;
			$search = socket::generateGetQuery("", "www.imdb.com", "/title/" . $link . "/", "1.0");
			$this->ircClass->addQuery("www.imdb.com", 80, $search, $line, $this, "title_response");
		}
		else
		{
			$total = count($topTen);
			
			$total = $total > 10 ? 10 : $total;

			if ($total <= 0)
			{
				$this->ircClass->notice($line['fromNick'], "No responses from server.  Try broadening your search.  If you included a date, remove it and try again.");
				return;
			}

			$this->ircClass->notice($line['fromNick'], "Top " . $total . " responses from www.imdb.com");

			$resp = "";
			for ($i = 0; $i < $total; $i++)
			{
				$resp .= DARK . "[" . BRIGHT . $topTen[$i] . DARK . "] - ";
			}
			
			$multi = irc::multiLine($resp);

			foreach($multi AS $mult)
			{
				$this->ircClass->notice($line['fromNick'], $mult);
			}

		}


	}

	public function title_response($line, $args, $result, $site)
	{
		if ($result == QUERY_ERROR)
		{
			$this->ircClass->notice($line['fromNick'], "Error: " . $site);
			return;
		}

		$site = html_entity_decode($site);

		preg_match("/<title>(.+?)<\/title>/is", $site, $match);


		$site = preg_replace("/<(.+?)>/s", "", $site);
		$site = str_replace("\r", "", $site);

		preg_match_all("/([^\s\n]+?):[\s\n]*([^\n]+?)\n/", $site, $matches, PREG_PATTERN_ORDER);

		$var = array();
		$var['Title'] = $match[1];

		for ($i = 0; $i < count($matches[1]); $i++)
		{
			if (!isset($var[$matches[1][$i]]))
			{
				$var[$matches[1][$i]] = $matches[2][$i];
			}
		}

		$n_array = array("Title", "Runtime", "Genre", "Country", "Rating", "Outline");
		
		foreach ($n_array AS $item)
		{
			if (!isset($var[$item]) || $var[$item] == "")
				$var[$item] = "N/A";
		}

		$offsetA = strpos($var["Outline"], "(");
		if ($offsetA !== false)
		{
			$var["Outline"] = substr($var["Outline"], 0, $offsetA);
		}

		$Tline = 	DARK . "[ " . BRIGHT . UNDERLINE . "http://www.imdb.com/title/" . $line["link"] . "/" . UNDERLINE . DARK . " ]".
					" - [" . BOLD . "Title:" . BOLD . " " . BRIGHT . trim($var["Title"]) . DARK . "]".
					" - [" . BOLD . "Runtime:" . BOLD . " " . BRIGHT . trim($var["Runtime"]) . DARK . "]".
					" - [" . BOLD . "Genre:" . BOLD . " " . BRIGHT . trim($var["Genre"]) . DARK . "]".
					" - [" . BOLD . "Country:" . BOLD . " " . BRIGHT . trim($var["Country"]) . DARK . "]".
					" - [" . BOLD . "Rating:" . BOLD . " " . BRIGHT . trim($var["Rating"]) . DARK . "]";
		$Tline2 = DARK . "[" . BOLD . "Outline:" . BOLD . " " . trim($var["Outline"]) . "]";

		$this->ircClass->notice($line['fromNick'], $Tline);
		
		if ($var["Outline"] != "N/A")
		{
			$this->ircClass->notice($line['fromNick'], $Tline2);
		}

		$resp = "";
		$count = 0;
		
		foreach ($this->cache[$line["title"]] AS $date => $link)
		{
			if ($date != $line["date"])
			{
				$resp .= DARK . "[" . BRIGHT . $date . DARK . "] -";
				$count++;
			}
		}

		if ($count > 0)
		{
			$this->ircClass->notice($line['fromNick'], DARK . "There are " . $count . " other dates: " . $resp . " [use !imdb title (date)]");
		}

	}

	//Methods here:
}

?>
