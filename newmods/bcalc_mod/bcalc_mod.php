<?php

/*
+---------------------------------------------------------------------------
|   PHP-IRC Bandwidth Calculator
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

class bcalc_mod extends module {

	public $title = "Bandwidth Calculator";
	public $author = "SubWorx";
	public $version = "0.1";

	public function init()
	{
	}

	public function destroy()
	{
	}

	public function priv_bcalc($line, $args)
	{
		$error = 0;
		if ($args['nargs']<2 ) {
			$error = 1;
		}

		if (!is_numeric(str_replace(',', '.', $args['arg1']))) {
			$error = 1;
		}

		if (!is_numeric(str_replace(',', '.', $args['arg2']))) {
			$error = 1;
		}

		$args['arg1'] = str_replace(',', '.', $args['arg1']);
		$args['arg2'] = str_replace(',', '.', $args['arg2']);

		if ($args['arg1'] <= 0.1) {
			$error = 1;
		}

		if ($args['arg2'] <= 0.1) {
			$error = 1;
		}

		if ($error == 1) {
			$this->ircClass->privMsg($line['to'], "Usage: !bcalc <MB> <kb/s>");
			return;
		}


		$time = round(($args['arg1'] * 1024) / $args['arg2']);
		$time2 = $time;
		if ($time > 86400) {
			$days = floor($time/86400);
			$time = $time - ($days * 86400);
		}
		if ($time > 3600) {
			$hours = floor($time/3600);
			$time = $time - ($hours * 3600);
		}
		if ($time > 60) {
			$mins = floor($time / 60);
			$time = $time - ($mins * 60);
		}
		$secs = $time;

		$message = "Downloading ".BOLD.$args['arg1'].BOLD." MB at ".BOLD.$args['arg2'].BOLD." kb/s will take approximately ";
		if (isset($days)) {
			$message .= BOLD.$days.BOLD." d ";
		}
		if (isset($hours)) {
			$message .= BOLD.$hours.BOLD." h ";
		}
		if (isset($mins)) {
			$message .= BOLD.$mins.BOLD." m ";
		}
		$message .= BOLD.$secs.BOLD." s.";
		$this->ircClass->privMsg($line['to'], $message);

		$timestamp = time();
		$destDay = date('l', $timestamp+$time2);
		$destDate = date('d.m.y', $timestamp+$time2);
		$destTime = date('H:i:s', $timestamp+$time2);

		$this->ircClass->privMsg($line['to'], "Download will be finished on ".BOLD.$destDay.BOLD.", ".BOLD.$destDate.BOLD." at ".BOLD.$destTime.BOLD.".");
	}

}

?>