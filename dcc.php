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
|   > code, post a pull request or issue on github and i will into it
|   > https://github.com/g2x3k/php-irc
|   >                                            maintained by g2x3k
+---------------------------------------------------------------------------
*/

class dcc
{

    private $dccList = array();            //holds all connected sockets
    private $dccChangedList = array();
    private $dccChangedCount = 0;

    private $fileDlCount = 0;
    private $fileUlCount = 0;
    private $chatCount = 0;

    //Classes
    private $timerClass;
    private $socketClass;
    private $ircClass;
    private $parserClass;

    //Bytes Transferred
    private $bytesUp = 0;
    private $bytesDown = 0;

    //Process Queue
    private $procQueue;

    public function __construct()
    {
        // do nothing...
    }

    public function setProcQueue($class)
    {
        $this->procQueue = $class;
    }

    public function setIrcClass($class)
    {
        $this->ircClass = $class;
    }

    public function setParserClass($class)
    {
        $this->parserClass = $class;
        $this->parserClass->setDccClass($this);
    }

    public function setSocketClass($class)
    {
        $this->socketClass = $class;
    }

    public function setTimerClass($class)
    {
        $this->timerClass = $class;
    }

    public function getDownloadCount()
    {
        return $this->fileDlCount;
    }

    public function getUploadCount()
    {
        return $this->fileUlCount;
    }

    public function getChatCount()
    {
        return $this->chatCount;
    }

    public function closeAll()
    {
        foreach ($this->dccList AS $dcc) {
            $dcc->disconnect("Owner Requsted Close");
        }
    }

    public function addBytesUp($num)
    {
        $this->bytesUp += $num;
    }

    public function addBytesDown($num)
    {
        $this->bytesDown += $num;
    }

    public function getBytesDown()
    {
        return $this->bytesDown;
    }

    public function getBytesUp()
    {
        return $this->bytesUp;
    }


    public function getDcc($someDcc)
    {
        if (isset($this->dccList[$someDcc])) {
            return $this->dccList[$someDcc];
        }

        return false;
    }


    public function dccInform($data, $from = null)
    {
        foreach ($this->dccList AS $dcc) {
            if ($dcc->type == CHAT && $dcc->verified == true && $dcc->isAdmin == true) {
                if ($from != null) {
                    if ($dcc->sockInt != $from->sockInt) {
                        $dcc->dccSend($data);
                    }
                } else {
                    $dcc->dccSend($data);
                }

            }

        }
    }

    //Works in conjunction with $oldConn->connected
    public function accepted($oldConn, $newConn)
    {
        $sockInt = $oldConn->getSockInt();
        $hasAccepted = $newConn->getSockInt();

        $dcc = $this->dccList[$sockInt];
        $this->dccList[$hasAccepted] = $dcc;
        $dcc->status = DCC_CONNECTED;
        $dcc->sockInt = $hasAccepted;
        unset($this->dccList[$sockInt]);
    }

    //CheckDccTimeout function
    public function checkDccTimeout($dcc)
    {
        if (!is_object($dcc) || $dcc->removed == true) {
            return false;
        }

        if ($dcc->status != DCC_LISTENING) {
            return false;
        }

        switch ($dcc->timeOutLevel) {
            case 0:
                $dcc->timeOutLevel++;
                break;
            case 1:
                $dcc->timeOutLevel++;
                $this->ircClass->notice($dcc->nick, "You have a DCC session pending.  Set your client to connect.  60 seconds before timeout.", 1);
                break;
            case 2:
                $dcc->timeOutLevel++;
                $this->ircClass->notice($dcc->nick, "You have a DCC session pending.  Set your client to connect.  30 seconds before timeout.", 1);
                break;
            case 3:
                $dcc->timeOutLevel = 0;
                $dcc->disconnect("DCC Session timed out (90 Seconds)");
                return false;
                break;
            default:
                break;
        }

        return true;

    }

    public function getDccList()
    {
        return $this->dccList;
    }

    private function removeDcc($dcc)
    {
        $sockInt = $dcc->sockInt;
        unset($this->dccList[$sockInt]);
    }

    public function dccSend($to, $data)
    {
        if (($len = $this->socketClass->sendSocket($to->sockInt, $data)) === false) {
            $to->disconnect();
        }
        return $len;
    }


    private function highestId()
    {
        $highest = 0;

        foreach ($this->dccList AS $index => $dcc) {
            $highest = ($dcc->id > $highest ? $dcc->id : $highest);
        }

        return $highest + 1;
    }


    public function addChat($nick, $host, $port, $admin, $handler, $fromTimer = false)
    {
        $lnick = irc::myStrToLower($nick);

        foreach ($this->dccList AS $index => $dcc) {
            if ($dcc->type == CHAT) {
                if (irc::myStrToLower($dcc->nick) == $lnick) {
                    $dcc->disconnect();
                    break;
                }
            }
        }

        $reverse = false;

        if ($this->ircClass->getClientConf("mircdccreverse") != "" && $fromTimer == false) {
            $port = intval($this->ircClass->getClientConf("mircdccreverse"));
            if ($port == 0) {
                return NULL;
            }

            $args = new argClass;

            $args->arg1 = $nick;
            $args->arg2 = $host;
            $args->arg3 = $port;
            $args->arg4 = $admin;
            $args->arg5 = $handler;
            $args->arg7 = time();
            $args->arg8 = CHAT;

            $this->ircClass->notice($nick, "DCC: NOTICE: This server is using the mIRC Chat Server Protocol.  Please use ' /dccserver +c on " . $this->ircClass->getClientConf("mircdccreverse") . " ' to chat with me!  Starting 6 second delay...", 0);

            $this->ircClass->sendRaw("WHOIS " . $nick);
            $this->timerClass->addTimer(irc::randomHash(), $this, "reverseTimer", $args, 6, false);
            return;
        }

        if ($fromTimer == true) {
            $reverse = DCC_REVERSE; // using mIRC dcc reverse protocol
        }

        if ($host == NULL || $port == NULL) {
            $conn = new connection(null, null, 0);
            $listening = true;
            $status = DCC_LISTENING;
        } else {
            $conn = new connection($host, $port, CONNECT_TIMEOUT);
            $listening = false;
            $status = DCC_CONNECTING;
        }

        $conn->setSocketClass($this->socketClass);
        $conn->setIrcClass($this->ircClass);
        $conn->setCallbackClass($this);
        $conn->setTimerClass($this->timerClass);
        $port = $conn->init();

        if ($conn->getError()) {
            $this->ircClass->log("Start Chat Error: " . $conn->getErrorMsg());
            return false;
        }

        $sockInt = $conn->getSockInt();

        $this->chatCount++;

        $id = $this->highestId();

        $chat = new chat($id, $nick, $admin, $sockInt, $host, $port, $handler, $reverse);
        $chat->setIrcClass($this->ircClass);
        $chat->setDccClass($this);
        $chat->setParserClass($this->parserClass);
        $chat->setSocketClass($this->socketClass);
        $chat->setTimerClass($this->timerClass);
        $chat->connection = $conn;
        $chat->status = $status;
        $chat->removed = false;

        $this->dccList[$sockInt] = $chat;

        $chat->initialize();

        $conn->setCallbackClass($chat);

        if ($listening == true) {
            $this->timerClass->addTimer(irc::randomHash(), $this, "checkDccTimeout", $chat, 30, true);
        } else {
            $conn->connect();
        }

        return $port;
    }

    public function addFile($nick, $host, $port, $type, $filename, $size, $fromTimer = false) // <-- ignore fromTimer, it is sent by reverseTimer() above
    {
        $reverse = false;

        if ($this->ircClass->getClientConf("mircdccreverse") != "" && $fromTimer == false && $type != DOWNLOAD) {
            $port = intval($this->ircClass->getClientConf("mircdccreverse"));
            if ($port == 0) {
                return NULL;
            }

            $args = new argClass;

            $args->arg1 = $nick;
            $args->arg2 = $host;
            $args->arg3 = $port;
            $args->arg4 = $type;
            $args->arg5 = $filename;
            $args->arg6 = $size;
            $args->arg7 = time();
            $args->arg8 = FILE;

            $this->ircClass->notice($nick, "DCC: NOTICE: This server is using the mIRC File Server Protocol.  Please use ' /dccserver +s on " . $this->ircClass->getClientConf("mircdccreverse") . " ' to recieve files from me!  Starting 6 second delay...", 0);

            $this->ircClass->sendRaw("WHOIS " . $nick);
            $this->timerClass->addTimer(irc::randomHash(), $this, "reverseTimer", $args, 6);
            return;
        }

        if ($fromTimer == true) {
            $reverse = DCC_REVERSE; // using mIRC dcc reverse protocol
        }

        if ($host == NULL || $port == NULL) {
            $conn = new connection(null, null, 0);
            $listening = true;
            $status = DCC_LISTENING;
        } else {
            $conn = new connection($host, $port, CONNECT_TIMEOUT);
            $listening = false;
            $status = DCC_CONNECTING;
        }

        $conn->setSocketClass($this->socketClass);
        $conn->setIrcClass($this->ircClass);
        $conn->setCallbackClass($this);
        $conn->setTransTimeout(30);
        $conn->setTimerClass($this->timerClass);
        $port = $conn->init();

        if ($conn->getError()) {
            $this->ircClass->log("File transfer start error: " . $conn->getErrorMsg());
            return false;
        }

        $sockInt = $conn->getSockInt();

        $id = $this->highestId();

        $file = new file($id, $nick, $sockInt, $host, $port, $type, $reverse);

        if ($file->transferType == UPLOAD) {
            $this->fileUlCount++;
        } else {
            $this->fileDlCount++;
        }

        $file->setIrcClass($this->ircClass);
        $file->setDccClass($this);
        $file->setSocketClass($this->socketClass);
        $file->setProcQueue($this->procQueue);
        $file->setTimerClass($this->timerClass);
        $file->connection = $conn;
        $file->status = $status;
        $file->removed = false;

        $this->dccList[$sockInt] = $file;

        $file->initialize($filename, $size);

        $conn->setCallbackClass($file);

        if ($listening == true) {
            $this->timerClass->addTimer(irc::randomHash(), $this, "checkDccTimeout", $file, 30, true);
        }

        if ($reverse == true) {
            $conn->connect();
        }

        return $port;
    }


    public function alterSocket($sockInt, $level, $opt, $val)
    {
        return $this->socketClass->alterSocket($sockInt, $level, $opt, $val);
    }


    public function reverseTimer($args)
    {
        $memData = $this->ircClass->getUserData($args->arg1);

        if ($memData == NULL || ($memData->host == "" || $memData->host == NULL)) {
            $this->ircClass->notice($args->arg1, "DCC: ERROR: Couldn't resolve your hostname.  Try again?");
        } else {

            if ($args->arg8 == FILE) {
                $this->addFile($args->arg1,
                    $memData->host,
                    $this->ircClass->getClientConf("mircdccreverse"),
                    $args->arg4,
                    $args->arg5,
                    $args->arg6,
                    true);
            } else {
                $this->addChat($args->arg1,
                    $memData->host,
                    $this->ircClass->getClientConf("mircdccreverse"),
                    $args->arg4,
                    $args->arg5,
                    true);
            }
        }

        return false;
    }

    public function sendFile($nick, $file)
    {
        return $this->addFile($nick, null, null, UPLOAD, $file, NULL);
    }


    public function dccResume($port, $size)
    {
        foreach ($this->dccList AS $dcc) {
            if ($dcc->type == FILE) {
                if ($dcc->port == $port) {
                    $dcc->resume($size);
                    break;
                }
            }
        }
    }

    public function dccAccept($port)
    {
        foreach ($this->dccList AS $dcc) {
            if ($dcc->type == FILE) {
                if ($dcc->port == $port) {
                    $dcc->accepted();
                    break;
                }
            }
        }
    }

    public function disconnect($dcc)
    {

        switch ($dcc->type) {
            case CHAT:
                $this->chatCount--;
                break;
            case FILE:
                switch ($dcc->transferType) {
                    case UPLOAD:
                        $this->fileUlCount--;
                        break;
                    case DOWNLOAD:
                        $this->fileDlCount--;
                        break;
                }
                break;
            default:
                break;
        }

        $dcc->removed = true;

        $dcc->connection->disconnect();
        $this->removeDcc($dcc);

        return true;
    }


}

?>
