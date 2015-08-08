<?php
class banip_mod extends module
{
    public $title = "BanIP";
    public $author = "g2x3k";
    public $version = "0.1";
    private $delay = 0;


    public function init()
    {
        // allow commands from who ?
        $this->bancfg["allowfrom"] = array("g2x3k", "bette");
        // where to ban ?
        $this->bancfg["cloudflare"] = true;
        $this->bancfg["ircdgline"] = true;
        $this->bancfg["iptables"] = true;
    }

    public function priv_banip($line, $args)
    {

        $channel = $line['to'];
        $text = explode(" ", $line["text"]);
        $nick = $line['fromNick'];
        $ip = $text[1];
        $reason = $text[2];

        if (strpos($channel, "#") === false)
            return;

        if (!in_array($nick, $this->bancfg["allowfrom"]))
            return;

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->ircClass->privMsg("$channel", "Provide a valid ip .. ($ip) is not valid");
            return;
        }

        if (strlen($reason) < 1) {
            $this->ircClass->privMsg("$channel", "No reason provided... !banip (IP) [reason], setting to no.reason");
            $reason = "No.Reason";
        }

        //Cloudflare
        if ($this->bancfg["cloudflare"])
            exec("curl https://www.cloudflare.com/api_json.html -d 'a=ban' -d 'tkn=" . $this->clapikey . "' -d 'email=faceydk@gmail.com' -d 'key=$ip' -d 'reason=$reason' ");

        // iRCD / GLiNE
        if ($this->bancfg["ircdgline"]) {
            $hostname = gethostbyaddr($ip);
            $this->ircClass->sendRaw("gline *@$ip 365d $reason");
            if ($ip != $host) {
                $this->ircClass->sendRaw("gline *@$hostname 365d $reason");
            }
        }
        // iptables - requires my iptables mysql addon, https://gist.github.com/g2x3k/58aa48c6c710052d9f85
        if ($this->bancfg["iptables"])
            $this->db->query("INSERT INTO `ipbans` (`id`, `ip`, `reason`) VALUES (NULL, '$ip', '$reason');");


        $this->ircClass->privMsg("$channel", "Banned $ip " . ($ip != $hostname ? "($hostname)" : ""));
    }

    public function priv_unbanip($line, $args)
    {
        $exectimer = xtimer(); // started timer
        $channel = $line['to'];
        $text = explode(" ", $line["text"]);
        $nick = $line['fromNick'];
        $ip = $text[1];


        if (strpos($channel, "#") === false)
            return;

        if (!in_array($nick, $this->bancfg["allowfrom"]))
            return;

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->ircClass->privMsg("$channel", "Provide a valid ip .. ($ip) is not valid");
            return;
        }


        //Cloudflare
        if ($this->bancfg["cloudflare"])
            exec("curl https://www.cloudflare.com/api_json.html -d 'a=nul' -d 'tkn=" . $this->clapikey . "' -d 'email=faceydk@gmail.com' -d 'key=$ip' -d 'reason=$reason' ");

        // iRCD / GLiNE
        if ($this->bancfg["ircdgline"]) {
            $hostname = gethostbyaddr($ip);
            $this->ircClass->sendRaw("gline -*@$ip 365d $reason");
            if ($ip != $host)
                $this->ircClass->sendRaw("gline -*@$hostname 365d $reason");
        }
        // iptables
        if ($this->bancfg["iptables"])
            $this->db->query("DELETE FROM `ipbans` WHERE `ipbans`.`ip` LIKE '$ip';");


        $this->ircClass->privMsg("$channel", "Unbanned $ip " . ($ip != $hostname ? "($hostname)" : ""));
    }
}


?>