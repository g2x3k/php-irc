<?php

/* ftp status vars */
define('FTP_IDLE', 0);
define('FTP_CONNECTING', 1);
define('FTP_CONNECTED', 2);
define('FTP_AUTH_USER', 3);
define('FTP_AUTH_PASS', 4);
define('FTP_REGISTERED', 5);
define('FTP_RETR', 6);
define('FTP_CLOSED', 7);
define('FTP_ERROR',8);

define('MODE_PASV', 0);
define('MODE_PORT', 1);

/* progress vars */

class _ftp {

	/* this is the control and connection objects */
	private $conn_control;
	private $conn_data;
	/* these are the sockInts of the above connections */
	private $control_ID;
	private $data_ID;

	/* these are the variables for this query */
	private $host;
	private $path;
	private $port;
	private $user;
	private $pass;
	private $id;
	private $filename;

	/* runtime vars */
	private $status;
	private $err;
	private $lastmode; //used with MODE_PASV, MODE_PORT
	private $filePointer;
	private $resumedSize;

	/* notify vars */
	private $cb_class;
	private $cb_func;


	public function __construct($callback_class, $callback_func, $id, $host, $path, $port = 21, $user = "anonymous", $pass = "")
	{
		$path = urldecode($path);

		$this->host = $host;
		$this->path = $path;
		$this->user = $user;
		$this->pass = $pass;
		$this->port = $port;
		$this->id = $id;
		$this->lastmode = -1;

		$this->cb_class = $callback_class;
		$this->cb_func = $callback_func;

		$this->control_ID = -1;
		$this->data_ID = -1;
		
		//check the $path to make sure we're downloading a file
		if (strpos($path, "/") !== false)
		{
			$filenameArray = explode("/", $path);
			$this->filename = $filenameArray[count($filenameArray) - 1];
		}
		else if (strpos($path, "\\") !== false)
		{
			$filenameArray = explode("\\", $path);
			$this->filename = $filenameArray[count($filenameArray) - 1];
		}
		else
		{
			$this->filename = $path;
		}		

		$this->status = FTP_ERROR;
		$this->err = "Invalid Callback class and/or callback function specified";

		if (!is_object($callback_class))
		{
			return;
		}

		if (!method_exists($callback_class, $callback_func))
		{
			return;
		}
		
		if (!is_object($callback_class->socketClass))
		{
			$this->err = "Callback class must contain reference to socket class";
			return;
		}

		if (!is_object($callback_class->ircClass))
		{
			$this->err = "Callback class must contain reference to irc class";
			return;
		}

		if (!is_object($callback_class->timerClass))
		{
			$this->err = "Callback class must contain reference to timer class";
			return;
		}

		$this->startControl();
	}
	
	public function getHost()
	{
		return $this->host;
	}

	public function getFilename()
	{
		return $this->filename;
	}

	public function getStatus()
	{
		return $this->status;
	}

	public function getErr()
	{
		return $this->err;
	}

	/* the user can cancel this connection */
	public function cancel()
	{
		if ($this->control_ID != -1)
		{
			$this->conn_control->disconnect();
			$this->control_ID = -1;
			$this->conn_control = null;
		}

		if ($this->data_ID != -1)
		{
			$this->conn_data->disconnect();
			$this->data_ID = -1;
			$this->conn_data = null;
		}
	}

	/* this changes the status to $status, and sends a notify to the callback class */
	private function notify($status, $err = "")
	{
		if ($status == FTP_ERROR)
		{
			//this was an abnormal error, let's make sure we close both the data
			//and the control connections if they're open.
			
			$this->cancel();
		}

		$this->status = $status;
		$this->err = $err;

		$func = $this->cb_func;
		$this->cb_class->$func($this->id, $this->status);
	}

	/* this function starts up the control connection */
	private function startControl()
	{
		$conn = new connection($this->host, $this->port, 30);
		$conn->setCallbackClass($this);

		$conn->setSocketClass($this->cb_class->socketClass);
		$conn->setIrcClass($this->cb_class->ircClass);
		$conn->setTimerClass($this->cb_class->timerClass);

		$conn->init();

		if ($conn->getError())
		{
			$this->err = "Could not initiate connection class";
			$this->status = FTP_ERROR;
			return;
		}

		$this->conn_control = $conn;
		$this->control_ID = $conn->getSockInt();

		$conn->connect();

		$this->status = FTP_CONNECTING;
	}

	/* this starts up a data connection */
	private function startData($ip, $port)
	{
	
		$conn = new connection($ip, $port, 30);
		$conn->setCallbackClass($this);

		$conn->setSocketClass($this->cb_class->socketClass);
		$conn->setIrcClass($this->cb_class->ircClass);
		$conn->setTimerClass($this->cb_class->timerClass);

		/* Set Timeouts */
		$conn->setTransTimeout(15);

		$conn->init();

		if ($conn->getError())
		{
			$this->notify(FTP_ERROR, "Could not initiate connection class for data connection");
			return;
		}

		$this->conn_data = $conn;
		$this->data_ID = $conn->getSockInt();

		$conn->connect();
	}

	private function send($text)
	{
		if (($len = $this->cb_class->socketClass->sendSocket($this->control_ID, $text . "\r\n")) === false)
		{
			$this->notify(FTP_ERROR, "Could not send to control connection");
		}
		return $len;
	}


	/* this function responds to all remote requests from the control connection */
	private function parseResponse($line)
	{
	
		echo $line . "\n";

		/* parse the line */
		$ctrlnum = intval($line);
		$response = substr($line, 4);

		switch ($ctrlnum)
		{
			case 220:
				if ($this->getStatus() == FTP_CONNECTED)
				{
					$this->notify(FTP_AUTH_USER);
					$this->send("USER " . $this->user);
				}
				break;

			case 331:
				if ($this->getStatus() == FTP_AUTH_USER)
				{
					$this->notify(FTP_AUTH_PASS);
					$this->send("PASS " . $this->pass);
				}
				break;

			case 202:
			case 230:
				if ($this->getStatus() < FTP_REGISTERED)
				{
					$this->notify(FTP_REGISTERED);
					$this->send("TYPE I");
				}
				break;

			case 200:
			case 250:
				$this->send("PASV");
				break;
				
			case 227:
				//find first comma
				$comma = strpos($line, ",") - 1;
				for ($j = 0; $j < 4; $j++)
				{
					$num = ord($line[$comma - $j]);

					if (!($num < 60 && $num > 47))
					{
						break;
					}
				}
				$resp = substr($line, $comma - ($j - 1));
				$respArray = explode(",", $resp);
				$ip = intval($respArray[0]) . "." . intval($respArray[1]) . "." . intval($respArray[2]) . "." . intval($respArray[3]);
				$port = (intval($respArray[4]) % 256) * 256 + intval($respArray[5]);
				$this->startData($ip, $port);
				break;

			case 350:
				if (fseek($this->filePointer, $this->resumedSize, SEEK_SET) == -1)
				{
					$this->notify(FTP_ERROR, "Could not seek to necessary position in the file");
					return;
				}
				$this->send("RETR " . $this->path);
				break;

			case 150:
				$this->notify(FTP_RETR);
				//all is good, shit is happening
				break;

			case 550:
				$this->notify(FTP_ERROR, "File does not exist on the server");
				return;
				break;

			default:
				break;
		}
	}

	private function beginDownload()
	{
		$this->cb_class->socketClass->alterSocket($this->data_ID, SOL_SOCKET, SO_RCVBUF, 32768);

		$uldir = $this->cb_class->ircClass->getClientConf('uploaddir');

		$lastChar = substr($uldir, strlen($uldir) - 1, 1);

		if ($lastChar != "\\" || $lastChar != "/")
		{
			$uldir .= "/";
		}

		$filename = $uldir . $this->filename;

		$this->filePointer = fopen($filename, "ab");

		if ($this->filePointer === false)
		{
			$this->notify(FTP_ERROR, "Could not open local file for writing");
			return;
		}

		if ($this->file_exists($filename))
		{
			$bytesFinished = $this->filesize($filename);
			$this->resumedSize = $bytesFinished;

			$this->send("REST " . $bytesFinished);
		}
		else
		{
			$this->send("RETR " . $this->path);
		}
	}

	private function xferDownload()
	{
		$data = $this->cb_class->socketClass->getQueue($this->data_ID);
		$dataSize = strlen($data);

		if (fwrite($this->filePointer, $data, $dataSize) === false)
		{
			$this->notify(FTP_ERROR, "Could not write to local data file");
		}
	}

	/* data transfer routines */
	public function onTransferTimeout($conn)
	{
		switch ($conn->getSockInt())
		{
			case $this->control_ID:
				if ($this->status != FTP_RETR)
				{
					$this->notify(FTP_ERROR, "Control connection timed out");
					break;
				}
				$conn->disconnect();
				$this->control_ID = -1;
				$this->conn_control = null;
				break;
			case $this->data_ID:
				$this->notify(FTP_ERROR, "Data connection timed out");
				break;
			default:
				break;
		}
	}

	public function onConnectTimeout($conn)
	{
		switch ($conn->getSockInt())
		{
			case $this->control_ID:
				$this->notify(FTP_ERROR, "Control connection attempt timed out");
				break;
			case $this->data_ID:
				if ($this->lastmode == MODE_PORT)
				{
					$this->notify(FTP_ERROR, "Data connection attempt timed out");
				}
				else
				{
					//passive didn't work, so let's try port
					$this->lastmode = MODE_PORT;


				}
				break;
			default:
				break;
		}
	}
	
	public function onAccept($listener, $newConnection)
	{
	


	}

	public function onConnect($conn)
	{
		switch ($conn->getSockInt())
		{
			case $this->control_ID:
				$this->notify(FTP_CONNECTED);
				break;
			case $this->data_ID:
				//okay, now we're ready to get the file.
				$this->beginDownload();
				break;
			default:
				break;
		}
	}

	public function onRead($conn)
	{

		switch ($conn->getSockInt())
		{
			case $this->control_ID:
				if ($this->cb_class->socketClass->hasLine($this->control_ID))
				{
					$this->parseResponse($this->cb_class->socketClass->getQueueLine($this->control_ID));
				}
				if ($this->cb_class->socketClass->hasLine($this->control_ID)) //control connection may need to return more
				{
					return true;
				}
				break;
			case $this->data_ID:
				$this->xferDownload();
				break;
			default:
				break;
		}
		return false; //data connection never needs to return true
	}

	public function onWrite($conn)
	{
		//yea, whatever.  we don't care about this.  we're only using this class
		//to download shit anyway.
	}

	public function onDead($conn)
	{
		switch ($conn->getSockInt())
		{
			case $this->control_ID:
				if ($this->getStatus() != FTP_CLOSED)
				{
					//we didn't close the connection, uh oh...
					$msg = $this->conn_control->getErrorMsg();
					$this->notify(FTP_ERROR, $msg);
					break;
				}
				$conn->disconnect();
				$this->control_ID = -1;
				$this->conn_control = null;
				break;
			case $this->data_ID:
				if ($this->getStatus() == FTP_RETR)
				{
					fclose($this->filePointer);

					//let's just interpret this as normal
					$conn->disconnect();
					$this->data_ID = -1;
					$this->conn_data = null;

					$this->notify(FTP_CLOSED);

				}
				else
				{
					$this->notify(FTP_ERROR, "Data connection closed unexpectedly");
				}
				break;
			default:
				break;
		}
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