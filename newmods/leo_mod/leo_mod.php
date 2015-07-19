<?php

/*
+---------------------------------------------------------------------------
|   PHP-IRC dict.leo.org translator
|   ========================================
|   v0.3 by SubWorx
|   (c) 2007-2009 by http://subworx.ath.cx
|   Contact:
|    email: hiphopman@gmx.net
|    irc:   #zauberpilz@irc.phat-net.de
|   ========================================
| 	Big Thanks to fragp <jb@fragp.com> for letting me adapt his leo.tcl
|	to PHP, especially these wicked regular expressions :D
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
|   0.3:    added italian, fixed mod
|   0.2:	made it work with all 3 languages of dict.leo.org
|   0.1: 	initial release
+---------------------------------------------------------------------------
|	Todo
|	====----------
|	implement the "similar match" part
|
+---------------------------------------------------------------------------
*/


class leo_mod extends module {

	public $title = "Leo Mod";
	public $author = "SubWorx";
	public $version = "0.3";

	public $max_results = 5;
	public $response_type = 0; // 0 = channel; 1 = query/pm; 2 = notice

	public $preg = array('#\<.*?\>#i', '#\s{2,}#i', '#&.*?\;#i', '#\|#');

	public function init()
	{
		//if (!isset($this->preg))
		//	$this->preg = '#\<h2 class\=r\>\<a href\="([^"]*)" #Ui';

		// we can't have more then 10 results
		if ($this->max_results > 10)
			$this->max_results = 10;
	}

	public function destroy()
	{
	}

	public function priv_leo($line, $args)
	{
    if ($args['nargs'] < 1)
    {
    	$this->sendMsg($line, $args, 'You need to supply a search string');
    	return;
    }

    $cmdLower = irc::myStrToLower($args['cmd']);
    switch($cmdLower){
    	case '!fra':
		    $query = 'lp=frde&lang=de&searchLoc=0&cmpType=relaxed&sectHdr=off&spellToler=on&search='.urlencode($args['query']).'&relink=off';
		    $getQuery = socket::generateGetQuery($query, 'dict.leo.org', '/frde');
    		break;
    	case '!esp':
    		$query = 'lp=esde&lang=de&searchLoc=0&cmpType=relaxed&sectHdr=off&spellToler=on&search='.urlencode($args['query']).'&relink=off';
    		$getQuery = socket::generateGetQuery($query, 'dict.leo.org', '/esde');
    		break;
    	case '!ita':
    		$query = 'lp=itde&lang=de&searchLoc=0&cmpType=relaxed&sectHdr=off&spellToler=on&search='.urlencode($args['query']).'&relink=off';
    		$getQuery = socket::generateGetQuery($query, 'dict.leo.org', '/itde');
    		break;
    	// skip chinese, output is garbage only anyway
    	//case '!chn':
    	//	$query = 'lp=chde&lang=de&searchLoc=0&cmpType=relaxed&sectHdr=off&spellToler=on&search='.urlencode($args['query']).'&relink=off';
    	//	$getQuery = socket::generateGetQuery($query, 'dict.leo.org', '/chde');
    	//	break;
    	default:
    		$query = 'lp=ende&lang=de&searchLoc=0&cmpType=relaxes&sectHdr=off&spellToler=on&search='.urlencode($args['query']).'&relink=off';
    		$getQuery = socket::generateGetQuery($query, 'dict.leo.org', '/ende');
    } // switch
    $this->ircClass->addQuery('dict.leo.org', 80, $getQuery, $line, $this, 'sendLeoResults');
	}

	public function sendLeoResults($line, $args, $result, $response)
	{
		if ($result == QUERY_SUCCESS)
		{
			// <td nowrap width="5%">
			// this tag appears only when we have a search result, so lets look for this
			// better than using regexps :)
			//
			// old variant:
			//$wordExistsExpr = "#\<TR\>\<TD ALIGN=CENTER COLSPAN=5\>.*?\<B\>(.*?)\</B\>.*?\</table\>#i";
			//if (0 == preg_match_all($wordExistsExpr, $response, $matches, PREG_SET_ORDER))
			if (false == strstr($response, '<td nowrap width="5%">'))
			{
				$this->sendMsg($line, $args, 'Your search - '.BOLD.$args['query'].BOLD.' - did not match anything.');
				return;
			}

			// .*? == ? makes the last quantifier ungreedy
			// so: match any char before next part of expression, but smallest version possible
			$wordMatchExpr = '\<td valign="middle" width="43%"\>(.*?)\</td\>';
        	// not used
			//$wordJumpExpr  = '\<td class="td1" valign="middle" width="43%"\>.*?\</td\>';
        	$wordCountExpr = '\<tr valign="top".*?'.$wordMatchExpr.'.*?'.$wordMatchExpr.'.*?\</tr\>';

			$wordCount = preg_match_all('#'.$wordCountExpr.'#i', $response, $matchesWordCount, PREG_SET_ORDER);
			// $matchesWordCount[x][y]
			// x: result number 0-n
			// y = 0: trash
			// y = 1: english/spanish/french
			// y = 2: german (needs cleaning from <b></b> tags

			if ($wordCount > $this->max_results)
			{
				$wordCount = $this->max_results;
			}

			$result = "";
			for ($i = 0; $i < $wordCount; $i++)
			{
				$result .= $this->cleanup($matchesWordCount[$i][1]).BOLD.' = '.BOLD.$this->cleanup($matchesWordCount[$i][2]).BOLD.' / '.BOLD;
			}

		    $cmdLower = irc::myStrToLower($args['cmd']);
    		switch($cmdLower){
	    		case '!fra':
			    	$lang = 'frde';
					break;
		    	case '!esp':
    				$lang = 'esde';
					break;
		    	case '!ita':
    				$lang = 'itde';
					break;
		    	//case '!chn':
    			//	$lang = 'chde';
				//	break;
    			default:
		    		$lang = 'ende';
    		} // switch

			$result .= 'more: '.UNDERLINE.'http://dict.leo.org/'.$lang.'?search='.$args['query'].UNDERLINE;
			$this->ircClass->privMsg($line['to'], $result);

		}
		else
		{
			$this->sendMsg($line, $args, 'Leo says NO! (server didn\'t respond)');
			return false;
		}
	}

	private function cleanup($word)
	{
		return preg_replace($this->preg, '', $word);
	}

	public function sendMsg($line, $args, $message)
	{
		switch($this->response_type)
		{
			case 0:
				$this->ircClass->privMsg($line['to'], $message);
			break;

			case 1:
				$this->ircClass->privMsg($line['fromNick'], $message);
			break;

			case 2;
				$this->ircClass->notice($line['fromNick'], $message);
			break;

			default:
				$this->ircClass->privMsg($line['to'], $message);
		}
	}

}

?>
