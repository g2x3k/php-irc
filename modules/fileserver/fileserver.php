<?php

require_once("./modules/fileserver/extra.php");

/*
+---------------------------------------------------------------------------
|   PHP-IRC v2.2.0
|   ========================================================
|   by Manick
|   (c) 2001-2005 by http://phpbots.sf.net/
|   Contact: manick@manekian.com
|   irc: #manekian@irc.rizon.net
|   ========================================
+---------------------------------------------------------------------------
|   > fileserver module
|   > Module written by Manick
|   > Module Version Number: 1.0.0
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

class fileserver extends module
{

    public $title = "Simple Filserver Demo";
    public $author = "Manick";
    public $version = "1.0.0";

    private $vDir = "F:/BT/";
    private $maxSends = 3;
    private $maxQueues = 15;
    private $queuesPerPerson = 1;
    private $sendsPerPerson = 1;
    private $timeout = 60; //seconds

    private $sessions = array();
    private $timeoutSession = -1;
    private $timeoutTime = 0;

    private $queues = array();
    private $queueCount = 0;

    public function init()
    {
        $this->timerClass->addTimer("check_fserv_sends", $this, "checkQueue", "", 3);
    }

    public function destroy()
    {
        $this->timerClass->removeTimer("check_fserv_sends");
    }

    // Start new fileserver session
    public function main($line, $args)
    {

        if ($this->checkIfSessionExists($line['fromNick']) === true) {
            $this->ircClass->notice($line['fromNick'], "File server busy, please try again later...");
            return;
        }

        $port = $this->dccClass->addChat($line['fromNick'], null, null, false, $this);

        if ($port === false) {
            $this->ircClass->notice($line['fromNick'], "Error starting chat, please try again.", 1);
        }
    }

    public function handle($chat, $args)
    {
        $session = $this->sessions[$chat->sockInt];
        $session->lastAction = time();

        switch ($args['cmd']) {
            case "exit":
                $chat->dccSend("Bye Bye");
                $this->removeSession($chat);
                $chat->disconnect();
                break;
            case "clr_queues":
                $this->clr_queues($chat);
                break;
            case "queues":
                $this->queues($chat);
                break;
            case "sends":
                $this->sends($chat);
                break;
            case "ls":
            case "dir":
                $this->dir($chat);
                break;
            case "pwd":
                $this->pwd($chat);
                break;
            case "get":
                $this->getFile($chat, $args);
                break;
            case "cd":
                $this->cd($chat, $args);
                break;
            default:
                $chat->dccSend("Invalid command");
                break;
        }

    }

    private function getFile($chat, $args)
    {
        $session = $this->sessions[$chat->sockInt];

        $file = trim($args['query']);
        $file = str_replace("\\", "/", $file);

        $newDir = $session->currDir;

        if (strpos($file, "/") !== false) {
            $fArray = explode("/", $file);
            $file = array_pop($fArray);
            $dir = implode("/", $fArray);
            $newDir = $this->getDir($session->currDir, $dir);
            if ($newDir === false) {
                $chat->dccSend("Invalid dir");
                return;
            }
        }

        $theFile = $this->vDir . $newDir . "/" . $file;

        if (!is_file($theFile)) {
            $chat->dccSend("Invalid file");
            return;
        }

        $this->addQueue($chat, $theFile, $file);

    }

    private function queues($chat)
    {

        $i = 1;
        if ($this->queueCount > 0) {
            foreach ($this->queues AS $i => $queue) {
                $chat->dccSend($i++ . ": " . $queue->nick . " is queued for file: " . $queue->shortFile);
            }
        }

        $chat->dccSend("There are currently " . BOLD . $this->queueCount . BOLD . " queues.");

    }


    private function clr_queues($chat)
    {

        $newArray = array();
        $chatNick = irc::myStrToLower($chat->nick);

        if ($this->queueCount > 0) {
            foreach ($this->queues AS $i => $queue) {
                if ($queue->nick != $chatNick) {
                    $newArray[] = $queue;
                } else {
                    $this->queueCount--;
                }
            }

            $this->queues = $newArray;
        }


        $chat->dccSend("All your queues were successfully removed.");
    }


    private function sends($chat)
    {

        $dccTransfers = $this->dccClass->getDccList();
        $sendCount = array();

        $i = 1;
        foreach ($dccTransfers AS $sockInt => $dcc) {
            if ($dcc->type == FILE && $dcc->transferType == UPLOAD) {
                $currDcc = true;

                if ($dcc->speed_lastavg == 0) {
                    $eta = "n/a";
                    $speed = 0.0;
                } else {
                    $eta = $this->ircClass->timeFormat(round(($dcc->filesize - $dcc->bytesTransfered) / $dcc->speed_lastavg, 0), "%hh,%mm,%ss");
                    $speed = round($dcc->speed_lastavg / 1000, 1);
                }

                $chat->dccSend($i++ . ": " . $dcc->nick . " is receiving " . $dcc->filenameNoDir . " at " . $speed . "kB/s. ETA:" . $eta);
            }
        }

        $chat->dccSend("There are currently " . ($i - 1) . " sends in progress.");
    }

    private function addQueue($chat, $file, $shortFile)
    {
        $nick = irc::myStrToLower($chat->nick);

        $queueCount = 0;
        if ($this->queueCount > 0) {
            foreach ($this->queues AS $i => $queue) {
                if ($nick == $queue->nick) {
                    $queueCount++;
                }
            }
        }

        if ($queueCount >= $this->queuesPerPerson) {
            $chat->dccSend("All your queue slots are full.");
            return;
        }

        if ($this->dccClass->getUploadCount() >= $this->maxSends) {
            $chat->dccSend("Queued > " . BOLD . $shortFile . BOLD . " < for you in queue slot: " . ($this->queueCount + 1));
        } else {

            $sendCount = $this->getSendCount();

            if (isset($sendCount[$nick]) && $sendCount[$nick] >= $this->sendsPerPerson) {
                $chat->dccSend("Queued > " . BOLD . $shortFile . BOLD . " < for you in queue slot: " . ($this->queueCount + 1));
            } else {
                $chat->dccSend("Sending you: " . $shortFile);
            }
        }

        $newQueue = new fserv_queue;

        $newQueue->nick = $nick;
        $newQueue->file = $file;
        $newQueue->shortFile = $shortFile;

        $this->queues[] = $newQueue;
        $this->queueCount++;

        $this->checkQueue();

    }


    public function getSendCount()
    {

        $dccTransfers = $this->dccClass->getDccList();
        $sendCount = array();

        foreach ($dccTransfers AS $sockInt => $xfer) {
            if ($xfer->type == FILE && $xfer->transferType == UPLOAD) {
                $nick = irc::myStrToLower($xfer->nick);
                if (!isset($sendCount[$nick])) {
                    $sendCount[$nick] = 1;
                } else {
                    $sendCount[$nick]++;
                }
            }
        }

        return $sendCount;
    }

    private function setNextTimeout()
    {

        $this->timeoutSession = -1;
        $this->timeoutTime = 0;

        if (count($this->sessions) <= 0) {
            return;
        }

        foreach ($this->sessions AS $sockInt => $data) {

            if ($this->timeoutSession == -1) {
                $this->timeoutSession = $sockInt;
                $this->timeoutTime = $data->lastAction;
                continue;
            }

            if ($data->lastAction < $this->timeoutTime) {
                $this->timeoutSession = $sockInt;
                $this->timeoutTime = $data->lastAction;
            }

        }

        $this->timeoutTime += $this->timeout;

    }


    public function checkQueue()
    {
        $theTime = time();

        if ($theTime > $this->timeoutTime && $this->timeoutSession != -1) {
            if ($theTime > $this->sessions[$this->timeoutSession]->lastAction + $this->timeout) {
                $chat = $this->dccClass->getDcc($this->timeoutSession);

                if (is_object($chat) && $chat != null && $chat->sockInt > 0) {
                    $chat->dccSend("Inactivity Timeout! Bye Bye");
                    $this->removeSession($chat);
                    $chat->disconnect();
                }
            }
            $this->setNextTimeout();
        }

        if ($this->dccClass->getUploadCount() < $this->maxSends) {
            if ($this->queueCount > 0) {

                $sendCount = $this->getSendCount();

                $doQueue = -1;
                foreach ($this->queues AS $i => $queue) {
                    if (isset($sendCount[$queue->nick])) {
                        if ($sendCount[$queue->nick] < $this->sendsPerPerson) {
                            $doQueue = $i;
                            break;
                        }
                    } else {
                        $doQueue = $i;
                        break;
                    }
                }

                if ($doQueue != -1) {
                    $queue = $this->queues[$doQueue];
                    $this->dccClass->addFile($queue->nick, null, null, UPLOAD, $queue->file, null);
                    $this->queues[$doQueue] = null;
                    unset($this->queues[$doQueue]);
                    $this->queueCount--;
                }

            }
        }
    }

    private function pwd($chat)
    {
        $session = $this->sessions[$chat->sockInt];

        $chat->dccSend("Current directory: /" . $session->currDir);
    }


    private function getDir($currDir, $newDir)
    {

        if ($newDir == "") {
            return false;
        }

        if ($currDir == "") {
            $currArray = array();
        } else {
            $currArray = explode("/", $currDir);
        }

        $newArray = explode("/", $newDir);

        if (isset($newArray[0])) {
            if ($newArray[0] == "") {
                //root dir selected
                $currArray = array();
            }

            foreach ($newArray AS $location) {

                if ($location == "") {
                    continue;
                }

                if ($location == "~") {
                    return false;
                }

                if ($location == "..") {
                    if (count($currArray) == 0) {
                        return false;
                    }
                    array_pop($currArray);
                } else if (str_replace(".", "", $location) == "") {
                    return false;
                } else {
                    $currArray[] = $location;
                }
            }
        }

        $newDir = implode("/", $currArray);

        if (is_dir($this->vDir . $newDir) === false) {
            return false;
        }

        return $newDir;

    }


    private function cd($chat, $args)
    {
        $session = $this->sessions[$chat->sockInt];

        $newDir = trim($args['query']);
        $newDir = str_replace("\\", "/", $newDir);

        $newDir = $this->getDir($session->currDir, $newDir);
        if ($newDir === false) {
            $chat->dccSend("Invalid dir");
            return;
        }

        $session->currDir = $newDir;

        $chat->dccSend("Directory changed to: /" . $newDir);

    }


    private function dir($chat)
    {
        $session = $this->sessions[$chat->sockInt];

        $dir = scandir($this->vDir . $session->currDir);

        $dirs = 0;
        $files = 0;

        foreach ($dir AS $file) {
            if ($file == ".." && $session->currDir == "") {
                continue;
            }

            if ($file == "." || $file == "...") {
                continue;
            }

            if (is_dir($this->vDir . $session->currDir . "/" . $file)) {
                $dirs++;
                $chat->dccSend(BOLD . "dir: " . BOLD . $file);
            } else if (is_file($this->vDir . $session->currDir . "/" . $file)) {
                $files++;
                $chat->dccSend($file);
            }
        }
        $chat->dccSend($dirs . " directories, " . $files . " files");

    }

    public function disconnected($chat)
    {
        $this->removeSession($chat);
    }

    public function connected($chat)
    {
        $chat->dccSend("Welcome to the file server, " . $chat->nick . ". Type 'dir' to begin.");
        $chat->dccSend("Inactive sessions expire within " . $this->timeout . " seconds. (Silent countdown, starting now.)");
        $chat->dccSend("Commands: DIR LS CD PWD GET SENDS QUEUES CLR_QUEUES EXIT");
        $this->addSession($chat);
    }

    private function checkIfSessionExists($nick)
    {
        foreach ($this->sessions AS $sockInt => $data) {
            if ($data->nick == $nick) {
                return true;
            }
        }

        return false;
    }

    public function addSession($chat)
    {
        $sess = new fserv_session;
        $sess->currDir = "";

        if (isset($this->sends_queues[$chat->nick])) {
            $sess->xfers =& $this->sends_queues[$chat->nick];
        } else {
            $sess->xfers = array();
        }

        $this->sessions[$chat->sockInt] = $sess;

        $sess->lastAction = time();

        $this->setNextTimeout();
    }

    public function removeSession($chat)
    {
        unset($this->sessions[$chat->sockInt]);
    }

}

?>
