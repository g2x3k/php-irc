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
|   > socket class module
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

class socket
{

    private $rawSockets; //array of raw sockets to be used by select
    private $socketInfo; //index by intval($rawSockets[socket])
    private $numSockets; //number of sockets currently in use
    private $writeSocks; //sockets that have write buffers queued
    private $numWriteSocks;

    private $readQueueSize = 0;

    private $tcpRangeStart = 1025;
    private $timeoutSeconds = 0;
    private $timeoutMicroSeconds = 0;

    private $myTimeout = 0;

    private $procQueue;

    public function __construct()
    {
        $this->connectSockets = array();
        $this->rawSockets = array();
        $this->socketInfo = array();
        $this->writeSocks = array();
        $this->readQueueSize = 0;
        $this->numSockets = 0;
        $this->numWriteSocks = 0;
    }

    public function setProcQueue($class)
    {
        $this->procQueue = $class;
    }

    public function getNumSockets()
    {
        return $this->numSockets;
    }

    public function setTcpRange($range)
    {
        if (intval($range) != 0) {
            $this->tcpRangeStart = $range;
        }
    }

    public function getHost($sockInt)
    {
        $status = socket_getsockname($this->socketInfo[$sockInt]->socket, $addr);

        if ($status == false) {
            return false;
        }

        return $addr;

    }

    public function getRemoteHost($sockInt)
    {
        $status = socket_getpeername($this->socketInfo[$sockInt]->socket, $addr);

        if ($status == false) {
            return false;
        }

        return $addr;

    }

    public function setTimeout($time)
    {
        $sec = intval($time);
        $msec = intval(($time - $sec) * 1e6);

        if ($sec == 0) {
            $msec = $msec < $this->myTimeout ? $this->myTimeout : $msec;
        }

        if ($sec < $this->timeoutSeconds) {
            $this->timeoutSeconds = $sec;
            $this->timeoutMicroSeconds = $msec;
        } else if ($sec == $this->timeoutSeconds) {
            if ($msec < $this->timeoutMicroSeconds) {
                $this->timeoutMicroSeconds = $msec;
            }
        }
    }

    public function setHandler($sockInt, $owner, $class, $function)
    {
        if (!isset($this->socketInfo[$sockInt])) {
            return false;
        }

        $sock = $this->socketInfo[$sockInt];

        $sock->owner = $owner;
        $sock->class = $class;
        $sock->func = $function;

        return true;
    }

    /* For debug... */
    public function showSocks($read, $write)
    {
        echo "\n\nRead:\n";
        if (is_array($read)) {
            foreach ($read AS $sock) {
                echo $sock . "\n";
            }
        }
        echo "\nWrite:\n";
        if (is_array($write)) {
            foreach ($write AS $sock) {
                echo $sock . "\n";
            }
        }
        echo "\n";
    }

    public function handle()
    {
        //For debug
        //echo "Read: " . $this->readQueueSize . " Write: " . $this->writeQueueSize . "\n";
        //echo "timeout: " . $this->timeoutSeconds . "-" . $this->timeoutMicroSeconds . "\n";

        if ($this->numSockets < 1) {
            if ($this->timeoutSeconds > 0) {
                sleep($this->timeoutSeconds);
            }

            if ($this->timeoutMicroSeconds > 0) {
                usleep($this->timeoutMicroSeconds);
            }

            $this->timeoutSeconds = 1000;
            return;
        }

        if ($this->numSockets < 1) {
            $sockArray = NULL;
            $except = NULL;
        } else {
            $sockArray = $this->rawSockets;
            $except = $this->rawSockets;
        }

        if ($this->numWriteSocks < 1) {
            $writeArray = NULL;
        } else {
            $writeArray = $this->writeSocks;
        }

        //For debug
        //$this->showSocks($sockArray, $writeArray);

        $newData = socket_select($sockArray, $writeArray, $except, $this->timeoutSeconds, $this->timeoutMicroSeconds);

        $this->timeoutSeconds = 1000;

        if ($newData === false) {
            die("socket_select error"); // need to change this to handle errors
            return;
        }

        if (!$newData) {
            return;
        }

        if (count($sockArray) != 0) {
            foreach ($sockArray AS $socket) {
                $sockIntval = intval($socket);

                switch ($this->socketInfo[$sockIntval]->status) {
                    case SOCK_CONNECTED:
                        $this->readSocket($sockIntval);
                        break;
                    case SOCK_LISTENING:
                        $this->acceptSocket($sockIntval);
                        break;
                    case SOCK_CONNECTING:
                        $this->connectSocket($sockIntval);
                        break;
                    default:
                        break;
                }
            }

        }

        if (count($writeArray) != 0) {
            foreach ($writeArray AS $socket) {
                $sockIntval = intval($socket);
                $this->sendSocketQueue($sockIntval, 1);
            }
        }

        if (count($except) != 0) {
            foreach ($except AS $socket) {
                $sockIntval = intval($socket);
                $this->markDead($sockIntval);
            }
        }

    }


    private function callBack($sockIntval, $msg)
    {
        //Schedule the callback to run
        if ($this->socketInfo[$sockIntval]->func != "" &&
            $this->socketInfo[$sockIntval]->func != null
        ) {
            $this->procQueue->addQueue($this->socketInfo[$sockIntval]->owner,
                $this->socketInfo[$sockIntval]->class,
                $this->socketInfo[$sockIntval]->func,
                $msg,
                .01);
        }
    }

    private function readSocket($sockIntval)
    {

        if ($this->isDead($sockIntval)) {
            return;
        }

        if ($this->socketInfo[$sockIntval]->status != SOCK_CONNECTED) {
            return;
        }

        $dataRead = false;

        $socket = $this->socketInfo[$sockIntval]->socket;
        //Read in 4096*30 bytes

        for ($i = 0; $i < 30; $i++) {
            $response = @socket_read($socket, 8192, PHP_BINARY_READ);

            $respLength = strlen($response);

            if ($response === false) {
                $err = socket_last_error($this->socketInfo[$sockIntval]->socket);

                if ($err != EALREADY && $err != EAGAIN && $err != EINPROGRESS) {
                    $this->markDead($sockIntval);
                }
                break;
            } else if ($respLength === 0) {
                if ($i == 0) {
                    $this->markDead($sockIntval);
                }
                break;
            }

            $dataRead = true;

            $this->readQueueSize += $respLength;
            $this->socketInfo[$sockIntval]->readLength += $respLength;
            $this->socketInfo[$sockIntval]->readQueue .= $response;

        }

        if ($dataRead == true) {
            if ($this->socketInfo[$sockIntval]->readScheduled == false) {
                $this->callBack($sockIntval, CONN_READ);
                $this->socketInfo[$sockIntval]->readScheduled = true;
            }
        }
    }


    private function sendSocketQueue($sockIntval, $queued = 0)
    {
        $socket = $this->socketInfo[$sockIntval]->socket;

        if ($this->isDead($sockIntval)) {
            return;
        }

        if (($bytesWritten = @socket_write($socket, $this->socketInfo[$sockIntval]->writeQueue)) === false) {

            $socketError = socket_last_error($socket);

            switch ($socketError) {
                case EAGAIN:
                case EALREADY:
                case EINPROGRESS:
                    break;
                default:
                    $this->markDead($sockIntval);
                    break;
            }

        } else {
            $this->socketInfo[$sockIntval]->writeQueue = substr($this->socketInfo[$sockIntval]->writeQueue, $bytesWritten);
            $this->socketInfo[$sockIntval]->writeLength -= $bytesWritten;

            //Queue Empty, Remove socket from write
            if ($this->socketInfo[$sockIntval]->writeLength == 0 && $queued == 1) {
                $this->removeWriteSocketFromArray($sockIntval);
            }

            if ($this->socketInfo[$sockIntval]->writeLength == 0) {
                unset($this->socketInfo[$sockIntval]->writeQueue);
                $this->socketInfo[$sockIntval]->writeQueue = "";
            }

            //Callback after we wrote to socket

            if ($this->socketInfo[$sockIntval]->writeScheduled == false) {
                $this->callBack($sockIntval, CONN_WRITE);
                $this->socketInfo[$sockIntval]->writeScheduled = true;
            }

        }

    }

    private function createSocket()
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, 0);

        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        if ($socket == false) {
            return false;
        }

        socket_clear_error($socket);

        if (socket_set_nonblock($socket) == false) {
            @socket_close($socket);
            return false;
        }

        return $socket;
    }

    public function bindIP($sockInt, $ip)
    {
        if (!isset($this->socketInfo[$sockInt])) {
            return;
        }

        $sockData = $this->socketInfo[$sockInt];

        if ($sockData->status != SOCK_CONNECTING) {
            return;
        }

        socket_bind($sockData->socket, $ip);
    }

    public function addSocket($host, $port)
    {
        $listening = false;

        if ($host == null) {
            $host = false;
            $listening = true;
        }

        $socket = $this->createSocket();

        if ($socket == false) {
            return false;
        }

        if ($listening == true) {
            $boundError = false;
            $currentPort = $this->tcpRangeStart;

            if ($port !== null) {
                $boundError = @socket_bind($socket, 0, $port);
                if ($boundError === false) {
                    return false;
                }
            } else {
                while ($boundError === false) {
                    $boundError = @socket_bind($socket, 0, $currentPort);
                    $currentPort++;

                    if ($currentPort > $this->tcpRangeStart + HIGHEST_PORT) {
                        return false;
                    }
                }

                $port = $currentPort - 1;
            }


            if (socket_listen($socket) === false) {
                return false;
            }


            if (DEBUG == 1) {
                echo "Socket Listening: " . intval($socket) . "\n";
            }


        }
        else {
            if (DEBUG == 1) {
                echo "Socket Opened: " . intval($socket) . "\n";
            }
        }

        $newSock = new socketInfo;

        $newSock->socket = $socket;
        $newSock->owner = null;
        $newSock->class = null;
        $newSock->func = null;
        $newSock->readQueue = "";
        $newSock->writeQueue = "";
        $newSock->readLength = 0;
        $newSock->writeLength = 0;
        $newSock->host = $host;
        $newSock->port = $port;
        $newSock->newSockInt = array();
        $newSock->readScheduled = false;
        $newSock->writeScheduled = false;

        $this->socketInfo[intval($socket)] = $newSock;

        if ($listening == true) {
            $newSock->status = SOCK_LISTENING;
        } else {
            $newSock->status = SOCK_CONNECTING;
        }

        $this->numSockets++;
        $this->rawSockets[] = $socket;

        return intval($socket);
    }

    public function clearReadSchedule($sockInt)
    {
        if (!isset($this->socketInfo[$sockInt])) {
            return false;
        }

        $this->socketInfo[$sockInt]->readScheduled = false;

        return true;
    }

    public function clearWriteSchedule($sockInt)
    {
        if (!isset($this->socketInfo[$sockInt])) {
            return false;
        }

        $this->socketInfo[$sockInt]->writeScheduled = false;

        return true;
    }

    /*
        public function beginConnect($sockInt)
        {
            $this->procQueue->addQueue(null, $this, "connectSocketProcess", $sockInt, 0);
        }
    */

    //process to connect the socket $sockInt
    public function connectSocketTimer($sockInt)
    {
        if (!isset($this->socketInfo[$sockInt])) {
            return false;
        }

        if ($this->socketInfo[$sockInt]->status != SOCK_CONNECTING) {
            return false;
        }

        $this->connectSocket($sockInt);

        return true;
    }


    //Remove all sockets from a specific irc bot
    public function removeOwner($class)
    {
        foreach ($this->socketInfo AS $sockInt => $data) {
            if ($class === $data->owner) {
                $this->killSocket($sockInt);
                $this->removeSocket($sockInt);
            }
        }
    }

    public function killSocket($sockInt)
    {
        if (DEBUG == 1) {
            echo "Killing socket: " . $sockInt . "\n";
        }

        if ($this->socketInfo[$sockInt]->status == SOCK_ACCEPTED) {
            $this->acceptedSockets--;
        }

        if ($this->socketInfo[$sockInt]->status != SOCK_DEAD) {
            $this->removeReadSocketFromArray($sockInt);
            $this->removeWriteSocketFromArray($sockInt);
            $this->socketInfo[$sockInt]->status = SOCK_DEAD;
        }

        if (is_resource($this->socketInfo[$sockInt]->socket)) {
            if (DEBUG == 1) {
                echo "Closed socket: " . $sockInt . "\n";
            }

            socket_clear_error($this->socketInfo[$sockInt]->socket);
            socket_close($this->socketInfo[$sockInt]->socket);
        } else {
            if (DEBUG == 1) {
                echo "Socket already closed: " . $sockInt . "\n";
            }
        }
    }

    public function removeSocket($socketIntval)
    {

        $this->readQueueSize -= $this->socketInfo[$socketIntval]->readLength;

        unset($this->socketInfo[$socketIntval]->class);
        unset($this->socketInfo[$socketIntval]->owner);
        unset($this->socketInfo[$socketIntval]);
    }

    private function removeReadSocketFromArray($socketIntval)
    {
        foreach ($this->rawSockets AS $index => $socket) {
            if ($socket === $this->socketInfo[$socketIntval]->socket) {
                unset($this->rawSockets[$index]);
                $this->numSockets--;
                break;
            }
        }
    }

    private function removeWriteSocketFromArray($socketIntval)
    {

        foreach ($this->writeSocks AS $index => $rawSocket) {
            if ($rawSocket === $this->socketInfo[$socketIntval]->socket) {
                unset($this->writeSocks[$index]);
                $this->numWriteSocks--;
                break;
            }
        }
    }

    public function sendSocket($sockInt, $data)
    {
        if ($this->isDead($sockInt)) {
            return;
        }

        if ($this->socketInfo[$sockInt]->status != SOCK_CONNECTED) {
            return;
        }

        $inQueue = $this->socketInfo[$sockInt]->writeLength > 0 ? true : false;

        $len = strlen($data);
        $this->socketInfo[$sockInt]->writeQueue .= $data;
        $this->socketInfo[$sockInt]->writeLength += $len;

        if (!$inQueue) {
            $this->sendSocketQueue($sockInt, 0);

            if ($this->socketInfo[$sockInt]->status == SOCK_CONNECTED) {
                if ($this->socketInfo[$sockInt]->writeLength > 0) {
                    $this->writeSocks[] = $this->socketInfo[$sockInt]->socket;
                    $this->numWriteSocks++;
                }
            }
        }

        return $len;
    }

    private function acceptSocket($sockInt)
    {
        $sockData = $this->socketInfo[$sockInt];

        $newSock = @socket_accept($sockData->socket);
        socket_set_nonblock($newSock);

        if ($newSock === false) {
            return false;
        }

        $newSockInt = intval($newSock);

        if (DEBUG == 1) {
            echo "Accepted new connection on: " . $newSockInt . "\n";
        }


        $this->socketInfo[$newSockInt] = clone $sockData;
        $this->socketInfo[$newSockInt]->socket = $newSock;
        $this->socketInfo[$newSockInt]->status = SOCK_ACCEPTING; /* fix a onRead done before onAccept */

        $this->numSockets++;
        $this->rawSockets[] = $newSock;

        $this->socketInfo[$sockInt]->newSockInt[] = $newSockInt;

        //Schedule the callback to run
        $this->callBack($sockInt, CONN_ACCEPT);

        return true;
    }

    private function connectSocket($sockInt)
    {
        if (!isset($this->socketInfo[$sockInt])) {
            return;
        }

        if ($this->socketInfo[$sockInt]->status == SOCK_CONNECTED) {
            return;
        }

        if (@socket_connect($this->socketInfo[$sockInt]->socket,
                $this->socketInfo[$sockInt]->host,
                $this->socketInfo[$sockInt]->port) === true
        ) {
            $this->socketInfo[$sockInt]->status = SOCK_CONNECTED;
            $this->callBack($sockInt, CONN_CONNECT);
        } else {
            $socketError = socket_last_error($this->socketInfo[$sockInt]->socket);

            switch ($socketError) {
                case 10022:
                    if (OS != 'windows') {
                        $this->markDead($sockInt);
                    }
                    break;
                case EISCONN:
                    $this->socketInfo[$sockInt]->status = SOCK_CONNECTED;
                    $this->callBack($sockInt, CONN_CONNECT);
                    break;
                case EAGAIN:
                case EALREADY:
                case EINPROGRESS:
                    break;
                default:
                    $this->markDead($sockInt);
                    break;
            }
        }

        return;
    }

    public function getSockStatus($sockInt)
    {
        return (isset($this->socketInfo[$sockInt]) ? $this->socketInfo[$sockInt]->status : false);
    }

    public function getSockData($sockInt)
    {
        return (isset($this->socketInfo[$sockInt]) ? $this->socketInfo[$sockInt] : false);
    }

    public function alterSocket($sockInt, $level, $opt, $val)
    {
        return socket_set_option($this->socketInfo[$sockInt]->socket, $level, $opt, $val);
    }

    public function getSockError($sockInt)
    {
        return socket_last_error($this->socketInfo[$sockInt]->socket);
    }

    public function getSockStringError($sockInt)
    {
        $strErr = "[" . self::getSockError($sockInt) . "]:" . socket_strerror(socket_last_error($this->socketInfo[$sockInt]->socket));
        $strErr = str_replace("\n", "", $strErr);
        return $strErr;
    }

    public function hasAccepted($sockInt)
    {
        if (!isset($this->socketInfo[$sockInt])) {
            return false;
        }

        $newSockInt = array_shift($this->socketInfo[$sockInt]->newSockInt);

        $this->socketInfo[$newSockInt]->status = SOCK_CONNECTED;

        return $newSockInt;
    }

    private function markDead($sockInt)
    {
        if (DEBUG == 1) {
            echo "Marking socket dead: " . $sockInt . "\n";
        }

        $this->removeReadSocketFromArray($sockInt);
        $this->removeWriteSocketFromArray($sockInt);
        $this->socketInfo[$sockInt]->status = SOCK_DEAD;
        $this->callBack($sockInt, CONN_DEAD);
    }

    public function isDead($sockInt)
    {
        $socket = $this->socketInfo[$sockInt]->socket;

        if (!is_resource($socket)) {
            $this->markDead($sockIntval);
            return true;
        }

        if (!isset($this->socketInfo[$sockInt])) {
            return true;
        }

        switch ($this->socketInfo[$sockInt]->status) {
            case SOCK_DEAD:
                return true;
                break;
            default:
                return false;
                break;
        }
    }

    public function hasWriteQueue($sockInt)
    {
        if ($this->socketInfo[$sockInt]->writeLength > 0) {
            return $this->socketInfo[$sockInt]->writeLength;
        } else {
            return false;
        }
    }

    public function getQueue($sockInt)
    {
        $this->readQueueSize -= $this->socketInfo[$sockInt]->readLength;
        $queue = $this->socketInfo[$sockInt]->readQueue;
        unset($this->socketInfo[$sockInt]->readQueue);
        $this->socketInfo[$sockInt]->readQueue = "";
        $this->socketInfo[$sockInt]->readLength = 0;
        return $queue;
    }

    public function hasQueue($sockInt)
    {
        if ($this->socketInfo[$sockInt]->readLength > 0) {
            return true;
        }

        return false;
    }


    public function hasLine($sockInt)
    {
        if (strpos($this->socketInfo[$sockInt]->readQueue, "\n") !== false) {
            return true;
        }
        return false;
    }

    public function getQueueLine($sockInt)
    {
        $readQueue =& $this->socketInfo[$sockInt]->readQueue;

        if (!$this->hasLine($sockInt)) {
            return false;
        }

        $crlf = "\r\n";
        $crlfLen = 2;

        $lineEnds = strpos($readQueue, $crlf);

        if ($lineEnds === false) {
            $crlf = "\n";
            $crlfLen = 1;
            $lineEnds = strpos($readQueue, $crlf);
        }

        $line = substr($readQueue, 0, $lineEnds);
        $readQueue = substr($readQueue, $lineEnds + $crlfLen);

        $this->readQueueSize -= ($lineEnds + $crlfLen);
        $this->socketInfo[$sockInt]->readLength -= ($lineEnds + $crlfLen);

        if ($readQueue == "") {
            unset($this->socketInfo[$sockInt]->readQueue);
            $this->socketInfo[$sockInt]->readQueue = "";
        }

        return $line;
    }


    /* Misc HTTP Functions */

    public static function generatePostQuery($query, $host, $path, $httpVersion = "1.0")
    {
        if ($query != "" && substr($query, 0, 1) != "?") {
            $query = "?" . $query;
        }

        if ($path == "") {
            $path = "/";
        }

        $postQuery = "POST " . $path . " HTTP/" . $httpVersion . "\r\n";
        $postQuery .= "Host: " . $host . "\r\n";
        $postQuery .= "Content-type: application/x-www-form-urlencoded\r\n";
        $postQuery .= "Content-length: " . strlen($query) . "\r\n\r\n";
        $postQuery .= $query;

        return $postQuery;
    }

    public static function generateGetQuery($query, $host, $path, $httpVersion = "1.0")
    {
        if ($path == "") {
            $path = "/";
        }

        if ($query != "" && substr($query, 0, 1) != "?") {
            $query = "?" . $query;
        }

        $getQuery = "GET " . $path . $query . " HTTP/" . $httpVersion . "\r\n";
        $getQuery .= "Host: " . $host . "\r\n";
        $getQuery .= "Connection: close\r\n";
        $getQuery .= "\r\n";

        return $getQuery;
    }

}

?>
