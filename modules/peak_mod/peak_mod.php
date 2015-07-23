<?php

class peak_mod extends module {

    public $title = "Channel Peak Mod";
    public $author = "Manick edited by g2x3k";
    public $version = "0.1";

    /**

    vers history:
    0.12 g2x3k
    can lookup other channels
    new syntax: !peak [channel]
    0.11 g2x3k
    changed !peak reply to a chan msg
    added announce when new peak is set
    0.1: Manick
    simple working peak mod ...
    syntax: !peak
    -------------

     **/
    private $peak;

    public function init()   {
        $this->peak = new ini("modules/peak_mod/peak.ini");
    }

    public function peak_on_join($line, $args) {
        if ($this->peak->getError())
            return;


        $chan = strtolower($line['text']);
        $chanData = $this->ircClass->getChannelData($chan);

        if (!is_object($chanData))
            return;

        $peak = $this->peak->getIniVal($chan, "peak");
        if ($peak === false || $peak < $chanData->count)    {


            $this->peak->setIniVal($chan, "peak", $chanData->count);
            $this->peak->setIniVal($chan, "time", time());
            $this->peak->writeIni();
            if ($chan != "#root")
                return;
            $this->ircClass->privMsg($chan, "New PEAK for $chan: ".$chanData->count." ");
        }
    }
    public function priv_peak($line, $args) 	{
        if ($line['to'] === $this->ircClass->getNick())		{
            return;
        }

        if ($this->peak->getError()) {
            $this->ircClass->notice($line['fromNick'], "Unexplained error opening peak database.");
            return;
        }

        if ($args['nargs'] <= 0)
            $chan = strtolower($line['to']);
        else
            $chan = strtolower($args['arg1']);

        $chanData = $this->peak->getSection($chan);
        if ($chanData == false) {
            $this->ircClass->notice($line['fromNick'], "I have no data for that channel.");
            return;
        }

        $time = date("l, F jS, Y @ G:i", $chanData['time']);
        $this->ircClass->privMsg($line['to'], "PEAK:, " . $line['fromNick'] . ", the current peak for $chan is " . BOLD .
            $chanData['peak'] . BOLD . " users on " . BOLD . $time . BOLD . ".");
    }
}
?>