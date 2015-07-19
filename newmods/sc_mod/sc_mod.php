<?php
/*

Shoutcast Announcer
(C) 2008 James Park-Watt
	jimmypwatgmaildotcom
	
Released under BSD License, Portions (supplied by mixstreams.net) with none/unknown license redistribute rights assumed.

get_sc_title() partially (C) MixStream.net 2008

Commands:
!scon (operators only) enables this script
!scoff (operators only) disables this script
!scnow (anyone) reprints what is currently playing

*/

class sc_mod extends module {

/* Edit these variables */
	private $stationName = "SUB.FM"; // Radio Station name
	private $scServer = "tropical.wavestreamer.com"; //shoutcast server 
	private $scServerPort = "8529"; //shoutcast server port
	private $refreshInterval = 60; //how often to check for changed metatata
/* End Editable variables */
/*Dont edit past this line unless you know what your doing :p */

	public $title = "Shoutcast Announcer";
	public $author = "James Park-Watt";
	public $version = "1.0";




	// this will hold the last title seen by the app
	private $lasttitle = "";
	private $functionEnabled = false;


	public function init()
	{
		// Add your timer declarations and whatever
		// else here...
	}

	public function destroy()
	{
		// Put code here to destroy the timers that you created in init()
		// and whatever else cleanup code you wanto.
		
		$this->lasttitle = "";
		$this_functionEnabled = false;
		$this->timerClass->removeTimer("check_sc");
		
	}

	//Methods here:

	public function ena_sc($line, $args)
	{
		$channel = $line['to'];

		if(!$this->ircClass->isMode($line['fromNick'], $channel, "o"))
		{
			$raw = "PRIVMSG $channel :This command is only for ops!";
			$this->ircClass->sendRaw($raw);	
			return;
}
		
		if($this->functionEnabled)
		{
			$raw = "PRIVMSG $channel :This function is alredy enabled";
			$this->ircClass->sendRaw($raw);
			return;
		}

		$raw = "PRIVMSG $channel :Function enabled.";
		$this->ircClass->sendRaw($raw);
		$this->functionEnabled = true;

		$this->timerClass->addTimer("check_sc", $this, "get_sc_title", "$channel", $this->refreshInterval, true);
	}

	public function dis_sc($line, $args)
	{
		$channel = $line['to'];

                if(!$this->ircClass->isMode($line['fromNick'], $channel, "o"))
                {
                        $raw = "PRIVMSG $channel :This command is only for ops!";
                        $this->ircClass->sendRaw($raw);
                        return;
                }

                if(!$this->functionEnabled)
                {
                        $raw = "PRIVMSG $channel :This function is not enabled";
                        $this->ircClass->sendRaw($raw);
                        return;
                }
		
                $raw = "PRIVMSG $channel :Function disabled.";
                $this->ircClass->sendRaw($raw);
                $this->functionEnabled = false;

		$this->lasttitle = " ";

		$this->timerClass->removeTimer("check_sc");
		
	}

	public function get_sc_title($channel) // run from timer from the ena_sc() function.
	
	/*

	Now Playing PHP script for SHOUTcast

	This script is (C) MixStream.net 2008

	Feel free to modify this free script 
	in any other way to suit your needs.

	Version: v1.1

	*/
	{
		$ip = $this->scServer;
		$port = $this->scServerPort;

		$title = "NULL";

		$fp = @fsockopen($ip,$port,$errno,$errstr,1);
		if (!$fp) 
		{
	                $raw = "PRIVMSG $channel :Failed to connect";
	                $this->ircClass->sendRaw($raw);
 
		} 
		else
		{ 
			fputs($fp, "GET /7.html HTTP/1.0\r\nUser-Agent: Mozilla\r\n\r\n");
			while (!feof($fp)) 
				{
					$info = fgets($fp);
				}
			$info = str_replace('</body></html>', "", $info);
			$split = explode(',', $info);
			if (empty($split[6]) )
			{
				$title = "NULL";
			}
		else
			{
				$title = str_replace('\'', '`', $split[6]);
				$title = str_replace(',', ' ', $title);
			}
		}
		
		/* end of mixstream.net code */ 
		
		if($title != "NULL" && $title != $this->lasttitle)
		{
			$raw = "PRIVMSG $channel :Now Playing on " . $this->stationName . chr(15) . " : $title";
			$this->ircClass->sendRaw($raw);
			$this->lasttitle = $title;
		}
		
		return true;
	}

	public function get_sc_title_now($list, $args)
	{
		if(!$this->functionEnabled)
		{
			return;
		}
	
		$channel = $list["to"];
	
		$raw = "PRIVMSG $channel :Now Playing on " . $this->stationName . chr(15) . " : $this->lasttitle";
		$this->ircClass->sendRaw($raw);
	}
}

?>
