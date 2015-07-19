<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC Invite-responder Mod 0.2
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
*/

class m_invite extends module {

	public $title = "Invite-responder Mod";
	public $author = "Rikard";
	public $version = "0.2";
	public $dontShow = true;

	
    public function chan_invite($line)
    {
        $this->ircClass->joinChannel($line['text']);
        $this->ircClass->maintainChannel($line['text']);
    }
}
?>