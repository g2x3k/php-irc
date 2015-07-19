<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC Auto-OP Mod 0.1
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

class m_autoop extends module {

	public $title = "Auto-OP Mod";
	public $author = "Rikard";
	public $version = "0.1";
	public $dontShow = true;

    public function mode_op($line)
    {
        /*
           Here you add the channel, mode and nick to be de/opped
           One line per channel/nick combo.
           Oh, and i shouldn't have to remind ya that the bot
           need to have op-status for this to work :)
        */
        $this->ircClass->changeMode("#channel", "+", "o", "nick");
    }
}
?>
