<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC Werewolf Game Mod
|   ========================================================
|   Coded by Jeff Gennusa
|   (c) 2006 by http://www.nonstophits.net
|   Contact: admin@nonstophits.net
|   irc: #werewolf@irc.gamesurge.net
|   ========================================
+---------------------------------------------------------------------------
*/

class ww_mod extends module {

	public $title = "Werewolf Game";
	public $author = "Juice";
	public $version = "0.1";
	public $gamestarted = "0";
	public $joining = "0";
	public $joined = "0";

	// this is where is all begins.  
	// someone needs to type one of the following commands to make stuff happen:
	// !ww (starts a game of werewolf)
	public function parse_ww($line, $args)
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;
		// if no game is started yet, then start a game.  
		if ($gamestarted == 0)
		{
			// did someone type !ww  
			if ($args['nargs'] <= 0)
			{
				// Set some game variables
				$minplayers = "5";	// Minimum number of players required to start a game
				$maxplayers = "30";	// Maximum number of players allowed to playt per game
				$twowolves = "9";	// Minimum number of players required to have two wolves
				$threewolves = "17";	// Minimum number of players required to have three wolves
				$twoseers = "14";	// Minimum number of players required to have two seers
				$daytime = "45";	// Time for daily lynching discussion
				$votetime = "60";	// Time for voting for daily lynching
				$nighttime = "45";	// Time for wolves and seers to complete their actions

				// get the channel the bot is in, the bot's nick and who typed !ww
				// send msg to channel that a game has begun
				// start counting number of players, need minimum of 5 to play
				// start timer for joining
				$this->doVoice("batch");
				$chan = $line['to'];
				$botnick = $this->ircClass->getNick();
				$fromNick =$line['fromNick'];
				$fromHost = $line['fromHost'];
				$this->ircClass->changeMode($chan, "+", "m", "");
				// Nick, Host, Role, Alive(1=yes), Didn't Vote, Votes against, seen
				$players = array();
				$players = array( array("$fromNick",  "$fromHost", "villager", 1, 0, 0, 0));
				$this->ircClass->changeMode($chan, "+", "v", "$fromNick");
				$huntleader = $line['fromNick'];
				$this->ircClass->privMsg($chan, chr(3) . 2 . BOLD . $line['fromNick']." has started a game. " . BOLD . " Everyone else has " . BOLD . " 60 " . BOLD . " seconds to join the mob. Type " . BOLD . " '/msg $botnick join' " . BOLD . " to join the hunt.", 1);
				$joining = 1;
				$gamestarted = 1;
				$joined = 1;
				$this->timerClass->addTimer("play_game", $this, "playGame", "", 60, false);
			}
		}
		// someone has already started a game, can't start two at the same time  
		else
			$this->ircClass->privMsg($line['fromNick'], $huntleader." has already started a game. Type " . BOLD . " '/msg $botnick join' " . BOLD . " to join the hunt.", 1);
	}

	// type '/msg $botnick alive' to get a list of alive players
	public function alive($line, $args) 
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;
		$fromNick =$line['fromNick'];
		for ( $gettingalivelist = 0; $gettingalivelist < $joined; $gettingalivelist++ )
		{
			if ($players[$gettingalivelist][3] == 1)
				$playersalive .= $players[$gettingalivelist][0].", ";
		}
			$this->ircClass->notice($chan, "$fromNick, The villagers who remain alive are: ". $playersalive, 1);
	}

	public function checkWin() 
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;
		for ( $checkingforwin = 0; $checkingforwin < $joined; $checkingforwin++ )
		{
			$maxRow = -1;
			$maxNum = -1;
			$maxRowArray = array();
			$maxVillagerArray = array();
			$maxWolfArray = array();
			foreach ($players AS $row => $data)
			{
				if ($data[3] == 1)
				{
					if ($data[2] == "wolf")
					{
						$wolfnick = $data[0];
						$maxWolfArray[] = $row;
					}
					else
						$maxVillagerArray[] = $row;
				}
			}
			if (count($maxWolfArray) == 0)
			{
				$this->ircClass->privMsg($chan, chr(3) . 2 . "With the beasts slain, the villagers cheer! Their peaceful village is once again free from the scourge of the Werewolf!", 1);
				$this->ircClass->privMsg($chan, chr(3) . 2 . BOLD . "Congratulations, Villagers! You win!" . BOLD, 1);
				$this->endGame();
			}
			elseif (count($maxWolfArray) >= count($maxVillagerArray))
			{
				if (count($maxWolfArray) == 1)
				{
					$this->ircClass->privMsg($chan, chr(3) . 2 . "Having successfully deceived the rest of the village's population, " . BOLD . " $wolfnick the Werewolf, " . BOLD . " breaks into the final villager's home and rips out their jugular. $wolfnick bays at the moon, before setting off to the next village...", 1);
					$this->ircClass->privMsg($chan, chr(3) . 2 . BOLD . "Congratulations, $wolfnick! You win!" . BOLD, 1);
				}
				else
				{
					$this->ircClass->privMsg($chan, chr(3) . 2 . "That night, their plan of deception finally bearing it's fruit, the Werewolves finish off the rest of the human population, and feast, before bounding off together, towards the next village...", 1);
					$this->ircClass->privMsg($chan, chr(3) . 2 . BOLD . "Congratulations, Werewolves! You win!" . BOLD, 1);
					for ( $gettingwolflist = 0; $gettingwolflist < $joined; $gettingwolflist++ )
					{
						if ($players[$gettingwolflist][2] == "wolf")
							$wolflist .= $players[$gettingwolflist][0].", ";
					}
					$this->ircClass->privMsg($chan, chr(3) . 2 . "The Werewolves were: ". $wolflist, 1);
				}
				$this->endGame();
			}
		}
	}

	//method to batch voice/devoice all the users on the playerlist.
	public function doVoice($on) 
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;

		if ($on == "batch")
		{
			$channelData = $this->ircClass->getChannelData("#werewolf");
			$members = $channelData->memberList;
			foreach ($members AS $memberData)
			{
				// $memberData is an individual member object
				$this->ircClass->changeMode($chan, "-", "v", $memberData->realNick);
			}
		
		}
		for ( $voicing = 0; $voicing < $joined; $voicing++ )
		{
			if ($players[$voicing][3] == 1)
			{
				$Nick = $players[$voicing][0];
				if ($on == 1)
					$this->ircClass->changeMode($chan, "+", "v", "$Nick");
				else
					$this->ircClass->changeMode($chan, "-", "v", "$Nick");
			}
		}
	}

	public function endGame() 
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;
		$gamestarted = "0";
		$joining = "0";
		$joined = "0";
		$this->ircClass->changeMode($chan, "-", "m", "");
		$this->doVoice("batch");
	}

	// type '/msg $botnick joinhunt' to join the hunt
	public function joinhunt($line, $args)
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;
		if ($this->ircClass->getNick() == $line['to'])
		{
			if ($this->ircClass->isOnline($line['fromNick'], $chan))
			{
				// did someone pm us with joinhunt  
				if ($args['nargs'] >= 0)
				{
					// if there are $maxplayers players signed up, 
					// everyone else must wait for next game  
					if ($joined <= $maxplayers)
					{
						// joining is open during 60 second timer
					        // add new player to the alive players list	
						if ($joining == 1)
						{
							$fromNick = $line['fromNick'];
							$fromHost = $line['fromHost'];
							$test = false;
							$row = 0;
							do
							{
								if ($fromHost === $players[$row][1])
								{
									$test = true;
									$row = $joined;
								}
								else
									$row++;
							}
							while ($row < $joined);
							if ($test == true)
							{
								$this->ircClass->notice($fromNick, "You are already in the mob.  If you are that desperate to be noticed, then start jumping up and down and waving your arms.", 1);
							}
							else
							{
								$joinvar = $joined - 1;
								$players[$joinvar] = array($fromNick,  $fromHost, "villager", 1, 0, 0, 0);
								$this->ircClass->privMsg($chan,  chr(3) . 2 . BOLD ."$fromNick has joined the mob.". BOLD, 1);
								$this->ircClass->changeMode($chan, "+", "v", "$fromNick");
								$joined++;
							}
						}
					        // a hunt is in progress, joining is closed during active hunts	
						elseif ($joining == 0 && $gamestarted == 1)
						{
							$this->ircClass->notice($fromNick, "A hunt is in progress.  You must wait for the next hunt to form before you can join.", 1);
						}
						else
						{
							$this->ircClass->notice($fromNick, "A hunt must be started before you can join the mob. Type !ww to start a hunt.", 1);
						}
					}
					else	
					{
						$this->ircClass->notice($fromNick, "Sorry, we cannot allow more than $maxplayers members per mob or else things run terribly out of control.  Please try to join the next mob.", 1);
					}
				}
			}	
		}
	}

	public function kill($line, $args) 
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;
		if ($this->ircClass->getNick() == $line['to'])
		{
			if ($this->ircClass->isOnline($line['fromNick'], $chan))
			{
				if ($day == 0)
				{
					if ($joining == 0 && $gamestarted == 1 && $voting == 1)
					{
						// did a wolf pm us with a killvote  
						if ($args['nargs'] >= 0)
						{
							$fromNick = $line['fromNick'];
							$fromHost = $line['fromHost'];
							$isalive = "false";
							// if voter is in the game and alive,
							// change their vote count to 5, 
							// to show they voted this time (one vote per round)
							// Nick, Host, Role, Alive(1=yes), Didn't Vote, Votes against
							for ( $wolfvoted = 0; $wolfvoted < $joined; $wolfvoted++ )
							{
								// wolf has already voted this round
								if (($fromHost === $players[$wolfvoted][1]) && ($players[$wolfvoted][3] == 1) && ($players[$wolfvoted][4] == 5) && ($players[$wolfvoted][2] == "wolf"))
										$this->ircClass->notice($fromNick, "You have already made your selection.  You may choose only one villager to eat per night.", 1);
								// wolf is dead
								elseif (($fromHost === $players[$wolfvoted][1]) && ($players[$wolfvoted][3] == 0) && ($players[$wolfvoted][2] == "wolf"))
									$this->ircClass->notice($fromNick, "Dead wolves don't eat.", 1);
								$wolfvoted = $joined;
								$canvote = 0;
							}
							for ( $wolfdidntvote = 0; $wolfdidntvote < $joined; $wolfdidntvote++ )
							{
								// if voter is in the game and alive,
								// change vote count to 5,
								// to show they voted this time (one vote per round)
								if (($fromHost === $players[$wolfdidntvote][1]) && ($players[$wolfdidntvote][3] == 1) && ($players[$wolfdidntvote][4] < 5) && ($players[$wolfdidntvote][2] == "wolf"))
								{
									$players[$wolfdidntvote][4] = 5;
									$isalive = "true";
									$wolfdidntvote = $joined;
									$canvote = 1;
								}
							}
							// voter is alive and voting, check the target		
							if ($canvote == 1)
							{
								if ($isalive == "true")
								{
									$targtisalive = "false";
									for ( $ismealalive = 0; $ismealalive < $joined; $ismealalive++ )
									{
										// is target ingame and alive
										$var1 = $args['arg1'];
										$var2 = $players[$ismealalive][0];
										if ((strcasecmp($var1, $var2) == 0) && ($players[$ismealalive][3] == 1))
										{
											$players[$ismealalive][5] += 1;
											$targtisalive = "true";
											$ismealalive = $joined;
										}
									}
									if ($targtisalive == "false")
										$this->ircClass->notice($fromNick, "Either your target is dead or never joined the hunt.  Get a list of alive hunting party members by typing " . BOLD . " '/msg $botnick alive' " . BOLD . " .", 1);
									else
									{
										if ($numwolf == "Wolf")
											$this->ircClass->notice($fromNick, "$fromNick, you have chosen ".$args['arg1']." for your feast tonight.", 1);
										else
											$this->ircClass->notice($fromNick, "$fromNick, you have chosen ".$args['arg1']." for your feast tonight. We shall wait to see who your bretheren selects before deciding the final choice.", 1);
									}
								}
							}
						}
					}
				}
			}	
		}
	}

	public function lastBreath()
	{
		$this->doVoice("batch");
	}

	public function onJoin($line, $args)
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;
		$this->ircClass->notice($chan, chr(3) . 2 . "Welcome to $chan!  Please go to http://ww4.gamesoda.com/index.php and give". chr(3) . 4 ." World War IV". chr(3) . 2 ." a try!", 1);
		if (($gamestarted == 1) && ($joining == 0))
			$this->ircClass->notice($line['fromNick'], chr(3) . 2 . "A game is currently underway. Please wait for it to finish before attempting to join.", 1);
		elseif (($gamestarted == 1) && ($joining == 1))
			$this->ircClass->notice($line['fromNick'], chr(3) . 2 . "A game is just beginning. Type". chr(3) . 4 ." /msg $botnick join". chr(3) . 2 ." to join.", 1);
		else
			$this->ircClass->notice($chan, chr(3) . 2 . "Type". chr(3) . 4 ." !ww". chr(3) . 2 ." to start a new game.", 1);
			

	}

	//if the player changed their nick and they're in the game, changed the listed name
	public function onNickChange($line, $args)
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;
		$chan = "#werewolf";
		$oldnick = $line['fromNick'];
		$playernick = $line['text'];
		if ($gamestarted == 1)
		{
			for ( $changingnick = 0; $changingnick < $joined; $changingnick++ )
			{
				if (($players[$changingnick][3] == 1) && ($oldnick == $players[$changingnick][0]))
				{
					$players[$changingnick][0] = $playernick;
					$this->ircClass->notice($chan, "$oldnick has been changed to $playernick.", 1);
					$changingnick = $joined;
				}
			}
		}
	}

	//if a player leaves while the game is on, remove him from the player list
	//and if there is a priority list, add the first person from that in his place.
	public function onPart($channel, $sender, $login, $hostname) 
	{
	}

	public function playGame() 
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;
		$this->ircClass->privMsg($chan, chr(3) . 3 . "Joining ends.", 1);
		// not enough players
		if ($joined < $minplayers)
		{
			$this->ircClass->privMsg($chan, chr(3) . 3 . "Sorry, Not enough members to form a valid mob. Please try again later.", 1);
			$this->endGame();
		}
		// we have at least the minimum number of players
		else
		{
			$this->ircClass->privMsg($chan, chr(3) . 2 . BOLD ."Congratulations! " . BOLD . " You have managed to scare " . BOLD . " $joined " . BOLD . " villagers enough to get them out there hunting.", 1);
			$joining = "0";
			$this->ircClass->privMsg($chan, chr(3) . 3 . "Please wait a moment while I assign your role.", 1);
			$this->setRoles();
			if ($joined == 5)
			{
				$this->setDay();
			}
			else
			{
				$this->ircClass->privMsg($chan, chr(3) . 2 . "Night descends on the sleepy village, and a full moon rises. Unknown to the villagers, tucked up in their warm beds, the early demise of one of their number is being plotted.", 1);
				$firstnight = 1;
				$this->setNight();
			}
		}
	}

	// type '/msg $botnick role' to get a list of alive players
	public function role($line, $args) 
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;
		$fromNick =$line['fromNick'];
		$fromHost = $line['fromHost'];
		for ( $gettingrole = 0; $gettingrole < $joined; $gettingrole++ )
		{
			if (($players[$gettingrole][3] == 1) && ($fromHost == $players[$gettingrole][1]))
			{
				$this->ircClass->notice($fromNick, "$fromNick, You are a " . BOLD . "". $players[$gettingrole][2] . "". BOLD, 1);
				$gettingrole = $joined;
			}
		}
	}

	public function see($line, $args) 
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2, $seeing;
		if ($this->ircClass->getNick() == $line['to'])
		{
			if ($this->ircClass->isOnline($line['fromNick'], $chan))
			{
				if ($day == 0)
				{
					if ($joining == 0 && $gamestarted == 1 && $voting == 1)
					{
						// did a see pm us with vision request  
						if ($args['nargs'] >= 0)
						{
							$fromNick = $line['fromNick'];
							$fromHost = $line['fromHost'];
							$isalive = "false";
							// if seer is in the game and alive, 
							// change their vote count to 5, 
							// to show they voted this time (one vote per round)
							// Nick, Host, Role, Alive(1=yes), Didn't Vote, Votes against
							for ( $seervoted = 0; $seervoted < $joined; $seervoted++ )
							{
								// seer has already voted this round
								if (($fromHost === $players[$seervoted][1]) && ($players[$seervoted][3] == 1) && ($players[$seervoted][4] == 5) && ($players[$seervoted][2] == "seer"))
									$this->ircClass->notice($fromNick, "You have already made your selection.  You may choose only one villager to see per night.", 1);
								// seer is dead
								elseif (($fromHost === $players[$seervoted][1]) && ($players[$seervoted][3] == 0) && ($players[$seervoted][2] == "wolf"))
									$this->ircClass->notice($fromNick, "Your link to the living in death is not as great as your link to the dead in life.", 1);
								$seervoted = $joined;
								$canvote = 0;
							}
							for ( $seerdidntvote = 0; $seerdidntvote < $joined; $seerdidntvote++ )
							{
								// if voter is in the game and alive,
							        // change vote count to 5,
								// to show they voted this time (one vote per round)
								if (($fromHost === $players[$seerdidntvote][1]) && ($players[$seerdidntvote][3] == 1) && ($players[$seerdidntvote][4] < 5) && ($players[$seerdidntvote][2] == "seer"))
								{
									$players[$seerdidntvote][4] = 5;
									$isalive = "true";
									$seerdidntvote = $joined;
									$canvote = 1;
								}
							}
							// voter is alive and voting, check the target		
							if ($canvote == 1)
							{
								if ($isalive == "true")
								{
									$targtisalive = "false";
									for ( $visionalive = 0; $visionalive < $joined; $visionalive++ )
									{
										// is target ingame and alive
										$var1 = $args['arg1'];
										$var2 = $players[$visionalive][0];
										if ((strcasecmp($var1, $var2) == 0) && ($players[$visionalive][3] == 1))
										{
											$players[$visionalive][6] = 1;
											$targtisalive = "true";
											$visionalive = $joined;
											$seeing = 1;
										}
									}
									if ($targtisalive == "false")
										$this->ircClass->notice($fromNick, "Either your target is dead or never joined the hunt.  Get a list of alive hunting party members by typing " . BOLD . " '/msg $botnick alive' " . BOLD . " .", 1);
									else
									{
										$this->ircClass->notice($fromNick, "$fromNick, You will see the identity of ".$args['arg1']." upon the dawning of tomorrow.", 1);
									}
								}
							}
						}
					}
				}
			}	
		}
	}

	public function setDay()
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;
		$this->doVoice("1");
		$this->ircClass->privMsg($chan,  chr(3) . 4 . BOLD ."Villagers, " . BOLD . " you have " . chr(3) . 5 . BOLD . " $daytime " . BOLD . chr(3) . 4 . " seconds to " . BOLD . " discuss suspicions, or cast accusations, " . BOLD . " after which time a lynch vote will be called.", 1);
		$day = 1;
		$voting = 0;
		$this->timerClass->addTimer("daytime", $this, "setDayTimer", "", $daytime, false);
	}

	public function setDayTimer()
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;
		$this->ircClass->privMsg($chan,  chr(3) . 4 . BOLD ."Villagers, " . BOLD . " you now have " . chr(3) . 5 . BOLD . " $votetime " . BOLD . chr(3) . 4 . " seconds to vote for the person you would like to see lynched! Type " . BOLD . " '/msg $botnick vote <player>' " . BOLD . " to cast your vote. Votes are non retractable!", 1);
		$voting = 1;
		$this->timerClass->addTimer("tallyVotes", $this, "tallyVotes", "", $votetime, false);
	}

	public function setNight()
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2, $seeing;
		$day = 0;
		$voting = 1;
		$seeing = 0;
		$this->doVoice("batch");
		$this->ircClass->privMsg($chan, chr(3) . 2 ."As the moon rises, the lynching mob dissipates, return to their homes and settle into an uneasy sleep. But in the pale moonlight, something stirs...", 1);
		if ($numwolf == "Wolf")
			$this->ircClass->privMsg($chan,  chr(3) . 4 . BOLD ."Werewolf, " . BOLD . " you have " . BOLD . " $nighttime " . BOLD . " seconds to decide who to attack. To make your final decision type " . BOLD . " '/msg $botnick kill <player>' " . BOLD . " .", 1);
		else
			$this->ircClass->privMsg($chan,  chr(3) . 4 . BOLD ."Werewolves, " . BOLD . " you have " . BOLD . " $nighttime " . BOLD . " seconds to confer via PM and unanimously decide who to attack. To make your final decision type " . BOLD . " '/msg $botnick kill <player>' " . BOLD . " .", 1);
		$this->ircClass->privMsg($chan,  chr(3) . 7 . BOLD ."Seer, " . BOLD . " you have " . BOLD . " $nighttime " . BOLD . " seconds to PM one name to $botnick and discover their true intentions. To enquire with the spirits type " . BOLD . " '/msg $botnick see <player>' " . BOLD . " .", 1);
		if ($firstnight == 1)
		{
			$nighttime = "120";
			$this->timerClass->addTimer("tallyVotesnight", $this, "tallyVotes", "", $nighttime, false);
			$firstnight = 0;
			$nighttime = "45";
		}
		else
			$this->timerClass->addTimer("tallyVotesnight", $this, "tallyVotes", "", $nighttime, false);
	}

	public function setRoles() 
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;
		//Assign the first wolf
		$joinvar = $joined - 1;
		$wolf1 = mt_rand(0, $joinvar);
		$players[$wolf1][2] = "wolf";
		$numwolf = "Wolf";
		//Assign the first seer and make sure it's not the same as the wolf
		do
			$seer1 = mt_rand(0, $joinvar);
		while ($seer1 == $wolf1);
		$players[$seer1][2] = "seer";
		//Assign the second wolf if there are enough players
		if ($joined >= $twowolves)
		{
			// and make sure it's not the same as a previous wolf or seer
			do
				$wolf2 = mt_rand(0, $joinvar);
			while ($wolf2 == ($wolf1 || $seer1));
			$players[$wolf2][2] = "wolf";
			$numwolf = "Wolves";
		}	
		//Assign the second seer if there are enough players
		if ($joined >= $twoseers)
		{
			// and make sure it's not the same as a previous wolf or seer
			do
				$seer2 = mt_rand(0, $joinvar);
			while (($seer2 == $wolf1) || ($seer2 == $seer1) || ($seer2 == $wolf2));
			$players[$seer2][2] = "seer";
		}	
		//Assign the third wolf if there are enough players
		if ($joined >= $threewolves)
		{
			// and make sure it's not the same as a previous wolf or seer
			do
				$wolf3 = mt_rand(0, $joinvar);
			while (($wolf3 == $wolf1) || ($wolf3 == $seer1) || ($wolf3 == $wolf2) || ($wolf3 == $seer2));
			$players[$wolf3][2] = "wolf";
		}
		// Tell everyone their role	
		for ( $row = 0; $row < $joined; $row++ )
		{
			if ($players[$row][2] == "wolf")
			{
				$this->ircClass->notice($players[$row][0], $players[$row][0].", You are a prowler of the night, a Werewolf! You must decide your nightly victims. By day you must deceive the villager and attempt to blend in. Keep this information to yourself! Good luck!", 1);
				$this->ircClass->notice($players[$row][0], $players[$row][0].", You are a " . BOLD . "". $players[$row][2] . "". BOLD, 1);
			}
			elseif ($players[$row][2] == "seer")
			{
				$this->ircClass->notice($players[$row][0], $players[$row][0].", You are one granted the gift of second sight, a Seer! Each night you may enquire as to the nature of one of your fellow village dwellers, and $botnick will tell you whether or not that person is a Werewolf - a powerful gift indeed! But beware revealing this information to the $numwolf, or face swift retribution!", 1);
				$this->ircClass->notice($players[$row][0], $players[$row][0].", You are a " . BOLD . "". $players[$row][2] . "". BOLD, 1);
			}
			else
			{
				$this->ircClass->notice($players[$row][0], $players[$row][0].", You are a peaceful peasant turned vigilante, a Villager! You must root out the $numwolf by casting accusations or protesting innocence at the daily village meeting, and voting who you believe to be untrustworthy during the daily Lynch Vote. Good luck!", 1);
				$this->ircClass->notice($players[$row][0], $players[$row][0].", You are a " . BOLD . "". $players[$row][2] . "". BOLD, 1);
			}
		}
		// Tell everyone if there is more than one wolf	
		if ($joined >= $threewolves)
		{
			$this->ircClass->privMsg($chan, "There are THREE wolves in this game!!!", 1);
			// Tell the wolves who their partners are
			$this->ircClass->notice($players[$wolf1][2], $players[$wolf1][2].", Your bretheren are ". $players[$wolf2][2] ." and ". $players[$wolf3][2].".", 1);
			$this->ircClass->notice($players[$wolf2][2], $players[$wolf2][2].", Your bretheren are ". $players[$wolf1][2] ." and ". $players[$wolf3][2].".", 1);
			$this->ircClass->notice($players[$wolf3][2], $players[$wolf3][2].", Your bretheren are ". $players[$wolf2][2] ." and ". $players[$wolf1][2].".", 1);
		}
		elseif ($joined >= $twowolves)
		{
			$this->ircClass->privMsg($chan, "There are TWO wolves in this game!!", 1);
			// Tell the wolves who their partner is
			$this->ircClass->notice($players[$wolf1][2], $players[$wolf1][2].", Your bretheren is ". $players[$wolf2][2].".", 1);
			$this->ircClass->notice($players[$wolf2][2], $players[$wolf2][2].", Your bretheren is ". $players[$wolf1][2].".", 1);
		}
	}

	public function tallyVotes() 
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2, $seeing, $mostvotes;
		global $maxRow, $maxNum, $maxRowArray, $maxNickArray, $maxHostArray;
		global $seerkilled, $voting, $maxNick, $maxHost, $countmaxRowArray;
		$maxRow = -1;
		$maxNum = -1;
		$maxRowArray = array();
		$maxNickArray = array();
		$maxHostArray = array();
		$seerkilled = 0;
		$voting = 0;
		$maxNick = -1;
		$maxHost = -1;
		$wolfdefenders = 0;
		if ($day == 1)
		 	$this->ircClass->privMsg($chan, chr(3) . 3 ."Tallying votes...", 1);
		else
			$this->doVoice("batch");
		for ( $tallyingvotes = 0; $tallyingvotes < $joined; $tallyingvotes++ )
		{
			// if it is daytime
			if (($day == 1) && ($wolfdefenders == 0))
			{
				// is player alive and did not vote
				if (($players[$tallyingvotes][3] == 1) && ($players[$tallyingvotes][4] < 5))
				{
					// then add a missed vote counter to them
					$players[$tallyingvotes][4] += 1;
					// two missed vote counters and they are executed for not voting
					if ($players[$tallyingvotes][4] == 2)
					{
						$players[$tallyingvotes][3] = 0;
						$this->ircClass->privMsg($chan, chr(3) . 2 . "Having defied the powers of Good and Justice for long enough, " . BOLD . "".$players[$tallyingvotes][0]." suddenly clutches their chest, " . BOLD . " before falling to the floor as blood pours from their ears. May that be a lesson to all who attempt to defend the $numwolf.", 1);
					}
				}
				$wolfdefenders = 1;
			}
			// Must be alive and voted this time, reset their missed vote counter
			if (($players[$tallyingvotes][3] == 1) && ($players[$tallyingvotes][4] == 5))
				$players[$tallyingvotes][4] = 0;
			// let's tally these votes
			// player is alive and has at least one vote against them
			if (($players[$tallyingvotes][3] == 1) && ($players[$tallyingvotes][5] > 0))
			{
				// first player fills the fields
				if ($maxRow == -1)
				{
					$maxRow = $players[$tallyingvotes][0]; 
					$maxNum = $players[$tallyingvotes][5]; 
					$maxNick = $players[$tallyingvotes][0]; 
					$maxHost = $players[$tallyingvotes][1]; 
					$maxRowArray[] = $players[$tallyingvotes][0];
					$maxNickArray[] = $players[$tallyingvotes][0];
					$maxHostArray[] = $players[$tallyingvotes][1];
					continue;
				}
				// must have more votes than current winner to take his place
				// also, reset the list with only this player
				if ($players[$tallyingvotes][5] > $maxNum)
				{
					$maxRow = 1; 
					$maxNum = $players[$tallyingvotes][5];
					$maxNick = $players[$tallyingvotes][0];
					$maxHost = $players[$tallyingvotes][1];
					$maxRowArray = array();
					$maxNickArray = array();
					$maxHostArray = array();
				}
				// tie in votes and get placed in the list
				else if ($players[$tallyingvotes][5] == $maxNum)
				{
					$maxRowArray[] = $players[$tallyingvotes][0];
					$maxNickArray[] = $players[$tallyingvotes][0];
					$maxHostArray[] = $players[$tallyingvotes][1];
				}
			}
		}
		// if the list has more than one player, choose one at random
		$testcount = count($maxRowArray);
		$this->ircClass->privMsg("Juice",  chr(3) . 2 ."This is a test message.". $testcount ." is how many people have the most amount of votes. If no one voted, this should be 0.  If this is not 0, then we should not see the Nobody Voted message.", 1);
		if (count($maxRowArray) >= 2)
		{
		 	$this->ircClass->privMsg($chan, chr(3) . 3 ."A tie. Randomly choosing one...", 1);
			$num = rand(0,count($maxRowArray)-1);
			$maxRow = $maxRowArray[$num];
			$maxNick = $maxNickArray[$num];
			$maxHost = $maxHostArray[$num];
		}
		// kill the lucky winner
		for ( $luckywinner = 0; $luckywinner < $joined; $luckywinner++ )
		{
			if ($players[$luckywinner][1] == $maxHost)
			{
				$players[$luckywinner][3] = 0;
			}
		}
		for ( $resetvotecount = 0; $resetvotecount < $joined; $resetvotecount++ )
			$players[$resetvotecount][5] = 0;
		// time to check the seers vision, if it's night
		if ($day == 0)
		{
			for ( $seerrole = 0; $seerrole < $joined; $seerrole++ )
			{
				if ($players[$seerrole][2] == "seer")
				{
					$seerNick = $players[$seerrole][0];
					$seerrole = $joined;
				}
			}
			for ( $seervision = 0; $seervision < $joined; $seervision++ )
			{
				if (($players[$seervision][2] == "seer") && ($players[$seervision][1] == $maxHost))
				{
					if ($day == 0)
					{
						if ($seeing == 1)
							$this->ircClass->notice($seerNick, chr(3) . 2 . "It appears the $numwolf got to you before your vision did...", 1);
						$this->ircClass->privMsg($chan, chr(3) . 2 ."The first villager to arrive at the center shrieks in horror - lying on the cobbles is a blood stained Ouija Board, and atop it sits " . BOLD . " $maxNick's " . BOLD . " head. It appears " . BOLD . " $maxNick the Seer" . BOLD . " had been seeking the guidance of the spirits to root out the $numwolf, but apparently the magic eight ball didn't see THIS one coming...", 1);
					}
					else
						$this->ircClass->privMsg($chan, chr(3) . 2 . BOLD . " $maxNick " . BOLD . "runs before the mob is organised, dashing away from the village. Tackled to the ground near to the lake, " . BOLD . " $maxNick " . BOLD . " is tied to a log, screaming, and thrown into the water. With no means of escape, " . BOLD . " $maxNick  the Seer" . BOLD . " drowns, but as the villagers watch, tarot cards float to the surface and their mistake is all too apparent...", 1);
					$players[$seervision][6] == 0;
					$seervision = $joined;
					$seerkilled = 1;
				}
				elseif (($players[$seervision][3] == 0) && ($players[$seervision][6] == 1))
				{
					$this->ircClass->notice($seerNick, chr(3) . 2 . "The spirits needn't have guided your sight tonight; Your target was also that of the $numwolf!", 1);
					$players[$seervision][6] == 0;
					$seervision = $joined;
				}
				elseif (($players[$seervision][3] == 1) && ($players[$seervision][6] == 1))
				{
					$this->ircClass->notice($seerNick, chr(3) . 2 . $players[$seervision][0]." is ". $players[$seervision][2], 1);
					$players[$seervision][6] == 0;
					$seervision = $joined;
				}
			}
		}
		// tell everyone who was killed...
		if ($day == 0)
		{
			if (count($maxRowArray) == 0)
			{
				$this->ircClass->privMsg($chan, chr(3) . 2 ."The villagers gather the next morning in the village center, to sighs of relief - it appears there was no attack the previous night.", 1);
			}
			elseif ($seerkilled == 1)
				$seerkilled = 0;
			else
			{
				$nightnum = rand(1,4);
				if ($nightnum == 1)
				$this->ircClass->privMsg($chan, chr(3) . 2 ."The villagers gather the next morning in the village center, but " . BOLD . " $maxNick " . BOLD . " does not appear. The villagers converge on " . BOLD . " $maxNick's " . BOLD . " home and find them decapitated in their bed. After carrying the body to the church, the villagers, now hysterical, return to the village center to decide how to retaliate... ", 1);
				elseif ($nightnum == 2)
				$this->ircClass->privMsg($chan, chr(3) . 2 ."As some villagers begin to gather in the village center, a scream is heard from the direction of" . BOLD . " $maxNick's " . BOLD . " house. The elderly villager who had screamed points to the fence, on top of which, the remains of" . BOLD . " $maxNick " . BOLD . " are impaled, with their intestines spilling onto the cobbles. Apparently" . BOLD . " $maxNick " . BOLD . " was trying to flee their attacker... ", 1);
				elseif ($nightnum == 3)
				$this->ircClass->privMsg($chan, chr(3) . 2 ."When the villagers gather at the village center, one comes running from the hanging tree, screaming at others to follow. When they arrive at the hanging tree, a gentle creaking echoes through the air as the body off" . BOLD . " $maxNick " . BOLD . " swings gently in the breeze, it's arms ripped off at the shoulders. It appears the attacker was not without a sense of irony...", 1);
				else
				$this->ircClass->privMsg($chan, chr(3) . 2 ."As the village priest gathers the prayer books for the mornings sermon, he notices a trickle of blood snaking down the aisle.. He looks upward to see" . BOLD . " $maxNick " . BOLD . " impaled on the crucifix - the corpse has been gutted. He shouts for help, and the other villagers pile into the church, and start arguing furiously...", 1);
			}
			$this->checkWin();
			if ($gamestarted == 1)
				$this->setDay();
		}
		// ... or lynched
		else
		{
			$wasseer = 0;
			if (count($maxRowArray) == 0)
			{
				$this->ircClass->privMsg($chan,  chr(3) . 2 . BOLD ."Nobody voted! " . BOLD . " The Powers of Good will not like this apparent support for the werewolves...", 1);
			}
			for ( $isseer = 0; $isseer < $joined; $isseer++ )
			{
				if (($players[$isseer][2] == "seer") && ($players[$isseer][1] == $maxHost))
				{
					$this->ircClass->privMsg($chan,  chr(3) . 2 . BOLD . " $maxNick" . BOLD . " runs before the mob is organised, dashing away from the village. Tackled to the ground near to the lake, " . BOLD . " $maxNick" . BOLD . " is tied to a log, screaming, and thrown into the water. With no means of escape, " . BOLD . " $maxNick the Seer" . BOLD . " drowns, but as the villagers watch, tarot cards float to the surface and their mistake is all too apparent...", 1);
					$this->ircClass->privMsg($chan,  chr(3) . 2 . BOLD ."$maxNick the Seer has been lynched.".BOLD, 1);
					$isseer = $joined;
					$wasseer = 1;
				}
			}
			if ($wasseer == 0)
			{
				$wolflynched = 0;
				for ( $iswolf = 0; $iswolf < $joined; $iswolf++ )
				{
					if (($players[$iswolf][2] == "wolf") && ($players[$iswolf][1] == $maxHost))
					{
						$wolflynched = 1;
						$iswolf = $joined;
					}
				}
				if ($wolflynched == 1)
				{
					$this->ircClass->privMsg($chan, chr(3) . 2 ."After coming to a decision, " . BOLD . " $maxNick " . BOLD . " is quickly dragged from the crowd, and dragged to the hanging tree.  " . BOLD . " $maxNick " . BOLD . " is strung up, and the block kicked from beneath their feet. There is a yelp of pain, but " . BOLD . " $maxNick's " . BOLD . " neck doesn't snap, and fur begins to sprout from their body. A gunshot rings out, as a villager puts a silver bullet in the beast's head...", 1);
					$this->ircClass->privMsg($chan,  chr(3) . 2 . BOLD ."$maxNick the Werewolf has been lynched.".BOLD, 1);
				}
				else
				{
					$lynchnum = rand(1,2);
					if ($lynchnum == 1)
						$this->ircClass->privMsg($chan,  chr(3) . 2 ."The air thick with adrenaline, the villagers grab" . BOLD . " $maxNick" . BOLD . " who struggles furiously, pleading innocence, but the screams fall on deaf ears." . BOLD . " $maxNick" . BOLD . " is dragged to the stake at the edge of the village, and burned alive. But the villagers shouts and cheers fade as they realise the moon is already up -" . BOLD . " $maxNick" . BOLD . " was not a werewolf after all...", 1);
					else
						$this->ircClass->privMsg($chan,  chr(3) . 2 ."Realising the angry mob is turning," . BOLD . " $maxNick" . BOLD . " tries to run, but is quickly seized upon." . BOLD . " $maxNick" . BOLD . " is strung up to the hanging tree, and a hunter readies his rifle with a silver slug, as the block is kicked from beneath them. But there is a dull snap, and" . BOLD . " $maxNick" . BOLD . " hangs, silent, motionless. The silent villagers quickly realise their grave mistake...", 1);
					$this->ircClass->privMsg($chan,  chr(3) . 2 . BOLD ."$maxNick has been lynched.".BOLD, 1);
				}
			}
			$this->checkWin();
			if ($gamestarted == 1)
			{
				$this->timerClass->addTimer("lastBreath", $this, "lastBreath", "", "15", false);
				$this->setNight();
			}
		}
	}

	// Send a PM to the bot to vote (daily Lynch vote)
	public function vote($line, $args) 
	{
		global $chan, $joining, $gamestarted, $huntleader, $joined, $botnick;
		global $minplayers, $players, $maxplayers, $twowolves, $threewolves, $twoseers;
		global $daytime, $votetime, $nighttime, $voting, $day;
		global $numwolf, $wolf1, $wolf2, $wolf3, $seer1, $seer2;
		if ($day == 1)
		{
			if ($this->ircClass->getNick() == $line['to'])
			{
				if ($this->ircClass->isOnline($line['fromNick'], $chan))
				{
					if ($joining == 0 && $gamestarted == 1 && $voting == 1)
					{
						// did someone pm us with a vote  
						if ($args['nargs'] >= 0)
						{
							$fromNick = $line['fromNick'];
							$fromHost = $line['fromHost'];
							$isalive = "false";
							// Nick, Host, Role, Alive(1=yes), Didn't Vote, Votes against
							for ( $vilgrvoted = 0; $vilgrvoted < $joined; $vilgrvoted++ )
							{
								// voter has already voted this round
								if (($fromHost === $players[$vilgrvoted][1]) && ($players[$vilgrvoted][3] == 1) && ($players[$vilgrvoted][4] == 5))
										$this->ircClass->notice($fromNick, "You have already voted.  You may cast only one vote per day.", 1);
								// voter is dead
								elseif (($fromHost === $players[$vilgrvoted][1]) && ($players[$vilgrvoted][3] == 0))
									$this->ircClass->notice($fromNick, "Dead villagers can't vote.", 1);
								$vilgrvoted = $joined;
								$canvote = 0;
							}
							for ( $vilgrvotingnow = 0; $vilgrvotingnow < $joined; $vilgrvotingnow++ )
							{
								// if voter is in the game and alive, change vote count to 5,
								// to show they voted this time (one vote per round)
								if (($fromHost === $players[$vilgrvotingnow][1]) && ($players[$vilgrvotingnow][3] == 1) && ($players[$vilgrvotingnow][4] < 5))
								{
									$players[$vilgrvotingnow][4] = 5;
									$votehold = $vilgrvotingnow;
									$isalive = "true";
									$vilgrvotingnow = $joined;
									$canvote = 1;
								}
							}
							// voter is alive and voting, check the target		
							if ($canvote == 1)
							{
								if ($isalive == "true")
								{
									$targtisalive = "false";
									for ( $lynchee = 0; $lynchee < $joined; $lynchee++ )
									{
										// is target ingame and alive
										$var1 = $args['arg1'];
										$var2 = $players[$lynchee][0];		
										if ((strcasecmp($var1, $var2) == 0) && ($players[$lynchee][3] == 1))
										{
											$players[$lynchee][5] += 1;
											$targtisalive = "true";
											$lynchee = $joined;
										}
									}
									if ($targtisalive == "false")
									{
										$this->ircClass->notice($fromNick, "Either your target is dead or never joined the hunt.  Get a list of alive hunting party members by typing '/msg $botnick alive'.", 1);
										$players[$votehold][4] = 1;
									}
									else
										$this->ircClass->privMsg($chan,  chr(3) . 2 . BOLD ."$fromNick " . BOLD . " has voted for ". BOLD . "". $args['arg1']."". BOLD . ".", 1);
								}
							}
						}
					}
				}
			}	
		}
	}

}
?>
