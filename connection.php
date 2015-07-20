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
|   > connection class module
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

/* Connection class.  A Layer between dcc/irc class and socket class. */
/* To use, simply create an object, call the calls listed under the constructor in order. */

/* Damn I'm hungry... */

class connection
{

    //External Classes
    private $socketClass;
    private $ircClass;
    private $timerClass;

    //Internal variables
    private $callbackClass;
    private $host;
    private $port;
    private $connTimeout;
    private $transTimeout;
    private $sockInt;

    //Function specific
    private $connected;
    private $connStartTime;
    private $lastTransTime;

    //If this is set to true, this connection class will no longer function.
    private $error;
    private $errorMsg;

    //Called first
    function __construct($host, $port, $connTimeout)
    {
        $this->error = true;
        $this->errorMsg = "Connection not initialized";
        $this->host = $host;
        $this->port = $port;
        $this->connTimeout = $connTimeout;
        $this->transTimeout = 0;
        $this->connected = false;
        $this->sockInt = false;
    }

    //Called second
    public function setSocketClass($class)
    {
        $this->socketClass = $class;
    }

    //Called third
    public function setIrcClass($class)
    {
        $this->ircClass = $class;
    }

    //Called fourth
    public function setCallbackClass($class)
    {
        $this->callbackClass = $class;
    }

    //Called fifth
    public function setTimerClass($class)
    {
        $this->timerClass = $class;
    }

    //Called sixth
    public function init()
    {
        $this->error = false;

        if ($this->host != null) {
            if ($this->connTimeout <= 0) {
                $this->setError("Must set connection timeout > 0 for non-listening sockets");
                return;
            }
        } else {
            if ($this->connTimeout < 0) {
                $this->setError("Must set connection timeout >= 0 for listening sockets");
                return;
            }
        }

        if (!is_object($this->callbackClass)) {
            $this->setError("Specified callback class is not an object");
            return;
        }

        if (!is_object($this->socketClass)) {
            $this->setError("Specified socket class is not an object");
            return;
        }

        if (!is_object($this->ircClass)) {
            $this->setError("Specified irc class is not an object");
            return;
        }

        $sockInt = $this->socketClass->addSocket($this->host, $this->port); // add socket
        if ($sockInt == false) {
            $this->setError("Could not create socket");
            return;
        }

        $sockData = $this->socketClass->getSockData($sockInt);

        $this->socketClass->setHandler($sockInt, $this->ircClass, $this, "handle");

        //Set internal variables
        if ($this->port == NULL) {
            $this->port = $sockData->port;
        }
        $this->sockInt = $sockInt;

        return $this->port;
    }

    public function bind($ip)
    {
        if ($this->error != false || $this->sockInt == false) {
            return;
        }

        if ($this->connected == true) {
            return;
        }

        $this->socketClass->bindIP($this->sockInt, $ip);
    }

    //Called to listen, only called by onAccept() function in this class
    public function listen()
    {
        $this->error = false;
        $this->connected = true;
    }

    //Called last, and only to start connection to another server
    public function connect()
    {
        if ($this->error == true) {
            return false;
        }

        if ($this->connTimeout > 0) {
            $this->timerClass->addTimer(irc::randomHash(), $this, "connTimeout", "", $this->connTimeout);
        }

        $this->timerClass->addTimer(irc::randomHash(), $this->socketClass, "connectSocketTimer", $this->sockInt, 1);

        /* $this->socketClass->beginConnect($this->sockInt); */
        $this->connStartTime = time();
    }

    public function disconnect()
    {
        unset($this->callbackClass);
        $this->socketClass->killSocket($this->sockInt);
        $this->socketClass->removeSocket($this->sockInt);
        $this->setError("Disconnected from server");
    }

    public function getSockInt()
    {
        return $this->sockInt;
    }

    public function setSockInt($sockInt)
    {
        $this->sockInt = $sockInt;
    }

    public function setTransTimeout($time)
    {
        $this->transTimeout = ($time < 0 ? 0 : $time);
    }

    /* Timers */

    public function connTimeout()
    {
        if ($this->connected == false) {
            $this->handle(CONN_CONNECT_TIMEOUT);
        }
    }

    public function transTimeout()
    {
        if ($this->error == true) {
            return false;
        }

        if ($this->connected == false) {
            return true;
        }

        if ($this->transTimeout > 0) {
            if (time() > $this->transTimeout + $this->lastTransTime) {
                $this->handle(CONN_TRANSFER_TIMEOUT);
            }
        }

        return true;
    }

    //handle function, handles all calls from socket class, and calls appropriate
    //functions in the callback class
    public function handle($msg)
    {

        if ($this->socketClass->getSockStatus($this->sockInt) === false) {
            return false;
        }

        $stat = false;

        if ($this->error == true) {
            return false;
        }

        switch ($msg) {
            case CONN_CONNECT:
                $stat = $this->onConnect();
                break;
            case CONN_READ:
                $stat = $this->onRead();
                break;
            case CONN_WRITE:
                $stat = $this->onWrite();
                break;
            case CONN_ACCEPT:
                $stat = $this->onAccept();
                break;
            case CONN_DEAD:
                $stat = $this->onDead();
                break;
            case CONN_TRANSFER_TIMEOUT:
                $stat = $this->onTransferTimeout();
                break;
            case CONN_CONNECT_TIMEOUT:
                $stat = $this->onConnectTimeout();
                break;
            default:
                return false;
                break;
        }

        return $stat;
    }

    /* Specific handling functions */

    private function onTransferTimeout()
    {
        $this->callbackClass->onTransferTimeout($this);
    }

    private function onConnectTimeout()
    {
        $this->callbackClass->onConnectTimeout($this);
    }

    private function onConnect()
    {
        $this->connected = true;

        if ($this->transTimeout > 0) {
            $this->timerClass->addTimer(irc::randomHash(), $this, "transTimeout", "", $this->transTimeout);
        }

        $this->callbackClass->onConnect($this);
        return false;
    }

    //For this function, true can be returned from onRead() to input more data.
    private function onRead()
    {
        $this->lastTransTime = time();

        $stat = $this->callbackClass->onRead($this);
        if ($stat !== true) {
            $this->socketClass->clearReadSchedule($this->sockInt);
        }

        return $stat;
    }

    private function onWrite()
    {
        $this->socketClass->clearWriteSchedule($this->sockInt);
        $this->lastTransTime = time();
        $this->callbackClass->onWrite($this);
        return false;
    }

    private function onAccept()
    {
        //Get the sockInt from the socketClass
        $newSockInt = $this->socketClass->hasAccepted($this->sockInt);

        if ($newSockInt === false) {
            //False alarm.. just ignore it.
            return false;
        }

        //Create a new connection object for the new socket connection, then return it to onAccept
        //We must assume that onAccept will handle the connection object.  Otherwise it'll die, and the
        //connection will be orphaned.

        $newConn = new connection(null, null, 0);
        $newConn->setSocketClass($this->socketClass);
        $newConn->setIrcClass($this->ircClass);
        $newConn->setCallbackClass($this->callbackClass); //Can be overwritten in the onAccept function
        $newConn->setTimerClass($this->timerClass);
        $newConn->listen();
        //We don't need to call init(), we're already setup.

        //Setup our connection transmission timeout thing.
        if ($this->transTimeout > 0) {
            $this->timerClass->addTimer(irc::randomHash(), $newConn, "transTimeout", "", $this->transTimeout);
        }

        $newConn->setTransTimeout($this->transTimeout);

        //Set handler for our new sockInt to new connection class
        $this->socketClass->setHandler($newSockInt, $this->ircClass, $newConn, "handle");

        //Set our new sockInt
        $newConn->setSockInt($newSockInt);

        //Call our callback function for accepting with old object, and new object.
        $this->callbackClass->onAccept($this, $newConn);
        return false;
    }

    private function onDead()
    {
        if ($this->connected == true) {
            $this->setError("Connection is dead");
        } else {
            $this->setError("Could not connect: " . $this->socketClass->getSockStringError($this->sockInt));
        }
        $this->callbackClass->onDead($this);
        return false;
    }

    /* Error handling routines */

    private function setError($msg)
    {
        $this->error = true;
        $this->errorMsg = $msg;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

}

?>
