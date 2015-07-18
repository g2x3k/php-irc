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
|   > error module
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

class ConnectException extends Exception {
 	
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


class SendDataException extends Exception {
 	
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



class ConnectionTimeout extends Exception {

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



class ReadException extends Exception {

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
