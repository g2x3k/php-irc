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
|   > parser class module
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

class parser
{

    private $cmd;
    private $args;
    private $timers = array();

    private $cmdList = array();
    private $cmdTypes = array();

    private $fileModified = array();
    private $loadDefError = false;

    //Classes
    private $ircClass;
    private $dccClass;
    private $timerClass;
    private $socketClass;
    private $db;

    /*  Part of easy alias idea I've been working on..., look below for large bit of commented out code
        for info...

        private $aliasArray = array(	"notice"			=>	"ircClass",
                                        "privMsg"			=>	"ircClass",
                                        "action"			=>	"ircClass",
                                        "sendRaw"			=>	"ircClass",
                                        "getNick"			=>	"ircClass",
                                        "isOnline"			=>	"ircClass",
                                        "isMode"			=>	"ircClass",
                                        "isChanMode"		=>	"ircClass",
                                        "sendFile"			=>	"dccClass",
                                        "dccInform"			=>	"dccClass",
                                        "addTimer"			=>	"timerClass",
                                        "addListener"		=>	"socketClass",
                                        "removeTimer"		=>	"timerClass",
        );
    */

    public function __construct()
    {
        $this->fileModified = array();
    }

    public function init()
    {

        if ($this->ircClass->getClientConf('functionfile') != "") {
            $this->loadFuncs($this->ircClass->getClientConf('functionfile'));
        }

    }

    public function setDccClass($class)
    {
        $this->dccClass = $class;
    }

    public function setIrcClass($class)
    {
        $this->ircClass = $class;
    }

    public function setTimerClass($class)
    {
        $this->timerClass = $class;
    }

    public function setSocketClass($class)
    {
        $this->socketClass = $class;
    }

    public function setDatabase($class)
    {
        $this->db = $class;
    }

    public function getCmdList($type = "")
    {
        if ($type == "") {
            return $this->cmdList;
        } else {
            if (isset($this->cmdList[$type])) {
                return $this->cmdList[$type];
            } else {
                return false;
            }
        }
    }

    public function destroyModules()
    {
        if (is_array($this->cmdList) && count($this->cmdList) > 0) {
            if (isset($this->cmdList['file']) && is_array($this->cmdList['file'])) {
                foreach ($this->cmdList['file'] AS $index => $data) {
                    if (is_object($data['class'])) {
                        $data['class']->destroy();
                    }
                }
            }
        }
    }

    private function readFile($file)
    {

        $configRaw = file_get_contents($file);

        if ($configRaw === false) {
            if (DEBUG == 1) {
                echo "Could not find function file '$file' or error.\n";
            }
            $this->dccClass->dccInform("Could not find function file '$file' or error.");
            return false;
        }

        return $configRaw;

    }

    public function include_recurse($file)
    {
        $configRaw = $this->readFile($file);

        if ($configRaw === false) {
            return false;
        }

        $configRaw = preg_replace("/;(.*\n?)?/", "\n", $configRaw);
        $configRaw = preg_replace("/~.*\n/", "", $configRaw);

        $configRaw = trim($configRaw);

        $lines = explode("\n", $configRaw);

        $num = 0;
        $lineNo = 1;
        $extra = 0;
        $fullLine = "";
        foreach ($lines as $line) {
            $line = trim($line);

            if ($line == "") {
                $lineNo++;
                continue;
            }

            $line = trim($fullLine . " " . $line);

            $newLine = $this->parseFunc($file, $lineNo, $line);

            $lineNo += $extra + 1;
            $extra = 0;
            $fullLine = "";

            if ($newLine === false) {
                continue;
            }

            if ($newLine['type'] == "type") {
                $this->cmdTypes[$newLine['typeArray']['name']]['numArgs'] = count($newLine['typeArray']);
                $this->cmdTypes[$newLine['typeArray']['name']]['args'] = $newLine['typeArray'];
            } else if ($newLine['type'] == "include") {
                if (isset($newLine['typeArray'][0])) {
                    $num += $this->include_recurse($newLine['typeArray'][0]);
                } else {
                    if (DEBUG == 1) {
                        echo "Malformed include line on line " . $lineNo . " of function file: " . $file . "\n";
                    }
                    $this->dccClass->dccInform("Malformed include line on line " . $lineNo . " of function file: " . $file);
                }
            } else {
                if (!isset($newLine['typeArray']['name'])) {
                    $name = irc::randomHash() . "_" . rand(1, 1024);
                } else {
                    $name = $newLine['typeArray']['name'];
                }

                unset($newLine['typeArray']['name']);

                $this->cmdList[$newLine['type']][$name] = $newLine['typeArray'];

                if (isset($this->cmdList[$newLine['type']][$name]['usage'])) {
                    if (isset($oldCmdList[$newLine['type']][$name]['usage'])) {
                        $this->cmdList[$newLine['type']][$name]['usage'] = $oldCmdList[$newLine['type']][$name]['usage'];
                    }
                }

            }

            $num++;
        }

        return $num;
    }


    public function loadFuncs($file)
    {
        $error = false;

        clearstatcache();

        $this->destroyModules();

        $oldCmdList = $this->cmdList;
        if (!is_array($oldCmdList)) {
            $oldCmdList = array();
        }

        unset($this->cmdList);
        unset($this->cmdTypes);
        $this->cmdList = array();
        $this->cmdTypes = array();

        $this->dccClass->dccInform("Rehashing Function File, please wait...");
        if (DEBUG == 1) {
            echo "Rehashing Function File, please wait...\n";
        }

        //Read in main function file
        $num = $this->include_recurse($file);

        //Book keeping
        foreach ($this->cmdList AS $cmd => $data) {
            ksort($this->cmdList[$cmd]);
        }

        if (isset($this->cmdList['file'])) {
            foreach ($this->cmdList['file'] AS $file => $data) {

                $classDef = $this->loadClassDef($data['filename'], $file);

                if ($this->loadDefError == true) {
                    $error = true;
                }
                $this->loadDefError = false;

                if ($classDef === false) {
                    continue;
                }

                //require_once($data['filename']);

                $this->cmdList['file'][$file]['class'] = new $classDef;

                $this->cmdList['file'][$file]['class']->__setClasses($this->ircClass,
                    $this->dccClass,
                    $this->timerClass,
                    $this,
                    $this->socketClass,
                    $this->db
                );
                $this->cmdList['file'][$file]['class']->init();
            }
        }

        $this->dccClass->dccInform("Successfully loaded " . $num . " functions into memory.");
        if (DEBUG == 1) {
            echo "Successfully loaded " . $num . " functions into memory.\n";
        }

        return $error;

    }

    private function loadClassError($filename, $msg)
    {
        $this->ircClass->log("Error loading $filename: $msg");
        $this->dccClass->dccInform("Error loading $filename: $msg");
    }

    /* Okay..
     * This method is really freaking cool.  It loads a module file with fopen, then
     * it finds the classname, and gives it a random name (changes it) so that you can
     * include functions you've changed multiple times without having to restart the bot!
     * Also, easier names than having to use $this->ircClass->blah() all the time, you can
     * just use blah()... These are defined in an array somewhere... I'm not sure where yet...
     * because I haven't written that yet!!!!!!!!!!!!111oneone.
     */

    private function loadClassDef($filename, $classname)
    {

        $stat = stat($filename);

        if ($stat === false) {
            $this->loadClassError($filename, "Could not find function file");
            return false;
        }

        $modified = $stat['mtime'];

        if (isset($this->fileModified[$filename])) {
            if ($modified == $this->fileModified[$filename]['modified']) {
                return $this->fileModified[$filename]['classdef'];
            }
        }

        $fileData = file_get_contents($filename);

        if ($fileData === false) {
            $this->loadClassError($filename, "Could not find function file");
            return false;
        }

        //Okay, we have the module now.. now we need to find some stuff.

        if (!preg_match("/class[\s]+?" . $classname . "[\s]+?extends[\s]+?module[\s]+?{/", $fileData)) {
            $this->loadClassError($filename, "Could not find valid classdef in function file");
            return false;
        }

        //Okay, our module is in the file... replace it with random hash.

        $newHash = irc::randomHash();

        $newClassDef = $classname . "_" . $newHash;

        $fileData = preg_replace("/(class[\s]+?)" . $classname . "([\s]+?extends[\s]+?module[\s]+?{)/", "\\1" . $newClassDef . "\\2", $fileData);

        /* Interesting idea, but lets leave it out for now
        foreach($this->aliasArray AS $func => $class)
        {
            $fileData = preg_replace("/([=\n\(\t\s]+?)".$func."[\s\t\n\r]*?\(/s", "\\1\$this->" . $class . "->" . $func . "(", $fileData);
        }
        */

        $success = eval("?>" . $fileData . "<?php ");

        if ($success === false) {
            $this->loadClassError($filename, "Error in function file");

            /* Attempt to fallback on a previous revision that worked! */
            if (isset($this->fileModified[$filename])) {
                $this->loadClassError($filename, "Using a cached version of the class definition");
                $this->loadDefError = true;
                return $this->fileModified[$filename]['classdef'];
            }

            return false;
        }

        $this->fileModified[$filename]['modified'] = $modified;
        $this->fileModified[$filename]['classdef'] = $newClassDef;

        return $newClassDef;

    }

    //Used to show array, as there seems to be some crazy bug in var_dump/print_r that
    //shows EVERY variable in my program when I do var_dump($this->cmdList) or use print_r the same way
    //This isn't used anywhere in the production copy of this script. (DEBUG ONLY!)
    private function show_all($title, $array, $level)
    {
        echo $title . " = array(" . "\r\n";

        foreach ($array AS $index => $val) {
            for ($i = 0; $i < $level; $i++) {
                echo " ";
            }

            if (is_array($val)) {
                $this->show_all($index, $val, $level + 1);
            } else if (is_object($val)) {
                echo "[$index] => [object]\r\n";
            } else {
                echo "[$index] => [$val]\r\n";
            }
        }

        for ($i = 0; $i < $level; $i++) {
            echo " ";
        }

        echo ")\r\n";

    }

    public function setCmdListValue($type, $cmd, $var, $value)
    {
        if (isset($this->cmdList[$type][$cmd][$var])) {
            $this->cmdList[$type][$cmd][$var] = $value;
            return true;
        }
        return false;
    }

    private function parseFunc($file, $lineNo, $line)
    {
        $strings = array();
        $line = str_replace("\t", " ", $line);

        $quotes = array("'", "\"");

        foreach ($quotes AS $quote) {
            $currPos = 0;
            $extraPos = 0;
            while (($firstPos = strpos($line, $quote, $currPos)) !== false && substr($line, strpos($line, $quote, $currPos) - 1, 1) != "\\") {

                while (($secondPos = strpos($line, $quote, $firstPos + 1 + $extraPos)) !== false && substr($line, strpos($line, $quote, $firstPos + 1 + $extraPos) - 1, 1) == "\\") {
                    $extraPos = $secondPos;
                }

                if ($secondPos === false) {
                    if (DEBUG == 1) {
                        echo "Syntax Error on line " . $lineNo . " of function file: " . $file . ".  Expected '" . $quote . "', got end of line.\n";
                    }
                    $this->dccClass->dccInform("Syntax Error on line " . $lineNo . " of function file: " . $file . ".  Expected '" . $quote . "', got end of line.");
                    return false;
                }

                $strings[$quote][] = substr($line, $firstPos + 1, $secondPos - $firstPos - 1);
                $currPos = $secondPos + 1;
            }
        }

        foreach ($strings AS $string) {
            $line = str_replace($string, "", $line);
        }

        $lineElements = explode(chr(32), $line);

        $type = "";
        $currElement = 0;
        $typeArray = array();

        foreach ($lineElements AS $element) {
            if (trim($element) == "") {
                continue;
            }

            $currElement++;

            if ($currElement == 1) {
                $element = irc::myStrToLower($element);

                if ($element == "type") {
                    $type = "type";
                } else if ($element == "include") {
                    $type = "include";
                } else {
                    if (isset($this->cmdTypes[$element])) {
                        $type = $element;
                    } else {
                        if (DEBUG == 1) {
                            echo "Error: Undefined type, '" . $element . "' on line " . $lineNo . " of function file: " . $file . "\n";
                        }
                        $this->dccClass->dccInform("Error: Undefined type, '" . $element . "' on line " . $lineNo . " of function file: " . $file . "");
                        return false;
                    }
                }
                continue;
            }

            if ($element == "\"\"") {
                $element = array_shift($strings["\""]);
            } else if ($element == "''") {
                $element = array_shift($strings["'"]);
            }

            $element = str_replace("\\" . "'", "'", $element);
            $element = str_replace("\\" . '"', '"', $element);

            if ($type == "type") {
                if ($currElement == 2) {
                    $typeArray['name'] = $element;
                } else {
                    $typeArray[] = $element;
                }
            } else if ($type == "include") {
                $typeArray[] = $element;
            } else {
                if ($currElement > $this->cmdTypes[$type]['numArgs']) {
                    if (DEBUG == 1) {
                        echo "Error on line " . $lineNo . " of function file: " . $file . ", too many arguments\n";
                    }
                    $this->dccClass->dccInform("Error on line " . $lineNo . " of function file: " . $file . ", too many arguments");
                    return false;
                }

                $element = (irc::myStrToLower($element) == "true" ? true : $element);
                $element = (irc::myStrToLower($element) == "false" ? false : $element);

                $typeArray[$this->cmdTypes[$type]['args'][$currElement - 2]] = $element;
            }


        }

        if ($type != "type" && $type != "include") {
            if ($currElement < $this->cmdTypes[$type]['numArgs']) {
                if (DEBUG == 1) {
                    echo "Error on line " . $lineNo . " of function file: " . $file . ", not enough arguments\n";
                }
                $this->dccClass->dccInform("Error on line " . $lineNo . " of function file: " . $file . ", not enough arguments");
                return false;
            }
        }

        return array('type' => $type, 'typeArray' => $typeArray);

    }


    public function parseDcc($chat, $handler)
    {

        $chat->readQueue = str_replace("\r", "", $chat->readQueue);

//		if (!($offSet = strpos($chat->readQueue, "\n")))
//		{
//			return false;
//		}
//		$rawLine = trim(substr($chat->readQueue, 0, $offSet));
//		$chat->readQueue = substr($chat->readQueue, $offSet + 1);

        $rawLine = $chat->readQueue;
        $chat->readQueue = "";

        $this->ircClass->log("DCC Chat(" . $chat->nick . "): " . $rawLine);

        $line = $this->createLine($rawLine);

        if ($line == false) {
            return;
        }

        if ($handler != false) {
            if (is_object($handler)) {
                $handler->handle($chat, $line);
                return;
            }
        }

        if ($chat->isAdmin == true && $chat->verified == false) {
            if (md5($line['cmd']) == $this->ircClass->getClientConf('dccadminpass')) {
                $this->dccClass->dccInform("DCC: " . $chat->nick . " has successfully logged in.");
                $chat->verified = true;
                $chat->dccSend("You have successfully logged in.");

            } else {
                $chat->dccSend("Invalid password, bye bye.");
                $this->dccClass->disconnect($chat);
            }
            return;
        }

        $cmdLower = irc::myStrToLower($line['cmd']);

        if (isset($this->cmdList['dcc'][$cmdLower])) {
            if ($this->cmdList['dcc'][$cmdLower]['admin'] == 1 && !$chat->isAdmin) {
                $chat->dccSend("Request Denied.  You must have admin access to use this function.");
                return;
            }

            if ($line['nargs'] < $this->cmdList['dcc'][$cmdLower]['numArgs']) {
                $chat->dccSend("Usage: " . $cmdLower . " " . $this->cmdList['dcc'][$cmdLower]['usage']);
                return;
            }

            $module = $this->cmdList['dcc'][$cmdLower]['module'];
            $class = $this->cmdList['file'][$module]['class'];
            $func = $this->cmdList['dcc'][$cmdLower]['function'];

            $class->$func($chat, $line);

            if ($chat->isAdmin) {
                $chat->dccSend("ADMIN " . irc::myStrToUpper($cmdLower) . " Requested");
            } else {
                $chat->dccSend("CLIENT " . irc::myStrToUpper($cmdLower) . " Requested");
            }
        } else {
            $chat->dccSend("Invalid Command: " . $line['cmd']);
        }

    }


    public static function createLine($rawLine)
    {

        $line = array();
        $rawLineArray = explode(chr(32), $rawLine);
        $lineCount = count($rawLineArray);

        if ($lineCount < 1) {
            return false;
        } else if ($lineCount == 1) {
            $line['cmd'] = irc::myStrToLower($rawLine);
            $line['nargs'] = 0;
            $line['query'] = "";
        } else {
            $line['nargs'] = 0;
            $line['cmd'] = irc::myStrToLower(array_shift($rawLineArray));
            while (($arg = array_shift($rawLineArray)) !== NULL) // NULL fixed contributed by cortex, 05/01/05
            {
                if (trim($arg) == "") {
                    continue;
                }

                $line['arg' . ++$line['nargs']] = $arg;
                if ($line['nargs'] > MAX_ARGS - 1) {
                    break;
                }
            }
            $line['query'] = trim(substr($rawLine, strlen($line['cmd']) + 1));
        }

        return $line;

    }

    public function parseLine($line)
    {
        if (DEBUG == 1) {
            //print_r($line);
        }

        if ($this->ircClass->checkIgnore($line['from'])) {
            return;
        }

        switch ($line['cmd']) {
            case "PRIVMSG":
                $args = $this->createLine($line['text']);
                $cmdLower = irc::myStrToLower($args['cmd']);
                if (isset($this->cmdList['priv'][$cmdLower])) {
                    if ($this->cmdList['priv'][$cmdLower]['active'] == true) {


                        $theCase = $this->ircClass->floodCheck($line);

                        switch ($theCase) {
                            case STATUS_NOT_BANNED:
                                if ($this->cmdList['priv'][$cmdLower]['inform'] == true) {
                                    $this->dccClass->dccInform("Sending " . irc::myStrToUpper($line['text']) . " to " . $line['fromNick']);
                                }
                                $this->cmdList['priv'][$cmdLower]['usage']++;
                                $func = $this->cmdList['priv'][$cmdLower]['function'];
                                $module = $this->cmdList['priv'][$cmdLower]['module'];
                                $class = $this->cmdList['file'][$module]['class'];

                                /*
                                if ($this->ircClass->getTextQueueLength() > 5) {
                                    $this->ircClass->notice($line['fromNick'], "Request Queued.  Please wait " . $this->ircClass->getTextQueueLength() . " seconds for your data.", 0);
                                }
                                */

                                $class->$func($line, $args);
                                break;
                            case STATUS_JUST_BANNED:
                                $this->ircClass->notice($line['fromNick'], "Flood Detected. All of your queues have been discarded and you have been banned from using this bot for " . $this->ircClass->getClientConf('floodtime') . " seconds.");
                                $this->dccClass->dccInform("BAN: (*!" . irc::myStrToUpper($line['fromHost']) . "): " . $line['fromNick'] . " is on ignore for " . $this->ircClass->getClientConf('floodtime') . " seconds.");
                                break;
                            case STATUS_ALREADY_BANNED:
                                break;
                        }
                    } else {
                        $this->dccClass->dccInform("FUNCTION: " . $line['fromNick'] . " attempted to use deactivated command '" . $cmdLower . "'");
                    }
                } else {
                    if ($line['to'] == $this->ircClass->getNick()) {
                        if (strpos($line['text'], chr(1)) !== false) {
                            $this->ircClass->floodCheck($line);
                            $this->parseCtcp($line);
                        } else {
                            $this->dccClass->dccInform("PRIVMSG: <" . $line['fromNick'] . "> " . $line['text']);
                        }
                    } else {
                        if (strpos($line['text'], chr(1)) !== false) {
                            $this->parseCtcp($line, "CHAN: " . $line['to']);
                        } else {
                            $chanData = $this->ircClass->getChannelData($line['to']);

                            if ($chanData == NULL) {
                                $this->dccClass->dccInform("CHAN PRIVMSG [" . $line['to'] . "]: <" . $line['fromNick'] . "> " . $line['text']);
                            }
                        }
                    }
                }
                break;

            case "MODE":
                break;

            case "NOTICE":
                $chan = $line['to'] != $this->ircClass->getNick() ? ":" . $line['to'] : "";
                $this->dccClass->dccInform("NOTICE: <" . ($line['fromNick'] == "" ? $line['from'] : $line['fromNick']) . $chan . "> " . $line['text']);
                break;

            case "JOIN":
                if ($line['fromNick'] == $this->ircClass->getNick()) {
                    $this->dccClass->dccInform("Joined: " . irc::myStrToUpper($line['text']));
                }
                break;

            case "PART":
                if ($line['fromNick'] == $this->ircClass->getNick()) {
                    $this->dccClass->dccInform("Parted: " . irc::myStrToUpper($line['to']));
                }
                break;

            case "KICK":
                if ($line['params'] == $this->ircClass->getNick()) {
                    $this->dccClass->dccInform("Kicked: " . $line['fromNick'] . " kicked you from " . $line['to']);
                }
                break;

            case "ERROR":
                $this->dccClass->dccInform("Server Error: " . $line['text']);
                break;

            case "366":
                $params = explode(chr(32), $line['params']);
                $channel = $params[0];
                $this->dccClass->dccInform("Finished receiving NAMES list for " . $channel);
                break;

            case "005":
                if ($this->ircClass->getServerConf("NETWORK") != "") {
                    //Only show this once...
                    if (strpos($line['params'], "NETWORK") !== false) {
                        $this->dccClass->dccInform("Parsing IRC-Server specific configuration...");
                        $this->dccClass->dccInform("Network has been identified as " . $this->ircClass->getServerConf("NETWORK") .
                            "(" . $line['from'] . ")");
                    }
                }
                break;

            case "315":
                $params = explode(chr(32), $line['params']);
                $this->dccClass->dccInform("Finished receiving WHO list for: " . $params[0]);
                break;

            case "368":
                $params = explode(chr(32), $line['params']);
                $this->dccClass->dccInform("Finished receiving ban list for: " . $params[0]);
                break;

            case "433":
                $this->dccClass->dccInform("Nick collision!  Unable to change your nick.  Nickname already in use!");
                break;

            default:
                break;

        }

        // Lets alias 004 to CONNECT, for the n00bs

        if ($line['cmd'] == "004") {
            $line['cmd'] = "connect";
        }
        if ($line['cmd'] == "error") {
            $line['cmd'] = "disconnect";
        }

        // Action type handler
        if (isset($this->cmdList['action']) && strtolower($line['cmd']) == "privmsg") {
            if (substr($line['text'], 0, 8) == chr(1) . "ACTION ") {
                $newLine = $line;
                $newLine['text'] = substr($line['text'], 8, strlen($line['text']) - 9);

                $sArgs = $this->createLine($newLine['text']);

                foreach ($this->cmdList['action'] AS $item) {
                    $func = $item['function'];
                    $class = $this->cmdList['file'][$item['module']]['class'];
                    $class->$func($newLine, $sArgs);
                }
            }
        }

        // Raw type handler
        if (isset($this->cmdList['raw'])) {
            if (!isset($args)) {
                $args = $this->createLine($line['text']);
            }

            foreach ($this->cmdList['raw'] AS $item) {
                $func = $item['function'];
                $class = $this->cmdList['file'][$item['module']]['class'];
                $class->$func($line, $args);
            }
        }


        // Here we will call any type

        if (isset($this->cmdList[irc::myStrToLower($line['cmd'])])) {
            if (!isset($args)) {
                $args = $this->createLine($line['text']);
            }

            foreach ($this->cmdList[irc::myStrToLower($line['cmd'])] AS $item) {
                $func = $item['function'];
                $class = $this->cmdList['file'][$item['module']]['class'];
                $class->$func($line, $args);
            }
        }

        if (isset($args)) {
            unset($args);
        }

    }


    /* Misc Functions */

    private function parseCtcp($line, $msgs = "PRIVMSG")
    {
        $cmd = str_replace(chr(1), "", $line['text']) . " ";
        $query = trim(substr($cmd, strpos($cmd, chr(32)) + 1));
        $cmd = substr(irc::myStrToLower($cmd), 0, strpos($cmd, chr(32)));

        $msg = "";

        switch ($cmd) {
            case "version":
                $this->dccClass->dccInform("CTCP VERSION: " . $line['fromNick'] . " versioned us.");
                break;

            case "time":
                $this->dccClass->dccInform("CTCP TIME: " . $line['fromNick'] . " requested the time.");
                break;

            case "uptime":
                $this->dccClass->dccInform("CTCP UPTIME: " . $line['fromNick'] . " requested our uptime.");
                break;

            case "ping":
                $this->dccClass->dccInform("CTCP PING: " . $line['fromNick'] . " pinged us.");
                break;

            case "dcc":
                $vars = explode(chr(32), $query);
                $this->dccParse($line, $vars, $query);
                break;

        }

        if ($msg != "") {
            $this->notice($this->lVars['fromNick'], chr(1) . $msg . chr(1));
        }

        if (isset($this->cmdList['ctcp'][$cmd])) {
            $func = $this->cmdList['ctcp'][$cmd]['function'];
            $class = $this->cmdList['file'][$this->cmdList['ctcp'][$cmd]['module']]['class'];
            $args = $this->createLine($cmd . " " . $query);
            $class->$func($line, $args);
        }

    }

    function dccParse($line, $vars, $query)
    {
        $cVars = count($vars);

        if ($cVars < 1) {
            return;
        }

        $cmd = irc::myStrToUpper($vars[0]);

        switch ($cmd) {
            case "CHAT":
                if ($cVars == 4) {
                    $iplong = long2ip((double)$vars[2]);
                    $port = $vars[3];
                    $this->dccClass->addChat($line['fromNick'], $iplong, (int)$port, false, null);
                }
                break;
            case "SEND":
                if ($this->ircClass->getClientConf('upload') != 'yes') {
                    $this->ircClass->notice($line['fromNick'], "DCC: I do not accept dcc transfers at this time.", 0);
                    break;
                }
                if ($cVars >= 5) {
                    //Some bastard sent a file with spaces.  Shit. Ass.
                    if (strpos($query, chr(34)) !== false) {
                        $first = strpos($query, chr(34));
                        $second = strpos($query, chr(34), $first + 1);
                        $filename = substr($query, $first + 1, $second - $first - 1);
                        $query = str_replace("\"" . $filename . "\"", "file.ext", $query);
                        $vars = explode(chr(32), $query);
                    } else {
                        $filename = $vars[1];
                    }

                    $iplong = long2ip((double)$vars[2]);
                    $port = $vars[3];
                    $filesize = $vars[4];

                    $this->dccClass->addFile($line['fromNick'], $iplong, (int)$port, DOWNLOAD, $filename, $filesize);
                }
                break;
            case "ACCEPT":
                if ($cVars == 4) {
                    $port = $vars[2];
                    $bytes = $vars[3];
                    $this->dccClass->dccAccept($port, $bytes);
                }
                break;
            case "RESUME":
                if ($cVars == 4) {
                    $port = $vars[2];
                    $bytes = $vars[3];
                    $this->dccClass->dccResume($port, $bytes);
                }
                break;
        }

    }

}

/* Used to access dcc admin commands via private message */

class chat_wrapper
{

    public $nick;
    private $ircClass;
    public $isAdmin;

    public function __construct($nick, $ircClass)
    {
        $this->nick = $nick;
        $this->ircClass = $ircClass;
        $this->isAdmin = 1;
    }

    public function dccSend($data, $to = null)
    {
        $this->ircClass->privMsg($this->nick, "--> " . $data);
    }

    public function disconnect($msg = "")
    {
        $this->ircClass->privMsg($this->nick, "Right........");
    }

}

?>
