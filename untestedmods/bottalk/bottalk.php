<?php

class bottalk extends module {

    public $title = "Make bot say anything";
    public $author = "hery@serasera.org";
    public $version = "0.9";

    public function reply($line, $args)
    {
        $chan = "";
        $query = "";
        if ($args['nargs'] == 0)
        {
            $this->ircClass->notice($line['fromNick'], "What do you make me say?");
            return;
        }
        
        if ($line['to'] == $this->ircClass->getNick())
        {
            if (substr($args['arg1'], 0, 1) == '#')
            {
                $chan = $args['arg1'];
                $query = trim(substr($args['query'], (strpos($args['query'], $args['arg1'])) + (strlen($args['arg1']))));
            }
            else
            {
                $this->ircClass->notice($line['fromNick'], "Where should I say that? Please use " . BOLD . "!say #channel word to say");
                return;
            }
        }
        else
        {
            $chan = $line['to'];
            $query = $args['query'];
        }
        
		if ($this->ircClass->isOnline($line['fromNick'], $chan))
		{
			$this->ircClass->privMsg($chan, $query);
		}
		else
		{
			$this->ircClass->notice($line['fromNick'], "You need to be in " . $chan . " to do that");
			return;
		}
    }

}
?>