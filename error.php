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
|   > error class module
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

class ConnectException extends Exception
{

    private $exceptionTime = 0;

    function __construct($message)
    {
        parent::__construct($message);
        $this->exceptionTime = time();
    }

    function getTime()
    {
        return $this->exceptionTime;
    }
}


class SendDataException extends Exception
{

    private $exceptionTime = 0;

    function __construct($message)
    {
        parent::__construct($message);
        $this->exceptionTime = time();
    }

    function getTime()
    {
        return $this->exceptionTime;
    }
}


class ConnectionTimeout extends Exception
{

    private $exceptionTime = 0;

    function __construct($message)
    {
        parent::__construct($message);
        $this->exceptionTime = time();
    }

    function getTime()
    {
        return $this->exceptionTime;
    }
}


class ReadException extends Exception
{

    private $exceptionTime = 0;

    function __construct($message)
    {
        parent::__construct($message);
        $this->exceptionTime = time();
    }

    function getTime()
    {
        return $this->exceptionTime;
    }
}

?>
