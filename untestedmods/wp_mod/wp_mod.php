<?
/*
;+---------------------------------------------------------------------------
;|   PHP-IRC Whatpulse stats module
;|   ========================================================
;|   Initial release
;|   v0.1 by Jos
;|   (c) 2007 by Jos
;|   Contact:
;|    email: jos@flauw.net
;|    irc:   #Chat@irc.Kwaaknet.org
;|   ========================================
;+---------------------------------------------------------------------------
;|   > This program is free software; you can redistribute it and/or
;|   > modify it under the terms of the GNU General Public License
;|   > as published by the Free Software Foundation; either version 2
;|   > of the License, or (at your option) any later version.
;|   >
;|   > This program is distributed in the hope that it will be useful,
;|   > but WITHOUT ANY WARRANTY; without even the implied warranty of
;|   > MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
;|   > GNU General Public License for more details.
;|   >
;|   > You should have received a copy of the GNU General Public License
;|   > along with this program; if not, write to the Free Software
;|   > Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
;+---------------------------------------------------------------------------
;|   Changes
;|   =======-------
;|   0.1: 	initial release
;+---------------------------------------------------------------------------
*/


class wp_mod extends module 
{

	public $title='Whatpulse stats';
	public $author='Jossie90';
	public $version='0.1';
    
	public function init()
		{
		}
    public function destroy()
		{
		}
/* I grabbed this function somewhere from the php.net comments. */
public function TextBetween($s1,$s2,$s)
	{
	$s1 = strtolower($s1);
	$s2 = strtolower($s2);
	$L1 = strlen($s1);
	$scheck = strtolower($s);
	if($L1>0){$pos1 = strpos($scheck,$s1);} else {$pos1=0;}
	if($pos1 !== false){
	if($s2 == '') return substr($s,$pos1+$L1);
	$pos2 = strpos(substr($scheck,$pos1+$L1),$s2);
	if($pos2!==false) return substr($s,$pos1+$L1,$pos2);
	};
	return '';
	}
public function WhatpulseUserStats($username)
	{
	$link="http://whatpulse.org/stats/users/".$username."/normal/";
	$openfile=fopen($link,'r');
	$found=false;
	while ($getfile=@fgets($openfile, 4096))
		{
		$nick=explode(" ",$getfile);
		If (count($nick)<3) continue;
		If ($nick[1]=="has" AND $nick[2]=="been")
			{
			$found=true;
			$nickname=$nick[0];
			$membersince=self::TextBetween("participant since "," (",$getfile);
			$keycount=self::TextBetween("has typed "," keys,",$getfile);
			$clickcount=self::TextBetween("clicked "," times and moved",$getfile);
			fclose($openfile);
			};
		};
	If ($found==true)
		{
		$returnvar['nickname']=$nickname;
		$returnvar['membersince']=$membersince;
		$returnvar['keycount']=$keycount;
		$returnvar['clickcount']=$clickcount;
		return $returnvar;
		} else {
		return false;
		};
	}

public function priv_whatpulse($line,$args)
	{
	If (!isset($args['arg1']))
		{
		$this->ircClass->notice($line['fromNick'],"Use this function with '!whatpulse <username>'");
		return;
		};
		$stats=self::WhatpulseUserStats($args['arg1']);
		If (!$stats)
			{
			$this->ircClass->notice($line['fromNick'],"Couldn't retrieve the statistics for you, are you sure you the account exists?");
			return;
			};
		$msg="User ".$stats['nickname']." has typed ".$stats['keycount']." keys and ".$stats['clickcount']." clicks since ".$stats['membersince'].".";
		$this->ircClass->notice($line['fromNick'],$msg);
	}
}
?>


