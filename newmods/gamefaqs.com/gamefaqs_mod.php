<?php
class gamefaqs_mod extends module {

	public $title = "gamefaqs.com parser";
	public $author = "khuong";
	public $version = "1.1";

	public function init()
	{
		$this->cache = array();
	}

	public function destroy()
	{
		$this->cache = array();
	}

	public function parse_gamefaqs($line, $args)
	{

		$nick = $line['fromNick'];

		if ($args['nargs'] < 1)
		{
			$this->ircClass->notice($line['fromNick'], "Usage: !games PLATFORM, available platforms are: GBA GC PC PS2 XBOX PSP 360 DS WII PS3");
			return;
		}

		$arg = strtoupper($args['arg1']);

		if ($arg != "PS3" && $arg != "WII" && $arg != "PC" && $arg != "GC" && $arg != "PS2" && $arg != "XBOX" && $arg != "GBA" && $arg != "PSP" && $arg != "360" && $arg != "DS") {
			$this->ircClass->notice($line['fromNick'], "Usage: !games PLATFORM, available platforms are: GBA GC PC PS2 XBOX PSP 360 DS WII PS3");
			$this->ircClass->notice($line['fromNick'], "Usage: !games PLATFORM REGION, available regions are: US NTSC EU PAL");
			return;
		}

		if($args['nargs'] > 1) {
			$arg1 = strtoupper($args['arg2']);
			if($arg1 != "PAL" && $arg1 != "US" && $arg1 != "EU" && $arg1 != "NTSC") {
				$this->ircClass->notice($line['fromNick'], "Usage: !games PLATFORM, available platforms are: GBA GC PC PS2 XBOX PSP 360 DS WII PS3");
				$this->ircClass->notice($line['fromNick'], "Usage: !games PLATFORM REGION, available regions are: US NTSC EU PAL");
				return;
			}
			switch ($arg1) {
				case "EU":
				case "PAL":
					$region = "EUROPE";
					break;
				case "US":
				case "NTSC":
					$region = "US";
					break;
			}
		}
		else {
			$region = "US";
		}

		switch($arg) {
			case "PC":
				$url = "http://www.gamefaqs.com/computer/doswin/";
				break;
			case "GC":
				$url = "http://www.gamefaqs.com/console/gamecube/";
				break;
			case "PS2":
				$url = "http://www.gamefaqs.com/console/ps2/";
				break;
			case "XBOX":
				$url = "http://www.gamefaqs.com/console/xbox/";
				break;
			case "GBA":
				$url = "http://www.gamefaqs.com/portable/gbadvance/";
				break;
			case "PSP":
				$url = "http://www.gamefaqs.com/portable/psp/";
				break;
			case "360":
				$url = "http://www.gamefaqs.com/console/xbox360/";
				break;
			case "DS":
				$url = "http://www.gamefaqs.com/portable/ds/";
				break;
			case "WII":
				$url = "http://www.gamefaqs.com/console/wii/";
				break;
			case "PS3":
				$url = "http://www.gamefaqs.com/console/ps3/";
				break;
		}

		$this->ircClass->privMsg($line['to'], "UPCOMING {$arg} GAMES // {$region} RETAIL");

		$contents = file($url);

		$this->cache = "";

		$bool = false;

		for($i = 0; $i < count($contents); $i++)
		{
			if($region == "US") {
				if(trim($contents[$i]) == "<div class=\"upcoming\">" && trim($contents[($i+1)]) == "<h2>North America</h2>") {
					$bool = true;
				}
			}
			elseif($region == "EUROPE") {
				if(trim($contents[$i]) == "<div class=\"upcoming\">" && trim($contents[($i+1)]) == "<h2>Europe</h2>") {
					$bool = true;
				}
			}
			if($bool) {
				if(trim($contents[$i]) == "</div>") {
					$bool = false;
				}
				$this->cache.= trim($contents[$i])."\n";
			}

		}

		$tmp = "";
		preg_match_all('/<td>(.*?)<\/td>\n<td><a href=\"(.*?)\" title=\"(.*?)\">(.*?)<\/a><\/td>\n/', $this->cache, $tmp);

		$dates = $tmp[1];
		$titles = $tmp[4];

		for($i=0; $i < count($titles); $i++) {
			if($i > 8) { return; }
			if($dates[$i] != "") {
				$date = $dates[$i];
			}
			$this->ircClass->privMsg($line['to'], "(" . $date .") " . html_entity_decode($titles[$i],ENT_QUOTES),$queue = 1);
		}

	}

}
?>

