<pre>
<?
	/*
	 *	readUserStats(userid)
	 *		-	Read WhatPulse user statistics from the webapi into an array.
	 *
	 *	Author: wasted@whatpulse.org
	 */
	function readUserStats($userid)
	{
		// prepare an array to hold your stats
		$WhatPulseStats = array();
		
		// types of statistics 
		$stat_types = array("UserID", "AccountName", "Country",
						"DateJoined", "Homepage", "LastPulse", 
						"Pulses", "TotalKeyCount", "TotalMouseClicks",
						"AvKeysPerPulse", "AvClicksPerPulse", 
						"AvKPS", "AvCPS", "Rank", "TeamID", 
						"TeamName", "TeamMembers", "TeamKeys", 
						"TeamClicks", "TeamDescription", 
						"TeamDateFormed", "RankInTeam", "GeneratedTime");
						
   		// init the xml parser and read the data into an array
   		$data = implode("", file("http://whatpulse.org/api/users/".$userid.".xml"));
   		$parser = xml_parser_create();
   		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
   		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
   		xml_parse_into_struct($parser, $data, $values, $tags);
   		xml_parser_free($parser);
   		
   		// loop through the structures
   		foreach ($tags as $key => $val) 
   		{
	   		// only process stuff between the <UserStats> tags
       		if ($key == "UserStats") 
       		{
	       		// loop through the tags inside <UserStats>
           		$ranges = $val;
           		for ($i = 0; $i < count($ranges); $i += 2) 
           		{
					$offset = $ranges[$i] + 1;
					$len = $ranges[$i + 1] - $offset;
					$statsarray = array_slice($values, $offset, $len);
					
					// loop through the structure of the xml tag
					foreach($statsarray as $key => $value)
					{
						// match to a stats_type
						for($i = 0; $i < count($stat_types); $i++)
						{
							if($value['tag'] == $stat_types[$i]) 
							{
								// remember the value of the stats_type
								$type = $stat_types[$i];
								$WhatPulseStats[$type] = $value['value'];
							}
						} 
					} 
				} 
			} 
			else {
				continue;
			}
		} 
		
		return $WhatPulseStats;
	} 
	
	// read statistics
	
?> 
</pre>
