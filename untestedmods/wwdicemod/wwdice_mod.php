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
|   > wwdice_mod module
|   > Module written by proof_of_death
|   > Contact: joeterranova@gmail.com
|   > Module Version Number: 0.3
|   > GetRandomNumber Function integrated from random.org PHP-Client
|   > http://www.random.org/clients/php-client
|   > GetRandomNumber Function, Copyright 2000 Paul Pearson
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
|   >
|   > 0.3:
|   > As per request of a friend, implemented caching of numbers.
|   > By default now caches 1000 numbers in memory.
|   > Default now uses approx 2k more of memory, but fewer http requests.
|   > Caching can be disabled/enabled and buffer size changed in class prototype.
|   > Changed user_agent to your email address (so you can be contacted instead
|   > of just blacklisted if there's a problem).
|   > Checks random.org's buffer before making requests. If less than 20%,
|   > makes request from hot bits instead. I'd do the same for hot bits, if
|   > they actually had such a feature :P
|   >
|   > 0.2:
|   > Fixed errors in config file (ie the change is the thing now _works_ =P )
|   > Added/modified Credits
|   > Added ability to choose between RNGs. Choose by setting $random in the class prototype.
|   > Added command !rng, which tells the current RNG.
|   >
|   > 0.1:
|   > Initial (not working) release.
|   >
|   > If you wish to suggest or submit an update/change to the source
|   > code, email me at joeterranova@gmail.com with the change, and I
|   > will look to adding it in as soon as I can.
+---------------------------------------------------------------------------
*/



class wwdice_mod extends module {

	public $title = "wwdice_mod";
	public $author = "proof_of_death";
	public $version = "0.3";
	public $dontShow = true;
	private $random = 1;

// $random = 3; use Rand function http://us2.php.net/manual/en/function.rand.php
// $random = 2; use Hotbits http://www.fourmilab.ch/hotbits/
// $random = 1; use random.org http://random.org/
	private $usebuffer = 1;
// set $usebuffer = 0 to not buffer rolls and get all numbers on the fly.
    private $email = "changethis@toyouremailaddress.com"; // change to your email address
	private $RANDBUFFERMAX = 1000;
// number of dice in buffer. Irrelevant if $usebuffer = 0

	public function init()
	{

	}

	public function destroy()
	{
		if($this->usebuffer){
			unset($GLOBALS['RANDBUFFER']);
			unset($GLOBALS['RANDCURRENT']);
		}
	}


	public function roll($line, $args)
	{

		if(substr($line['to'],0,1) != '#')
			return;
		if (($args['nargs'] < 1 || !(ctype_digit($args['arg1']))) && !($args['cmd'] == "!init" || $args['cmd'] == "!chance" || $args['cmd'] == "!inits"))
		{
			$this->ircClass->notice($line['to'], "format is:", $queue = 1);
			$this->ircClass->notice($line['to'], $line['cmd']." <number> <reason?>", $queue = 1);
			return;
		}

		$response;
		$flag;
		$rerolls = 0;
		$this->ircClass->notice($line['to'], " ".$line['fromNick'].": ".$args['cmd']." ".$args['query'], $queue = 1);
		if(isset($args['arg1']) && $args['arg1'] > 20)
		{
			$this->ircClass->notice($line['to'], " ".$line['fromNick'].": I'm sorry. Antideluvians don't exist in New World of Darkness", $queue = 1);
			return;
		}
		switch ($args['cmd'])
		{
			case "!roll":
				$flag = "n";
				break;
			case "!9roll":
				$flag = "9";
				break;
			case "!8roll":
				$flag = "8";
				break;
			case "!sroll":
				$flag = "s";
				break;
			case "!chance":
				$flag = "c";
				break;
			case "!init":
				$flag = "i";
				break;
			case "!inits":
				$flag = "i";
				break;
		}
		$rerolls = $this->wwroll($response, $args['arg1'],$flag);
		$this->ircClass->notice($line['to'], " ".$line['fromNick'].": ".$response, $queue = 1);
		while($rerolls > 0)
		{

			$rerolls = $this->wwroll($response, $rerolls,$flag, 1);
			$this->ircClass->notice($line['to'], " ".$line['fromNick'].": rerolls: ".$response, $queue = 1);
		}

	}

	public function rng($line, $args)
	{
		if(substr($line['to'],0,1) != '#')
			return;
		if($this->random == "3")
			$response = "PHP Rand Function";
		else if($this->random == "2")
			$response = "Hotbits";
		else
		$response = "Random.org";

		$this->ircClass->notice($line['to'], " Current RNG: ".$response, $queue = 1);

	}


	private function wwroll(&$response,$dice,$flag,$isreroll = "0")
	{


		$i;
		$successes = 0;
		$rerolls = 0;


		if(!($flag == "i" || $flag == "c"))
		{
			$temp = $this->RngFunc($dice);
			for($i = 0; $i < $dice * 2; $i+=2){
				$token = substr($temp,$i,1);
				if($token > "7" || $token == "0")
					$successes++;
				if($token == "0" && ($flag == "n" || $flag == "9" || $flag == "8"))
					$rerolls++;
				if($token == "9" && ($flag == "9" || $flag == "8"))
					$rerolls++;
				if($token == "8" && $flag == "8")
					$rerolls++;
				if($token == "1" && $flag == "s")
					$successes--;
			}
		}
		else if ($flag == "i")
		{
			$temp = $this->RngFunc("1");
			$response = $temp;
			return rerolls;
		}
		else if($flag == "c")
		{
			$temp = $this->RngFunc("1");
			if($temp == 0){
				$rerolls = 1;
				$response = "0 : Success (Reroll)";
			}
			else if($temp == 1 && !$isreroll)
			{
				$response = "1 : CRITICAL FAILURE";
			}
			else {
				$response = $temp." : FAILURE";
			}
			return $rerolls;
		}





		$response = "you suck";
		if($successes > 1)
			$response = $temp." : ".$successes." Successes (".$rerolls." Rerolls)";
		else if($successes == 1)
			$response = $temp." : ".$successes." Success (".$rerolls." Rerolls)";
		else
			$response = $temp." : FAILURE";
		return $rerolls;
	}

	private function RngFunc($num)
	{
		$response = "";
		if($this->usebuffer && $this->random != 3)
			$temp = $this->RandBuffer($num);
		else
			$temp = $this->GetRandomNumber($num);
		for($i = 0; $i < strlen($temp); $i++)
			$response .= substr($temp,$i,1)." ";
		return $response;
	}

	private function RandBuffer($num) {
		unset ($response);
		if(!isset($GLOBALS['RANDBUFFER']) || ($GLOBALS['RANDCURRENT'] + $num > $this->RANDBUFFERMAX))
		{
			$GLOBALS['RANDBUFFER'] = $this->GetRandomNumber($this->RANDBUFFERMAX);
			$GLOBALS['RANDCURRENT'] = 0;
		}


		$response = substr($GLOBALS['RANDBUFFER'],$GLOBALS['RANDCURRENT'], $num);
		$GLOBALS['RANDCURRENT']+= $num;

		return $response;



	}

	private function GetRandomNumber($num) {
		unset ($ReturnedValue);
		$random = $this->random;
		//$random = "1";
		if(!ctype_digit($random) || $random < 1 || $random > 3)
			$random = 1;


		if ($random=="3" ) {
			for($i = 0; $i < $num; $i++)
			{
				srand((double)microtime()*intval(rand(1,1000000)));
				$ReturnedValue .=intval(rand("0", "9"));
			}
		}

		else if ($random=="2" ) {
			#
			# HotBits does not use min or max. Instead, it generates a Hex number (00-FF).
			# We fake min/max with hotbits.
			#

       		ini_set('user_agent',$this->email);
			$fp_HotBits = fopen ("http://www.fourmilab.to/cgi-bin/uncgi/Hotbits?nbytes=".$num."&fmt=hex", "r");
			$HotBits_Text = fread ($fp_HotBits, 4096);
			fclose($fp_HotBits);
			for($i = 0; $i < $num; $i++)
			{
				$HotBits_PickedNumber=substr($HotBits_Text, 463 + $i * 2, 2);
				$ReturnedValue.=intval(((hexdec($HotBits_PickedNumber)/255)*(9)));
			}
		}

		else if ($random=="1"){
			ini_set('user_agent',$this->email);
			$fp_RandomOrg = fopen ("http://www.random.org/cgi-bin/checkbuf", "r");
			$RandomOrg_Text = fread ($fp_RandomOrg, 4096);
			$ReturnedValue=$RandomOrg_Text;
			fclose($fp_RandomOrg);
			if(str_replace($ReturnedValue,"%","") < 20)
			{
				$this->random = "2";
				$ReturnedValue = $this->GetRandomNumber($num);
				$this->random = "1";
				return $ReturnedValue;
			}


			$fp_RandomOrg = fopen ("http://www.random.org/cgi-bin/randnum?num=".$num."&min=0&max=9&col=1", "r");
			$RandomOrg_Text = fread ($fp_RandomOrg, 4096);
			$ReturnedValue=$RandomOrg_Text;
			fclose($fp_RandomOrg);
			$searchstuff = array("\n","\r");
			$ReturnedValue = str_replace($searchstuff,"",$ReturnedValue);
		}

		return $ReturnedValue;
	}


}
?>
