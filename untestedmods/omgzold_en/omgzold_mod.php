<?php
/*

The following table is used in with mysql:

CREATE TABLE `omgzold` (
  `id` int(11) NOT NULL auto_increment,
  `deleted` tinyint(1) default '0',
  `author` varchar(50) default '',
  `url` text,
  `channel` varchar(100) default '',
  `time` timestamp NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
	
*/

class omgzold_mod extends module {

	public $title = "OMGZOLD";
	public $author = "dalurka";
	public $version = "0.1.en";

/*
	public function init()
	{

	}
*/

	public function omgzold($line, $args)
	{
		if ($line['to'] == $this->ircClass->getNick())
		{
			return;
		}
		
		$chan = irc::myStrToLower($line['to']);
		if(preg_match('@(https?://[^ ]*)@',$line['text']. " ",$url))
		{
			//print("URLZ: ". $url[1]);
			
			$values = array(	$url[1],
						$chan,
					);

			$query = $this->db->query("SELECT * FROM omgzold WHERE url='[1]' AND channel='[2]' AND deleted='0' LIMIT 1",$values);
			if($this->db->numRows($query))
			{
				$quote	= $this->db->fetchArray($query);
				$diff = $this->timeDiff(time(),$quote['time']);
				$msg = BOLD."OMGZOLD: " .BOLD.$quote['author'] . " wrote ". $quote['url'] ." ". $diff ."ago";
				$this->ircClass->privMsg($chan,$msg);
			}
			else
			{
				$values = array(	$line['fromNick'],
							$url[1],
							$chan,
						);
				$query = $this->db->query("INSERT INTO omgzold (author,url,channel) VALUES('[1]', '[2]', '[3]')",$values);
			}
		}	
	}


/* ruthlessly stolen from the date() documentetion comment section at php.net */

public function timeDiff($starttime, $endtime, $detailed=true, $short = true){ 
        if(! is_int($starttime)) $starttime = strtotime($starttime);
        if(! is_int($endtime)) $endtime = strtotime($endtime);
       
        $diff = ($starttime >= $endtime ? $starttime - $endtime : $endtime - $starttime);
   
        # Set the periods of time
        $periods = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
        $lengths = array(1, 60, 3600, 86400, 604800, 2630880, 31570560, 315705600);
       
        if($short){
            $periods = array("s", "m", "h", "d", "mån", "år");
            $lengths = array(1, 60, 3600, 86400, 2630880, 31570560);
        }
   
        # Go from decades backwards to seconds
        $i = sizeof($lengths) - 1; # Size of the lengths / periods in case you change them
        $time = ""; # The string we will hold our times in
        while($i >= 0) {
            if($diff > $lengths[$i-1]) { # if the difference is greater than the length we are checking... continue
                $val = floor($diff / $lengths[$i-1]);    # 65 / 60 = 1.  That means one minute.  130 / 60 = 2. Two minutes.. etc
                $time .= $val . ($short ? '' : ' ') . $periods[$i-1] . ((!$short && $val > 1) ? 's ' : ' ');  # The value, then the name associated, then add 's' if plural
                $diff -= ($val * $lengths[$i-1]);    # subtract the values we just used from the overall diff so we can find the rest of the information
                if(!$detailed) { $i = 0; }    # if detailed is turn off (default) only show the first set found, else show all information
            }
            $i--;
        }
      
        return $time;
    }

}

?>
