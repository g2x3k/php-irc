<?php
/**
* Made by Ben Chapman (thejetset)
* Free for distribution etc...
* Provided the authors name remains at the top (HERE)
**/

class twitter_mod extends module {

	//SET THESE!!!
	public $twitname = ""; //Twitter Username
	public $twitpass = ""; // Twitter Password
	public $chan = "#slack-stuff"; // Channel
	public $channame = "Slack-Stuff"; // Channel Name
	public $permline = ""; // Permenant line at end
	public $owner = "usr_local"; // Channel owner (used for status) 
	
	/* Do not change beyond this line. Unless you know what you are doing! */
	public $title = "TwitterTopic";
	public $author = "thejetset";
	public $version = "0.4";
	public $topic = "";
	public $status = "";
	public $tweet = TRUE; // Set this using !tweet (on|off)
	public $seperator = "||"; // Seperator used between topic status permline
	
	// initialize the module on startup
	public function init() {
	    $handle = fopen("modules/twittertopic/topic.txt", "r");
      	$this->topic = fread($handle, filesize("modules/twittertopic/topic.txt"));
      	fclose($handle);
        $handle2 = fopen("modules/twittertopic/status.txt", "r");
      	$this->status = fread($handle2, filesize("modules/twittertopic/status.txt"));
      	fclose($handle2);
      	$line['fromNick'] = "WicketRocks";
      	$line['to'] = $this->chan;
      	$args = null;
      	$this->priv_tweet($line, $args);
	}

	// main method
	public function priv_tweet($line, $args) {
	if($this->ircClass->hasModeSet($line['to'], $line['fromNick'], "oh")) {
			$tweet = "Topic: ".$this->topic."; Status: ".$this->status." -- irc.wyldryde.com ".$this->chan;
			$send = $this->channame." Topic: ".$this->topic." ".$this->seperator." ".$this->owner." ".$this->status;
			if($this->permline != ""){
				$send .= " ".$this->seperator." ".$this->permline;
			}
			if($this->tweet){
				$exec = exec('curl -u '.$this->twitname.':'.$this->twitpass.' -d status="'.$tweet.'&source=TwitterTopic" http://twitter.com/statuses/update.xml > /dev/null');
			}
			$this->ircClass->sendRaw("TOPIC ".$this->chan." :".$send);
			if($this->tweet){
            $this->ircClass->privMsg($line['to'], "Tweeted & set as topic!");
            } else {
            $this->ircClass->privMsg($line['to'], "Set as topic! but not tweeted");
            }
            return;
      } else {
            $this->ircClass->privMsg($line['to'], "You are not authorized to set the topic!");
            return;
      }
      }
      
      public function priv_topic($line, $args) {
      if($this->ircClass->hasModeSet($line['to'], $line['fromNick'], "oh")) {
      	$this->topic = $args['query'];
      	$this->priv_tweet($line, $args);
      	$handle = fopen("modules/twittertopic/topic.txt", "w+");
      	fwrite($handle, $args['query']);
      	fclose($handle);
      } else {
            $this->ircClass->privMsg($line['to'], "You are not authorized to set the topic!");
            return;
      }
      }
      
      public function priv_status($line, $args) {
      if($this->ircClass->hasModeSet($line['to'], $line['fromNick'], "oh")) {
      	$this->status = $args['query'];
      	$this->priv_tweet($line, $args);
      	$handle = fopen("modules/twittertopic/status.txt", "w+");
      	fwrite($handle, $args['query']);
      	fclose($handle);
      } else {
            $this->ircClass->privMsg($line['to'], "You are not authorized to set the topic!");
            return;
      }
      }
      
      public function priv_toggle($line, $args) {
      if($this->ircClass->hasModeSet($line['to'], $line['fromNick'], "oh")) {
      	switch($args['query']){
      	case "on":
      		$this->tweet = TRUE;
      		$this->ircClass->privMsg($line['to'], "This will tweet the topic");
      		break;
      	case "off":
      		$this->tweet = FALSE;
      		$this->ircClass->privMsg($line['to'], "This will not tweet the topic");
      		break;
      	}
      } else {
            $this->ircClass->privMsg($line['to'], "You are not authorized to set the topic!");
            return;
      }
      }
      
      public function priv_topicl($line, $args) {
      	$fixed = 38;
      	$statusl = strlen($this->status);
      	$chanl = strlen($this->chan);
      	$totl = $fixed + $statusl + $chanl;
      	$left = 139 - $totl;
      	
      	$this->ircClass->privMsg($line['to'], "There are ".$left." characters left!");
        return;
      }
      
      public function priv_statusl($line, $args) {
      	$fixed = 38;
      	$topicl = strlen($this->topic);
      	$chanl = strlen($this->chan);
      	$totl = $fixed + $topicl + $chanl;
      	$left = 139 - $totl;
      	
      	$this->ircClass->privMsg($line['to'], "There are ".$left." characters left!");
        return;
      }
      }
?>
