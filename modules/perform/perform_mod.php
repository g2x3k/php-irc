<?php

class perform_mod extends module
{

    public $title = "perform_mod";

    public $author = "g2x3k";
    public $version = "0.1.1";
    private $delay = 0;


    public function init()
    {
        // add actions to perform when reloading the bot here

    }

    public function onConnect()
    {
        // add actions to perform upon connect to the server here
        // you shuld only use for on connect stuff oper login etc, tho thats added in the bot.conf
        // ircop def is not set here yet, this runs on raw(004) ircd wont relay if we are ircop after raw(376) end of motd

        $this->ircClass->privMsg("g2x3k", "Iam back!");
    }

    public function onJoin($line)
    {
        // simple example of a onJoin mod
        $nick = $line['fromNick'];
        $channel = $line['text'];
        $me = $this->ircClass->getNick();

        if ($this->ircClass->IRCOP)
            $mode = "SAMODE";
        else
            $mode = "MODE";

        // make bot give itself +a upon entering a channel
        if ($nick == $me) {
            $this->ircClass->sendRaw("$mode $channel +a $me ", true);
        }
        else {
            // a welcome text ?
        }

    }
}
?>