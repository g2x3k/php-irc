<?php

class user_mod extends module
{

    public $title = "User Mod";
    public $author = "Greedi";
    public $version = "0.1";

    public function init()
    {
        $this->lcd = new BitcoinClient("http", "user", "pass",
            "localhost", 9332);
    }
    public function priv_help($line, $args)
    {
        $channel = $line["to"];
        $nick = $line["fromNick"];
        $host = $line["from"];
        $this->ircClass->privMsg("$channel",
            "!signup - (nickname pass addy mail)");
    }

    public function priv_signup($line, $args)
    {
        $channel = $line["to"];
        $nick = $line["fromNick"];
        $host = $line["from"];
        $nickname = $args["arg1"];
        $pass = $args["arg3"];
        $mail = $args["arg4"];
        $ltc_address = $args["arg2"];
//        $userinfo = mysql_query("SELECT * FROM members") or die(mysql_error
//            ());
//        while ($info = mysql_fetch_array($userinfo)) {
//            $user = $info['usr'];
//            $mail = $info['email'];
//            $addy = $info['LTC_addy'];
//            $reghost = $info['reghost'];
//        }
//        if ($reghost == $host) {
//            $this->ircClass->privMsg("$channel", "$host was already found in the database!");
//            goto stop;
//        }
//        if ($user == $nick) {
//            $this->ircClass->privMsg("$channel", "$nick was already found in the database!");
//            goto stop;
//        }
//        if ($addy == $ltc_address) {
//            $this->ircClass->privMsg("$channel", "$addy was already found in the database!");
//            goto stop;
//        }

        mysql_query("INSERT INTO members(usr,admin,pass,email,LTC_addy,reghost,loggedin,dt)
                                                VALUES(
                                               
                                                        '" . $nickname . "',
                                                                '0',
                                                        '" . md5($pass) . "',
                                                        '" . $mail . "',
                                                        '" . $ltc_address . "',
                                                        '" . $host . "',
                                                        '0',
                                                         NOW()
                                                       
                                                )");
        $this->ircClass->privMsg("$channel", "$nick added");
        $this->ircClass->notice($nick, $text, $queue = 1);  
        //stop :
         }

    public function priv_login($line, $args)
    {
        $channel = $line["to"];
        $nick = $line["fromNick"];
        $host = $line["from"];
        mysql_query("UPDATE members SET loggedin='1'") or die(mysql_error
            ());
        $this->ircClass->privMsg("$channel", "$nick logged in");
    }
    public function priv_logout($line, $args)
    {
        $channel = $line["to"];
        $nick = $line["fromNick"];
        $host = $line["from"];
        mysql_query("UPDATE members SET loggedin='0'") or die(mysql_error
            ());
        $this->ircClass->privMsg("$channel", "$nick logged out");
    }
    public function priv_profile($line, $args)
    {

        $channel = $line["to"];
        $nick = $line["fromNick"];
        $host = $line["from"];

        $userinfo = mysql_query("SELECT * FROM members") or die(mysql_error
            ());
        while ($info = mysql_fetch_array($userinfo)) {
            $mail = $info['email'];
            $addy = $info['LTC_addy'];
            $reghost = $info['reghost'];

            $this->ircClass->privMsg("$channel", "$nick: $mail $addy $reghost");
        }
    }
}

?>