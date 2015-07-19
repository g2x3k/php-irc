<?php

/*
+---------------------------------------------------------------------------
|   PHP-IRC Pizza Timer
|   ========================================
|   Initial release
|   v0.1 by SubWorx
|   (c) 2007 by http://subworx.ath.cx
|   Contact:
|    email: sub@subworx.ath.cx
|    irc:   #php@irc.phat-net.de
|   ========================================
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
|   0.1.1:	fixed stupid bug in pizzatimer.conf
|   0.1: 	initial release
+---------------------------------------------------------------------------
*/


class pizzatimer extends module {

	public $title = "Pizza Timer";
	public $author = "SubWorx";
	public $version = "0.1";

	public function init()
	{
	}

	public function destroy()
	{
	}

	public function priv_pizza($line, $args)
	{
    	if ($args['nargs'] < 1)
		{
    		$this->ircClass->privMsg($line['to'], chr(2).'Usage'.chr(2).': !pizza <time> , in minutes, 0<'.chr(2).'x'.chr(2).'<=240');
    		return;
    	}
    	if (!is_numeric($args['arg1']))
		{
    		$this->ircClass->privMsg($line['to'], chr(2).'Usage'.chr(2).': !pizza <time> , in minutes, 0<'.chr(2).'x'.chr(2).'<=240');
    		return;
    	}
    	$args['arg1']++;
    	$args['arg1']--;
    	if (!is_integer($args['arg1']))
		{
			$this->ircClass->privMsg($line['to'], chr(2).'Usage'.chr(2).': !pizza <time> , in minutes, integer, 0<'.chr(2).'x'.chr(2).'<=240');
			return;
		}
    	if ($args['arg1']>240)
		{
    		$this->ircClass->privMsg($line['to'], chr(2).'Usage'.chr(2).': !pizza <time> , in minutes, integer, 0<'.chr(2).'x'.chr(2).'<=240');
    		return;
    	}
    	$pizza_id = "pizza_". irc::randomhash();
    	$time = $args['arg1'] *60;
    	$this->timerClass->addTimer($pizza_id, $this, "pizzaRemind", $line, $time, false);
    	$this->ircClass->privMsg($line['to'], $line['fromNick'].", you will be notified at ".date("d.m.y, H:i", time()+$time).", ".$args['arg1']. " minutes from now");
	}

	public function pizzaRemind($line){
		$this->ircClass->privMsg($line['to'], $line['fromNick'].", your Pizza is finished! Enjoy :)");
	}

	public function sendMsg($line, $args, $message)
	{
		switch($this->response_type)
		{
			case 0:
				$this->ircClass->privMsg($line['to'], $message);
			break;

			case 1:
				$this->ircClass->privMsg($line['fromNick'], $message);
			break;

			case 2;
				$this->ircClass->notice($line['fromNick'], $message);
			break;

			default:
				$this->ircClass->privMsg($line['to'], $message);
		}
	}

	public function pizzaInfoDcc($line, $args)
	{
		$timers = $this->timerClass->getTimers();
		if ($timers == 0) {
			$this->dccClass->dccInform("no pizza timers set");
			return;
		}
		$counter = 0;
		foreach($timers as $result)
		{
			if (stristr($result->name, "pizza" ))
			{
				$counter++;
				$names[] = $result->name;
				$time[] = $result->nextRunTime;
			}
		}
		if ($counter >0) {
			$this->dccClass->dccInform("$counter pizza timers active");
			for ($i=0; $i<count($names); $i++) {
				$this->dccClass->dccInform($names[$i] . " - running @ " . date("d.m.y, H:i:s",$time[$i]) . ", " . date("m",$time[i] - time())  ."m "  .date("s", $time[i]-time()) . "s from now");
			}
		} else {
			$this->dccClass->dccInform("no pizza timers active");
		}
	}

}

?>