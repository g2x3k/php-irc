<?php

class perform_mod extends module
{

    public $title = "perform_mod";

    public $author = "g2x3k";
    public $version = "0.1";
    private $delay = 0;


    public function init()
    {
        // add actions to perform upon connect & reload of the bot here

    }

    public function doPerform()
    {
        // add actions to perform upon connect to the server here
        // you shuld only use for on connect stuff oper login etc, tho thats added in the bot.conf

        // example
        $this->ircClass->privMsg("nick", "Hello there..");

    }

}

?>