<?php
class spot_mod extends module {

	public $title = "Spotify Lookup";
	public $author = "MuNgLo";
	public $version = "0.1";

	public function spot_check($line, $args)
	{
	$matchstring = "/spotify:track:(.*)/";
	if(preg_match($matchstring,$line['text']. " ",$trackkey)){
		if($trackkey==""){return;}
	
		$xmlUrl="http://ws.spotify.com/lookup/1/?uri=spotify:track:".$trackkey[1];
		$xmlStr=file_get_contents($xmlUrl);
		$xmlEle= new SimpleXMLElement($xmlStr);
		// $xmlObj=simplexml_load_file($xmlUrl);
		// $title=$xmlObj->xpath("name");
		$title=$xmlEle->name;
		$artist=$xmlEle->artist->name;
		$this->ircClass->privMsg($line['to'], ".: " . BOLD .$title. BOLD ." :. by .: ". BOLD . $artist . BOLD . " :.");
	}
	}

}

?>
