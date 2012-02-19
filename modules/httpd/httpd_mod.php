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

class httpd_mod extends module {

	public $title = "HTTP/1.0 Server";
	public $author = "Manick";
	public $version = "0.1";

	private $httpListener;
	private $error;

	//easily referenced from httpConfig;
	private $port;
	private $root;
	private $defaultIndex;
	
	private $httpConfig;
	
	private $index;

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
		
		//$this->timerClass->addTimer("httpd_check_listeners", $this, "handleSessions", "", 0.25, false);
	}

	/* Destroy our socket listener */
	public function destroy()
	{
		$this->httpListener->disconnect();

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
		if ($error == true)
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
				//Get next 10 lines from input, instead of just getting all lines (keeping TIME
				//in mind!)
				for ($i = 0; $i < 10; $i++)
				{
					if ($this->socketClass->hasLine($conn->sockInt))
					{
						$conn->queue[] = $response = $this->socketClass->getQueueLine($conn->sockInt);
						
						echo $response . "\n";

						if ($response == "")
						{
							echo "request ended...\n";
							$conn->requestEnded = true;
							$this->respondSession($connInt);
							break;
						}
					}
				}
			}
		}

		if ($oconn->requestEnded == true)
		{
			return false;
		}
		else
		{
			return $this->socketClass->hasLine($oconn->sockInt);
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




	/* Checks connections for new data, and appends it to the queue.  Then, attempts
	 * to parse that data and handle the http request, unless the request never ends and 8
	 * seconds comes first...
	 */
	public function handleSessions($args)
	{
		if ($error == true)
		{
			return;
		}

		foreach($this->conn AS $index => $conn)
		{
			$sockData = $this->socketClass->getSockData($conn->sockInt);

			if ($sockData === false)
			{
				unset($this->conn[$index]);
				continue;
			}

			if ($this->socketClass->isDead($conn->sockInt))
			{
				$this->destroySession($index);
				continue;
			}

			if ($conn->requestEnded === false)
			{
				if (time() > $conn->time + 15)
				{
					$this->destroySession($index);
					continue;
				}
				
				$count = count($conn->queue);

				if ($count > 50)
				{
					//Uhh.. fuck this...
					$this->destroySession($index);
					continue;
				}

				if ($this->socketClass->hasLine($conn->sockInt))
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
								break;
							}
						}
					}
				}
			}
			else
			{
				if ($conn->finished === true)
				{
					if (time() > $conn->time + 15)
					{
						//We took too long..
						$this->destroySession($index);
					}

					if ($this->socketClass->hasWriteQueue($conn->sockInt))
					{
						continue;
					}
					else
					{
						$this->destroySession($index);
					}
				}
				else
				{

					/* Now the real juicy stuff, we have a full query (maybe...).. lets respond! */

					$this->respondSession($index);
				}

			}
		}

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
		$query = "HTTP/1.0 " . $type;
		if (isset($conn->request["Host"]))
		{
			$query .= "Host: " . $conn->request["Host"];
		}
		$query .= "\r\n" . $conn->writeHeaders;
		$query .= "Content-Length: " . strlen($conn->writeQueue) . "\r\n\r\n";

		$query .= $conn->writeQueue;

		$conn->finished = true;
		$conn->time = time();
		$this->socketClass->sendSocket($conn->sockInt, $query);
		$conn->writeQueue = "";
	}

	private function loadConfig()
	{
		$httpConfig = new ini("modules/httpd/http.ini");

		if ($httpConfig->getError())
		{
			return false;
		}

		$this->port = intval($httpConfig->getIniVal("config","port"));
		if ($this->port == 0)
		{
			return false;
		}

		$root = $httpConfig->getIniVal("config","root");
		if ($root === false)
		{
			$root = "";
		}
		$this->root = $root;
		
		$defaultIndex = $httpConfig->getIniVal("config", "defaultindex");
		
		if ($defaultIndex === false)
		{
			$defaultIndex = "index.html";
		}

		$this->defaultIndex = $defaultIndex;
		
		$this->httpConfig = $httpConfig;

		return true;
	}

	private function respondSession($index)
	{
		$conn = $this->conn[$index];

		$request = $this->parseRequest($conn);
		
		switch($request->command)
		{
			case "GET":
			
				$file = $this->getFile($request->target);

				if ($file === false)
				{
					$this->sendResponse($conn, "404 Not Found");
					$this->endResponse($conn, HTTP_NOT_FOUND);
				}
				else
				{
					$ext = $this->getExt($file);
					$handler = $this->httpConfig->getIniVal("handler", $ext);
					
					if ($handler === false)
					{
						$ifp = fopen($file, "rb");
						if ($ifp === false)
						{
							$this->sendResponse($conn, "404 Not Found");
							$this->endResponse($conn, HTTP_NOT_FOUND);
						}
						else
						{
							$fileContents = "";
							while (!feof($ifp))
							{
								$fileContents .= fread($ifp, 4096);
							}
							fclose($ifp);
							
							$conn->writeHeaders .= "Content-Type: " . $this->getType($file) . "\r\n";
	
							$this->sendResponse($conn, $fileContents);
							$this->endResponse($conn, HTTP_OK);
						}
					}
					else
					{
						$this->handler($conn, $handler, $file);
					}
				}
				break;
			default:
				$this->sendResponse($conn, "Unknown Command");
				$this->endResponse($conn, HTTP_BAD_REQUEST);
				break;
		}
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

	private function parseRequest($conn)
	{
		$request = new http_request;
		
		$line = array_shift($conn->queue);
		
		//Request line...
		
		$lineArray = explode(chr(32), $line);
		
		$request->command = strtoupper($lineArray[0]);

		$target = $lineArray[1];
		$offsetA = strpos($target, "?");

		//Only parse get querys for now....

		if ($offsetA !== false)
		{
			$query = substr($target, $offsetA + 1);
			$target = substr($target, 0, $offsetA);
			$request->query = $this->parseQuery($query);
		}
		
		$request->target = $target;

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
				else
				{
					$request->randomData[] = $line;
				}
			}
		}

		return $request;
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
	
	private function generateIndex($directory)
	{

	}

}

define('HTTP_OK', "200 OK");
define('HTTP_BAD_REQUEST', "400 Bad Request");
define('HTTP_NOT_FOUND', "404 Not Found");

class http_request {
	public $command;
	public $target;
	public $query = array();
	public $headers = array();
	public $randomData = array();
	public $postData = "";
	public $writeHeaders = "\r\n";
}

class http_session {
	public $connection;
	public $sockInt;
	public $requestEnded;
	public $finished;
	public $time;
	public $queue;
	public $writeQueue = "";
}

?>
