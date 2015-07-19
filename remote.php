<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC v2.2.1 Service Release
|   ========================================================
|   by Manick
|   (c) 2001-2005 by http://www.phpbots.org/
|   Contact: manick@manekian.com
|   irc: #manekian@irc.rizon.net
|   ========================================
+---------------------------------------------------------------------------
|   > remote class module
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
|   > code, email me at manick@manekian.com with the change, and I
|   > will look to adding it in as soon as I can.
+---------------------------------------------------------------------------
*/

/* Remote, a class to handle addQuery connection from ircClass */

class remote
{

    //External Classes
    private $socketClass;
    private $ircClass;
    private $timerClass;

    //Internal variables
    private $host;
    private $port;
    private $query;
    private $line;
    private $class;
    private $function;
    private $connTimeout;
    private $transTimeout;
    private $sockInt;
    private $connection;
    private $connected;

    //Output internal variables
    private $response;
    private $type;

    function __construct($host, $port, $query, $line, $class, $function, $transTimeout)
    {
        $this->host = $host;
        $this->port = $port;
        $this->query = $query;
        $this->line = $line;
        $this->class = $class;
        $this->function = $function;
        $this->transTimeout = $transTimeout;
        $this->response = "";
        $this->connected = false;
        $this->type = QUERY_SUCCESS;
    }

    public function setSocketClass($class)
    {
        $this->socketClass = $class;
    }

    public function setIrcClass($class)
    {
        $this->ircClass = $class;
    }

    public function setTimerClass($class)
    {
        $this->timerClass = $class;
    }

    public function connect()
    {
        if ($this->host == null || $this->port == null) {
            return false;
        }

        if (!is_object($this->socketClass)) {
            return false;
        }

        if (!is_object($this->ircClass)) {
            return false;
        }

        $conn = new connection($this->host, $this->port, CONNECT_TIMEOUT);

        $conn->setSocketClass($this->socketClass);
        $conn->setIrcClass($this->ircClass);
        $conn->setCallbackClass($this);
        $conn->setTimerClass($this->timerClass);

        /* Set Timeouts */
        $conn->setTransTimeout($this->transTimeout);

        $conn->init();

        if ($conn->getError()) {
            $this->setError("Could not allocate socket");
            return false;
        }

        $this->sockInt = $conn->getSockInt();
        $conn->connect();

        $this->connection = $conn;

        return true;
    }

    public function disconnect()
    {
        $this->connection->disconnect();
        $this->setError("Manual disconnect");
    }

    /* Specific handling functions */

    public function onTransferTimeout($conn)
    {
        $this->connection->disconnect();
        $this->setError("The connection timed out");
    }

    public function onConnectTimeout($conn)
    {
        $this->connection->disconnect();
        $this->setError("Connection attempt timed out");
    }

    public function onConnect($conn)
    {
        $this->connected = true;
        $this->socketClass->sendSocket($this->sockInt, $this->query);
    }

    public function onRead($conn)
    {
        $this->response .= $this->socketClass->getQueue($this->sockInt);
    }

    public function onWrite($conn)
    {
        // do nothing, we really don't care about this
    }

    public function onDead($conn)
    {
        $this->connection->disconnect();

        if ($this->connected === true) {
            $this->doCallback();
        } else {
            $this->setError($this->connection->getErrorMsg());
        }

    }

    /* Error handling */

    private function setError($msg)
    {
        $this->response = $msg;
        $this->type = QUERY_ERROR;
        $this->doCallback();
    }

    private function doCallback()
    {
        if ($this->line != null && is_array($this->line) && isset($this->line['text'])) {
            $lineArgs = parser::createLine($this->line['text']);
        } else {
            $lineArgs = array();
        }

        $func = $this->function;
        $this->class->$func($this->line, $lineArgs, $this->type, $this->response);
    }

}

?>
