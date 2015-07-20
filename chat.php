<?php

/*
+---------------------------------------------------------------------------
|   PHP-IRC v2.2.3 Service Release
|   ========================================================
|   by Manick
|   (c) 2001-2005 by http://www.phpbots.org/
|   Contact: manick@manekian.com
|   irc: #manekian@irc.rizon.net
|   ========================================
|   Maintained by g2x3k
|   2011-2015 https://github.com/g2x3k/php-irc
|   contant: g2x3k@layer13.net
|   irc: #root @ irc.layer13.net:+7000
+---------------------------------------------------------------------------
|   > chat class module
|   > Module written by Manick
|   > Module Version Number: 2.2.0
+---------------------------------------------------------------------------
|   > This program is free software; you can redistribute it and/or
|   > modify it under the terms of the GNU General Public License
|   > as published by the Free Software Foundation; either version 2
|   > of the License, or (at your option) any later version.
|   >
|   > This program is distributed in the hope that it will be useful,
|   > but WITHOUT ANY WARRANTY; without even the implied warranty of
|   > MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
|   > GNU General Public License for more details.
|   >
|   > You should have received a copy of the GNU General Public License
|   > along with this program; if not, write to the Free Software
|   > Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
+---------------------------------------------------------------------------
|   Changes
|   =======-------
|   > If you wish to suggest or submit an update/change to the source
|   > code, post a pull request or issue on github and i will into it
|   > https://github.com/g2x3k/php-irc
|   >                                            maintained by g2x3k
+---------------------------------------------------------------------------
*/

class chat
{

    /* Chat specific Data */
    public $id;
    public $status;
    public $sockInt;
    public $isAdmin;
    public $timeConnected;
    public $verified;
    public $readQueue;
    public $floodQueue;
    public $floodQueueTime;
    public $port;
    public $type;
    public $nick;
    public $timeOutLevel;
    public $removed;
    public $connection;

    public $handShakeSent;
    public $handShakeTime;
    public $reverse;
    public $connectHost;

    /* Classes */
    private $dccClass;
    private $parserClass;
    private $ircClass;
    private $socketClass;
    private $timerClass;

    //class handler
    private $handler;

    /* Constructor */
    public function __construct($id, $nick, $admin, $sockInt, $host, $port, $handler, $reverse)
    {
        $this->id = $id;
        $this->handler = $handler;
        $this->nick = $nick;
        $this->isAdmin = $admin;
        $this->sockInt = $sockInt;
        $this->port = $port;
        $this->connectHost = $host;
        $this->reverse = $reverse;
        $this->handShakeSent = false;

        $this->sendQueue = array();
        $this->sendQueueCount = 0;
    }

    public function setIrcClass($class)
    {
        $this->ircClass = $class;
    }

    public function setDccClass($class)
    {
        $this->dccClass = $class;
    }

    public function setSocketClass($class)
    {
        $this->socketClass = $class;
    }

    public function setParserClass($class)
    {
        $this->parserClass = $class;
    }

    public function setTimerClass($class)
    {
        $this->timerClass = $class;
    }

    private function sendUserGreeting()
    {
        if ($this->verified == true) {
            return;
        }

        $this->dccSend("Welcome to " . $this->ircClass->getNick());
        $this->dccSend("PHP-iRC v" . VERSION . " [" . VERSION_DATE . "]");
        $time = $this->ircClass->timeFormat($this->ircClass->getRunTime(), "%d days, %h hrs, %m min, %s sec");
        $this->dccSend("running " . $time);
        $this->dccSend("You are currently in the dcc chat interface. Type 'help' to begin.");
    }

    private function sendAdminGreeting()
    {
        if ($this->verified == true) {
            return;
        }

        $this->dccSend("Welcome to " . $this->ircClass->getNick());
        $this->dccSend("PHP-iRC v" . VERSION . " [" . VERSION_DATE . "]");
        $time = $this->ircClass->timeFormat($this->ircClass->getRunTime(), "%d days, %h hrs, %m min, %s sec");
        $this->dccSend("running " . $time);
        $this->dccSend("Enter Your Password:");
    }

    public function dccSend($data, $to = null)
    {
        if ($this->status != DCC_CONNECTED) {
            return;
        }

        if ($to == null) {
            $to = $this;
        }

        $this->dccClass->dccSend($to, "--> " . $data . "\n");
    }

    public function dccSendRaw($data, $to = null)
    {
        if ($this->status != DCC_CONNECTED) {
            return;
        }

        if ($to == null) {
            $to = $this;
        }

        $this->dccClass->dccSend($to, $data);
    }


    public function disconnect($msg = "")
    {

        $msg = str_replace("\r", "", $msg);
        $msg = str_replace("\n", "", $msg);

        if (is_object($this->handler) && $this->status == DCC_CONNECTED) {
            $this->handler->disconnected($this);
        }

        $this->status = false;

        if ($msg != "") {
            $this->dccClass->dccInform("DCC: " . $this->nick . " closed DCC Chat (" . $msg . ")", $this);
            $this->ircClass->notice($this->nick, "DCC session ended: " . $msg, 1);
        } else {
            $this->dccClass->dccInform("DCC: " . $this->nick . " closed DCC Chat", $this);
        }

        $this->dccClass->disconnect($this);

        $this->connection = null;

        return true;
    }


    private function doHandShake()
    {
        $this->dccSendRaw("100 " . $this->ircClass->getNick() . "\n");
        $this->handShakeSent = true;
        $this->timerClass->addTimer(irc::randomHash(), $this, "handShakeTimeout", "", 8);
    }

    private function processHandShake()
    {
        if ($this->readQueue == "") {
            return;
        }

        $response = $this->readQueue;
        $this->readQueue = "";
        $responseArray = explode(chr(32), $response);
        if ($responseArray[0] == "101") {
            $this->reverse = false;
            $this->onConnect($this->connection);
            return;
        }

        $this->disconnect("DCC Client Server reported error on attempt to start chat");
    }

    public function handShakeTimeout()
    {
        if ($this->status != false) {
            if ($this->reverse == true) {
                $this->disconnect("DCC Reverse handshake timed out");
            }
        }
        return false;
    }

    /* Main events */
    public function onTimeout($conn)
    {
        $this->disconnect("Connection transfer timed out");
    }

    public function onDead($conn)
    {
        $this->disconnect($this->connection->getErrorMsg());
    }

    public function onRead($conn)
    {
        if ($this->socketClass->hasLine($this->sockInt)) {
            $this->readQueue .= $this->socketClass->getQueueLine($this->sockInt);
        }

        if ($this->status == DCC_CONNECTED) {
            if ($this->reverse != false) {
                if ($this->handShakeSent != false) {
                    $this->processHandShake();
                }
            } else {
                if ($this->readQueue != "") {
                    $this->parserClass->parseDcc($this, $this->handler);
                }
            }

        }

        if ($this->socketClass->hasLine($this->sockInt)) {
            return true;
        }
    }

    public function onWrite($conn)
    {
        //do nothing
    }

    public function onAccept($oldConn, $newConn)
    {
        $this->dccClass->accepted($oldConn, $newConn);
        $this->connection = $newConn;
        $oldConn->disconnect();
        $this->sockInt = $newConn->getSockInt();
        $this->onConnect($newConn);
    }

    public function onConnectTimeout($conn)
    {
        $this->disconnect("Connection attempt timed out");
    }

    public function onConnect($conn)
    {
        $this->status = DCC_CONNECTED;

        if ($this->reverse != false) {
            $this->dccClass->dccInform("DCC CHAT: " . $this->nick . " handling dcc server request");
            $this->doHandShake();
            return;
        }

        $this->dccClass->dccInform("DCC CHAT: " . $this->nick . " connection established");

        if ($this->handler === false || $this->handler == null) {
            if ($this->isAdmin == true) {
                $this->sendAdminGreeting();
            } else {
                $this->sendUserGreeting();
            }
        } else {

            if (is_object($this->handler)) {
                $this->handler->connected($this);
            }
        }

    }


    public function initialize()
    {

        $this->dccClass->dccInform("DCC: " . $this->nick . " is attempting to login");

        if ($this->status == DCC_LISTENING) {
            $this->ircClass->privMsg($this->nick, "\1DCC CHAT chat " . $this->ircClass->getClientIP(1) . " " . $this->port . "\1", 0);
            $this->ircClass->notice($this->nick, "DCC Chat (" . $this->ircClass->getClientIP(0) . ")", 0);
        }

        $this->timeConnected = time();
        $this->timeOutLevel = 0;
        $this->verified = 0;
        $this->readQueue = "";
        $this->floodQueue = "";
        $this->floodQueueTime = 0;
        $this->type = CHAT;
    }


}

?>
