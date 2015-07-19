<?php

/*
+---------------------------------------------------------------------------
|   PHP-IRC Top Level Domain display
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
|   0.1: 	initial release
+---------------------------------------------------------------------------
*/

class tld_mod extends module {

	public $title = "TopLevelDomain Mod";
	public $author = "SubWorx";
	public $version = "0.1";

	public function init()
	{
		$this->tld = new ini("modules/tld_mod/tld_mod.ini");
	}

	public function priv_tld($line, $args)
	{

		if ($line['to'] === $this->ircClass->getNick())
		{
			return;
		}

		if ($args['nargs']<1) {
			$this->ircClass->privMsg($line['to'], "Please supply a Top Level Domain");
			return;
		}

		if ($this->tld->getError())
		{
			$this->ircClass->privMsg($line['to'], "Unexplained error opening TLD database.");
			return;
		}
		if (preg_match("/^\..*/", $args['arg1'])) {
			$args['arg1'] = substr($args['arg1'], 1);
		}
		$tld = strtolower($args['arg1']);

		$tldData = $this->tld->getIniVal('domains', $tld);

		if ($tldData == false)
		{
			$this->ircClass->privMsg($line['to'], "The Top Level Domain $tld doesn't seem to exist (yet)2.");
			return;
		}

		$this->ircClass->privMsg($line['to'], "Country name for $tld is $tldData");
	}

}

?>
