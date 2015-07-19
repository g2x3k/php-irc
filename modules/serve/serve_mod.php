<?
class serve_mod extends module {

	public $title = "iRC SerVe";
	public $author = "g2x3k";
	public $version = "0.2";

	public function init() {
		// init


		// not working atm .. *TODO
		$this->serve["settings"]["antispam"] = "1/3"; //1 trigger each 3 sec ...
		$this->serve["settings"]["spamreplies"][] = "Hey hey there dont you think its going a bit to fast there only [since] since youre last ...";
		$this->serve["settings"]["spamreplies"][] = "iam busy ...";
		$this->serve["settings"]["spamreplies"][] = "havent you just had ?";
		$this->serve["settings"]["dateformat"] = "h:i:s";
		// config of avaible stuff for serving ...
		
		$this->serve["triggers"]["bar"] = "This Bar have: !coffee !bang !cola !beer !joint !head !mix !whisky !pipe !pussy !coke !icecream";
		$this->serve["triggers"]["coke"] = "Are you stupid? We doesn't do shit like this... GO SLAP YOUR SELF IN THE NUTS! :P";
		
		$this->serve["triggers"]["coffee"]  = "Making a cup of coffee for [nick], [today] made today of [total] ordered wich make it the [sumtotal] time i make coffee";

		$this->serve["triggers"]["bang"][] 	= "fills a bang from stash and serves it to [nick] ([today]/[total]/[sumtotal])";
		$this->serve["triggers"]["cola"][] 	= "Serves icecold cola ([today]/[total]/[sumtotal])";
        $this->serve["triggers"]["cola"][] 	= "Serves cola that been laying in pile of shit ~45c ([today]/[total]/[sumtotal])";
        $this->serve["triggers"]["cola"][] 	= "Serves cola been standing close to box of dryice ~1,3c ([today]/[total]/[sumtotal])";
		$this->serve["triggers"]["cola"][] 	= "Serves cola that been standing next to comp for few hrs ([today]/[total]/[sumtotal])";
		$this->serve["triggers"]["beer"][] 	= "Serves icecold beer ([today]/[total]/[sumtotal])";
		$this->serve["triggers"]["joint"][] = "Grabs a joint to [nick] from the stash ([today]/[total]/[sumtotal])";

		$this->serve["triggers"]["head"][] 	= ".h.e.a.d. ([total])";
		$this->serve["triggers"]["head"][] 	= "head for you sir. ([total])";
		
        $this->serve["triggers"]["wine"][] 	= "pours up some fine stuff from the basement ([total])";
        $this->serve["triggers"]["wine"][] 	= "here you are, found something out back ([total])";		
        $this->serve["triggers"]["wine"][] 	= "lucky you we just got one of this left enjoy ([total])";
		$this->serve["triggers"]["wine"][] 	= "so youre hit hard, where you want it ?, dont cry";
		
		$this->serve["triggers"]["mix"][] 	= "grinding up some weed for a mix ([total])";
		$this->serve["triggers"]["mix"][] 	= "grabs some the good stuff for a mix ([total])";
		$this->serve["triggers"]["mix"][] 	= "sneaks into g2x3ks stash and steals for a mix, here you go ([total])";
		$this->serve["triggers"]["mix"][] 	= "goes strain hunting in india for some good shit for your mix ([total])";
		$this->serve["triggers"]["mix"][] 	= "goes strain hunting in morocco for some good shit for your mix ([total])";

		$this->serve["triggers"]["pipe"][] 	= "goes strain hunting in morocco for some good shit for your pipe ([total])";
        $this->serve["triggers"]["pipe"][] 	= "saw some shit in corner, fills a pipe ([total])";
        $this->serve["triggers"]["pipe"][] 	= "skunky just arrieved peace all over ([total])";
        
        $this->serve["triggers"]["whiskey"][] 	= "serves whiskey on the rocks ([total])";
        $this->serve["triggers"]["whiskey"][] 	= "found some weird looking bottle in corner, might hit gold cheers ([total])";
        $this->serve["triggers"]["whiskey"][] 	= "cola and bad whiskey for you ([total])";
        
		$this->serve["triggers"]["pussy"][]	= "slaps [nick] in face with a smelly pussy ([total])";
		$this->serve["triggers"]["pussy"][] = "Sends some pussy [nick]`s way .. ([total])";
		$this->serve["triggers"]["pussy"][] = "not enough money to suply you aswell ... ([total])";

    	$this->serve["triggers"]["icecream"][] 	= "here [nick]... one ball for you only ([today]/[total]/[sumtotal])";
        $this->serve["triggers"]["icecream"][] 	= "finds a biig icecream for [nick] eat and you get for free (50$ to use toilet) ([today]/[total]/[sumtotal])";
        $this->serve["triggers"]["icecream"][] 	= "dusts off something that look like icecream from the corner of fridge, here [nick] ([today]/[total]/[sumtotal])";
		// - docu:
		// [nick] = nick that triggered, [today] how many heads/coffee person had today
		// [total] = how many nick had it total, [last] time of last, [since] time since last
		// [sumtotal], [sumchannel], [sumnetwork] = how many all had

		// - reply syntax (random reply) :
		// $this->serve["triggers"]["coffee"]["replies"][] = "some reply here";
		// $this->serve["triggers"]["coffee"]["replies"][] = "another reply here";
		// - reply syntax - two line reply:
		// $this->serve["triggers"]["coffee"]["replies"][1] = "Line 1 of the reply 1";
		// $this->serve["triggers"]["coffee"]["replies"][1] = "Line 2 of the reply 1";
		// $this->serve["triggers"]["coffee"]["replies"][2] = "Line 1 of the reply 2";
		// $this->serve["triggers"]["coffee"]["replies"][2] = "Line 2 of the reply 2";
		// * [1/2] binds the two replies togeter must be incremented

		// - default settings syntax
		// $this->serve["settings"]["antispam"] = "1/3"; //1 trigger each 3 sec ...
		// $this->serve["settings"]["dateformat"] = "h:i:s";
		// - trigger settings syntax (overrides default settings if set)
		// $this->serve["triggers"]["coffee"]["settings"]["antispam"] = "1/60"; one a minute
		// $this->serve["triggers"]["coffee"]["settings"]["spamreplies"][] = "hey stop that iam burning my hands on this coffee ... one per minute shuld be enough";
		// set timer to clear "today" stats every 24hr

		// timer stuff
		//timerinfo for nxt day
		$args = new argClass();
		$args->timerid = strtotime(date("Y-m-d 00", time()+86400).":00:00");
		$settimer = $args->timerid-time();
		//start timers
		$this->ircClass->privMsg("#coders", "timer set to run at $args->timerid / ".date("Y-m-d H:i:s",$args->timerid));
		$this->timerClass->addTimer("serveclear" . $args->timerid, $this, "timer_serve", $args, $settimer, false);

	}

	public function timer_serve($args = false) {
		//clear daily stats
		$res = $this->db->query("UPDATE `layer13`.`servestats` SET `today` = '0'");
		$this->ircClass->privMsg("#coders", "cleared serve_today");

		//timerinfo for nxt day
		$args = new argClass();
		$args->timerid = strtotime(date("Y-m-d 00", time()+86400).":00:00");
		$settimer = $args->timerid-time();
		//start timers
		$this->ircClass->privMsg("#coders", "timer set to run at $args->timerid / ".date("Y-m-d H:i:s",$args->timerid));
		$this->timerClass->addTimer("serveclear" . $args->timerid, $this, "timer_serve", $args, $settimer, false);
	}

	public function priv_serve($line, $args) {
		$chan = strtolower($line['to']);
		$nick = $line['fromNick'];
		$address = $line["fromIdent"]."@".$line["fromHost"];
		$network = $this->ircClass->getServerConf ("NETWORK");

		// failsafes ...
		if (strpos ( $chan, "#" ) === false)
		return;
		if ($this->ircClass->getStatusRaw () != STATUS_CONNECTED_REGISTERED)
		return;
		if ($line['to'] == $this->ircClass->getNick())
		return; // dont work in private ...

		if ($chan == "#addpre.info" or $chan == "#addpre.ext2" or $chan == "#addpre.ext" or $chan == "#addt" or $chan == "#addpre.ftp" or $chan == "#addpre")
		return;
		if ($nick == "l13a" or $nick == "thb") 												// ignored nicks
		return;
        
        if (preg_match("/(#addpre.ext2|#addnf0|#addpre.ftp|#addpre2)/i", $chan))
        return;

		foreach ($this->serve["triggers"] as $trigger => $reply) {
			if (preg_match("/(!|\.)$trigger\b/i", $line["text"])) {

				// parse trigger settings and replies

				$rid = rand(0, count($reply)-1);

				if (is_array($reply)) $reply = $reply[$rid];


				// check for nick in db
				$ures = $this->db->query("SELECT * FROM servestats WHERE nick LIKE ".sqlesc($nick)." AND network LIKE '$network' AND type LIKE ".sqlesc($trigger)." LIMIT 1");
				if (mysql_num_rows($ures) >= 1) {
					$urow = mysql_fetch_assoc($ures);

					$nres = $this->db->query("UPDATE `layer13`.`servestats` SET `today` = today+1, `total` = total+1 WHERE `servestats`.`id` = $urow[id]");

				}
				else {
					// check for address in db'
					$ures = $this->db->query("SELECT * FROM servestats WHERE address LIKE ".sqlesc($address)." AND network LIKE '$network' AND type LIKE ".sqlesc($trigger)."");
					if (mysql_num_rows($ures) >= 1) {
						$urow = mysql_fetch_assoc($ures);
						//$this->ircClass->privMsg("$chan", "found in db using address .. setting nick and updateing");
						$nres = $this->db->query("UPDATE `layer13`.`servestats` SET `today` = today+1, `total` = total+1, `nick` = ".sqlesc($nick)." WHERE `servestats`.`id` = $urow[id]");
					}
					else {
						// else add
						//$this->ircClass->privMsg("$chan", "not found in db ($nick - $address) .. adding");
						$ires = $this->db->query("INSERT INTO `layer13`.`servestats` (`id`, `nick`, `address`, `type`, `last`, `today`, `total`, `channel`, `network`)
						 VALUES (NULL, ".sqlesc($nick).", ".sqlesc($address).", ".sqlesc($trigger).", UNIX_TIMESTAMP(), '1', '1', ".sqlesc($chan).", ".sqlesc($network).");");
					}
				}

				// grab info from db parse reply and return result
				$ures = $this->db->query("SELECT * FROM servestats WHERE nick LIKE ".sqlesc($nick)." AND network LIKE '$network' AND type LIKE ".sqlesc($trigger)." LIMIT 1");
				$urow = mysql_fetch_assoc($ures);
				//grap totals
				$tres = $this->db->query("SELECT sum(total) as sumtotal, sum(today) as sumtoday  FROM servestats WHERE network LIKE '$network' AND type LIKE ".sqlesc($trigger)." LIMIT 1");
				$trow = mysql_fetch_assoc($tres);

				$message = str_replace(array("[nick]", "[today]", "[total]", "[sumtotal]", "[sumtoday]"), array("$nick", $urow["today"], $urow["total"], $trow["sumtotal"],$trow["sumtoday"]), $reply);
				//lookup nick or insert, update stats and reply
				//$this->ircClass->privMsg("$chan", "trigger: $trigger - $reply @ $chan/$network");
				$this->ircClass->privMsg("$chan", "$message");
			}
		}
	}

	function addOrdinalNumberSuffix($num) {
		if (!in_array(($num % 100),array(11,12,13))){
			switch ($num % 10) {
				// Handle 1st, 2nd, 3rd
				case 1:  return $num.'st';
				case 2:  return $num.'nd';
				case 3:  return $num.'rd';
			}
		}
		return $num.'th';
	}
}
?>