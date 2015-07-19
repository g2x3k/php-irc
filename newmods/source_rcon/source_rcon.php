<?php

/*
+---------------------------------------------------------------------------
|   PHP-IRC Source Rcon
|   ========================================
|     by Mad Clog
|   (c) 2007-2008 by http://www.madclog.nl
|   Contact:
|    email: phpirc@madclog.com
|    msn:   gertjuhh@hotmail.com
|    irc:   #madclog@irc.quakenet.org
|   ========================================
|   Changelog:
|   1.0
|    - Initial release
|   1.1
|    - Added automatic reconnecting
|    - Added option to disable text markup (colors etc.)
|    - Removed bogus second parameter on 'func<Name>' functions
|   ========================================
|   Todo:
|   ========================================
|   Known Bugs:
|    - None
|   If you find any bugs please leave post them at http://www.phpbots.org/showtopic.php?tid=253
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
*/
class source_rcon extends module {
	public $title = 'Source Rcon';
	public $author = 'Mad_Clog';
	public $version = '1.1';

	/**
	 * Holds TCP socket used for sending rcon commands
	 *
	 * @var object
	 */
	private $m_oSendSock = null;
	/**
	 * The socket indicator
	 *
	 * @var int
	 */
	private $m_iSendSock = 0;
	/**
	 * Keeps track of the number of request send, is also need for propper rcon commands
	 *
	 * @var int
	 */
	private $m_iRequestID = 0;
	/**
	 * To see if our rcon authentication was succesfull
	 *
	 * @var boolean
	 */
	private $m_bAuthenticated = false;
	/**
	 * Rcon send queue
	 *
	 * @var array
	 */
	private $m_aSendBuff = array();
	/**
	 * Holds data in between rcon responses to process multi-packet responses
	 *
	 * @var array
	 */
	private $m_aResponseBuff = array();
	/**
	 * Keeps track of our responses, coordinates with m_iRequestID
	 *
	 * @var array
	 */
	private $m_aResponses = array();
	/**
	 * Keep track of commands send from irc
	 *
	 * @var array
	 */
	private $m_aIrcCommands = array();
	/**
	 * Keep track of commands send from the game server
	 *
	 * @var array
	 */
	private $m_aServerCommands = array();
	/**
	 * Holds UDP socket for reading server logs
	 *
	 * @var object
	 */
	private $m_oRecvSock = null;
	/**
	 * Holds the handlers for our reading log
	 *
	 * @var array
	 */
	private $m_aReadHandlers = array();
	/**
	 * Holds the configuration options
	 *
	 * @var array
	 */
	private $m_aConfig = array();
	/**
	 * Holds server status and players (parsed from 'rcon status')
	 *
	 * @var array
	 */
	private $m_aServer = array();
	/**
	 * Holds the details of all players on server
	 *
	 * @var array
	 */
	private $m_aPlayers = array();
	/**
	 * Holds a list of the maps on the server
	 *
	 * @var array
	 */
	private $m_aMaps = array();
	/**
	 * Time in seconds it takes to retrieve a full rcon response
	 * We need this because some responses span over multiple packets while there is no indication when the last packet is received
	 *
	 * @var float
	 */
	private $m_fCommandDelay = 0.5;
	/**
	 * Name of the local log file
	 *
	 * @var string
	 */
	private $m_sLogFilename = null;
	/**
	 * The number of lines to store in the buffer before writing it to file
	 * Bigger number means less disk writing activities but bigger memory usage
	 *
	 * @var int
	 */
	private $m_iLogBufferSize = 20;
	/**
	 * Buffer for writing files to a log on disk
	 *
	 * @var unknown_type
	 */
	private $m_aLogBuffer = array();
	/**
	 * Keeps track of the number of reconnects
	 *
	 * @var int
	 */
	private $m_iReconnectCount = 0;

	/**
	 * init
	 * initialize the module
	 * sets up our sockets and loads the configuration settings
	 *
	 * @access public
	 * @return void
	 */
	public function init() {
		// Set up the constants
		require_once('modules/source_rcon/defines.php');

		$this->readConfig();

		// Set up TCP sending socket
		$this->m_oSendSock = new connection($this->m_aConfig['server']['ip'], $this->m_aConfig['server']['port'], 5);

		$this->m_oSendSock->setSocketClass($this->socketClass);
		$this->m_oSendSock->setIrcClass($this->ircClass);
		$this->m_oSendSock->setTimerClass($this->timerClass);

		$this->m_oSendSock->setCallbackClass($this);

		$this->m_oSendSock->init();

		if ($this->m_oSendSock->getError()) {
			$this->destroy('Error creating TCP socket: ' . $this->m_oSendSock->getErrorMsg());
			return;
		}

		$this->m_oSendSock->connect();

		$this->m_iSendSock = $this->m_oSendSock->getSockInt();

		// setup UDP socket for recieviing server logs
		if (!$this->m_oRecvSock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
			$this->destroy('Error creating UDP socket [1]');
			return;
		}
		if (!socket_bind($this->m_oRecvSock, '0.0.0.0', $this->m_aConfig['local']['port'])) {
			$this->destroy('Error creating UDP socket [2]');
			return;
		}

		if (!socket_set_nonblock($this->m_oRecvSock)) {
			$this->destroy('Error creating UDP socket [3]');
			return;
		}

		// set up udp reading process
		$this->timerClass->addTimer('parseReadLog', $this, 'parseReadLog', '', 0.1, true);

		// add the status timer, call it every 5 minutes
		$this->timerClass->addTimer('statusQuery', $this, 'statusQuery', '', 300, false);

		// add our UDP handlers
		$this->registerHandlers();

		// initiate logging to file
		$this->openLogfile();
	}

	/**
	 * destroy
	 * destroy the module
	 *
	 * @access public
	 * @param string $p_sErrorMsg
	 * @return void
	 */
	public function destroy($p_sErrorMsg = '') {
		if (!empty($p_sErrorMsg)) {
			$this->ircClass->privMsg($this->m_aConfig['setup']['channel'], $p_sErrorMsg);
		}

		$this->closeLogfile();

		if (!empty($this->m_aConfig['local']['ip']) && $this->m_bAuthenticated == true) {
			$this->rawPacketSend('logaddress_del '.$this->m_aConfig['local']['ip'].':'.$this->m_aConfig['local']['port']);
		}

		if ($this->timerExists('processResponse')) {
			$this->timerClass->removeTimer('processResponse');
		}
		if ($this->timerExists('doSendQueue')) {
			$this->timerClass->removeTimer('doSendQueue');
		}
		if ($this->timerExists('doReconnect')) {
			$this->timerClass->removeTimer('doReconnect');
		}
		$this->timerClass->removeTimer('parseReadLog');
		$this->timerClass->removeTimer('statusQuery');

		@socket_close($this->m_oRecvSock);
		if (!is_null($this->m_oSendSock)) {
			$this->m_oSendSock->disconnect();
		}

		$this->resetClassVars();
	}

	/**
	 * rawCommands
	 * Monitors any text send to irc, and processes class methods when needed
	 *
	 * @access public
	 * @param array $p_aLine
	 * @param array $p_aArgs
	 * @return void
	 */
	public function rawCommands($p_aLine, $p_aArgs) {
		// Only process commands send from the setup channel
		if ($p_aLine['cmd'] != 'PRIVMSG' || count($this->m_aConfig) == 0 || $p_aArgs['cmd'][0] != $this->m_aConfig['setup']['commandPrefix'] || strtolower($p_aLine['to']) != strtolower($this->m_aConfig['setup']['channel'])) {
			return;
		}

		// Build the command name
		// e.g. !test -> funcTest
		$sCommand = 'func'.strtoupper($p_aArgs['cmd'][1]).strtolower(substr($p_aArgs['cmd'], 2));
		if (method_exists($this, $sCommand)) {
			$sReturn = call_user_func(array($this, $sCommand), $p_aLine, null, $p_aArgs['query']);
			// send output when needed
			if (!empty($sReturn)) {
				$this->ircClass->privMsg($this->m_aConfig['setup']['channel'], $sReturn);
			}
		}
	}

	/**
	 * funcStatus
	 * Returns the server status
	 *
	 * @access private
	 * @param array $p_aLine
	 * @param string $p_sSteamId
	 * @param string $p_sArguments
	 * @return string
	 */
	private function funcStatus($p_aLine, $p_sSteamId, $p_sArguments) {
		if (!empty($p_sSteamId)) {
			return; // no need to use this from within the server
		}

		if (count($this->m_aServer)  > 0) {
			return 'Map: '.$this->m_aServer['map'].' - Players: '.count($this->m_aPlayers).'/'.$this->m_aServer['maxplayers'].' - Hostname: '.$this->m_aServer['hostname'].' [ '.$this->m_aConfig['server']['ip'].':'.$this->m_aConfig['server']['port'].' ]';
		} else {
			return '>_< No server status...';
		}
	}

	/**
	 * funcSay
	 * Send a message to the server through console
	 *
	 * @access private
	 * @param array $p_aLine
	 * @param string $p_sSteamId
	 * @param string $p_sArguments
	 * @return void
	 */
	private function funcSay($p_aLine, $p_sSteamId, $p_sArguments) {
		if (empty($p_sArguments)) {
			return; // we need something to say
		}

		if (!empty($p_sSteamId)) {
			return; // no need to use this from within the server
		}

		if (!$this->isInOptions(LVL_SAY, $this->getAdminInfo($p_aLine, $p_sSteamId, 'level'))) {
			return; // check if we have enough access
		}

		$sPrefix = ($this->m_aConfig['setup']['prefixsay'] === true && is_null($p_sSteamId)) ? '(IRC|'.$p_aLine['fromNick'].') ' : '';

		// 127 chars is the max
		$iTextLength = 127 - strlen($sPrefix);
		while (strlen($p_sArguments) > 0) {
			if (strlen($p_sArguments) > $iTextLength && ($iLastSpace = strrpos(substr($p_sArguments, 0, $iTextLength), ' ')) !== false) {
				$this->sendCommand($p_aLine, 'say '.$sPrefix.substr($p_sArguments, 0, $iLastSpace));
				$p_sArguments = substr($p_sArguments,$iLastSpace+1);
			} else {
				// Failsafe in case there are no spaces (which would be stupid...)
				$p_sArguments = substr($p_sArguments, 0, $iTextLength);
				$this->sendCommand($p_aLine, 'say '.$sPrefix.$p_sArguments);
				$p_sArguments = '';
			}
		}
	}

	/**
	 * funcChangelevel
	 * Change level on server
	 *
	 * @access private
	 * @param array $p_aLine
	 * @param string $p_sSteamId
	 * @param string $p_sArguments
	 * @return void
	 */
	private function funcChangelevel($p_aLine, $p_sSteamId, $p_sArguments) {
		if (empty($p_sArguments)) {
			return; // we need a map
		}

		// try to find a match
		if (($sSearch = $this->searchMap($p_sArguments)) !== false) {
			$p_sArguments = $sSearch;
		}

		// check if we have enough access
		if ($this->isInOptions(LVL_CHANGELEVEL, $this->getAdminInfo($p_aLine, $p_sSteamId, 'level'))) {
			$this->sendCommand($p_aLine, 'changelevel '.$p_sArguments);
		}
	}

	/**
	 * funcMap
	 * Alias for funcChangelevel
	 *
	 * @access private
	 * @param array $p_aLine
	 * @param string $p_sSteamId
	 * @param string $p_sArguments
	 * @return void
	 */
	private function funcMap($p_aLine, $p_sSteamId, $p_sArguments) {
		return $this->funcChangelevel($p_aLine, $p_sSteamId, $p_sArguments);
	}

	/**
	 * funcKick
	 * Kick someone from the server
	 *
	 * @access private
	 * @param array $p_aLine
	 * @param string $p_sSteamId
	 * @param string $p_sArguments
	 * @return string
	 */
	private function funcKick($p_aLine, $p_sSteamId, $p_sArguments) {
		if (empty($p_sArguments)) {
			return;
		}
		// check if we have enough access
		if ($this->isInOptions(LVL_KICK, $this->getAdminInfo($p_aLine, $p_sSteamId, 'level'))) {
			$aMatches = $this->searchPlayer($p_sArguments);
			if (count($aMatches) == 0) {
				return 'No matches found for "'.$p_sArguments.'"';
			} else if (count($aMatches) > 1) {
				return 'Too many matches for "'.$p_sArguments.'"';
			}

			$this->sendCommand($p_aLine, 'kickid '.$aMatches[0]['uid'].' Kicked by admin '.$this->getAdminInfo($p_aLine, $p_sSteamId, 'name'));
		}
	}

	/**
	 * funcBan
	 * permanently ban someone from the server
	 *
	 * @access private
	 * @param array $p_aLine
	 * @param string $p_sSteamId
	 * @param string $p_sArguments
	 * @return string
	 */
	private function funcBan($p_aLine, $p_sSteamId, $p_sArguments) {
		if (empty($p_sArguments)) {
			return;
		}
		// check if we have enough access
		if ($this->isInOptions(LVL_BAN, $this->getAdminInfo($p_aLine, $p_sSteamId, 'level'))) {
			$aMatches = $this->searchPlayer($p_sArguments);
			if (count($aMatches) == 0) {
				return 'No matches found for "'.$p_sArguments.'"';
			} else if (count($aMatches) > 1) {
				return 'Too many matches for "'.$p_sArguments.'"';
			}

			$this->sendCommand($p_aLine, 'banid 0 '.$aMatches[0]['steamid']);
			$this->sendCommand($p_aLine, 'kickid '.$aMatches[0]['uid'].' Banned by admin '.$this->getAdminInfo($p_aLine, $p_sSteamId, 'name'));
			$this->sendCommand($p_aLine, 'writeid');
		}
	}

	/**
	 * funcRcon
	 * Execute an rcon command
	 *
	 * @access private
	 * @param array $p_aLine
	 * @param string $p_sSteamId
	 * @param string $p_sArguments
	 * @return void
	 */
	private function funcRcon($p_aLine, $p_sSteamId, $p_sArguments) {
		if (empty($p_sArguments)) {
			return; // we need a command
		}

		// check if we have enough access
		if ($this->isInOptions(LVL_RCON, $this->getAdminInfo($p_aLine, $p_sSteamId, 'level'))) {
			$this->sendCommand($p_aLine, $p_sArguments);
		}
	}

	/**
	 * funcRehash
	 * Restarts the logging process, also reloads config and such
	 *
	 * @access private
	 * @param array $p_aLine
	 * @param string $p_sArguments
	 * @return void
	 */
	private function funcRehash($p_aLine, $p_sSteamId, $p_sArguments) {
		// need rcon level for this, only from irc
		if (!is_null($p_aLine) && $this->isInOptions(LVL_REHASH, $this->getAdminInfo($p_aLine, $p_sSteamId, 'level'))) {
			$this->destroy('Rehashing...');
			$this->init();
		}
	}

	/**
	 * onTransferTimeout
	 * socket function
	 *
	 * @access public
	 * @param object $p_oConnection
	 * @return void
	 */
	public function onTransferTimeout($p_oConnection) {
		$this->debug("onTransferTimeout");
	}

	/**
	 * onConnectTimeout
	 * socket function
	 *
	 * @access public
	 * @param object $p_oConnection
	 * @return void
	 */
	public function onConnectTimeout($p_oConnection) {
		$this->debug("onConnectTimeout");
	}

	/**
	 * onConnect
	 * socket function
	 * initiates authentication with source server
	 *
	 * @access public
	 * @param object $p_oConnection
	 * @return void
	 */
	public function onConnect($p_oConnection) {
		$this->debug("onConnect");
		if ($this->m_iReconnectCount > 0) {
			$this->m_iReconnectCount = 0;
			$this->ircClass->privMsg($this->m_aConfig['setup']['channel'], $this->bold().$this->color(3).'***NOTICE***'.$this->color().' Successfully reconnected to server');
		}
		$this->auth();
	}

	/**
	 * onRead
	 * socket function
	 *
	 * @access public
	 * @param object $connection
	 * @return boolean
	 */
	public function onRead($p_oConnection) {
		$this->debug("onRead");

		// Get the socket int
		$iConnInt = $p_oConnection->getSockInt();

		if ($iConnInt == $this->m_iSendSock) {
			// Grab the data
			$sLine = $this->socketClass->getQueue($iConnInt);
			$this->rawPacketRead($sLine);
		}

		// Any data left?
		return $this->socketClass->hasLine($iConnInt);
	}

	/**
	 * onWrite
	 * socket function
	 *
	 * @access public
	 * @param object $p_oConnection
	 * @return void
	 */
	public function onWrite($p_oConnection) {
		$this->debug("onWrite");
	}

	/**
	 * onAccept
	 * socket function
	 *
	 * @access public
	 * @param object $p_oConnection
	 * @return void
	 */
	public function onAccept($p_oListener, $p_oNewConnection) {
		$this->debug("onAccept");
	}

	/**
	 * onDead
	 * socket function
	 *
	 * @access public
	 * @param object $p_oConnection
	 * @return void
	 */
	public function onDead($p_oConnection) {
		if ($this->m_aConfig['reconnect']['enabled'] === true && ($this->m_aConfig['reconnect']['numTries'] == 0 || $this->m_iReconnectCount < $this->m_aConfig['reconnect']['numTries'])) {
			$iDelay = $this->m_aConfig['reconnect']['delay'];
			$this->destroy($this->bold().$this->color(4).'***WARNING***'.$this->color().' Lost connection to server, reconnecting in '.$iDelay.' seconds ['.++$this->m_iReconnectCount.']');
			$this->timerClass->addTimer('doReconnect', $this, 'init', '',$iDelay, false);
		} else {
			$this->destroy($this->bold().$this->color(4).'***WARNING***'.$this->color().' Lost connection to server, shutting down...');
		}
		$this->debug("onDead");
	}

	/**
	 * sendCommand
	 * Put a rcon command in the queue and send the first one
	 *
	 * @access private
	 * @param array $p_aLine
	 * @param string $p_sCommand
	 * @return void
	 */
	private function sendCommand($p_aLine, $p_sCommand) {
		if ($this->m_bAuthenticated !== true) {
			$this->ircClass->privMsg($this->m_aConfig['setup']['channel'], 'Not authenticated...');
			return;
		}

		// push the command onto the queue
		if (!empty($p_sCommand)) {
			// store where the command was send from
			if (is_null($p_aLine)) {
				array_push($this->m_aServerCommands, $p_sCommand);
			} else {
				array_push($this->m_aIrcCommands, $p_sCommand);
			}

			array_push($this->m_aSendBuff, $p_sCommand);

			if (!$this->timerExists('doSendQueue')) {
				$this->timerClass->addTimer('doSendQueue', $this, 'doSendQueue', '', ($this->m_fCommandDelay), true);
			}
		}
	}

	/**
	 * soSendQueue
	 * Sends the next command in line
	 *
	 * @access public
	 * @return void
	 */
	public function doSendQueue() {
		if (count($this->m_aSendBuff) < 1) {
			return false;
		}

		$this->rawPacketSend(array_shift($this->m_aSendBuff));
		return true;
	}

	/**
	 * rawPacketSend
	 * Sends a command to the server
	 *
	 * @access private
	 * @param string $p_sString1
	 * @param string $p_sString2
	 * @param int $p_iCommand
	 * @return void
	 */
	private function rawPacketSend($p_sString1, $p_sString2 = NULL, $p_iCommand = SERVERDATA_EXECCOMMAND) {
		// build the packet backwards
		$sPacket = $p_sString1 . "\x00" . $p_sString2 . "\x00";
		// build the Request ID and Command into the Packet
		$sPacket = pack('VV',++$this->m_iRequestID, $p_iCommand) . $sPacket;
		// add the length
		$sPacket = pack('V',strlen($sPacket)) . $sPacket;
		// send the packet.
		$this->socketClass->sendSocket($this->m_iSendSock, $sPacket);
		// store the command send
		$this->m_aResponses[$this->m_iRequestID]['CommandSend'] = $p_sString1;
		// set up a timer to read the response
		$this->timerClass->addTimer('processResponse', $this, 'processResponse', $this->m_iRequestID, $this->m_fCommandDelay, false);

		$this->debug('rawPacketSend -> '.$p_sString1);
	}

	/**
	 * rawPacketRead
	 * Process raw data from the socket
	 *
	 * @access private
	 * @param string $p_sResponse
	 * @return void
	 */
	private function rawPacketRead($p_sResponse) {
		$iTotalSizeRead = 0;
		if (isset($this->m_aResponseBuff['packet'])) {
			$iPacketSizeRead = strlen($this->m_aResponseBuff['packet']);
		} else {
			$iPacketSizeRead = 0;
		}
		while ($iTotalSizeRead < strlen($p_sResponse)) {
			// New packet
			if (!isset($this->m_aResponseBuff['length'])) {
				$aLength = unpack('V1PacketLength', substr($p_sResponse, $iTotalSizeRead, 4));
				$this->m_aResponseBuff['length'] = $aLength['PacketLength'];
				$this->m_aResponseBuff['packet'] = null;
				$iTotalSizeRead += 4;
				$iPacketSizeRead = 0;
			}

			$this->m_aResponseBuff['packet'] .= substr($p_sResponse, $iTotalSizeRead, ( $this->m_aResponseBuff['length'] - $iPacketSizeRead ) );
			$iTotalSizeRead += ( $this->m_aResponseBuff['length'] - $iPacketSizeRead );

			if (strlen($this->m_aResponseBuff['packet']) == $this->m_aResponseBuff['length']) {
				$aResponse = unpack('V1RequestID/V1CommandResponse/a*String1/a*String2', $this->m_aResponseBuff['packet']);
				$this->m_aResponseBuff = array();

				if ($this->m_bAuthenticated === false && $aResponse['RequestID'] == 1 && $aResponse['CommandResponse'] == SERVERDATA_AUTH_RESPONSE) {
					// authenticated succesfully
					$this->m_bAuthenticated = true;
					if (!empty($this->m_aConfig['local']['ip'])) {
						$this->sendCommand(null, 'logaddress_add '.$this->m_aConfig['local']['ip'].':'.$this->m_aConfig['local']['port']);
					}
					$this->statusQuery();
					$this->sendCommand(null, 'maps *');
				} else if (isset($this->m_aResponses[$aResponse['RequestID']]['String1'])) {
					// add to command response
					$this->m_aResponses[$aResponse['RequestID']]['String1'] .= $aResponse['String1'];
					$this->m_aResponses[$aResponse['RequestID']]['String2'] .= $aResponse['String2'];
				} else {
					// build new command response
					$this->m_aResponses[$aResponse['RequestID']]['CommandResponse'] = $aResponse['CommandResponse'];
					$this->m_aResponses[$aResponse['RequestID']]['String1'] = $aResponse['String1'];
					$this->m_aResponses[$aResponse['RequestID']]['String2'] = $aResponse['String2'];
				}
			}
		}
	}

	/**
	 * processResponse
	 * process rcon command response
	 *
	 * @access public
	 * @param int $p_iRequestId
	 * @return void
	 */
	public function processResponse($p_iRequestId) {
		$this->debug(print_r($this->m_aResponses[$p_iRequestId], true));
		@list($sCommand, $sArgs) = explode(' ', $this->m_aResponses[$p_iRequestId]['CommandSend'], 2);
		// check if we've got a parsing function
		if (method_exists($this, 'sLog_'.$sCommand)) {
			$sReturn = call_user_func(array($this, 'sLog_'.$sCommand), $sArgs, $this->m_aResponses[$p_iRequestId]['String1']);
		}
		// send output to irc
		if (($iIndex = array_search($this->m_aResponses[$p_iRequestId]['CommandSend'], $this->m_aIrcCommands)) !== false) {
			if (!empty($sReturn)) {
				$this->ircClass->privMsg($this->m_aConfig['setup']['channel'], $sReturn);
			}
			unset($this->m_aIrcCommands[$iIndex]);
		}
		// send output to server
		if (($iIndex = array_search($this->m_aResponses[$p_iRequestId]['CommandSend'], $this->m_aServerCommands)) !== false) {
			if (!empty($sReturn)) {
				$this->sendCommand(null, 'say '.$sReturn);
			}
			unset($this->m_aServerCommands[$iIndex]);
		}

		unset($this->m_aResponses[$p_iRequestId]);
	}

	/**
	 * sLog_status
	 * Parses 'rcon status' to retreive server information
	 *
	 * @access private
	 * @param array $p_sArgs
	 * @param string $p_sResponse
	 * @return void
	 */
	private function sLog_status($p_sArgs, $p_sResponse) {
		$sPattern = 	'#';
		$sPattern .=	'hostname:\040*([^\r\n]*)\r?\n';
		$sPattern .=	'version.*\r?\n';
		$sPattern .=	'udp/ip.*\r?\n';
		$sPattern .=	'map\040*:\040*(.*) at:.*\r?\n';
		$sPattern .=	'(?:sourcetv:.*\r?\n)?'; // optional in source, ignored in output
		$sPattern .=	'players\040*:\040*\d* \((\d*) max\)\r?\n';
		$sPattern .=	'\r?\n?';
		$sPattern .=	'\# userid name uniqueid connected ping loss state adr\r?\n?';
		$sPattern .=	'(.*)';
		$sPattern .=	'#i';
		// get server info
		preg_match($sPattern, $p_sResponse, $aMatches);
		$this->m_aServer['hostname'] = $aMatches[1];
		$this->m_aServer['map'] = $aMatches[2];
		$this->m_aServer['maxplayers'] = $aMatches[3];
		// parse the player list
		if (!empty($aMatches[4])) {
			$this->debug('sLog_status -> FILLING PLAYER ARRAY');
			$sPattern = '#\#\040{0,2}(?P<uid>\d{1,8}) "(?P<name>.*)" (?P<steamid>BOT|STEAM_\d:\d:\d{1,9}).*\040?(?P<ip>\d+\.\d+\.\d+\.\d+:\d+)?\r?\n?#i';
			preg_match_all($sPattern, $p_sResponse, $aPlayers, PREG_SET_ORDER);
			foreach($aPlayers as $aPlayer) {
				$this->m_aPlayers[strtolower($aPlayer['name'])] = array('name' => $aPlayer['name'], 'uid' => $aPlayer['uid'] , 'steamid' => $aPlayer['steamid'], 'ip' => (isset($aPlayer['ip']) ? $aPlayer['ip'] : ''));
			}
		}
	}

	/**
	 * sLog_maps
	 * Parses 'rcon maps *' to retreive the map list
	 *
	 * @access private
	 * @param array $p_sArgs
	 * @param string $p_sResponse
	 * @return void
	 */
	private function sLog_maps($p_sArgs, $p_sResponse) {
		preg_match_all('/([^\040\r\n\t]*)\.bsp/i', $p_sResponse, $aMatches);
		$this->m_aMaps = $aMatches[1];
	}

	/**
	 * sLog_changelevel
	 * Parses 'rcon changelevel' to see if map existed
	 *
	 * @access private
	 * @param array $p_sArgs
	 * @param string $p_sResponse
	 * @return void
	 */
	private function sLog_changelevel($p_sArgs, $p_sResponse) {
		// Map not found
		if (preg_match('#changelevel failed: (.*) not found#i', $p_sResponse) === 1) {
			return 'Map not found on server';
		}
	}

	/**
	 * parseReadLog
	 * parse the UDP log
	 *
	 * @access public
	 * @return boolean
	 */
	public function parseReadLog() {
		@socket_recvfrom($this->m_oRecvSock, $sLine, 1024, 0, $this->m_aConfig['server']['ip'], $this->m_aConfig['local']['port']);
		// some cleaup
		$sLine = str_replace(array("\r","\n","\xFF","\x00"), '', $sLine);
		if (empty($sLine)) {
			// no need to process empty lines
			return true;
		}

		if ($this->m_aConfig['setup']['logtofile'] === true) {
			array_push($this->m_aLogBuffer, $sLine);
			if (count($this->m_aLogBuffer) >= $this->m_iLogBufferSize) {
				file_put_contents($this->m_sLogFilename, implode("\n", $this->m_aLogBuffer), FILE_APPEND);
				$this->m_aLogBuffer = array();
			}
		}

		// loops each handler and call the registerd function on match
		foreach ($this->m_aReadHandlers as $aHandler) {
			// If a function exists for the event, call it and catch the return
			if (preg_match('#\d{2}/\d{2}/\d{4} - \d{2}:\d{2}:\d{2}: '.$aHandler['regex'].'#i', $sLine, $aMatches) === 1) {
				$sOutput = call_user_func(array($this, $aHandler['func']), $aMatches);
				$this->debug('parseReadLog -> EVENT HANDLER CALLED -> '.$aHandler['func']);
				break;
			}
		}

		if (!empty($sOutput)) {
			$this->ircClass->privMsg($this->m_aConfig['setup']['channel'], $sOutput);
		}

		$this->debug($sLine);

		// return true so the timer comes here again
		return true;
	}

	/**
	 * rLog_say
	 * parse UDP log say events
	 * Also acts as a way to grab 'admin commands' from the server
	 *
	 * @access private
	 * @param array $p_aMatches
	 * @return string
	 */
	private function rLog_say($p_aMatches) {
		// 1:name || 2:uid || 3:steamid || 4:team || 5:text
		if (strtolower($p_aMatches[1]) == 'console' && $p_aMatches[2] == 0 && $this->isInOptions(LOG_SERVERSAY, $this->m_aConfig['setup']['loglevel']) === false) {
			// no need to continue for server messages when they don't need to be logged
			return;
		}

		if (substr($p_aMatches[5], 0, 1) == $this->m_aConfig['setup']['commandPrefix']) {
			$aTmp = explode(' ', substr($p_aMatches[5],1), 2);
			$sCommand = $aTmp[0];
			$sCommand = strtoupper($sCommand[0]).strtolower(substr($sCommand,1));
			if (method_exists($this, 'func'.$sCommand)) {
				$this->debug('rLog_say -> Calling function func'.$sCommand);
				$sReturn = call_user_func(array($this, 'func'.$sCommand), null, $p_aMatches[3], (isset($aTmp[1]) ? $aTmp[1] : null));
				if (!empty($sReturn)) {
					$this->sendCommand(null, 'say '.$sReturn);
				}
			}
		}

		if ($this->isInOptions(LOG_SAY, $this->m_aConfig['setup']['loglevel']) || (strtolower($p_aMatches[1]) == 'console' && $p_aMatches[2] == 0 && $this->isInOptions(LOG_SERVERSAY, $this->m_aConfig['setup']['loglevel']) === true)) {
			return $this->color($this->getColor($p_aMatches[4])).' '.$p_aMatches[1].$this->color().': '.$p_aMatches[5];
		}
	}

	/**
	 * rLog_say_team
	 * parse UDP log say_team events
	 *
	 * @access private
	 * @param array $p_aMatches
	 * @return string
	 */
	private function rLog_say_team($p_aMatches) {
		// 1:name || 2:uid || 3:steamid || 4:team || 5:text
		return $this->color($this->getColor($p_aMatches[4])).' '.$p_aMatches[1].$this->color().' (TEAM): '.$p_aMatches[5];
	}

	/**
	 * rLog_kill
	 * parse UDP log kill events
	 *
	 * @access private
	 * @param array $p_aMatches
	 * @return string
	 */
	private function rLog_kill($p_aMatches) {
		// ATTACKER 1:name || 2:uid || 3:steamid || 4:team
		// VICTIM 5:name || 6:uid || 7:steamid || 8:team
		// 9:weapon
		return $this->color($this->getColor($p_aMatches[4])).' '.$p_aMatches[1].$this->color().' killed'.$this->color($this->getColor($p_aMatches[8])).' '.$p_aMatches[5].$this->color().' with '.$p_aMatches[9];
	}

	/**
	 * rLog_connected
	 * parse UDP log connected events
	 *
	 * @access private
	 * @param array $p_aMatches
	 * @return string
	 */
	private function rLog_connected($p_aMatches) {
		list($sIp, $sPort) = explode(':', $p_aMatches[4]);

		if ($this->checkBadName($p_aMatches[1], $p_aMatches[2]) !== false) {
			return;
		}

		$this->m_aPlayers[strtolower($p_aMatches[1])] = array('name' => $p_aMatches[1], 'uid' => $p_aMatches[2], 'steamid' => $p_aMatches[3], 'ip' => $sIp);

		if (strtolower($p_aMatches[3]) != 'bot' && $this->isInOptions(LOG_CONNECT, $this->m_aConfig['setup']['loglevel'])) {
			return $p_aMatches[1].' has connected ('.$sIp.')';
		}
	}

	/**
	 * rLog_userid_validated
	 * parse UDP log userid_validated events
	 *
	 * @access private
	 * @param array $p_aMatches
	 * @return void
	 */
	private function rLog_userid_validated($p_aMatches) {
		$this->m_aPlayers[strtolower($p_aMatches[1])]['steamid'] = $p_aMatches[3];
	}

	/**
	 * rLog_enter_game
	 * parse UDP log 'entered the game' events
	 *
	 * @access private
	 * @param array $p_aMatches
	 * @return void
	 */
	private function rLog_enter_game($p_aMatches) {
		if (!isset($this->m_aPlayers[strtolower($p_aMatches[1])])) {
			$this->m_aPlayers[strtolower($p_aMatches[1])] = array('name' => $p_aMatches[1], 'uid' => $p_aMatches[2], 'steamid' => $p_aMatches[3]);
		} else {
			$this->m_aPlayers[strtolower($p_aMatches[1])]['name'] = $p_aMatches[1];
			$this->m_aPlayers[strtolower($p_aMatches[1])]['uid'] = $p_aMatches[2];
			$this->m_aPlayers[strtolower($p_aMatches[1])]['steamid'] = $p_aMatches[3];
		}
	}

	/**
	 * rLog_joined_team
	 * parse UDP log 'joined team' events
	 *
	 * @access private
	 * @param array $p_aMatches
	 * @return string
	 */
	private function rLog_joined_team($p_aMatches) {
		// 1:name || 2:uid || 3:steamid || 4:team || 5:new team
		return $this->color($this->getColor($p_aMatches[4])).' '.$p_aMatches[1].$this->color().' joined team '.$p_aMatches[5];
	}

	/**
	 * rLog_disconnect
	 * parse UDP log disconnect events
	 *
	 * @access private
	 * @param array $p_aMatches
	 * @return string
	 */
	private function rLog_disconnect($p_aMatches) {
		unset($this->m_aPlayers[strtolower($p_aMatches[1])]);

		if (strtolower($p_aMatches[3]) != 'bot' && $this->isInOptions(LOG_DISCONNECT, $this->m_aConfig['setup']['loglevel'])) {
			return $p_aMatches[1].' disconnected ('.$p_aMatches[5].')';
		}
	}

	/**
	 * rLog_file_start
	 * parse UDP log 'log file started' events
	 *
	 * @access private
	 * @param array $p_aMatches
	 * @return void
	 */
	private function rLog_file_start($p_aMatches) {
		$this->openLogfile();
		$this->sendCommand(null, 'status');
	}

	/**
	 * rLog_loading_map
	 * parse UDP log 'loading map' events
	 *
	 * @access private
	 * @param array $p_aMatches
	 * @return string
	 */
	private function rLog_loading_map($p_aMatches) {
		$this->m_aServer['map'] = $p_aMatches[1];
		if ($this->isInOptions(LOG_MAPCHANGE, $this->m_aConfig['setup']['loglevel'])) {
			return 'Loading map '.$p_aMatches[1];
		}
	}

	/**
	 * rLog_file_close
	 * parse UDP log 'log file close events
	 *
	 * @access private
	 * @param array $p_aMatches
	 * @return void
	 */
	private function rLog_file_close($p_aMatches) {
		$this->closeLogfile();
		$this->m_aPlayers = array();
	}

	/**
	 * rLog_change_name
	 * parse UDP log 'changed name' events
	 *
	 * @access private
	 * @param array $p_aMatches
	 * @return string
	 */
	private function rLog_change_name($p_aMatches) {
		// 1:old name || 2:uid || 3:steamid || 4:team || 5:new name
		if ($this->checkBadName($p_aMatches[5], $p_aMatches[2]) !== false) {
			return;
		}

		// Copy details from old to new entry
		$this->m_aPlayers[strtolower($p_aMatches[5])] = $this->m_aPlayers[strtolower($p_aMatches[1])];
		// update the real nick (propper-case)
		$this->m_aPlayers[strtolower($p_aMatches[5])]['name'] = $arr[5];
		// unset the old entry
		unset($this->m_aPlayers[strtolower($p_aMatches[1])]);

		if ($this->isInOptions(LOG_NAMECHANGE, $this->m_aConfig['setup']['loglevel'])) {
			return $this->bold().$p_aMatches[1].$this->bold().' changed name to '.$this->bold().$p_aMatches[5];
		}
	}

	/**
	 * readConfig
	 * Load configuration settings
	 *
	 * @access private
	 * @return void
	 */
	private function readConfig() {
		require('modules/source_rcon/settings.php');
		$this->m_aConfig['setup']		= $setup;
		$this->m_aConfig['reconnect']	= $reconnect;
		$this->m_aConfig['server']		= $server;
		$this->m_aConfig['local']		= $local;
		$this->m_aConfig['colors']		= $colors;

		require('modules/source_rcon/admins.php');
		$this->m_aConfig['admins'] = $admins;
	}

	/**
	 * auth
	 * Authenticate rcon session
	 *
	 * @access private
	 * @return void
	 */
	private function auth() {
		$this->rawPacketSend($this->m_aConfig['server']['password'], NULL, SERVERDATA_AUTH);
	}

	/**
	 * statusQuery
	 * Used to keep the connection alive, this way we dont have to open it for each command
	 * returns true so we can use this in a timer
	 *
	 * @access public
	 * @return boolean
	 */
	public function statusQuery() {
		$this->sendCommand(null, 'status');
		return true;
	}

	/**
	 * getColor
	 * Return the IRC color code for a team
	 *
	 * @access private
	 * @param string $p_sTeam
	 * @return int
	 */
	private function getColor($p_sTeam) {
		$p_sTeam = strtolower($p_sTeam);
		return (isset($this->m_aConfig['colors'][$p_sTeam])) ? $this->m_aConfig['colors'][$p_sTeam] : '';
	}

	/**
	 * getAdminInfo
	 * returns the admin info, either matched by steamid or host
	 *
	 * @access private
	 * @package array $p_aLine
	 * @param string $p_sSteamId
	 * @param string $p_sTarget
	 * @return mixed
	 */
	private function getAdminInfo($p_aLine = null, $p_sSteamId = null, $p_sTarget) {
		if (!in_array($p_sTarget, array('name', 'level', 'steamid', 'host'))) {
			return false;
		}

		if (!is_null($p_sSteamId)) {
			$sIndex = 'steamid';
			$sSearch = $p_sSteamId;
		} else {
			$sIndex = 'host';
			$sSearch = $p_aLine['fromHost'];
		}

		for ($i = 0; $i < count($this->m_aConfig['admins']); $i++) {
			if ($this->m_aConfig['admins'][$i][$sIndex] == $sSearch) {
				return $this->m_aConfig['admins'][$i][$p_sTarget];
			}
		}

		// exception for 'irc-op-admins'
		if (($p_sTarget == 'level' || $p_sTarget == 'name') && is_null($p_sSteamId)) {
			if ($p_sTarget == 'level') {
				return $this->m_aConfig['setup']['opLevel'];
			} else if ($p_sTarget == 'name') {
				return $p_aLine['fromNick'];
			}
		}

		return false;
	}

	/**
	 * searchPlayer
	 * Search for a player by name or portion of the name
	 *
	 * @access private
	 * @param string $p_sName
	 * @return array
	 */
	private function searchPlayer($p_sName) {
		$aMatches = array();
		$p_sName = strtolower($p_sName);
		foreach ($this->m_aPlayers as $sName => $aPlayer) {
			if (strstr($sName, $p_sName) !== false) {
				$aMatches[] = $aPlayer;
			}
		}
		return $aMatches;
	}

	/**
	 * searchMap
	 * Search for a map by name or portion of the name
	 * returns false when more then 1 match is found
	 *
	 * @access private
	 * @param string $p_sName
	 * @return array
	 */
	private function searchMap($p_sName) {
		$aMatches = array();
		for ($i = 0; $i < count($this->m_aMaps); $i++) {
			if (stristr($this->m_aMaps[$i], $p_sName) !== false) {
				// exact match
				if (strtolower($p_sName) == strtolower($this->m_aMaps[$i])) {
					return $this->m_aMaps[$i];
				}
				$aMatches[] = $this->m_aMaps[$i];
			}
		}
		if (count($aMatches) === 1) {
			return $aMatches[0];
		}
		return false;
	}

	/**
	 * checkBadName
	 * Checks if a players nick, or part of it, is not allowed on the server and kicks them when needed
	 *
	 * @access private
	 * @param string $p_sName
	 * @param int $p_iUid
	 * @return boolean
	 */
	private function checkBadName($p_sName, $p_iUid = 0) {
		if (count($this->m_aConfig['setup']['badnames']) > 0) {
			$this->debug('checkBadName -> checking "'.$p_sName.'" against '.count($this->m_aConfig['setup']['badnames']).' patterns');
			for($i = 0; $i < count($this->m_aConfig['setup']['badnames']); $i++) {
				if (preg_match($this->m_aConfig['setup']['badnames'][$i], $p_sName)) {
					if ($p_iUid == 0) {
						$aPlayer = $this->searchPlayer($p_sName);
						$p_iUid = $aPlayer[0]['uid'];
					}
					$this->sendCommand(null, 'kickid '.$p_iUid.' Your nick, or part of your nick, is not allowed on this server');
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * timerExists
	 * check if a timer exists
	 *
	 * @access private
	 * @param string $p_sTimer
	 * @return boolean
	 */
	private function timerExists($p_sTimer) {
		return array_key_exists($p_sTimer, $this->timerClass->getTimers());
	}

	/**
	 * registerHandlers
	 * Register our UDP handlers
	 *
	 * @access private
	 * @return void
	 */
	private function registerHandlers() {
		// always need this to catch 'admin commands' from server
		$this->addHandler('rLog_say',					'"'.PATTERN_PLAYER_FULL.'" say "(.*)"');

		if ($this->isInOptions(LOG_TEAMSAY, $this->m_aConfig['setup']['loglevel'])) {
			$this->addHandler('rLog_say_team',			'"'.PATTERN_PLAYER_FULL.'" say_team "(.*)"');
		}
		if ($this->isInOptions(LOG_KILL, $this->m_aConfig['setup']['loglevel'])) {
			$this->addHandler('rLog_kill',				'"'.PATTERN_PLAYER_FULL.'" killed "'.PATTERN_PLAYER_FULL.'" with "(.*)"');
		}
		if ($this->isInOptions(LOG_CONNECT, $this->m_aConfig['setup']['loglevel'])) {
			$this->addHandler('rLog_connected',			'"(.*)<(\d{1,9})><(BOT|STEAM_ID_PENDING)><>" connected, address "(none|\d{1,3}.\d{1,3}.\d{1,3}.\d{1,3}:\d{1,5})"');
		}
		if ($this->isInOptions(LOG_TEAMJOIN, $this->m_aConfig['setup']['loglevel'])) {
			$this->addHandler('rLog_joined_team',		'"'.PATTERN_PLAYER_FULL.'" joined team "(.*)"');
		}

		// Always use these, used to fill and update player array
		$this->addHandler('rLog_enter_game', 	'"'.PATTERN_PLAYER_FULL.'" entered the game');
		$this->addHandler('rLog_change_name',	'"'.PATTERN_PLAYER_FULL.'" changed name to "(.{1,35})"');
		$this->addHandler('rLog_disconnect', 	'"'.PATTERN_PLAYER_FULL.'" disconnected \(reason "(.*)"\)');

		// to check for map changes
		$this->addHandler('rLog_file_start', 	'Log file started \(file "(.*)"\) \(game "(.*)"\) \(version "(.*)"\)');
		$this->addHandler('rLog_loading_map',	'Loading map "(.*)"');
		$this->addHandler('rLog_file_close', 	'Log file closed');
	}

	/**
	 * addHandler
	 * Adds a handler for the UDP log
	 *
	 * @access private
	 * @param string $sFunc
	 * @param string $sRegex
	 * @return void
	 */
	private function addHandler($sFunc, $sRegex) {
		if (method_exists($this, $sFunc)) {
			array_push($this->m_aReadHandlers, array('func' => $sFunc, 'regex' => $sRegex));
		}
	}

	/**
	 * openLogfile
	 * Assigns a new logfile
	 *
	 * @access private
	 * @return void
	 */
	private function openLogfile() {
		if ($this->m_aConfig['setup']['logtofile'] === true) {
			$this->closeLogfile();
			$this->m_sLogFilename = 'modules/source_rcon/logs/'.date('Ymd-His').'.txt';
		}
	}

	/**
	 * closeLogfile
	 * Writes the remaining buffer to, and unassigns the current logfile
	 *
	 * @access private
	 * @return void
	 */
	private function closeLogfile() {
		if ($this->m_aConfig['setup']['logtofile'] === true) {
			if (count($this->m_aLogBuffer) > 0 && !empty($this->m_sLogFilename)) {
				file_put_contents($this->m_sLogFilename, implode("\n", $this->m_aLogBuffer), FILE_APPEND);
				$this->m_aLogBuffer = array();
			}
			$this->m_sLogFilename = null;
		}
	}

	/**
	 * isInOptions
	 *
	 * @access private
	 * @param int $p_iSearch
	 * @param int $p_iOptions
	 * @return boolean
	 */
	private function isInOptions($p_iSearch, $p_iOptions) {
		if ($p_iSearch == 0) {
			return false;
		}
		if (($p_iSearch & $p_iOptions) == $p_iSearch) {
			return true;
		}
		return false;
	}

	/**
	 * resetClassVars
	 * Reset all the class wide variables back to their default values
	 *
	 * @access private
	 * @return void
	 */
	private function resetClassVars() {
		$this->m_oSendSock = null;
		$this->m_iSendSock = null;
		$this->m_iRequestID = 0;
		$this->m_bAuthenticated = false;
		$this->m_aSendBuff = array();
		$this->m_aResponseBuff = array();
		$this->m_aResponses = array();
		$this->m_aIrcCommands = array();
		$this->m_aServerCommands = array();
		$this->m_oRecvSock = null;
		$this->m_aReadHandlers = array();
		$this->m_aConfig = array();
		$this->m_aServer = array();
		$this->m_aPlayers = array();
		$this->m_aMaps = array();
		$this->m_fCommandDelay = 0.5;
		$this->m_sLogFilename = null;
		$this->m_iLogBufferSize = 20;
		$this->m_aLogBuffer = array();
	}

	/**
	 * color
	 * Return color coding character with optional formatting
	 *
	 * @access private
	 * @param string $p_sCode
	 * @return string
	 */
	private function color($p_sCode = '') {
		return ($this->m_aConfig['setup']['textMarkup'] === true) ? chr(3).$p_sCode : '';
	}

	/**
	 * bold
	 * return bold character
	 *
	 * @access private
	 * @return string
	 */
	private function bold() {
		return ($this->m_aConfig['setup']['textMarkup'] === true) ? chr(2) : '';
	}

	/**
	 * underline
	 * return underline character
	 *
	 * @access private
	 * @return string
	 */
	private function underline() {
		return ($this->m_aConfig['setup']['textMarkup'] === true) ? chr(31) : '';
	}

	/**
	 * reverse
	 * return reverse character
	 *
	 * @access private
	 * @return string
	 */
	private function reverse() {
		return ($this->m_aConfig['setup']['textMarkup'] === true) ? chr(22) : '';
	}


	/**
	 * debug
	 * simple debugging function
	 *
	 * @access private
	 * @param unknown_type $string
	 * @return void
	 */
	private function debug($p_sLine) {
		if (isset($this->m_aConfig['setup']['debug']) && $this->m_aConfig['setup']['debug'] === true) {
			echo '-[ DEBUG ]- '.$p_sLine."\r\n";
		}
	}
}

?>