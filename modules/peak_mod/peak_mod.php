<?php

class peak_mod extends module {

	public $title = "Channel Peak Mod";
	public $author = "Manick";
	public $version = "0.1";

	private $peak;

	public function init()
	{
		$this->peak = new ini("modules/peak_mod/peak.ini");
	}

	public function peak_on_join($line, $args)
	{
		if ($this->peak->getError())
		{
			return;
		}
		
		$chan = strtolower($line['text']);
		$chanData = $this->ircClass->getChannelData($chan);
		
		if (!is_object($chanData))
		{
			return;
		}

		$peak = $this->peak->getIniVal($chan, "peak");

		if ($peak === false || $peak < $chanData->count)
		{
			$this->peak->setIniVal($chan, "peak", $chanData->count);
			$this->peak->setIniVal($chan, "time", time());
			$this->peak->writeIni();
		}

	}

	public function priv_peak($line, $args)
	{
	
		if ($line['to'] === $this->ircClass->getNick())
		{
			return;
		}

		if ($this->peak->getError())
		{
			$this->ircClass->notice($line['fromNick'], "Unexplained error opening peak database.");
			return;
		}

		$chan = strtolower($line['to']);

		$chanData = $this->peak->getSection($chan);

		if ($chanData == false)
		{
			$this->ircClass->notice($line['fromNick'], "I have no data for that channel.");
			return;
		}

		$time = date("l, F jS, Y @ g:i a O", $chanData['time']);
		
		$this->ircClass->notice($line['fromNick'], "Hello, " . $line['fromNick'] . ", the current peak for " . $line['to'] . " is " . BOLD . 
				$chanData['peak'] . BOLD . " users on " . BOLD . $time . BOLD . ".");
	}

}

?>
