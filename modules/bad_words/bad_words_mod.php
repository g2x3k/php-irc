<?php


class bad_words_mod extends module
{

    public $title = "Bad Words Mod";
    public $author = "Manick";
    public $version = "0.1";

    private $badWords = array();

    public function init()
    {
        unset($this->badWords);
        $this->badWords = array();

        $badWords = new ini("modules/bad_words/bad_words.ini");

        if ($badWords->getError()) {
            return;
        }

        $channels = $badWords->getSections();

        if ($channels === false) {
            return;
        }

        foreach ($channels AS $channel) {
            $channel = irc::myStrToLower($channel);

            $bw = $badWords->getSection($channel);

            if ($bw == false || !is_array($bw)) {
                continue;
            }

            foreach ($bw AS $badword => $blah) {
                $this->badWords[$channel][$badword] = true;
            }
        }
    }


    public function reload($line, $args)
    {
        if (!$this->ircClass->hasModeSet($line['to'], $line['fromNick'], "oa")) {
            return;
        }

        $this->init();
        $this->ircClass->notice($line['fromNick'], "Reload command sent.");
    }

    public function bad_words($line, $args)
    {
        if ($line['to'] == $this->ircClass->getNick()) {
            return;
        }

        $chan = irc::myStrToLower($line['to']);

        if (!$this->ircClass->hasModeSet($line['to'], $this->ircClass->getNick(), "hoa")) {
            return;  // we don't have power to kick!
        }

        if (isset($this->badWords[$chan])) {
            foreach ($this->badWords[$chan] AS $bw => $blah) {
                if (preg_match("/ " . $bw . " /i", " " . $line['text'] . " ")) {
                    $this->ircClass->sendRaw("KICK " . $line['to'] . " " . $line['fromNick'] . " :Bad word!");
                    break;
                }
            }
        }
    }
}

?>
