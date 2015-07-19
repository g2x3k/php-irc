<?php

require_once("modules/wget/ftp.php");

class wget_mod extends module {

	public $title = "WGet";
	public $author = "Manick";
	public $version = "0.1";

	/* config vars */
	private $maxRedirect = 3;
	private $dir = "E:\\";

	/* holds the current ID, incremented whenever a new ftp or http is started */
	private $currID;

	/* runtime vars */
	private $gets = array();

	public function init()
	{
		$this->currID = 0;
	}

	public function destroy()
	{
		foreach ($this->gets AS $get)
		{
			$this->destroy_get($get);
		}
	}

	public function dcc_wget($chat, $args)
	{
		$url = $args['arg1'];

		$stat = $this->add_get($url);

		if ($stat == false)
		{
			$chat->dccSend("The transfer could not be started.");
		}
		else
		{
			$chat->dccSend("Successfully started the transfer.");
		}
	}

	public function ftp_notify($gid, $stat)
	{
		switch($stat)
		{
			case FTP_CONNECTING:
				$this->dccClass->dccInform("FTP Transfer #".$gid.": Connecting to server: " . $this->gets[$gid]['CLASS']->getHost() . "...");
				break;
			case FTP_CONNECTED:
				$this->dccClass->dccInform("FTP Transfer #".$gid.": Connected to server: " . $this->gets[$gid]['CLASS']->getHost());
				break;
			case FTP_AUTH_USER:
				$this->dccClass->dccInform("FTP Transfer #".$gid.": Client is now logging in...");
				break;
			case FTP_AUTH_PASS:
				break;
			case FTP_REGISTERED:
				$this->dccClass->dccInform("FTP Transfer #".$gid.": Client has successfully logged in to the ftp server.");
				break;
			case FTP_RETR:
				$this->dccClass->dccInform("FTP Transfer #".$gid.": File '". $this->gets[$gid]['CLASS']->getFilename() ."' is now downloading...");
				break;
			case FTP_CLOSED:
				$this->dccClass->dccInform("FTP Transfer #".$gid.": Download complete.");
				$this->destroy_get($this->gets[$gid]);
				break;
			case FTP_ERROR:
				$this->dccClass->dccInform("FTP Transfer #".$gid.": Failed: " . $this->gets[$gid]['CLASS']->getErr());
				$this->destroy_get($this->gets[$gid]);
				break;
			default:
				$this->destroy_get($this->gets[$gid]);
		}
	}

	private function add_get($givenUrl)
	{
		$url = parse_url($givenUrl); // array

		if ($url == false)
		{
			return false;
		}

		if (!isset($url['scheme']))
		{
			$url['scheme'] = "http";
		}

		if (!isset($url['host']))
		{
			return false;
		}
		
		if (!isset($url['path']))
		{
			$url['path'] = "/";
		}

		if (!isset($url['user']))
		{
			$url['user'] = "anonymous";
		}
		
		if (!isset($url['pass']))
		{
			$url['pass'] = "bot@bot.org";
		}

		$id = $this->currID++;
		$this->gets[$id] = array();

		switch($url['scheme'])
		{
			case "ftp":
				if (!isset($url['port']))
				{
					$url['port'] = 21;
				}
				$this->gets[$id]['CLASS'] = new _ftp($this, "ftp_notify", $id, $url['host'], $url['path'], $url['port'], $url['user'], $url['pass']);
				if ($this->gets[$id]['CLASS']->getStatus() == FTP_ERROR)
				{
					$this->destroy_get($this->gets[$id]);
					return false;
				}
				$this->dccClass->dccInform("FTP Retrieval of file: " . $this->gets[$id]['CLASS']->getFilename() . " has begun as id #".$id."...");
				break;
			case "http":
				if (!isset($url['port']))
				{
					$url['port'] = 80;
				}
				return false;
				break;
			default:
				return false;
				break;
		}

		$this->gets[$id]['MAX_REDIRECT'] = $this->maxRedirect;
		$this->gets[$id]['ID'] = $id;
		$this->gets[$id]['URL'] = $url;
		return true;
	}

	private function destroy_get(&$get)
	{
		if (!is_array($get))
		{
			return;
		}
		
		$get['CLASS']->cancel();
		
		$this->gets[$get['ID']]['CLASS'] = null;
		unset($this->gets[$get['ID']]);

	}
}

?>