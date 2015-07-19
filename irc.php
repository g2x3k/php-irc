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
|   > irc module
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

class irc
{

    // Config Vars
    private $clientConfig = array();
    private $configFilename = "";
    // nick, realname, localhost, remotehost, ident, host, port
    private $serverConfig = array();
    private $nick = "";
    private $tempNick = "";
    // only used if our nick is taken initially
    private $clientIP;
    private $clientLongIP;
    // Above address related determined on runtime..
    private $startTime = 0; //when the bot was started

    // Status Vars
    private $status = STATUS_IDLE;
    private $exception;
    private $reportedStatus = STATUS_IDLE;

    //Classes
    private $timerClass = null;
    private $socketClass = null;
    private $dccClass = null;
    private $parserClass = null;

    //Socket Vars
    private $sockInt; //old pre 2.1.2 method
    private $conn; //new 2.1.2 method
    private $connectStartTime = 0;
    private $lagTime = 0;
    private $timeConnected = 0;

    //Queue Vars
    private $textQueueTime = 0;
    private $textQueue = array();
    private $textQueueLength = 0;

    /* This variable will be set when new text is sent to be sent to the irc server, its so we don't
       have to call addQueue() more than once. */
    private $textQueueAdded = false;
    private $modeQueueAdded = false;

    private $modeQueue = array();
    private $modeQueueLength = 0;
    private $modeTimerAdded = false;

    //Stats Var
    private $stats = array();

    //Parsing Vars
    private $lVars = array();
    private $modeArray;
    private $prefixArray;

    //Channel and User Linked Lists
    private $chanData = array();
    private $maintainChannels = array();
    /*	private $chanData = null; */

    private $usageList = array();

    //Timers
    private $timeoutTimer = 0;
    private $lastPing = 0;
    private $lastPingTime = 0;
    private $nickTimer = 0;
    private $checkChansTimer = 0;

    //Kill
    private $setKill = false;

    //My process id
    private $pid;

    //Process Queue
    private $procQueue;

    function __construct()
    {

        $this->startTime = time();

        // Initialize the stats array
        $this->stats = array('BYTESUP' => 0,
            'BYTESDOWN' => 0,
        );

        $this->pid = getmypid();

        return;

    }

    public function init()
    {
        $this->socketClass->setTcpRange($this->getClientConf('dccrangestart'));

        /* Add other timers */
        $this->timerClass->addTimer("check_nick_timer", $this, "checkNick", "", NICK_CHECK_TIMEOUT);
        $this->timerClass->addTimer("check_channels_timer", $this, "checkChans", "", CHAN_CHECK_TIMEOUT);
        $this->timerClass->addTimer("check_ping_timeout_timer", $this, "checkPingTimeout", "", PING_TIMEOUT + 1);

        /* Timer that makes sure we're connected every 1:15 minutes */
        $this->reconnect();
        //$this->timerClass->addTimer("check_connection", $this, "checkConnection", "", 75, true);
    }

    public function pid()
    {
        return $this->pid;
    }

    public function setConfig($config, $filename = "")
    {
        $this->clientConfig = $config;
        $this->configFilename = $filename;
        $this->nick = $config['nick'];
    }

    public function setProcQueue($class)
    {
        $this->procQueue = $class;
    }

    public function setDccClass($class)
    {
        $this->dccClass = $class;
        $this->dccClass->setIrcClass($this);
    }

    public function setParserClass($class)
    {
        $this->parserClass = $class;
        $this->parserClass->setIrcClass($this);
    }

    public function setSocketClass($class)
    {
        $this->socketClass = $class;
    }

    public function setTimerClass($class)
    {
        $this->timerClass = $class;
    }

    public function setClientConfigVar($var, $value)
    {
        $this->clientConfig[$this->myStrToLower($var)] = $value;
    }

    public function getNick()
    {
        return $this->nick;
    }

    public function getMaintainedChannels()
    {
        return $this->maintainChannels;
    }

    public function getServerConf($var)
    {
        if (isset($this->serverConfig[$this->myStrToUpper($var)])) {
            return $this->serverConfig[$this->myStrToUpper($var)];
        }

        return "";
    }


    public function getConfigFilename()
    {
        return $this->configFilename;
    }

    public function getClientConf($var = "")
    {

        if ($var != "") {
            if (isset($this->clientConfig[$var])) {
                return $this->clientConfig[$var];
            }
        } else {
            return $this->clientConfig;
        }

        return "";

    }


    //debug only!
    public function displayUsers()
    {
        $toReturn = "";

        foreach ($this->chanData AS $chanPtr) {

            $toReturn .= $chanPtr->name . "\n";

            foreach ($chanPtr->memberList AS $memPtr) {
                $toReturn .= $memPtr->realNick . " -- ";
                $toReturn .= $memPtr->status . "\n";
            }

        }

        return trim($toReturn);
    }


    private function addMember($channel, $nick, $ident, $status, $host = "")
    {
        $channel = $this->myStrToLower($channel);
        $realNick = trim($nick);
        $nick = trim($this->myStrToLower($nick));

        $newMember = new memberLink;
        if ($host != "") {
            $newMember->host = $host;
        }
        $newMember->nick = $nick;
        $newMember->realNick = $realNick;
        $newMember->ident = $ident;
        $newMember->status = $status;

        if (!isset($this->chanData[$channel])) {
            $chanPtr = new channelLink;
            $chanPtr->count = 1;
            $chanPtr->memberList = NULL;
            $chanPtr->banComplete = 1;
            $chanPtr->name = $channel;
            $this->chanData[$channel] = $chanPtr;
        } else {
            $chanPtr = $this->chanData[$channel];

            if (!isset($chanPtr->memberList[$nick])) {
                $chanPtr->count++;
            }
        }

        $chanPtr->memberList[$nick] = $newMember;

        return $chanPtr->count;

    }

    public function &getChannelData($channel = "")
    {

        if ($channel == "") {
            return $this->chanData;
        }

        $channel = $this->myStrToLower($channel);

        if (isset($this->chanData[$channel])) {
            return $this->chanData[$channel];
        }

        return NULL;

    }


    public function setMemberData($nick, $ident, $host)
    {
        $nick = $this->myStrToLower($nick);

        foreach ($this->chanData AS $chanPtr) {
            if (isset($chanPtr->memberList[$nick])) {
                $chanPtr->memberList[$nick]->ident = $ident;
                $chanPtr->memberList[$nick]->host = $host;
            }
        }

    }


    public function getUserData($user, $channel = "")
    {
        if ($user == "") {
            return NULL;
        }

        $channel = $this->myStrToLower($channel);
        $user = $this->myStrToLower($user);

        if ($channel == "") {
            foreach ($this->chanData AS $chanPtr) {
                if (isset($chanPtr->memberList[$user])) {
                    return $chanPtr->memberList[$user];
                }
            }
            return NULL;
        }

        if (isset($this->chanData[$channel])) {
            if (isset($this->chanData[$channel]->memberList[$user])) {
                return $this->chanData[$channel]->memberList[$user];
            }
            return NULL;
        }
    }


    private function changeMember($channel, $oldNick, $newNick, $ident,
                                  $newStatus, $action, $newHost = "")
    {

        $channel = $this->myStrToLower($channel);
        $ident = trim($ident);
        $oldNick = trim($this->myStrToLower($oldNick));
        $realNick = trim($newNick);
        $newNick = trim($this->myStrToLower($newNick));

        // See if we have a valid usermode

        if ($newStatus != "") {
            if (strpos($this->getServerConf('PREFIX'), $newStatus) === false) {
                $newStatus = "";
                $action = "";
            }
        }

        //Find our channel, also change user name if no channel name given

        if ($channel == "") {
            foreach ($this->chanData AS $chanPtr) {
                if (isset($chanPtr->memberList[$oldNick])) {
                    $memPtr = $chanPtr->memberList[$oldNick];

                    if ($newHost != "") {
                        $memPtr->host = $newHost;
                    }
                    if ($ident != "") {
                        $memPtr->ident = $ident;
                    }
                    $memPtr->nick = $newNick;
                    $memPtr->realNick = $realNick;

                    $chanPtr->memberList[$newNick] = $memPtr;
                    unset($chanPtr->memberList[$oldNick]);
                }
            }
            return;
        }

        if (isset($this->chanData[$channel])) {
            $chanPtr = $this->chanData[$channel];

            if (isset($chanPtr->memberList[$oldNick])) {
                $memPtr = $chanPtr->memberList[$oldNick];

                if ($newHost != "") {
                    $memPtr->host = $newHost;
                }
                if ($ident != "") {
                    $memPtr->ident = $ident;
                }

                if ($newStatus != "") {
                    if ($action == "+") {
                        if (strpos($memPtr->status, $newStatus) === false) {
                            $memPtr->status .= $newStatus;
                        }
                    } else {
                        $memPtr->status = str_replace($newStatus, "", $memPtr->status);
                    }
                }

            }

        }


    }

    private function setChannelData($channel, $item, $val)
    {
        $channel = $this->myStrToLower($channel);
        $item = $this->myStrToLower($item);

        if (isset($this->chanData[$channel])) {
            $chanPtr = $this->chanData[$channel];

            switch ($item) {
                case "topicby":
                    $chanPtr->topicBy = $val;
                    break;

                case "topic":
                    $chanPtr->topic = $val;
                    break;

                case "bancomplete":
                    $chanPtr->banComplete = $val;
                    break;

                case "created":
                    $chanPtr->created = $val;
                    break;

                case "modes":
                    unset($chanPtr->modes);
                    $chanPtr->modes = array();
                    $chanPtr->modes['MODE_STRING'] = substr($val, 1);
                    break;
            }

        }

    }


    private function clearBanList($channel)
    {
        $channel = $this->myStrToLower($channel);

        if (isset($this->chanData[$channel])) {
            unset($this->chanData[$channel]->banList);
            $this->chanData[$channel]->banList = array();
        }
    }

    private function changeChannel($channel, $action, $newStatus, $extraStatus = "")
    {

        $channel = $this->myStrToLower($channel);
        $newStatus = trim($newStatus); // i.e, l, b

        if (!is_array($extraStatus)) {
            $extraStatus = trim($extraStatus); // i.e, 50, *!*@*
        }


        //ex. CHANMODES=eIb,k,l,cimnpstMNORZ

        if ($newStatus == "" || $action == "") {
            return;
        }

        if (!isset($this->modeArray[$newStatus])) {
            return;
        }

        $type = $this->modeArray[$newStatus];

        if ($type != BY_NONE && $extraStatus == "") {
            return;
        }

        if (strpos($this->getServerConf('CHANMODES'), $newStatus) === false) {
            return;
        }

        //Find our channel

        if (isset($this->chanData[$channel])) {
            $chanPtr = $this->chanData[$channel];

            if (!is_array($chanPtr->modes)) {
                unset($chanPtr->modes);
                $chanPtr->modes = array();
            }

            if (!isset($chanPtr->modes['MODE_STRING'])) {
                $chanPtr->modes['MODE_STRING'] = "";
            }

            if ($type == BY_MASK) {

                if ($newStatus == "b") {
                    if ($action == "+") {
                        if (is_array($extraStatus)) {
                            $ban = $extraStatus[0];
                            $time = $extraStatus[1];
                        } else {
                            $ban = $extraStatus;
                            $time = time();
                        }

                        $chanPtr->banList[] = array('HOST' => $ban,
                            'TIME' => $time,
                        );


                    } else {
                        foreach ($chanPtr->banList AS $index => $data) {
                            if ($data['HOST'] == $extraStatus) {
                                unset($chanPtr->banList[$index]);
                                break;
                            }
                        }
                    }
                }
            } else {
                if ($action == "+") {
                    if (strpos($chanPtr->modes['MODE_STRING'], $newStatus) === false) {
                        $chanPtr->modes['MODE_STRING'] .= $newStatus;
                        if ($type != BY_NONE) {
                            $chanPtr->modes[$newStatus] = $extraStatus;
                        }
                    }
                } else {
                    $chanPtr->modes['MODE_STRING'] = str_replace($newStatus, "", $chanPtr->modes['MODE_STRING']);
                    if ($type != BY_NONE) {
                        unset($chanPtr->modes[$newStatus]);
                    }
                }
            }

        }


    }

    private function purgeChanList()
    {
        foreach ($this->chanData AS $cIndex => $chanPtr) {
            foreach ($chanPtr->memberList AS $mIndex => $memPtr) {
                unset($chanPtr->memberList[$mIndex]);
            }

            unset($this->chanData[$cIndex]);
        }

        unset($this->chanData);
        $this->chanData = array();
    }

    private function removeMember($channel, $nick)
    {

        $channel = $this->myStrToLower($channel);
        $nick = trim($this->myStrToLower($nick));

        if ($channel == "") {
            foreach ($this->chanData AS $chanPtr) {
                if (isset($chanPtr->memberList[$nick])) {
                    unset($chanPtr->memberList[$nick]);
                    $chanPtr->count--;
                }
            }

        } else {
            if (isset($this->chanData[$channel])) {
                if (isset($this->chanData[$channel]->memberList[$nick])) {
                    unset($this->chanData[$channel]->memberList[$nick]);
                    $this->chanData[$channel]->count--;
                }
            }
        }

    }

    private function removeChannel($channel)
    {
        $channel = $this->myStrToLower($channel);

        if (isset($this->chanData[$channel])) {
            $chanPtr = $this->chanData[$channel];

            foreach ($chanPtr->memberList AS $mIndex => $memPtr) {
                $this->removeMember($channel, $mIndex);
            }

            unset($chanPtr->banList);
            unset($chanPtr->modes);

            unset($this->chanData[$channel]);
        }
    }

    public function isChanMode($channel, $mode, $extra = "")
    {
        $channel = $this->myStrToLower($channel);
        $extra = $this->myStrToLower($extra);

        if (!isset($this->chanData[$channel])) {
            return;
        }

        $chanPtr = $this->chanData[$channel];

        if (!isset($this->modeArray[$mode])) {
            return false;
        }

        $type = $this->modeArray[$mode];

        if ($type == BY_MASK) {
            if ($mode == "b") {

                foreach ($chanPtr->banList AS $index => $ban) {

                    if ($this->hostMasksMatch($ban['HOST'], $extra)) {
                        return true;
                    }

                }
            }
        } else {
            if (strpos($chanPtr->modes['MODE_STRING'], $mode) !== false) {
                return true;
            }
        }

        return false;
    }

    public function hasModeSet($chan, $nick, $modes)
    {
        $channel = $this->myStrToLower($chan);
        $nick = $this->myStrToLower($nick);

        if (isset($this->chanData[$channel])) {
            if (isset($this->chanData[$channel]->memberList[$nick])) {
                $memPtr = $this->chanData[$channel]->memberList[$nick];

                while ($modes != "") {
                    $mode = substr($modes, 0, 1);
                    $modes = substr($modes, 1);

                    if (strpos($memPtr->status, $mode) !== false) {
                        return true;
                    }
                }

            }
        }

        return false;

    }

    public function isMode($nick, $channel, $mode)
    {
        $channel = $this->myStrToLower($channel);
        $nick = $this->myStrToLower($nick);

        if (isset($this->chanData[$channel])) {
            if (isset($this->chanData[$channel]->memberList[$nick])) {
                $memPtr = $this->chanData[$channel]->memberList[$nick];

                if ($mode == "online") {
                    return true;
                } else {
                    if (strpos($memPtr->status, $mode) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getHostMask($mask)
    {
        $offsetA = strpos($mask, "!");
        $offsetB = strpos($mask, "@");

        $myMask = array();

        $myMask['nick'] = $this->myStrToLower(substr($mask, 0, $offsetA));
        $myMask['ident'] = $this->myStrToLower(substr($mask, $offsetA + 1, $offsetB - $offsetA - 1));
        $myMask['host'] = $this->myStrToLower(substr($mask, $offsetB + 1));

        return $myMask;
    }

    public function hostMasksMatch($mask1, $mask2)
    {

        $maskA = $this->getHostMask($mask1);
        $maskB = $this->getHostMask($mask2);

        $ident = false;
        $nick = false;
        $host = false;

        if ($maskA['ident'] == $maskB['ident']
            || $maskA['ident'] == "*" || $maskB['ident'] == "*"
        ) {
            $ident = true;
        }

        if ($maskA['nick'] == $maskB['nick']
            || $maskA['nick'] == "*" || $maskB['nick'] == "*"
        ) {
            $nick = true;
        }

        if ($maskA['host'] == $maskB['host']
            || $maskA['host'] == "*" || $maskB['host'] == "*"
        ) {
            $host = true;
        }

        if ($host && $nick && $ident) {
            return true;
        } else {
            return false;
        }

    }


    public function setToIdleStatus()
    {
        $this->status = STATUS_IDLE;
    }


    private function getStatus()
    {
        $this->reportedStatus = $this->status;
        return $this->status;
    }

    private function getStatusChange()
    {
        return ($this->reportedStatus != $this->status);
    }

    private function getException()
    {
        return $this->exception;
    }

    private function updateContext()
    {

        if ($this->getStatusChange()) {
            $status = $this->getStatus();
            $statusStr = $this->getStatusString($status);
            $this->log("STATUS: " . $statusStr);
            $this->dccClass->dccInform("Status: " . $statusStr);

            switch ($status) {
                case STATUS_ERROR:
                    $exception = $this->getException();
                    $this->log("Error: " . $exception->getMessage());
                    $this->dccClass->dccInform("Error: " . $exception->getMessage());
                    break;
                default:
                    break;
            }

        }

    }

    public function reconnect()
    {
        $this->updateContext();

        try {

            $this->connectStartTime = time();

            $conn = new connection($this->getClientConf('server'), $this->getClientConf('port'), CONNECT_TIMEOUT);

            $this->conn = $conn;

            $conn->setSocketClass($this->socketClass);
            $conn->setIrcClass($this);
            $conn->setCallbackClass($this);
            $conn->setTimerClass($this->timerClass);
            $conn->init();

            if ($conn->getError()) {
                throw new ConnectException($conn->getErrorMsg());
            }

            //Bind socket...
            if ($this->getClientConf('bind') != "") {
                $conn->bind($this->getClientConf('bind'));
            }

            $this->sockInt = $conn->getSockInt();
            $conn->connect();


        } catch (ConnectException $e) {
            $this->beginReconnectTimer();
            $this->status = STATUS_ERROR;
            $this->exception = $e;
            return;
        }

        $this->status = STATUS_CONNECTING;

        $this->updateContext();

        return;
    }

    public function onConnect($conn)
    {
        $this->status = STATUS_CONNECTED;

        $this->updateContext();

        $this->timeoutTimer = time();
        $ip = "";
        if ($this->getClientConf('natip') != "") {
            $ip = $this->getClientConf('natip');
        }
        $this->setClientIP($ip);

        $this->register();
        //TODO: Add registration timeout timer (with $conn to check identity)

        return false;
    }

    public function onRead($conn)
    {
        $this->updateContext();

        return $this->readInput();
    }

    public function onWrite($conn)
    {
        //Do nothing.. this function has no purpose for the ircClass
        return false;
    }

    public function onAccept($listenConn, $newConn)
    {
        //Do nothing.. this function has no purpose for the ircClass
        return false;
    }

    public function onDead($conn)
    {
        if ($conn->getError()) {
            $this->status = STATUS_ERROR;
            $this->exception = new ConnectException($conn->getErrorMsg());
        }

        $this->disconnect();
        return false;
    }

    public function onConnectTimeout($conn)
    {
        $this->status = STATUS_ERROR;
        $this->exception = new ConnectException("Connection attempt timed out");
        $this->disconnect();
    }

    private function beginReconnectTimer()
    {
        $this->timerClass->addTimer(self::randomHash(), $this, "reconnectTimer", $this->conn, ERROR_TIMEOUT);
    }

    public function reconnectTimer($conn)
    {
        //If curr connection is equal to the stored connection, then no forced
        //connect was attempted, so attempt another.

        if ($this->conn === $conn) {
            $this->reconnect();
        }

        return false;
    }


    public function disconnect()
    {
        $this->conn->disconnect();

        $this->updateContext();

        $this->status = STATUS_ERROR;
        $this->exception = new ConnectException("Disconnected from server");

        $this->updateContext();

        //reset all vars
        $this->purgeChanList();
        $this->timeoutTimer = 0;
        $this->lastPing = 0;
        $this->lastPingTime = 0;
        $this->nickTimer = 0;
        $this->connectStartTime = 0;
        $this->lagTime = 0;
        $this->checkChansTimer = 0;
        $this->purgeTextQueue();
        $this->nick = $this->getClientConf('nick');
        $this->tempNick = "";
        unset($this->modeArray);
        unset($this->prefixArray);
        unset($this->serverConfig);
        $this->modeArray = array();
        $this->prefixArray = array();
        $this->serverConfig = array();

        $this->beginReconnectTimer();

        return;
    }

    private function register()
    {
        $login_string = "NICK " . $this->getClientConf('nick') . "\r\n" . "USER " . $this->getClientConf('ident') . " " . "localhost" . " " . $this->getClientConf('server') . " :" . $this->getClientConf('realname');
        if ($this->getClientConf('serverpassword') != "")
            $login_string = "PASS " . $this->getClientConf('serverpassword') . "\r\n" . $login_string;

        $validate = $this->clientFormat($login_string);

        $this->timeConnected = time();
        $this->pushAfter($validate);
        $this->status = STATUS_CONNECTED_SENTREGDATA;
        $this->timerClass->addTimer($this->randomHash(), $this, "regTimeout", $this->sockInt, REGISTRATION_TIMEOUT);


    }


    public function regTimeout($sockInt)
    {
        if ($sockInt != $this->sockInt) {
            return false;
        }

        if ($this->status != STATUS_CONNECTED_SENTREGDATA) {
            return false;
        }

        $this->disconnect();

        $this->status = STATUS_ERROR;
        $this->exception = new ConnectionTimeout("Session Authentication Timed out");

        return false;
    }

    //The following function kills this irc object, but ONLY if there is no send queue.
    //A good way to use it is with a timer, say, ever second or so.
    public function shutDown()
    {
        if (!$this->conn->getError()) {
            return;
        }

        /*
        if ($this->socketClass->hasWriteQueue($this->sockInt))
        {
            return true;
        }

        $this->disconnect();
        */
        $this->parserClass->destroyModules();
        $this->dccClass->closeAll();
        $this->procQueue->removeOwner($this);
        $date = date("h:i:s a, m-d-y");
        $this->log("The bot successfully shutdown, $date");

        //Okay.. now the bot is PSEUDO dead.  It still exists, however it has no open sockets, it will not
        //attempt to reconnect to the server, and all dcc sessions, modules, timers, and processes related to it
        //have been destroyed.

        return false;

    }

    /* Some assorted timers */

    public function checkNick()
    {
        if ($this->getStatusRaw() != STATUS_CONNECTED_REGISTERED) {
            return true;
        }

        if ($this->nick != $this->getClientConf('nick')) {
            $this->changeNick($this->getClientConf('nick'));
        }

        return true;
    }

    public function checkPingTimeout()
    {
        if ($this->getStatusRaw() != STATUS_CONNECTED_REGISTERED) {
            return true;
        }

        try {
            if ($this->lastPing == 1) {
                $this->lastPing = 0;
                throw new ConnectionTimeout("The connection with the server timed out.");
            } else {
                if (time() > $this->timeoutTimer + PING_TIMEOUT + $this->lagTime) {
                    $this->pushBefore($this->clientFormat("PING :Lagtime"));
                    $this->lastPingTime = time();
                    $this->lastPing = 1;
                }
            }
        } catch (ConnectionTimeout $e) {
            $this->disconnect();
            $this->status = STATUS_ERROR;
            $this->exception = $e;
        }

        return true;
    }

    public function getStatusRaw()
    {
        return $this->status;
    }


    public function getLine()
    {
        return $this->lVars;
    }

    private function readInput()
    {
        if ($this->status != STATUS_CONNECTED_REGISTERED && $this->status != STATUS_CONNECTED_SENTREGDATA) {
            return false;
        }

        if ($this->socketClass->isDead($this->sockInt) && !$this->socketClass->hasLine($this->sockInt)) {
            $this->disconnect();
            $this->status = STATUS_ERROR;
            $this->exception = new ReadException("Failed while reading from socket");
            return false;
        }

        if ($this->socketClass->hasLine($this->sockInt)) {
            $this->timeoutTimer = time();

            if ($this->lastPing == 1) {
                $this->lagTime = time() - $this->lastPingTime;
            }
            $this->lastPing = 0;

            $line = $this->socketClass->getQueueLine($this->sockInt);

            $this->stats['BYTESDOWN'] += strlen($line);

            $this->log($line);

            if (substr($line, 0, 1) != ":") {
                $line = ":Server " . $line;
            }

            $line = substr($line, 1);

            $parts = explode(chr(32), $line);

            $params = substr($line, strlen($parts[0]) + strlen($parts[1]) + strlen($parts[2]) + 3);
            if (strpos($params, " :")) {
                $params = substr($params, 0, strpos($params, " :"));
            }

            $offset1 = strpos($parts[0], '!');
            $offset2 = $offset1 + 1;
            $offset3 = strpos($parts[0], '@') + 1;
            $offset4 = $offset3 - $offset2 - 1;
            $offset5 = strpos($line, " :") + 2;

            unset($this->lVars);

            $this->lVars = array('from' => $parts[0],
                'fromNick' => substr($parts[0], 0, $offset1),
                'fromIdent' => substr($parts[0], $offset2, $offset4),
                'fromHost' => substr($parts[0], $offset3),
                'cmd' => $parts[1],
                'to' => $parts[2],
                'text' => substr($line, $offset5),
                'params' => trim($params),
                'raw' => ":" . $line,
            );

            if ($offset5 === false) {
                $line['text'] = "";
            }

            if (intval($this->lVars['cmd']) > 0) {
                $this->parseServerMsgs($this->lVars['cmd']);
            } else {
                $this->parseMsgs();
            }

            $this->parserClass->parseLine($this->lVars);
        }

        if ($this->socketClass->hasQueue($this->sockInt)) {
            return true;
        }

        return false;
    }


    private function parseServerMsgs($cmd)
    {
        switch ($cmd) {
            case 004:
                $this->status = STATUS_CONNECTED_REGISTERED;
                if ($this->tempNick != "") {
                    $this->nick = $this->tempNick;
                }
                // oper login
                if ($this->getClientConf('operlogin') != "") {
                    $oper_string = "OPER " . $this->getClientConf('operlogin');
                    $validate = $this->clientFormat($oper_string);
                    $this->pushAfter($validate);
                }
                break;

            case 005:
                $this->parseServerConfig();
                if (!isset($this->modeArray) || !is_array($this->modeArray) || count($this->modeArray) <= 0) {
                    if ($this->getServerConf("CHANMODES") != "") {
                        $this->createModeArray();
                        $this->checkChans();


                    }
                }
                break;

            case 311:
                $params = explode(chr(32), $this->lVars['params']);
                $this->setMemberData($params[0], $params[1], $params[2]);

            case 324:
                $params = explode(chr(32), $this->lVars['params']);
                $channel = $params[0];
                $query = substr($this->lVars['params'], strlen($channel) + 1);
                $this->setChannelData($channel, "modes", $query);
                break;

            case 329:
                $params = explode(chr(32), $this->lVars['params']);
                $channel = $params[0];
                $query = substr($this->lVars['params'], strlen($channel) + 1);
                $this->setChannelData($channel, "created", $query);
                break;

            case 332:
                $this->setChannelData(trim($this->lVars['params']), "topic", $this->lVars['text']);
                break;

            case 333:
                $params = explode(chr(32), $this->lVars['params']);
                $channel = $params[0];
                $query = substr($this->lVars['params'], strlen($channel) + 1);
                $this->setChannelData($channel, "topicby", $query);
                break;

            case 352:
                $params = explode(chr(32), $this->lVars['params']);
                $this->changeMember($params[0], $params[4], $params[4], $params[1], "", "", $params[2]);
                break;

            case 353:
                $channel = substr($this->lVars['params'], 2);
                $this->updateOpList($channel);
                break;

            case 367:
                $params = explode(chr(32), $this->lVars['params']);
                $data = $this->getChannelData($params[0]);
                if ($data != NULL) {
                    if ($data->banComplete == 1) {
                        $this->clearBanList($params[0]);
                        $data->banComplete = 0;
                    }

                    $this->changeChannel($params[0], "+", "b", array($params[1], $params[3]));
                }
                break;

            case 368:
                $params = explode(chr(32), $this->lVars['params']);
                $channel = $params[0];
                $this->setChannelData($channel, "bancomplete", 1);
                break;

            case 401:
                $this->removeQueues($this->lVars['params']);
                break;

            case 433:
                if ($this->getStatusRaw() != STATUS_CONNECTED_REGISTERED) {
                    if ($this->nick == $this->getClientConf('nick')) {
                        $this->changeNick($this->nick . rand() % 1000);
                    }
                    $this->nickTimer = time();
                }
                break;
        }

    }

    public function isOnline($nick, $chan)
    {
        return $this->isMode($nick, $chan, "online");
    }


    private function updateOpList($channel)
    {
        $channel = $this->myStrToLower($channel);
        $users = explode(chr(32), $this->lVars['text']);

        if (!isset($this->prefixArray) || count($this->prefixArray) <= 0) {
            $this->createPrefixArray();
        }

        foreach ($users AS $user) {
            if (trim($user) == "") {
                continue;
            }

            $userModes = "";
            $userNick = "";

            for ($currIndex = 0; $currIndex < strlen($user); $currIndex++) {
                $currChar = substr($user, $currIndex, 1);

                if (!isset($this->prefixArray[$currChar])) {
                    $userNick = substr($user, $currIndex);
                    break;
                }

                $userModes .= $currChar;
            }

            if ($userNick != $this->nick) {
                $this->addmember($channel, $userNick, "", $this->convertUserModes($userModes));
            }

        }

    }


    private function convertUserModes($modes)
    {
        $newModes = "";

        for ($index = 0; $index < strlen($modes); $index++) {
            $newModes .= $this->prefixArray[$modes[$index]];
        }

        return $newModes;
    }


    private function createPrefixArray()
    {
        $modeSymbols = substr($this->getServerConf('PREFIX'), strpos($this->getServerConf('PREFIX'), ")") + 1);

        $leftParan = strpos($this->getServerConf('PREFIX'), "(");
        $rightParan = strpos($this->getServerConf('PREFIX'), ")");
        $modeLetters = substr($this->getServerConf('PREFIX'), $leftParan + 1, $rightParan - $leftParan - 1);

        for ($index = 0; $index < strlen($modeLetters); $index++) {
            $this->prefixArray[$modeSymbols[$index]] = $modeLetters[$index];
        }

    }


    public function doMode()
    {
        $this->modeQueueAdded = false;

        $currAct = "";
        $currChan = "";

        $modeLineModes = "";
        $modeLineParams = "";

        $maxModesPerLine = ($this->getServerConf('MODES') == "" ? 1 : $this->getServerConf('MODES'));
        $currLineModes = 0;

        foreach ($this->modeQueue AS $modeChange) {
            if ($modeLineModes != "" && ($currChan != $modeChange['CHANNEL'] || $currLineModes >= $maxModesPerLine)) {
                $this->pushAfter($this->clientFormat("MODE " . $currChan . " " . $modeLineModes . " " . trim($modeLineParams)));
                $modeLineModes = "";
                $currAct = "";
                $currChan = "";
                $modeLineParams = "";
                $currLineModes = 0;
            }

            if ($currAct != $modeChange['ACT']) {
                $modeLineModes .= $modeChange['ACT'];
            }

            $modeLineModes .= $modeChange['MODE'];

            if ($modeChange['USER'] != "") {
                $modeLineParams .= $modeChange['USER'] . " ";
            }

            $currLineModes++;

            $currAct = $modeChange['ACT'];
            $currChan = $modeChange['CHANNEL'];

        }

        if ($modeLineModes != "") {
            $this->pushAfter($this->clientFormat("MODE " . $currChan . " " . $modeLineModes . " " . trim($modeLineParams)));
        }

        unset($this->modeQueue);
        $this->modeQueue = array();
        $this->modeQueueLength = 0;

        return false;

    }

    public function changeMode($chan, $act, $mode, $user)
    {
        $user = trim($user);
        $chan = trim($chan);
        $act = trim($act);
        $mode = trim($mode);

        if ($chan == "" || $mode == "") {
            return false;
        }

        if (!($act == "+" || $act == "-")) {
            return false;
        }

        if (strlen($mode) > 1) {
            return false;
        }

        if (!isset($this->modeArray[$mode])) {
            if ($user == "") {
                return false;
            }
        }

        if ($this->modeQueueAdded != true) {
            $this->timerClass->addTimer("mode_timer", $this, "doMode", "", 0, true);
            $this->modeQueueAdded = true;
        }

        $this->modeQueue[] = array('USER' => $user, 'CHANNEL' => $chan, 'ACT' => $act, 'MODE' => $mode);
        $this->modeQueueLength++;

        return true;

    }

    public function parseModes($modeString)
    {
        $modeString .= " ";

        $offset = strpos($modeString, chr(32));
        $modes = substr($modeString, 0, $offset);
        $users = substr($modeString, $offset + 1);
        $userArray = explode(chr(32), $users);

        if (count($this->modeArray) <= 0) {
            $this->createModeArray();
        }

        $action = "";
        $returnModes = array();

        while (trim($modes) != "") {
            $thisMode = substr($modes, 0, 1);

            $modes = substr($modes, 1);

            if ($thisMode == "-" || $thisMode == "+") {
                $action = $thisMode;
                continue;
            }

            if (strpos($this->getServerConf('CHANMODES'), $thisMode) !== false) {
                if (!isset($this->modeArray[$thisMode])) {
                    return false;
                }

                $type = $this->modeArray[$thisMode];
                $extra = "";
                if ($type != BY_NONE) {
                    $extra = array_shift($userArray);
                }

                $type = CHANNEL_MODE;

            } else {
                $extra = array_shift($userArray);
                $type = USER_MODE;
            }

            $returnModes[] = array('ACTION' => $action,
                'MODE' => $thisMode,
                'EXTRA' => $extra,
                'TYPE' => $type,
            );

        }

        return $returnModes;
    }

    public static function intToSizeString($size)
    {

        $i = 20;
        while ($size > pow(2, $i)) {
            $i += 10;
        }

        switch ($i) {
            case 20:  //kb
                $num = $size / 1000;
                $type = "KB";
                break;
            case 30:  //mb
                $num = $size / 1000000;
                $type = "MB";
                break;
            case 40:  //gb
                $num = $size / 1000000000;
                $type = "GB";
                break;
            case 50:  //tb
                $num = $size / 1000000000000;
                $type = "TB";
                break;
            default:  //pb
                $num = $size / 1000000000000000;
                $type = "PB";
                break;
        }

        $stringSize = round($num, 2) . $type;

        return $stringSize;

    }


    public function checkIgnore($mask)
    {
        $ignore = $this->getClientConf('ignore');

        if ($ignore == "") {
            return false;
        }

        if (!is_array($ignore)) {
            $ignore = array($ignore);
        }

        foreach ($ignore AS $ig) {
            $case = $this->hostMasksMatch($mask, $ig);

            if ($case) {
                return true;
            }
        }

        return false;
    }


    private function parseMsgs()
    {

        switch ($this->lVars['cmd']) {
            case "JOIN":
                $chan = $this->lVars['to'];
                if (substr($this->lVars['to'], 0, 1) == ":") {
                    $chan = substr($this->lVars['to'], 1);
                }
                $this->addMember($chan, $this->lVars['fromNick'], $this->lVars['fromIdent'], "", $this->lVars['fromHost']);
                if ($this->lVars['fromNick'] == $this->getNick()) {
                    $this->sendRaw("MODE " . $chan);

                    if (isset($this->clientConfig['populatebans'])) {
                        $this->sendRaw("MODE " . $chan . " +b");
                    }

                    if (isset($this->clientConfig['populatewho'])) {
                        $this->sendRaw("WHO " . $chan);
                    }
                }
                break;

            case "PART":
                if ($this->lVars['fromNick'] == $this->nick) {
                    $this->removeChannel($this->lVars['to']);
                } else {
                    $this->removeMember($this->lVars['to'], $this->lVars['fromNick']);
                }
                break;

            case "QUIT":
                if ($this->lVars['fromNick'] == $this->nick) {
                    $this->purgeChanList();
                } else {
                    $this->removeMember("", $this->lVars['fromNick']);
                }
                break;

            case "NICK":
                if ($this->lVars['fromNick'] == $this->nick) {
                    $this->nick = $this->lVars['text'];
                }
                $this->changeMember("", $this->lVars['fromNick'], $this->lVars['text'], "", "", "");
                break;

            case "KICK":
                if ($this->myStrToLower($this->lVars['params']) == $this->myStrToLower($this->nick)) {
                    $this->removeChannel($this->lVars['to']);
                    $this->joinChannel($this->lVars['to']);
                } else {
                    $this->removeMember($this->lVars['to'], $this->lVars['params']);
                }
                break;

            case "MODE":
                $channel = $this->myStrToLower($this->lVars['to']);
                if ($channel == $this->myStrToLower($this->nick))
                    break;
                $modes = $this->parseModes($this->lVars['params']);
                foreach ($modes AS $mode) {
                    if ($mode['TYPE'] == CHANNEL_MODE) {
                        $this->changeChannel($channel, $mode['ACTION'], $mode['MODE'], $mode['EXTRA']);
                    } else {
                        $this->changeMember($channel, $mode['EXTRA'], $mode['EXTRA'], "", $mode['MODE'], $mode['ACTION']);
                    }
                    unset($mode);
                }
                unset($modes);
                break;
            case "NOTICE":
                if ($this->checkIgnore($this->lVars['from'])) {
                    return;
                }
                if ($this->myStrToLower($this->lVars['fromNick']) == "nickserv") {
                    if (strpos($this->myStrToLower($this->lVars['text']), "identify") !== false) {
                        if ($this->getClientConf('password') != "") {
                            $this->pushBefore($this->clientFormat("PRIVMSG NickServ :IDENTIFY " . $this->getClientConf('password')));
                        }
                    }
                }
                break;
            case "PRIVMSG":
                if ($this->checkIgnore($this->lVars['from'])) {
                    return;
                }
                if (strpos($this->lVars['text'], chr(1)) !== false) {
                    $this->parseCtcp();
                }
                break;
            case "TOPIC":
                $this->setChannelData($this->lVars['to'], "topic", $this->lVars['text']);
                break;

            case "PING":
                if ($this->lVars['from'] == "Server") {
                    $this->pushBefore($this->clientFormat("PONG :" . $this->lVars['text']));
                }

            default:
                break;


        }


    }


    private function parseCtcp()
    {
        $cmd = str_replace(chr(1), "", $this->lVars['text']) . " ";
        $query = trim(substr($cmd, strpos($cmd, chr(32)) + 1));
        $cmd = substr($this->myStrToLower($cmd), 0, strpos($cmd, chr(32)));

        $msg = "";

        switch ($cmd) {
            case "version":
                // PLEASE DO NOT CHANGE THE FOLLOWING LINE OF CODE.  It is the only way for people to know that this project
                // exists.  If you would like to change it, please leave the project name/version or url in there somewhere,
                // so that others may find this project as you have. :)
                $msg = "PHP-iRC v" . VERSION . " [" . VERSION_DATE . "] by Manick (visit http://www.phpbots.org/ to download)";
                $this->notice($this->lVars['fromNick'], chr(1) . $msg . chr(1));
                $msg = "";
                $this->showModules($this->lVars['fromNick']);
                break;

            case "time":
                $msg = "My current time is " . date("l, F jS, Y @ g:i a O", time()) . ".";
                break;

            case "uptime":
                $msg = "My uptime is " . $this->timeFormat($this->getRunTime(), "%d days, %h hours, %m minutes, and %s seconds.");
                break;

            case "ping":
                $msg = "PING " . $query;

        }

        if ($msg != "") {
            $this->notice($this->lVars['fromNick'], chr(1) . $msg . chr(1));
        }

    }

    //Split huge lines up by spaces 255 by default
    public static function multiLine($text, $separator = " ")
    {
        $returnArray = array();
        $text = trim($text);
        $strlen = strlen($text);
        $sepSize = strlen($separator);

        while ($strlen > 0) {
            if (256 > $strlen) {
                $returnArray[] = $text;
                break;
            }

            for ($i = 255; $i > 0; $i--) {
                if (substr($text, $i, $sepSize) == $separator) {
                    break;
                }
            }

            if ($i <= 0) {
                $returnArray[] = substr($text, 0, 255);
                $text = substr($text, 254);
                $strlen -= 255;
            } else {
                $returnArray[] = substr($text, 0, $i);
                $text = substr($text, $i - 1);
                $strlen -= $i;
            }
        }

        return $returnArray;
    }


    private function showModules($nick)
    {
        $cmdList = $this->parserClass->getCmdList();

        if (isset($cmdList['file'])) {
            $mod = "";

            foreach ($cmdList['file'] AS $module) {
                $class = $module['class'];

                if (isset($class->dontShow) && $class->dontShow == true) {
                    continue;
                }
                $mod .= "[" . $class->title . " " . $class->version . "] ";
            }

            if ($mod != "") {
                $modArray = $this->multiLine("Running Modules: " . $mod);

                foreach ($modArray AS $myMod) {
                    $this->notice($nick, chr(1) . $myMod . chr(1));
                }
            }
        }
        unset($cmdList);
    }


    public function getRunTime()
    {
        return (time() - $this->startTime);
    }


    public static function timeFormat($time, $format)
    {

        $days = 0;
        $seconds = 0;
        $minutes = 0;
        $hours = 0;

        if (strpos($format, "%d") !== FALSE) {
            $days = (int)($time / (3600 * 24));
            $time -= ($days * (3600 * 24));
        }

        if (strpos($format, "%h") !== FALSE) {
            $hours = (int)($time / (3600));
            $time -= ($hours * (3600));
        }

        if (strpos($format, "%m") !== FALSE) {
            $minutes = (int)($time / (60));
            $time -= ($minutes * (60));
        }

        $seconds = $time;

        $format = str_replace("%d", $days, $format);
        $format = str_replace("%s", $seconds, $format);
        $format = str_replace("%m", $minutes, $format);
        $format = str_replace("%h", $hours, $format);

        return $format;

    }


    private function createModeArray()
    {
        $modeArray = explode(",", $this->getServerConf('CHANMODES'));

        for ($i = 0; $i < count($modeArray); $i++) {
            for ($j = 0; $j < strlen($modeArray[$i]); $j++) {
                $this->modeArray[$modeArray[$i][$j]] = $i;
            }

        }
    }

    public function checkChans()
    {
        if ($this->getStatusRaw() != STATUS_CONNECTED_REGISTERED) {
            return true;
        }

        foreach ($this->maintainChannels AS $index => $channel) {
            if ($this->isOnline($this->nick, $channel['CHANNEL']) === false) {
                if ($channel['KEY'] != "") {
                    $this->joinChannel($channel['CHANNEL'] . " " . $channel['KEY']);
                } else {
                    $this->joinChannel($channel['CHANNEL']);
                }
            }
        }

        return true;
    }

    public function getStatusString($status)
    {
        $msg = "";

        switch ($status) {
            case STATUS_IDLE:
                $msg = "Idle";
                break;
            case STATUS_ERROR:
                $msg = "Error";
                break;
            case STATUS_CONNECTING:
                $msg = "Connecting to server...";
                break;
            case STATUS_CONNECTED:
                $msg = "Connected to server: " . $this->getClientConf('server') . " " . $this->getClientConf('port');
                break;
            case STATUS_CONNECTED_SENTREGDATA:
                $msg = "Sent registration data, awaiting reply...";
                break;
            case STATUS_CONNECTED_REGISTERED:
                $msg = "Authenticated";
                break;
            default:
                $msg = "Unknown";
        }

        return $msg;
    }


    public function purgeMaintainList()
    {
        unset($this->maintainChannels);
        $this->maintainChannels = array();
    }


    public function removeMaintain($channel)
    {
        $channel = $this->myStrToLower($channel);

        foreach ($this->maintainChannels AS $index => $chan) {
            if ($chan['CHANNEL'] == $channel) {
                unset($this->maintainChannels[$index]);
                break;
            }
        }
    }


    public function maintainChannel($channel, $key = "")
    {
        $channel = $this->myStrToLower($channel);
        $this->maintainChannels[] = array('CHANNEL' => $channel, 'KEY' => $key);
    }


    public function joinChannel($chan)
    {
        $this->pushBefore($this->clientFormat("JOIN " . $chan));
    }

    public function changeNick($nick)
    {
        $this->pushBefore($this->clientFormat("NICK " . $nick));
        $this->tempNick = $nick;
    }


    private function parseServerConfig()
    {
        $args = explode(chr(32), $this->lVars['params']);

        foreach ($args AS $arg) {

            if (strpos($arg, "=") === false) {
                $arg .= "=1";
            }

            $argParts = explode("=", $arg);

            $this->serverConfig[$argParts[0]] = $argParts[1];

        }
    }

    private function clientFormat($text)
    {
        return array("USER" => "*", "TEXT" => $text);
    }


    public function removeQueues($nick)
    {
        $nick = $this->myStrToLower($nick);

        foreach ($this->textQueue AS $index => $queue) {
            if ($this->myStrToLower($queue['USER']) == $nick) {
                unset($this->textQueue[$index]);
                $this->textQueueLength--;
            }
        }


    }

    public function getStats()
    {
        return $this->stats;
    }

    public function doQueue()
    {
        if ($this->status < STATUS_CONNECTED) {
            $this->textQueueAdded = false;
            return false;
        }

        if ($this->socketClass->hasWriteQueue($this->sockInt) !== false) {
            return true;
        }

        if ($this->textQueueLength < 0) {
            if (is_array($this->textQueue)) {
                unset($this->textQueue);
                $this->textQueue = array();
            }
            $this->textQueueAdded = false;
            return false;
        }

        $bufferSize = $this->getClientConf("queuebuffer");

        $bufferSize = $bufferSize <= 0 ? 0 : $bufferSize;

        $sendData = "";

        $nextItem = array_shift($this->textQueue);

        if (trim($nextItem['TEXT']) != "") {
            $sendData .= $nextItem['TEXT'] . "\r\n";
        }

        unset($nextItem);

        $this->textQueueLength--;

        while ($this->textQueueLength > 0 && ((strlen($this->textQueue[0]['TEXT']) + strlen($sendData)) < $bufferSize)) {

            $nextItem = array_shift($this->textQueue);

            if (trim($nextItem['TEXT']) != "") {
                $sendData .= $nextItem['TEXT'] . "\r\n";
                unset($nextItem);
            }

            $this->textQueueLength--;

        }

        $this->stats['BYTESUP'] += strlen($sendData);

        $this->writeToSocket($sendData);

        unset($sendData);

        return true;
    }


    private function writeToSocket($sendData)
    {

        if (DEBUG == 1) {
            $this->log($sendData);
        }

        try {
            if ($this->socketClass->sendSocket($this->sockInt, $sendData) === false) {
                throw new SendDataException("Could not write to socket");
            }
        } catch (SendDataException $e) {
            $this->disconnect();
            $this->exception = $e;
            $this->status = STATUS_ERROR;
        }

    }


    private function pushAfter($data)
    {
        $this->textQueueLength++;
        $this->textQueue[] = $data;

        if ($this->textQueueAdded == false) {
            $this->timerClass->addTimer("queue_timer", $this, "doQueue", "", $this->getQueueTimeout(), true);
            $this->textQueueAdded = true;
        }

    }


    private function pushBefore($data)
    {
        $this->textQueueLength++;
        $this->textQueue = array_merge(array($data), $this->textQueue);

        if ($this->textQueueAdded == false) {
            $this->timerClass->addTimer("queue_timer", $this, "doQueue", "", $this->getQueueTimeout(), true);
            $this->textQueueAdded = true;
        }
    }

    public function sendRaw($text, $force = false)
    {
        if ($force == false) {
            $format = $this->clientFormat($text);
            $this->pushBefore($format);
        } else {
            $this->writeToSocket($text . "\r\n");
        }
    }


    public function privMsg($who, $msg, $queue = 1)
    {
        $text = array('USER' => $who,
            'TEXT' => 'PRIVMSG ' . $who . ' :' . $msg);

        if ($queue) {
            $this->pushAfter($text);
        } else {
            $this->pushBefore($text);
        }
    }


    public function action($who, $msg, $queue = 1)
    {
        $text = array('USER' => $who,
            'TEXT' => 'PRIVMSG ' . $who . ' :' . chr(1) . 'ACTION ' . $msg . chr(1));

        if ($queue) {
            $this->pushAfter($text);
        } else {
            $this->pushBefore($text);
        }
    }


    public function notice($who, $msg, $queue = 1)
    {

        $text = array('USER' => $who,
            'TEXT' => 'NOTICE ' . $who . ' :' . $msg);

        if ($queue) {
            $this->pushAfter($text);
        } else {
            $this->pushBefore($text);
        }
    }

    public function getClientIP($long = 1)
    {
        if ($long == 1) {
            return $this->clientLongIP;
        } else {
            return $this->clientIP;
        }
    }

    public function setClientIP($ip = "")
    {
        if ($ip == "") {
            $ip = $this->socketClass->getHost($this->sockInt);
        }

        $this->clientIP = $ip;

        $this->clientLongIP = ip2long($this->clientIP);

        if ($this->clientLongIP <= 0) {
            $this->clientLongIP += pow(2, 32);
        }
    }


    public function purgeTextQueue()
    {
        $this->textQueueTime = 0;
        unset($this->textQueue);
        $this->textQueue = array();
        $this->textQueueLength = 0;
    }

    public function getTextQueueLength()
    {
        return $this->textQueueLength;
    }

    public function log($data)
    {
        $network = $this->getServerConf('Network') == "" ? $this->getClientConf('server') : $this->getServerConf('Network');

        if (DEBUG == 1) {
            echo "[" . date("h:i:s") . "] " . "({$this->nick}@$network) > " . $data . "\n";
        } else {
            if ($this->getClientConf('logfile') != "") {
                error_log("[" . date("h:i:s") . "] " . "({$this->nick}@$network) > " . $data . "\n", 3, $this->getClientConf('logfile'));
            }
        }
    }

    public function getUsageList()
    {
        return $this->usageList;
    }

    public function floodCheck($line)
    {
        $host = $line['fromHost'];

        if (!array_key_exists($host, $this->usageList)) {
            $this->usageList[$host] = new usageLink;
            $this->usageList[$host]->isBanned = false;
            $this->usageList[$host]->lastTimeUsed = time();
            $this->usageList[$host]->timesUsed = 1;
            $user = $this->usageList[$host];
        } else {
            $user = $this->usageList[$host];
            $floodcheck = $this->getClientConf('floodcheck');

            if ($floodcheck == true) {
                $floodTime = intval($this->getClientConf('floodtime'));
                if ($floodTime <= 0) {
                    $floodTime = 60;
                }

                if ($user->isBanned == true) {
                    if ($user->timeBanned > time() - $floodTime) {
                        return STATUS_ALREADY_BANNED;
                    }
                    $user->isBanned = false;
                }


                if ($user->lastTimeUsed < time() - 10) {
                    $user->timesUsed = 0;
                }
                $user->lastTimeUsed = time();
                $user->timesUsed++;
            }
            $numLines = intval($this->getClientConf('floodlines'));
            if ($numLines <= 0) {
                $numLines = 5;
            }

            if ($user->timesUsed > $numLines) {
                $user->isBanned = true;
                $user->timeBanned = time();
                $user->timesUsed = 0;
                $user->lastTimeUsed = 0;
                $this->removeQueues($line['fromNick']);
                return STATUS_JUST_BANNED;
            }
        }
        return STATUS_NOT_BANNED;
    }

    public static function myStrToLower($text)
    {
        $textA = strtolower($text);

        $textA = str_replace("\\", "|", $textA);
        $textA = str_replace("[", "{", $textA);
        $textA = str_replace("]", "}", $textA);
        $textA = str_replace("~", "^", $textA);

        return $textA;
    }

    public static function myStrToUpper($text)
    {
        $textA = strtoupper($text);

        $textA = str_replace("|", "\\", $textA);
        $textA = str_replace("{", "[", $textA);
        $textA = str_replace("}", "]", $textA);
        $textA = str_replace("^", "~", $textA);

        return $textA;
    }

    public static function randomHash()
    {
        return md5(uniqid(rand(), true));
    }

    private function getQueueTimeout()
    {
        $timeout = $this->getClientConf("queuetimeout");
        $timeout = $timeout <= 0 ? 0 : $timeout;
        return $timeout;
    }

    public function addQuery($host, $port, $query, $line, $class, $function)
    {
        $remote = new remote($host, $port, $query, $line, $class, $function, 8);
        $remote->setIrcClass($this);
        $remote->setTimerClass($this->timerClass);
        $remote->setSocketClass($this->socketClass);
        return $remote->connect();
    }

}

?>