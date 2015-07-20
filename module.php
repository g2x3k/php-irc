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

abstract class module
{

    public $title = "<title>";
    public $author = "<author>";
    public $version = "<version>";
    public $dontShow = false;

    public $ircClass;
    public $dccClass;
    public $timerClass;
    public $parserClass;
    public $socketClass;
    public $db;

    public function __construct()
    {
        //Nothing here...
    }

    public function __destruct()
    {
        $this->ircClass = null;
        $this->dccClass = null;
        $this->timerClass = null;
        $this->parserClass = null;
        $this->socketClass = null;
        $this->db = null;
        //Nothing here
    }

    public final function __setClasses($ircClass, $dccClass, $timerClass, $parserClass,
                                       $socketClass, $db)
    {
        $this->ircClass = $ircClass;
        $this->dccClass = $dccClass;
        $this->timerClass = $timerClass;
        $this->parserClass = $parserClass;
        $this->socketClass = $socketClass;
        $this->db = $db;
    }

    public final function getModule($modName)
    {
        $mods = $this->parserClass->getCmdList("file");

        if ($mods === false) {
            return false;
        }

        if (isset($mods[$modName])) {
            return $mods[$modName]['class'];
        }

        return false;
    }

    public function handle($chat, $args)
    {
    }

    public function connected($chat)
    {
    }

    public function main($line, $args)
    {
        $port = $this->dccClass->addChat($line['fromNick'], null, null, false, $this);

        if ($port === false) {
            $this->ircClass->notice($line['fromNick'], "Error starting chat, please try again.", 1);
        }
    }

    public function init()
    {
        //Global.. this needs to be overwritten
    }

    public function destroy()
    {
        //Global.. this needs to be overwritten
    }

}

?>
