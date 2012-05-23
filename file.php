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
|   > dcc chat module
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

class file {

  /* Chat specific Data */
  	public $id;
	public $status;
	public $sockInt;
	public $timeConnected;
	public $readQueue;
	public $port;
	public $dccString;
	public $type;
	public $transferType;
	public $nick;
	public $timeOutLevel;
	public $removed;
	public $connection;

	public $reverse; // reverse dcc?
	private $handShakeSent;
	private $handShakeTime;

	public $filename;
	public $filenameNoDir;
	public $filePointer;
	public $filesize;
	public $bytesTransfered;
	public $resumedSize;
	public $completed;
	public $reportedRecieved;

	public $connectHost;

	//private $resumed;
	private $started;

	private $sendQueue;
	private $sendQueueCount;

	//keep track of speed;
	private $speed_sec_add;
	public $speed_lastavg;
	private $speed_starttime;
	
  /* Classes */
  	private $dccClass;
	private $ircClass;
	private $socketClass;
	private $procQueue;
	private $timerClass;

	/* Constructor */
	public function __construct($id, $nick, $sockInt, $host, $port, $type, $reverse)
	{
		$this->id = $id;
		$this->nick = $nick;
		$this->sockInt = $sockInt;
		$this->connectHost = $host;
		$this->port = $port;
		$this->transferType = $type;
		$this->filesize = 0;
		$this->bytesTransfered = 0;
		$this->resumedSize = 0;
		$this->started = false;
		$this->status = DCC_WAITING;
		$this->reverse = $reverse;
		$this->handShakeSent = false;

		$this->speed_sec_add = 0;
		$this->speed_lastavg = 0;
		$this->speed_starttime = 0;

		if ($type == UPLOAD)
		{
			$this->dccString = "DCC UPLOAD[".$this->id."]: ";
		}
		else
		{
			$this->dccString = "DCC DOWNLOAD[".$this->id."]: ";
		}

	}

	public function setProcQueue($class)
	{
		$this->procQueue = $class;
	}

	public function setSocketClass($class)
	{
		$this->socketClass = $class;
	}

	public function setIrcClass($class)
	{
		$this->ircClass = $class;
	}

	public function setTimerClass($class)
	{
		$this->timerClass = $class;
	}

	public function setDccClass($class)
	{
		$this->dccClass = $class;
	}

	public function dccSend($data)
	{
		return $this->dccClass->dccSend($this, $data);
	}

	public function disconnect($msg = "")
	{

		$msg = str_replace("\r", "", $msg);
		$msg = str_replace("\n", "", $msg);

		if ($this->started == true)
		{
			fclose($this->filePointer);
		}

		if ($msg != "")
		{
			$this->dccClass->dccInform($this->dccString . "DCC session ended with " . $this->nick . " (" . $msg . ")", $this);
			$this->ircClass->notice($this->nick, "DCC session ended: " . $msg, 1);
		}
		else
		{
			$this->dccClass->dccInform($this->dccString . "DCC session ended with " . $this->nick, $this);
		}

		$this->status = false;

	  	$this->dccClass->disconnect($this);

	  	$this->connection = null;

		return true;
	}

	function xferUpload()
	{

		while ($this->readQueue != "")
		{
			$unsignedLong = substr($this->readQueue, 0, 4);

			if (strlen($unsignedLong) < 4)
			{
				break;
			}

			$sizeArray = unpack("N", $unsignedLong);

			$this->reportedRecieved = $sizeArray[1];

			$this->readQueue = substr($this->readQueue, 4);
		}

		if ($this->completed == 1)
		{
			if ($this->reportedRecieved >= $this->filesize)
			{
				$avgspeed = "";
				if ($this->speed_lastavg != 0)
				{
					$size = irc::intToSizeString($this->speed_lastavg);
					$avgspeed = " (" . $size . "/s)";
				}

				$totalTime = $this->ircClass->timeFormat(time() - $this->timeConnected, "%h hrs, %m min, %s sec");
				$size = irc::intToSizeString($this->bytesTransfered - $this->resumedSize);
				$this->disconnect("Transfer Completed, Sent " . $size . " in " . $totalTime . $avgspeed);
			}
			return;
		}

		if ($this->status != DCC_CONNECTED)
		{
			return;
		}

		if ($this->socketClass->hasWriteQueue($this->sockInt))
		{
			return;
		}

		if ($this->bytesTransfered >= $this->filesize)
		{
			$this->completed = 1;
			return;
		}

		if (time() >= $this->speed_starttime + 3)
		{
			$this->speed_lastavg = $this->speed_sec_add / 3.0;
			$this->speed_sec_add = 0;
			$this->speed_starttime = time();
		}

		if (!is_resource($this->filePointer))
		{
			$this->disconnect("File pointer is not a resource");
			return;
		}

		for ($i = 0; $i < 30; $i++)
		{
			if (($data = fread($this->filePointer, 4096)) === false)
			{
				$this->disconnect("Read error: Could not access file");
				return;
			}

			$this->dccSend($data);
			
			$dataSize = strlen($data);

			$this->bytesTransfered += $dataSize;
			$this->dccClass->addBytesUp($dataSize);
			$this->speed_sec_add += $dataSize;

			if ($this->socketClass->hasWriteQueue($this->sockInt))
			{
				break;
			}
		}

	}

	function xferDownload()
	{

		if ($this->status != DCC_CONNECTED)
		{
			return;
		}

		$readQueueSize = strlen($this->readQueue);

		if ($readQueueSize <= 0)
		{
			return;
		}

		if (fwrite($this->filePointer, $this->readQueue, $readQueueSize) === false)
		{
			$this->disconnect("Write error: Could not access file");
		}

		$this->speed_sec_add += $readQueueSize;
		$this->dccClass->addBytesDown($readQueueSize);
		$this->bytesTransfered += $readQueueSize;
		$this->readQueue = "";

		$this->dccSend(pack("N", $this->bytesTransfered));

		if ($this->bytesTransfered >= $this->filesize)
		{
			$avgspeed = "";
			if ($this->speed_lastavg != 0)
			{
				$size = irc::intToSizeString($this->speed_lastavg);
				$avgspeed = " (" . $size . "/s)";
			}

			$totalTime = $this->ircClass->timeFormat(time() - $this->timeConnected, "%h hrs, %m min, %s sec");
			$size = irc::intToSizeString($this->bytesTransfered - $this->resumedSize);
			$this->disconnect("Transfer Completed, Recieved " . $size . " in " . $totalTime . $avgspeed);
		}

		if (time() >= $this->speed_starttime + 3)
		{
			$this->speed_lastavg = $this->speed_sec_add / 3.0;
			$this->speed_sec_add = 0;
			$this->speed_starttime = time();
		}
	}


	private function doHandShake()
	{
		$this->dccSend("120 ".$this->ircClass->getNick()." ".$this->filesize." ".$this->filenameNoDir."\n");
		$this->handShakeSent = true;
		$this->timerClass->addTimer(irc::randomHash(), $this, "handShakeTimeout", "", 8);
	}

	private function processHandShake()
	{
		if ($this->readQueue == "")
		{
			return;
		}

		$response = $this->readQueue;
		$this->readQueue = "";
		$responseArray = explode(chr(32), $response);
		if ($responseArray[0] == "121")
		{
			$this->resumedSize = intval($responseArray[2]);
			$this->reverse = false;
			$this->onConnect($conn);
			return;
		}

		$this->disconnect("DCC Client Server reported error on attempt to send file");
	}

	public function handShakeTimeout()
	{
		if ($this->status != false)
		{
			if ($this->reverse == true)
			{
				$this->disconnect("DCC Reverse handshake timed out");
			}
		}
		return false;
	}
	
	

	/* Main events */
	public function onDead($conn)
	{
		if ($this->completed == 1)
		{
			$avgspeed = "";
			if ($this->speed_lastavg != 0)
			{
				$size = irc::intToSizeString($this->speed_lastavg);
				$avgspeed = " (" . $size . "/s)";
			}

			$totalTime = $this->ircClass->timeFormat(time() - $this->timeConnected, "%h hrs, %m min, %s sec");
			$size = irc::intToSizeString($this->bytesTransfered - $this->resumedSize);
			$this->disconnect("Transfer Completed, Sent " . $size . " in " . $totalTime . $avgspeed);
		}
		else
		{
			$this->disconnect($this->connection->getErrorMsg());
		}
	}

	public function onRead($conn)
	{
	
		$this->readQueue .= $this->socketClass->getQueue($this->sockInt);

		if ($this->status == DCC_CONNECTED)
		{

			if ($this->transferType == UPLOAD)
			{
				if ($this->reverse != false)
				{
					if ($this->handShakeSent != false)
					{
						$this->processHandShake();
					}
				}
			}
			else
			{
				$this->xferDownload();
			}
		}
		return false;
	}

	public function onWrite($conn)
	{
		if ($this->status == DCC_CONNECTED && $this->reverse == false)
		{
			$this->xferUpload();
		}
	}

	public function onAccept($oldConn, $newConn)
	{
		$this->dccClass->accepted($oldConn, $newConn);
		$this->connection = $newConn;
		$oldConn->disconnect();
		$this->sockInt = $newConn->getSockInt();
		$this->onConnect($newConn);
	}

	public function onTransferTimeout($conn)
	{
		$this->disconnect("Transfer timed out");
	}

	public function onConnectTimeout($conn)
	{
		$this->disconnect("Connection attempt timed out");
	}

	public function onConnect($conn)
	{
		$this->status = DCC_CONNECTED;

		$this->dccClass->dccInform($this->dccString . $this->nick . " connection established");

		if ($this->reverse != false)
		{
			$this->doHandShake();
			return;
		}

		if ($this->transferType == UPLOAD)
		{
			$this->dccClass->alterSocket($this->sockInt, SOL_SOCKET, SO_SNDBUF, 32768);

			$this->filePointer = fopen($this->filename, "rb");
			
			if ($this->filePointer === false)
			{
				$this->disconnect("Error opening local file for reading");
				return;
			}

			if ($this->resumedSize > 0)
			{
				if (fseek($this->filePointer, $this->resumedSize, SEEK_SET) == -1)
				{
					$this->disconnect("Error seeking to resumed file position in file");
					return;
				}
			}
			
			$this->xferUpload();

		}
		else
		{
			$this->dccClass->alterSocket($this->sockInt, SOL_SOCKET, SO_RCVBUF, 32768);

			$this->filePointer = fopen($this->filename, "ab");

			$this->ircClass->notice($this->nick, "DCC: Upload connection established", 0);

			if ($this->filePointer === false)
			{
				$this->disconnect("Error opening local file for writing");
				return;
			}

		}

		$this->started = true;
		$this->speed_starttime = time();

	}


	public function initialize($filename, $size = 0)
	{
		$this->reportedRecieved = 0;
		$this->completed = 0;
		$this->filesize = $size;
		$this->timeConnected = time();
		$this->timeOutLevel = 0;
		$this->readQueue = "";
		$this->type = FILE;

		if ($this->transferType == UPLOAD)
		{
			$this->filename = $filename;

			if (strpos($filename, "/") !== false)
			{
				$filenameArray = explode("/", $filename);
				$this->filenameNoDir = $filenameArray[count($filenameArray) - 1];
			}
			else if (strpos($filename, "\\") !== false)
			{
				$filenameArray = explode("\\", $filename);
				$this->filenameNoDir = $filenameArray[count($filenameArray) - 1];
			}
			else
			{
				$this->filenameNoDir = $filename;
			}

			$this->filenameNoDir = $this->cleanFilename($this->filenameNoDir);

			$this->dccClass->dccInform($this->dccString . "Initiating file transfer of (".$this->filenameNoDir.") to " . $this->nick);

			if (!$this->file_exists($this->filename))
			{
				$this->disconnect("File does not exist");
				return;
			}

			$fileSize = $this->filesize($this->filename);
			if ($fileSize === false)
			{
				$this->disconnect("File does not exist");
				return;
			}

			$this->filesize = $fileSize;


			$kbSize = irc::intToSizeString($fileSize);

			if ($this->reverse == false)
			{
				$this->ircClass->privMsg($this->nick, "\1DCC SEND " . $this->filenameNoDir . " " . $this->ircClass->getClientIP(1) . " " . $this->port . " " . $fileSize . "\1", 0);
			}

			$this->ircClass->notice($this->nick, "DCC: Sending you (\"" . $this->filenameNoDir . "\") which is " . $kbSize . " (resume supported)", 0);

		}
		else
		{
			$uldir = $this->ircClass->getClientConf('uploaddir');
			
			$lastChar = substr($uldir, strlen($uldir) - 1, 1);

			if ($lastChar != "\\" || $lastChar != "/")
			{
				$uldir .= "/";
			}
			
			$filename = $this->cleanFilename($filename);

			$this->filename = $uldir . $filename;
			$this->dccClass->dccInform($this->dccString . "Initiating file transfer of (".$filename.") from " . $this->nick);

			if ($this->file_exists($this->filename))
			{
				$bytesFinished = $this->filesize($this->filename);
				if ($bytesFinished >= $this->filesize)
				{
					$this->disconnect("Connection aborted. I already have that file.");
					return;
				}
				else
				{
					$this->status = DCC_WAITING;
					$this->bytesTransfered = $bytesFinished;
					$this->resumedSize = $bytesFinished;
					$this->ircClass->privMsg($this->nick, "\1DCC RESUME file.ext " . $this->port . " " . $bytesFinished . "\1", 0);
				}
				return;
			}

			$this->ircClass->notice($this->nick, "DCC: Upload accepted, connecting to you (" . $this->connectHost . ") on port " . $this->port . ".",0);


			$this->status = DCC_CONNECTING;
			$this->connection->connect();

		}

	}
	
	public function accepted()
	{
		$this->status = DCC_CONNECTING;
		$this->connection->connect();
	}

	public function resume($size)
	{
		$this->resumedSize = $size;
		$this->bytesTransfered = $size;
		
		$resumePlace = round($size / 1000, 0);
		$this->dccClass->dccInform($this->dccString . "Resumed at " . $resumePlace . "K");
		$this->ircClass->privMsg($this->nick, "\1DCC ACCEPT file.ext " . $this->port . " " . $size . "\1", 0);
	}

	public static function cleanFilename($filename)
	{
		$filename = str_replace("..", "__", $filename);
		$filename = str_replace(chr(47), "_", $filename);
		$filename = str_replace(chr(92), "_", $filename);
		$filename = str_replace(chr(58), "_", $filename);
		$filename = str_replace(chr(63), "_", $filename);
		$filename = str_replace(chr(34), "_", $filename);
		$filename = str_replace(chr(62), "_", $filename);
		$filename = str_replace(chr(60), "_", $filename);
		$filename = str_replace(chr(124), "_", $filename);
		$filename = str_replace(chr(32), "_", $filename);

		return $filename;
	}

	private function file_exists($filename)
	{
		$fp = @fopen($filename, "rb");
		if ($fp === false)
		{
			return false;
		}
		else
		{
			fclose($fp);
			return true;
		}
	}

	private function filesize($filename)
	{

		$fp = @fopen($filename, "rb");
		if ($fp === false)
		{
			return false;
		}
		else
		{
			$fstat = fstat($fp);
			fclose($fp);
			return $fstat['size'];
		}

	}

}

?>
