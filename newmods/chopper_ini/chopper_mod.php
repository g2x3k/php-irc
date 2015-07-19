<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC Channel Opper w/ INI
|   by Jason Hines <jason@greenhell.com>
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


class chopper_mod extends module {

	public $title = "Chopper Mask";
	public $author = "oweff";
	public $version = "0.1";

	public function handle_onjoin($line, $args) {
		$nick = $line['fromNick'];
		$hostmask = $line['fromIdent'] . "@" . $line['fromHost'];
		$channel = $line['text'];
		if ($channel == $this->ircClass->getNick()) {
			return;
		}
		if ($nick == $this->ircClass->getNick()) {
			return;
		}

		$ini = new ini("modules/chopper_ini/hosts.ini");

		$_ops = $ini->getVars($channel);
		foreach ($_ops as $_hm=>$_mode) {
			$_mode = trim($_mode);
			//$this->ircClass->privMsg($channel,"Does {$hostmask} match {$_hm}?");
			if ($this->ircClass->hostMasksMatch($hostmask,$_hm)) {
				//$this->ircClass->privMsg($channel,"Yep. Granting +{$_mode} to {$nick} in {$channel}.");
				$this->ircClass->changeMode($channel, "+", $_mode, $nick);
				break;
			}
		}
	}

}

?>
