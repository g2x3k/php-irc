<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC Invite-responder Mod 0.1
|   ========================================================
|   (c) 2006 by Rikard Nilsson (Rikard)
|   Contact: rikard@nilsson-online.net
|   irc: Usually at #manekian @ irc.rizon.net
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

A small module to to provide a generic !help for all modules (the ones you want to)

Requirements:

You need to change the typedefs.conf.
Change the priv section to:

type 	priv		~ ;----Used to process input of users in channels
	name		~
	active		~
	inform		~
	canDeactivate	~
	usage		~
	module		~
	function	~
	section		~	;---- String - section
	args		~   ;---- String - <arg1> <arg2> etc.  like dcc usage
	help			;---- String - Description of the function

The change to typedefs.conf will ofc make mods with their current config files from working as the conf file won't load right.
Just add 'null "" ""' at the end of them and they will work as before.

Any function that has "" (empty string) for the help part will not display help (if there are functions you do not
wish to advertise).

You also need to add a section description to the module you are implementing help for.

See the commands_mod.conf for an example

*/

/**
 * Class commands_mod
 *
 */
class commands_mod extends module {

	public $title = "Command list Mod";
	public $author = "Aragno";
	public $version = "0.1";
	public $dontShow = true;


	/**
	 * Send message
	 *
	 * If the message is sent as a pm we pm back else we notice to users channel
	 *
	 * Less clutter
	 *
	 * @param string $to
	 * @param string $msg
	 * @param string $from
	 */
	private function doMessage($to, $msg, $from)
	{
		if ($to == $this->ircClass->getNick())
		{
			$this->ircClass->privMsg($from, $msg);
		}
		else
		{
			$this->ircClass->notice($from, $msg);
		}
	}

	/**
	 * Output a list of commands available to the users
	 *
	 * Based on the dcc_help function
	 *
	 * @param array $line
	 * @param array $args
	 */
    public function priv_commands($line, $args)
    {
		$channel = $line['to'];
		$fromNick = $line['fromNick'];

    	$cmdList = $this->parserClass->getCmdList('priv');
		$sectionList = $this->parserClass->getCmdList('section');

		if ($args['nargs'] > 0)
		{
			$cmd = $args['arg1'];

			if (isset($cmdList[$cmd]))
			{
				$this->doMessage($channel, "Usage: " . $cmd . " " . $cmdList[$cmd]['args'], $fromNick);
				$this->doMessage($channel, "Section: " . $sectionList[$cmdList[$cmd]['section']]['longname'], $fromNick);
				$this->doMessage($channel, "Description: " . $cmdList[$cmd]['help'], $fromNick);
			}
			else
			{
				$this->doMessage($channel, "Invalid Command: " . $line['arg1'],$fromNick);

			}
			return;
		}

		$this->doMessage($channel, "Commands:",$fromNick);

		$sections = array();

		foreach ($cmdList AS $cmd => $cmdData)
		{
			// Older mods without the added info will not display help msg until added
			if ($cmdList[$cmd]['help'] == "")
			{
				continue;
			}

			$sections[$cmdData['section']][] = strtoupper($cmd) . " - " . $cmdData['help'];
		}

		foreach ($sections AS $section => $data)
		{
			$this->doMessage($channel, $sectionList[$section]['longname'],$fromNick);

			foreach ($data AS $cmd)
			{
				$this->doMessage($channel, "-- " . $cmd, $fromNick);
			}
		}

		$this->doMessage($channel, "Use !help <command> for a list of arguments",$fromNick);

    }
}
?>