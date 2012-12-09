<?
class serve_mod extends module
{

    public $title = "iRC SerVe";
    public $author = "g2x3k moded Greedi";
    public $version = "0.5";

    public function init()
    {
        // init


        // not working atm .. *TODO
        $this->serve["settings"]["antispam"] = "1/3"; //1 trigger each 3 sec ...
        $this->serve["settings"]["spamreplies"][] =
            "Hey hey there dont you think its going a bit to fast there only [since] since youre last ...";
        $this->serve["settings"]["spamreplies"][] = "iam busy ...";
        $this->serve["settings"]["spamreplies"][] = "havent you just had ?";
        $this->serve["settings"]["dateformat"] = "h:i:s";
        // config of avaible stuff for serving ...


        $this->serve["triggers"]["coffee"] =
            "Making a cup of coffee for [nick], [today] made today of [total] ordered wich make it the [sumtotal] time i make coffee";


        $this->serve["triggers"]["cola"][] =
            "Serves icecold cola ([today]/[total]/[sumtotal])";
        $this->serve["triggers"]["cola"][] =
            "Serves cola that been standing next to comp for few hrs ([today]/[total]/[sumtotal])";


        $this->serve["triggers"]["redbull"][] =
            "Serves icecold Redbull ([today]/[total]/[sumtotal])";
        $this->serve["triggers"]["pepsi"][] =
            "Serves icecold Pepsi ([today]/[total]/[sumtotal])";
        $this->serve["triggers"]["vodka"][] =
            "Serves icecold vodka ([today]/[total]/[sumtotal])";
        $this->serve["triggers"]["wator"][] =
            "Serves icecold wator ([today]/[total]/[sumtotal])";

        $this->serve["triggers"]["beer"][] =
            "Serves icecold beer ([today]/[total]/[sumtotal])";

        $this->serve["triggers"]["bang"][] =
            "fills a bang from stash and serves it to [nick] ([today]/[total]/[sumtotal])";

        $this->serve["triggers"]["joint"][] =
            "Grabs a joint to [nick] from the stash ([today]/[total]/[sumtotal])";

        $this->serve["triggers"]["head"][] = ".h.e.a.d. ([total])";
        $this->serve["triggers"]["head"][] = ".... ([total])";
        $this->serve["triggers"]["head"][] = "head for you sir. ([total])";


        $this->serve["triggers"]["mix"][] = "grinding up some weed for a mix ([total])";
        $this->serve["triggers"]["mix"][] =
            "grabs some the good stuff for a mix ([total])";
        $this->serve["triggers"]["mix"][] =
            "sneaks into g2x3ks stash and steals for a mix, here you go ([total])";
        $this->serve["triggers"]["mix"][] =
            "goes strain hunting in india for some good shit for your mix ([total])";
        $this->serve["triggers"]["mix"][] =
            "goes strain hunting in morocco for some good shit for your mix ([total])";

        $this->serve["triggers"]["pussy"][] =
            "slaps [nick] in face with a smelly pussy ([total])";
        $this->serve["triggers"]["pussy"][] =
            "Sends some pussy [nick]`s way .. ([total])";

        $this->serve["triggers"]["smoke"][] =
            "Give [nick] a smoke and lights it up ([today]/[total]/[sumtotal])";
        $this->serve["triggers"]["smoke"][] =
            "You should quit smoking -.- ([today]/[total]/[sumtotal])";
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
        $args->timerid = strtotime(date("Y-m-d 00", time() + 86400) . ":00:00");
        $settimer = $args->timerid - time();
        //start timers
        $this->ircClass->privMsg("#GreeDiCE", "timer set to run at $args->timerid / " .
            date("Y-m-d H:i:s", $args->timerid));
        $this->timerClass->addTimer("serveclear" . $args->timerid, $this, "timer_serve",
            $args, $settimer, false);

    }

    public function timer_serve($args = false)
    {
        //clear daily stats
        $res = mysql_query("UPDATE `servestats` SET `today` = '0'");
        $this->ircClass->privMsg("#GreeDiCE", "cleared serve_today");

        //timerinfo for nxt day
        $args = new argClass();
        $args->timerid = strtotime(date("Y-m-d 00", time() + 86400) . ":00:00");
        $settimer = $args->timerid - time();
        //start timers
        $this->ircClass->privMsg("#GreeDiCE", "timer set to run at $args->timerid / " .
            date("Y-m-d H:i:s", $args->timerid));
        $this->timerClass->addTimer("serveclear" . $args->timerid, $this, "timer_serve",
            $args, $settimer, false);
    }

    public function priv_serve($line, $args)
    {
        $chan = strtolower($line['to']);
        $nick = $line['fromNick'];
        $address = $line["fromIdent"] . "@" . $line["fromHost"];
        $network = $this->ircClass->getServerConf("NETWORK");
        $time = $_SERVER['REQUEST_TIME'];
        // failsafes ...
        if (strpos($chan, "#") === false)
            return;
        if ($this->ircClass->getStatusRaw() != STATUS_CONNECTED_REGISTERED)
            return;
        if ($line['to'] == $this->ircClass->getNick())
            return; // dont work in private ...

        if ($chan == "#addpre.info" or $chan == "#addpre.ext2" or $chan == "#addpre.ext" or
            $chan == "#addt" or $chan == "#addpre.ftp" or $chan == "#addpre")
            return;
        if ($fromnick == "thorbits" or $fromnick == "thb") // ignored nicks

            return;

        foreach ($this->serve["triggers"] as $trigger => $reply) {
            if (preg_match("/(!|\.)$trigger/i", $line["text"])) {

                // parse trigger settings and replies

                $rid = rand(0, count($reply) - 1);

                if (is_array($reply))
                    $reply = $reply[$rid];


                // check for nick in db
                $ures = mysql_query("SELECT * FROM `servestats` WHERE nick LIKE " . sqlesc($nick) .
                    " AND network LIKE '$network' AND type LIKE " . sqlesc($trigger) . " LIMIT 1");
                if (mysql_num_rows($ures) >= 1) {
                    $urow = mysql_fetch_assoc($ures);

                    $nres = mysql_query("UPDATE `servestats` SET `today` = today+1, `total` = total+1, `last` = $time WHERE `servestats`.`id` = $urow[id]");

                } else {
                    // check for address in db'
                    $ures = mysql_query("SELECT * FROM servestats WHERE address LIKE " . sqlesc($address) .
                        " AND network LIKE '$network' AND type LIKE " . sqlesc($trigger) . "");
                    if (mysql_num_rows($ures) >= 1) {
                        $urow = mysql_fetch_assoc($ures);
                        //$this->ircClass->privMsg("$chan", "found in db using address .. setting nick and updateing");
                        $nres = mysql_query("UPDATE servestats SET `today` = today+1, `total` = total+1, `nick` = " .
                            sqlesc($nick) . ", `last` = $time  WHERE `servestats`.`id` = $urow[id]");
                    } else {
                        // else add
                        //$this->ircClass->privMsg("$chan", "not found in db ($nick - $address) .. adding");
                        $ires = mysql_query("INSERT INTO `servestats` (`id`, `nick`, `address`, `type`, `last`, `today`, `total`, `channel`, `network`)
						 VALUES (NULL, " . sqlesc($nick) . ", " . sqlesc($address) . ", " . sqlesc
                            ($trigger) . ", UNIX_TIMESTAMP(), '1', '1', " . sqlesc($chan) . ", " . sqlesc($network) .
                            ");");
                    }
                }

                // grab info from db parse reply and return result
                $ures = mysql_query("SELECT * FROM servestats WHERE nick LIKE " . sqlesc($nick) .
                    " AND network LIKE '$network' AND type LIKE " . sqlesc($trigger) . " LIMIT 1");
                $urow = mysql_fetch_assoc($ures);
                //grap totals
                $tres = mysql_query("SELECT sum(total) as sumtotal, sum(today) as sumtoday  FROM servestats WHERE network LIKE '$network' AND type LIKE " .
                    sqlesc($trigger) . " LIMIT 1");
                $trow = mysql_fetch_assoc($tres);
                $last = date("H:i:s", $trow["last"]);
                $message = str_replace(array("[nick]", "[today]", "[total]", "[sumtotal]",
                    "[sumtoday]", '[last]'), array("$nick", $urow["today"], $urow["total"], $trow["sumtotal"],
                    $trow["sumtoday"], "$last"), $reply);
                $time = $_SERVER['REQUEST_TIME'];
                //lookup nick or insert, update stats and reply
                //$this->ircClass->privMsg("$chan", "trigger: $trigger - $reply @ $chan/$network");
                $this->ircClass->privMsg("$chan", "$message");
            }
        }
    }

    function addOrdinalNumberSuffix($num)
    {
        if (!in_array(($num % 100), array(11, 12, 13))) {
            switch ($num % 10) {
                    // Handle 1st, 2nd, 3rd
                case 1:
                    return $num . 'st';
                case 2:
                    return $num . 'nd';
                case 3:
                    return $num . 'rd';
            }
        }
        return $num . 'th';
    }
}
?>

