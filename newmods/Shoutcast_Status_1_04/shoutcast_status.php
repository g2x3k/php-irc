<?PHP

class shoutcast_status extends module {

	public $title = "Shoutcast_Status";
	public $author = "EHCanadian EHCanadian83@hotmail.com";
	public $version = "1.04";
	
	private $SourceStream    = 'http://208.94.242.219:80';
	private $AccessProxy     = '';
	private $AnnounceTime    = '60';
	private $functionEnabled = false;

	public function init()
	{
	    require('modules/shoutcast_status/shoutcast_status.class.php');	
	}

	public function destroy()
	{
		$this->functionEnabled = false;
		$this->timerClass->removeTimer("announce_timer");
	}
	
	public function shoutcaston($line, $args){
	 //Not Oper ?
	 if(!$this->ircClass->isMode($line['fromNick'], $line['to'], "o")){	 
	 $raw = "PRIVMSG ".$line['fromNick']." Command Can Only Be Ran By Channel Ops";
	 $this->ircClass->sendRaw($raw,true);	
	 return;
	 //
	 }elseif($this->functionEnabled === false){	 
	 //Enable
	 $raw = "PRIVMSG ".$line['fromNick']." Command Accepted. Atempting Connection";
	 $this->ircClass->sendRaw($raw,true);
	 $this->timerClass->addTimer("announce_timer", $this, "shoutcastannounce", $line['to'], $this->AnnounceTime, true);
	 $this->functionEnabled = true; 
	 //
	 //Already Executed?
	 }else{
	 $raw = "PRIVMSG ".$line['fromNick']." Shoutcast Already Enabled ".$line['fromNick'];
	 $this->ircClass->sendRaw($raw,true);
	 };
	 //
	//
	}
	
	public function shoutcastoff($line, $args){
	 //Not Oper ?
	 if(!$this->ircClass->isMode($line['fromNick'], $line['to'], "o")){	 
	 $raw = "PRIVMSG ".$line['fromNick']." Command Can Only Be Ran By Channel Ops";
	 $this->ircClass->sendRaw($raw,true);
	 $this->functionEnabled = false;
	 return;
	 //
	 }elseif($this->functionEnabled === true){	
	 //Disable
	 $raw = "PRIVMSG ".$line['fromNick']." Command Accepted. Killing Connection";
	 $this->ircClass->sendRaw($raw,true);
	 $this->timerClass->removeTimer("announce_timer");
	 $this->functionEnabled = false; 
	 //	
	 //Already Executed?
	 }else{
	 $raw = "PRIVMSG ".$line['fromNick']." Shoutcast Already Disabled ".$line['fromNick'];
	 $this->ircClass->sendRaw($raw,true);
	 };
	 //
	//
	}
	
	//Timer Set, Show Stats
	public function shoutcastannounce($Channel){
	 //If Not Enabled
	 if(!$this->functionEnabled){return;}else{	
	 //Announce
	 $sc = new hn_ShoutcastInfo();
	 $sc->query_URL($this->SourceStream,$this->AccessProxy);
	 $Station = $sc->station(); 
     $Title   = $sc->song();
	
	 switch($sc->is_online()){
      case '0': $Online = 'Offline'; break;
      case '1': $Online = 'Online'; break;
     };
	
	 if($Online == 'Online'){
	  $raw = "PRIVMSG $Channel $Station Now Playing: $Title @ ".$sc->bandwidth();	  
	  $this->ircClass->sendRaw($raw,true);
	  $raw = "PRIVMSG $Channel Listen In @ ".$this->SourceStream."/listen.pls";
	  $this->ircClass->sendRaw($raw,true);	  
	  return true;
	 }else{
	  $raw = "PRIVMSG $Channel Unable To Connect To Shoutcast. Disabling ".$this->SourceStream." ".$this->AccessProxy."";
	  $this->ircClass->sendRaw($raw,true);
	  $this->timerClass->removeTimer("announce_timer");
	  return;
	 };
	 //
	};
	//
   }	
}
?>