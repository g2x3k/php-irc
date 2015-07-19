<?php

/*
+---------------------------------------------------------------------------
|   Half-Life RCON Module for PHP-IRC v2.2.1
|   ========================================================
|   by Mad_Clog
|   (c) 2006 by http://www.madclog.com
|   Contact: php-irc@madclog.com
|   irc: #usrforce@irc.quakenet.org
|   ========================================
+---------------------------------------------------------------------------
|   > hlserver module
|   > Module written by Mad_Clog
|   > Module Version Number: 0.1
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
|   > code, email me at php-irc@madclog.com with the change, and I
|   > will look to adding it in as soon as I can.
+---------------------------------------------------------------------------
*/

class hlserver extends module {

	public $title = "Half-Life RCON";
	public $author = "Mad_Clog";
	public $version = "0.1";

	private $socket;				// reference to our reading socket
	private $sendSocket;			// reference to our sending socket
	private $challenge = 0;			// This will hold the challenge number needed to execute queries
	private $rconError = FALSE;		// This will determine if the script keeps logging
	private $buff = '';				// Buffer used for reading the logs
	private $oldBuff = '';			// backup buffer to filter double log entries
	
	private $server = array();
	private $local = array();
	private $logging = array();
	private $colors = array();

	public function init()
	{
		// add a timer to start the logging stuff
		$this->timerClass->addTimer("hlserverStart", $this, "createSockets", "", 30, false);
		
		//get the config
		$this->config = new ini("modules/hlserver/hlserver.ini");
		if ($this->config->getError())
		{
			return FALSE;
		}	
		$this->server = $this->config->getSection('server');
		$this->local = $this->config->getSection('local');
		$this->logging = $this->config->getSection('logging');
		$this->colors = $this->config->getSection('colors');
		
		if (!defined('COLOR'))
			define('COLOR', chr(3));
	}
	
	public function destroy()
	{
		$this->challenge = 0;
		$this->rconError = TRUE;
		@socket_close($this->socket);
		@socket_close($this->sendSocket);
		$this->timerClass->removeTimer("hlserver_read");
	}

	public function reload_ini($line, $args)
	{
		if (!$this->ircClass->hasModeSet($line['to'], $line['fromNick'], "oa"))
		{
			return;
		}

		// unset the old stuff
		unset($this->config);
		unset($this->server);
		unset($this->local);
		unset($this->logging);
		unset($this->colors);
		
		//get the config
		$this->config = new ini("modules/hlserver/hlserver.ini");
		if ($this->config->getError())
		{
			return FALSE;
		}	
		$this->server = $this->config->getSection('server');
		$this->local = $this->config->getSection('local');
		$this->logging = $this->config->getSection('logging');
		$this->colors = $this->config->getSection('colors');		
		
		$this->ircClass->notice($line['fromNick'], "HL Rcon ini reloaded");
	}	
	
	function createSockets() 
	{
		// Create the sending socket
		if (!$sendSocket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))
			$this->rconError = TRUE;
			
		if (!socket_bind($sendSocket,'0.0.0.0',$this->server['port']))
			$this->rconError = TRUE;
		
		$packet = chr(255).chr(255).chr(255).chr(255)."challenge rcon";
		socket_sendto($sendSocket, $packet, strlen($packet), 0, $this->server['ip'], $this->server['port']);
		
		$buff = '';
		if (@!socket_recvfrom($sendSocket, $buff, 1024, 0, $this->server['ip'], $this->server['port'])) {
		    echo('socket_recvfrom error: '.socket_strerror(socket_last_error()));
		    $this->rconError = TRUE;
		}
		  
		$challenge = trim(substr($buff,strlen($packet)));
		$challenge = str_replace(	array("\r", "\n"), array('',''), $challenge);
		
		echo "Challenge number: ".$challenge."\r\n";
		
		if ((is_numeric($challenge)) && ($challenge > 0)) 
		{
			$this->challenge = $challenge;
			$this->sendSocket = $sendSocket;
			echo "Sending logging requests\r\n";
			
			$this->send('mp_logmessages 1');
			$this->send('mp_logfile 1');
			$this->send('mp_logdetail 0');
			$this->send('log on');
			$this->send('logaddress_add '.$this->local['ip'].' '.$this->local['port']);			
		} else {
			socket_close($sendSocket);
			$this->rconError = TRUE;
		}
		
		// create the reading socket
		if (!$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))
			$this->rconError = TRUE;
		
		if (!socket_bind($socket,'0.0.0.0',$this->local['port']))
			$this->rconError = TRUE;
		
		if (!socket_set_nonblock($socket))
			$this->rconError = TRUE;
		
		if ($this->rconError !== TRUE) {
			$this->socket = $socket;
			$this->timerClass->addTimer("hlserver_read", $this, "read", "", 0.1, true);
		}
		
		if ($this->rconError == TRUE)
		{
			$this->ircClass->privMsg($this->server['channel'] , 'Could not start logging proccess');
			$this->destroy();
			return FALSE;
		}
		//$this->ircClass->privMsg($this->server['channel'] , 'Logging proccess started, monitoring '.$this->server['ip'].':'.$this->server['port']);
		// Need to return false to stop the timer
		return FALSE;		
	}
	
	
	/**
	 * Only use this for rcon commands where do you dont need to read the output, such as say
	 * For things such as status (which has a multiline response) please do it manually
	 */
	public function send($command) 
	{
		if ($this->rconError === FALSE && $this->challenge > 0)
		{
			$packet = chr(255).chr(255).chr(255).chr(255)."rcon ".$this->challenge." \"".$this->server['password']."\" ".$command;
			socket_sendto($this->sendSocket, $packet, strlen($packet), 0, $this->server['ip'], $this->server['port']);
			
			@socket_recvfrom($this->sendSocket, $buff, 512, 0, $ip, $port);			
			if(substr($buff,4,19) == 'lBad rcon_password.')
			{
				$this->ircClass->privMsg($this->server['channel'] , 'Bad rcon password, stopping logging process');
				$this->destroy();
				return FALSE;
			}
			return TRUE;
		}
		return FALSE;
	}
	
	/**
	 * Reading function called by the timer every 0.1 seconds (this should be enough to keep things in line)
	 * Build in some really simple parsing things so people can see what you can do with it
	 **/
	public function read() 
	{
		@socket_recvfrom($this->socket, $this->buff, 1024, 0, $this->server['ip'], $this->local['port']);
		
		// Sometimes you will get double log lines, we filter these out
		//
		// Filter ANY line which contains the rcon password, we dont want to leak this now do we
		if (($this->buff != $this->oldBuff) && 
			(strpos($this->buff,'"'.$this->server['password'].'"') === FALSE) &&
			(strpos($this->buff,' '.$this->server['password'].' ') === FALSE))
		{
			// Kill event
			if ($this->logging['kills'] == 1 && strpos($this->buff,'" killed "') !== FALSE) 
			{
				// log L 06/07/2006 - 21:57:50: "ToTo<334><BOT><Red>" killed "ComPo<332><BOT><Blue>" with "rocket"				
				$parts = explode('"',$this->buff);
				$player1 = $this->parsePlayer($parts[1]);
				$player2 = $this->parsePlayer($parts[3]);
				
				$output = COLOR.$this->colors[strtolower($player1['team'])].$player1['name'] . COLOR .' killed '.COLOR.$this->colors[strtolower($player2['team'])].$player2['name'].COLOR.' with '.$parts[5];
			}

			// Say event
			elseif ($this->logging['says'] == 1 && strpos($this->buff,'" say "') !== FALSE) 
			{
				// log L 06/06/2006 - 23:35:28: "[USRF]Mad Clog<13><STEAM_0:0:123456><Blue>" say "bla bla bla bla bla"
				$parts = explode('"', $this->buff, 4);
				$player = $this->parsePlayer($parts[1]);
				
				$output = COLOR.$this->colors[strtolower($player['team'])].$player['name'].COLOR.': '.substr($parts[3],0,-3);
			}
			
			// Teamsay event
			elseif ($this->logging['teamsays'] == 1 && strpos($this->buff,'" say_team "') !== FALSE) 
			{
				//log L 06/06/2006 - 23:33:03: "[USRF]Mad Clog<13><STEAM_0:0:123456><Blue>" say_team "SOLDIER CFG LOADED
				$parts = explode('"', $this->buff, 4);
				$player = $this->parsePlayer($parts[1]);
				
				$output = COLOR.$this->colors[strtolower($player['team'])].$player['name'].COLOR.' (TEAM): '.substr($parts[3],0,-3);
			}

			// server say event
			elseif ($this->logging['serversays'] == 1 && strpos($this->buff,': Server say "') !== FALSE) 
			{
				echo $this->buff;
				$parts = explode('"', $this->buff, 2);
				
				$output = 'Server: '.substr($parts[1],0,-3);
			}
			
			if (isset($output) && $output != '')
				$this->ircClass->privMsg($this->server['channel'], $output);
				//echo $output."\r\n";
			
		}
		$this->oldBuff = $this->buff;
		return TRUE;
	}
	
	/**
	 * Parses stuff like "[USRF]Mad Clog<1286><STEAM_0:0:123456><Blue>" into 4 vars,
	 * starts at the back to prevent error with strange nicknames like "T<o|<T<123><o>" 
	 * how uncommen they might be
	 **/
	private function parsePlayer($string)
	{
		$player = array();
		$player['string'] = $string;
		
		// Get the team
		$start = strlen($string)-2;
		$end = strlen($string)-2;
	
		while (($string{$start} != '<') && ($start >= 0))
			$start--;
			
		$player['team'] = substr($string,$start+1,$end-$start);
		
		// Get the steamid
		$end = $start-2;
		$start--;
		
		while (($string{$start} != '<') && ($start >= 0))
			$start--;
			
		$player['steamid'] = substr($string,$start+1,$end-$start);
		
		// Get the UID
		$end = $start-2;
		$start--;
		
		while (($string{$start} != '<') && ($start >= 0))
			$start--;
			
		$player['uid'] = substr($string,$start+1,$end-$start);	
		
		// Get the name
		$player['name'] = substr($string,0,$start);	
	
		return $player;
	}
	
	/**
	 * Send a chat message to the server with "rcon say" and prefix it with (IRC|ircNick)
	 * (They don't come any more basic then this ;p)
	 */
	public function priv_say($line, $args)
	{
		$this->send('say (IRC|'.$line['fromNick'].')'.$args['query']);
	}	
	
}

?>