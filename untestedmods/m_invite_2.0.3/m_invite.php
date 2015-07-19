<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC Invite-responder Mod 0.4
|   ========================================================
|   (c) 2006 by Rikard Nilsson (Rikard)
|   Contact: rikard@nilsson-online.net
|   irc: Usually at #manekian @ irc.rizon.net
|   ================================================
|   Efreak
|   Contact: Efreak2004@gmail.com
|   irc: Usually at #CerealKillers or #EndOfTheInternet @ irc.mugglenet.com
|   Nicks: Efreak, Peeves_the_Poltergeist. May have |Offline appended to nick.
|   ========================================
|
+---------------------------------------------------------------------------
|
|   This module works the same as Invite Responder by rikardn, but it removes
|   the channel from the maintain list prior to adding it; this way if it
|   gets invited to a channel and is at the limit, you dont get multiple
|   entries of the same channel in the maintain list.
|
|   Optionally, you can also have the bot tell you and as many people as you like
|   that whoever has invited the bot to wherever.
|
|   Also, it now notices the user that the invite has been logged
|   (no log is made--it just says so)
|
|   Module based on rikardn's Invite-Responder, modified by Efreak (efreak2004@gmail.com)
|
+---------------------------------------------------------------------------
|   SETUP (if you wish to be notified on invite)
|   ========================================================
|   1. Configuration block starts at line 71.
|   2. You can have it notify the user that their invite attempt has been logged
|           by uncommenting the line 73
|   3. Put your nickname into line 74. If you wish to also notify others of invite
|           attempts, copy/paste the entire line and simple change the nickname.
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

class m_invite extends module {

	public $title = "Invite-responder Mod 2.0";
	public $author = "Efreak";
	public $version = "2.0";
	public $dontShow = true;
	
    public function chan_invite($line)
    {
	$nick = irc::myStrToLower( $line[ 'fromNick' ] );
	$chan = irc::myStrToLower( $line[ 'text' ] );
        $this->ircClass->joinChannel($line['text']);
        $this->ircClass->removeMaintain($line['text']);
        $this->ircClass->maintainChannel($line['text']);

/*      CONFIGURATION BLOCK

//        $this->ircClass->notice( $nick, "Your attempt to make me join $chan has been logged." );
//        $this->ircClass->notice( PUT_YOUR_NICKNAME_HERE, "$nick has attempted to make me join $chan" );
    }

}
?>
