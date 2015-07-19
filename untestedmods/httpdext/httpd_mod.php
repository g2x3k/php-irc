<?php
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
|   > httpd module
|   > Module written by Manick
|   > Module Version Number: 0.0.1
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

//This is a quick implementation of an http server.  It doesn't really support all the
//spiffy http stuff, like persistant connections and chunked enocding... but it gets
//the job done. (That and I really have no interest in reading the especially long
//HTTP RFC.)

require_once("modules/httpdext/httpd_extra.php");

class httpd_mod extends module {

	public $title = "HTTP/1.0 Server";
	public $author = "Manick";
	public $version = "0.2";

	private $httpListener;
	private $error;

	//html
	private $html;

	//easily referenced from httpConfig;
	private $port;

	//config
	private $httpConfig;

	private $conn = array(); // Array of all of our active http connections

	/* Create our socket listener */
	public function init()
	{
		$this->error = false;

		if ($this->loadConfig() === false)
		{
			$this->error = true;
			return;
		}

		$conn = new connection(null, $this->port, 0);

		$conn->setSocketClass($this->socketClass);
		$conn->setIrcClass($this->ircClass);
		$conn->setCallbackClass($this);
		$conn->setTimerClass($this->timerClass);

		$conn->setTransTimeout(15);
		
		$conn->init();

		if ($conn->getError())
		{
			$this->error == true;
			return;
		}

		$this->httpListener = $conn;
		$this->html = $this;
	}

	/* Destroy our socket listener */
	public function destroy()
	{
		$this->html = null;

		if ($this->error === false)
		{
			$this->httpListener->disconnect();
		}

		foreach ($this->conn AS $index => $conn)
		{
			$this->destroySession($index);
		}
	}

	/* Setup a new http session, and init variables
	 * Adds the sock to our database which is handled every half second
	 */
	private function addSession($conn)
	{
		if ($this->error == true)
		{
			return;
		}

		$http = new http_session;

		$http->connection = $conn;
		$http->sockInt = $conn->getSockInt();
		$http->queue = array();
		$http->time = time();
		$http->requestEnded = false;
		$http->finished = false;

		$this->conn[$http->sockInt] = $http;
	}


	public function onTransferTimeout($conn)
	{
	
		$connInt = $conn->getSockInt();

		if (!isset($this->conn[$connInt]))
		{
			$conn->disconnect();
			return false;
		}

		$this->destroySession($connInt);
	}

	public function onRead($connection)
	{
		//Find associated $connection index;

		$connInt = $connection->getSockInt();

		if (!isset($this->conn[$connInt]))
		{
			$connection->disconnect();
			return false;
		}

		$conn = $this->conn[$connInt];

		if ($conn->requestEnded === false)
		{
			if (time() > $conn->time + 15)
			{
				$this->destroySession($connInt);
				return false;
			}

			$count = count($conn->queue);

			if ($count > 50)
			{
				//Uhh.. fuck this...
				$this->destroySession($connInt);
				return false;
			}

			if ($this->socketClass->hasLine($conn->sockInt))
			{
				if ($conn->request === null)
				{
					$request = $this->socketClass->getQueueLine($conn->sockInt);

					if (!$this->parseRequest($conn, $request))
					{
						//Client sent an evil http request, screw them.
						$this->destroySession($connInt);
						return false;
					}
					
					return true;

					//Interesting, seems they want to chat!
				}
				else
				{
					if ($conn->requestEnded === false)
					{
						//Get next 10 lines from input, instead of just getting all lines (keeping TIME
						//in mind!)

						for ($i = 0; $i < 10; $i++)
						{
							if ($this->socketClass->hasLine($conn->sockInt))
							{
								$conn->queue[] = $response = $this->socketClass->getQueueLine($conn->sockInt);
								
								if ($response == "")
								{
									$conn->requestEnded = true;

									if ($conn->request->command == "GET")
									{
										$this->parseHeaders($conn);
										$this->respondSession($connInt);
									}
									else
									{
										$this->parseHeaders($conn);
									}
									break;
								}
							}
						}
					}
					else
					{
						if (!isset($conn->request->headers['Content-Length']))
						{
							$this->destroySession($connInt);
							return false;
						}

						$size =& $conn->request->headers['Content-Length'];

						if ($conn->request->postLength >= $size)
						{
							$conn->request->post = $this->parseQuery($conn->request->postData);
							$this->respondSession($connInt);
						}
						else
						{
							$data = $this->socketClass->getQueue();
							$len = strlen($data);
							$conn->request->postData .= $data;
							$conn->request->postLength += $len;
						}
					}
				}
			}
		}

		if ($conn->requestEnded == true)
		{
			return false;
		}
		else
		{
			return $this->socketClass->hasLine($conn->sockInt);
		}
	}

	public function onWrite($connection)
	{
		$connInt = $connection->getSockInt();

		if (!isset($this->conn[$connInt]))
		{
			$connection->disconnect();
			return false;
		}

		$conn = $this->conn[$connInt];

		if ($conn->finished === true)
		{
			if (time() > $conn->time + 15)
			{
				//We took too long..
				$this->destroySession($connInt);
			}

			if ($this->socketClass->hasWriteQueue($conn->sockInt))
			{
				return false;
			}
			else
			{
				$this->destroySession($connInt);
			}
		}
	}

	public function onAccept($listener, $new)
	{
		$this->addSession($new);
	}
	
	public function onDead($conn)
	{
		if ($conn === $this->httpListener)
		{
			$this->error = true;
			return false;
		}

		$connInt = $conn->getSockInt();

		if (!isset($this->conn[$connInt]))
		{
			$conn->disconnect();
			return false;
		}

		$this->destroySession($connInt);
	}


	private function destroySession($index)
	{
		$conn = $this->conn[$index];

		if (!is_object($conn))
		{
			return;
		}

		$conn->connection->disconnect();

		unset($this->conn[$index]);
	}
	
	private function handler($conn, $handler, $file)
	{
		$query = exec($handler . " " . $file);
		
		$conn->finished = true;
		$conn->time = time();
		$this->socketClass->sendSocket($conn->sockInt, $query);
		$conn->writeQueue = "";
	}


	private function endResponse($conn, $type)
	{
		$query = "HTTP/1.0 " . $type . "\r\n";
		if (isset($conn->headers["Host"]))
		{
			$query .= "Host: " . $conn->headers["Host"] . "\r\n";
		}
		$query .= $conn->request->writeHeaders;
		$query .= "Content-Length: " . strlen($conn->writeQueue) . "\r\n\r\n";

		$query .= $conn->writeQueue;

		$conn->finished = true;
		$conn->time = time();
		$this->socketClass->sendSocket($conn->sockInt, $query);
		$conn->writeQueue = "";
	}

	private function loadConfig()
	{
		$httpConfig = new ini("modules/httpdext/http.ini");

		if ($httpConfig->getError())
		{
			return false;
		}

		$this->port = intval($httpConfig->getIniVal("config","port"));
		if ($this->port == 0)
		{
			return false;
		}

		$this->httpConfig = $httpConfig;

		return true;
	}

	private function respondSession($index)
	{
		$conn = $this->conn[$index];

		$request = $conn->request;

		if ($this->httpConfig->getIniVal("auth","auth") === "true")
		{
			if (!$this->validate($conn))
			{
				$realm = $this->httpConfig->getIniVal("auth","realm");
				$conn->writeHeaders .= "WWW-Authenticate: Digest realm=\"".$realm."\", qop=\"auth\"".
								", nonce=\"".uniqid()."\", opaque=\"".md5($realm)."\"\r\n";
				$this->sendResponse($conn, "You are unauthorized to view this page!");
				$this->endResponse($conn, HTTP_AUTH);
				return false;
			}
		}

		switch($request->command)
		{
			case "GET":
				$this->handleGet($conn, $request);
				break;
			case "POST":
				$this->handlePost($conn, $request);
				break;
			default:
				$this->sendResponse($conn, "Unknown Command");
				$this->endResponse($conn, HTTP_BAD_REQUEST);
				break;
		}
	}

/************************* SKIN STUFF **********************************/
public function header()
{
return <<<EOF


EOF;
}

public function footer()
{
return <<<EOF


EOF;
}
/************************* SKIN STUFF **********************************/

	private function handleGet($conn, $request)
	{
		$out = "";

		switch($request->target)
		{
			case "/":
				$out = $this->html->header();
				$out .= $this->html->footer();
				break;

			default:
				$this->sendResponse($conn, "404 Not Found");
				$this->endResponse($conn, HTTP_NOT_FOUND);
				return;
				break;
		}

		$this->sendResponse($conn, $out);
		$this->endResponse($conn, HTTP_OK);
		return;
	}

	private function handlePost($conn, $request)
	{


	}


	/* DON'T EDIT BELOW HERE */
	/* *********************************************************** */

	private function validate($conn)
	{
		$request = $conn->request;

		$realm = $this->httpConfig->getIniVal("auth","realm");
		$user = $this->httpConfig->getIniVal("auth","user");
		$pass = $this->httpConfig->getIniVal("auth","pass");

		if (!isset($conn->request->headers['Authorization']))
		{
			return false;
		}

		$digest = array();
		//Check Authentication header....
		$auth = $conn->request->headers['Authorization'];
		$auth = trim(str_replace("Digest", "" , $auth));
		$authArray = explode(", ", $auth);
		foreach ($authArray AS $option)
		{
			$offsetA = strpos($option, "=");
			if ($offsetA === false)
			{
				return false;
			}
			$opt = substr($option, 0, $offsetA);
			if ($option[$offsetA+1] == "\"")
			{
				$val = substr($option, $offsetA + 2);
				$val = substr($val, 0, strlen($val) - 1);
			}
			else
			{
				$val = substr($option, $offsetA + 1);
			}
			$digest[$opt] = $val;
		}

		if ($digest['username'] != $user)
		{
			return false;
		}
		
		//generate valid response (basically taken from php's website...)
		$a1 = md5($digest['username'] . ":" . $realm . ":" . $pass);
		$a2 = md5($request->command . ":" . $request->target);
		$valid = md5($a1.":".$digest['nonce'].":".$digest['nc'].":".$digest['cnonce'].":".$digest['qop'].":".$a2);

		if ($digest['response'] != $valid)
		{
			return false;
		}

		//everything's good...
		return true;
	}

	private function getExt($file)
	{
		if (strpos($file, ".") !== false)
		{
			$extArray = explode(".", $file);
			$ext = array_pop($extArray);
			return $ext;
		}
		return false;
	}

	private function getType($file)
	{
		if (($ext = $this->getExt($file)) !== false)
		{
			$extType = $this->httpConfig->getIniVal("mime-types", $ext);
			if ($extType === false)
			{
				$extType = "text/plain";
			}

			return $extType;
		}
		
		return "text/plain";
	}

	private function getFile($file)
	{
		$file = trim($file);
		$file = str_replace("\\", "/", $file);

		$newDir = $this->root;

		if (strpos($file, "/") !== false)
		{
			$fArray = explode("/", $file);
			$file = array_pop($fArray);

			if ($file == "")
			{
				$file = $this->defaultIndex;
			}

			$dir = implode("/", $fArray);

			$newDir = $this->getDir($this->root, $dir);
			if ($newDir === false)
			{
				return false;
			}
		}

		$theFile = $newDir . "/" . $file;

		if (!is_file($theFile))
		{
			return false;
		}

		return $theFile;

	}
	
	private function getDir($currDir, $newDir)
	{
	
		if ($newDir == "")
		{
			return $currDir;
		}

		if ($currDir == "")
		{
			$currArray = array();
		}
		else
		{
			$currArray = explode("/", $currDir);
		}

		$newArray = explode("/", $newDir);

		if (isset($newArray[0]))
		{
			if ($newArray[0] == "")
			{
				//root dir selected
				$currArray = array();
			}

			foreach($newArray AS $location)
			{

				if ($location == "")
				{
					continue;
				}

				if ($location == "~")
				{
					return false;
				}

				if ($location == "..")
				{
					return false;
				}
				else if (str_replace(".", "", $location) == "")
				{
					return false;
				}
				else
				{
					$currArray[] = $location;
				}
			}
		}

		$newDir = implode("/", $currArray);

		if (is_dir($this->root . $newDir) === false)
		{
			return false;
		}

		return $newDir;

	}

	private function sendResponse($conn, $text)
	{
		$conn->writeQueue .= $text;
	}

	private function parseRequest($conn, $line)
	{
		if (!preg_match("/(GET|POST) ([^\s]+) HTTP\/1.([0-9]{1})/i", $line, $match))
		{
			return false;
		}

		$request = new http_request;

		$request->command = strtoupper($match[1]);

		$target = $match[2];
		$offsetA = strpos($target, "?");

		if ($offsetA !== false)
		{
			$query = substr($target, $offsetA + 1);
			$target = substr($target, 0, $offsetA);
			$request->get = $this->parseQuery($query);
		}

		$request->target = $target;

		$conn->request = $request;

		return true;
	}

	private function parseHeaders($conn)
	{
		$request = $conn->request;

		if (is_array($conn->queue))
		{

			while (($line = array_shift($conn->queue)) != NULL)
			{
				$offsetA = strpos($line, ":");

				if ($offsetA !== false)
				{
					$var = substr($line, 0, $offsetA);
					$val = substr($line, $offsetA + 1);
					$request->headers[$var] = $val;
				}
			}
		}
	}


	private function parseQuery($query)
	{
		$queryArray = array();

		$queryParts = explode("&", $query);
		$count = count($queryParts);

		//only get first 100 querys
		for ($i = 0; $i < (100 > $count ? $count : 100); $i++)
		{
			$var = array_shift($queryParts);

			if (strpos($var, "=") !== false)
			{
				$varParts = explode("=", $var);
				$var = $varParts[0];
				$val = urldecode($varParts[1]);
			}
			else
			{
				$val = "";
			}

			$queryArray[$var] = $val;
		}

		return $queryArray;
	}


}

?>
