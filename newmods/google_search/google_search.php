<?php

/*
+---------------------------------------------------------------------------
|   PHP-IRC Google Search
|   ========================================
|     by Mad Clog
|   (c) 2007-2009 by http://www.madclog.nl
|   Contact:
|    email: phpirc@madclog.nl
|    msn:   gertjuhh@hotmail.com
|    irc:   #madclog@irc.quakenet.org
|   ========================================
|   Changelog:
|   0.1
|    - Initial release
|   0.2
|    - Added image support
|   0.3
|    - Improved main search preg pattern
|    - Added calculator support (also includes currency conversions)
|    - Added config option for google extention
|   0.4
|    - Added video support
|   0.4.1
|    - Fixed calculated results
|   0.4.2
|    - Fixed video searches
|   0.4.3
|    - Fixed video searches (again...)
|   0.4.4
|    - Fixed all searches (google had a nice little update...)
|   0.5.0
|    - Added !define functionality
|    - Did some MINOR code cleanup
|   0.5.1
|    - Fixed images searches (again...)
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

class google_search extends module {
	
	public $title = 'Google Search';
	public $author = 'Mad_Clog';
	public $version = '0.5.1';
	
	public $max_results = 2;
	public $max_image_results = 2;
	public $max_video_results = 2;
	public $response_type = 0; // 0 = channel; 1 = query/pm; 2 = notice
	public $extension = 'com'; // Which google to search, eg 'com' 'nl' 'co.uk'
	

	public function init() {
		// we can't have more then 10 results
		if ($this->max_results > 10)
			$this->max_results = 10;
		if ($this->max_image_results > 10)
			$this->max_image_results = 10;
		if ($this->max_video_results > 10)
			$this->max_video_results = 10;
	}
	
	public function destroy() {
	}
	
	public function priv_google($line, $args) {
		if ($args ['nargs'] < 1) {
			$this->sendMsg ( $line, $args, 'You need to supply a search string' );
			return;
		}
		
		$getQuery = socket::generateGetQuery ( 'q=' . urlencode ( $args ['query'] ), 'www.google.' . $this->extension, '/search' );
		if (strtolower ( substr ( $args ['cmd'], 1 ) ) == 'calc') {
			$this->ircClass->addQuery ( 'www.google.' . $this->extension, 80, $getQuery, $line, $this, 'sendCalcResults' );
		} else {
			$this->ircClass->addQuery ( 'www.google.' . $this->extension, 80, $getQuery, $line, $this, 'sendSearchResults' );
		}
	}
	
	public function priv_define($line, $args) {
		if ($args ['nargs'] < 1) {
			$this->sendMsg ( $line, $args, 'You need to supply a search string' );
			return;
		}
		
		$getQuery = socket::generateGetQuery ( 'q=define:' . urlencode ( $args ['query'] ), 'www.google.' . $this->extension, '/search' );
		$this->ircClass->addQuery ( 'www.google.' . $this->extension, 80, $getQuery, $line, $this, 'sendSearchResults' );
	}
	
	public function sendSearchResults($line, $args, $result, $response) {
		if ($result == QUERY_SUCCESS) {
			if (strtolower ( substr ( $args ['cmd'], 1 ) ) == 'define') {
				$pattern = '#<li>([^<]+)#i';
			} else {
				$pattern = '#<h3 class=r><a href="([^"]*)" class=l>(([^<]|<[^a][^ ])*)</a></h3>#i';
			}
			
			$count = preg_match_all ( $pattern, $response, $matches, PREG_SET_ORDER );
			if ($count == 0) {
				$this->sendMsg ( $line, $args, 'Your search - ' . BOLD . $args ['query'] . BOLD . ' - did not match any documents.' );
				return;
			}
			
			$numResults = ($count < $this->max_results) ? $count : $this->max_results;
			for($i = 0; $i < $numResults; $i ++) {
				$this->sendMsg ( $line, $args, strip_tags ( html_entity_decode ( $matches [$i] [2], ENT_QUOTES, 'utf-8' ) ) . ' - ' . $matches [$i] [1] );
			}
		} else {
			$this->sendMsg ( $line, $args, 'Google says NO! (server didn\'t respond)' );
		}
	}
	
	public function sendCalcResults($line, $args, $result, $response) {
		if ($result == QUERY_SUCCESS) {
			$pattern = '#<h2 class=r><font size=\+1><b>(.*)</b></h2>#Ui';
			$res = preg_match ( $pattern, $response, $match );
			if ($res === 1) {
				$this->sendMsg ( $line, $args, 'Google calculator: ' . strip_tags ( html_entity_decode ( $match [1] ) ) );
			} else {
				$this->sendMsg ( $line, $args, 'Your search - ' . BOLD . $args ['query'] . BOLD . ' - did not return a calculated result' );
			}
		} else {
			$this->sendMsg ( $line, $args, 'Google says NO! (server didn\'t respond)' );
		}
	}
	
	public function priv_image($line, $args) {
		if ($args ['nargs'] < 1) {
			$this->sendMsg ( $line, $args, 'You need to supply a search string' );
			return;
		}
		
		$getQuery = socket::generateGetQuery ( 'q=' . urlencode ( $args ['query'] ), 'images.google.' . $this->extension, '/images' );
		$this->ircClass->addQuery ( 'images.google.' . $this->extension, 80, $getQuery, $line, $this, 'sendImageResults' );
	}
	
	public function sendImageResults($line, $args, $result, $response) {
		if ($result == QUERY_SUCCESS) {
			$pattern = '#\["([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)",\[([^\]]*)\],"([^"]*)"#i';
			$count = preg_match_all ( $pattern, $response, $matches, PREG_SET_ORDER );
			if ($count == 0) {
				$this->sendMsg ( $line, $args, 'Your search - ' . BOLD . $args ['query'] . BOLD . ' - did not match any documents.' );
				return;
			}
			
			$numResults = ($count < $this->max_image_results) ? $count : $this->max_image_results;
			for($i = 0; $i < $numResults; $i ++) {
				$this->sendMsg ( $line, $args, strip_tags ( html_entity_decode ( $matches [$i] [4] ) . ' (' . $matches [$i] [10] . ' - ' . $matches [$i] [12] . ')' ) );
			}
		} else {
			$this->sendMsg ( $line, $args, 'Google says NO! (server didn\'t respond)' );
		}
	}
	
	public function priv_video($line, $args) {
		if ($args ['nargs'] < 1) {
			$this->sendMsg ( $line, $args, 'You need to supply a search string' );
			return;
		}
		
		$getQuery = socket::generateGetQuery ( 'q=' . urlencode ( $args ['query'] ) . '&hl=en', 'video.google.' . $this->extension, '/videosearch' );
		$this->ircClass->addQuery ( 'video.google.' . $this->extension, 80, $getQuery, $line, $this, 'sendVideoResults' );
	}
	
	public function sendVideoResults($line, $args, $result, $response) {
		/* Array mapping
		1: link
		2: title
		3: length
		4: date
		*/
		if ($result == QUERY_SUCCESS) {
			$pattern = '#srcurl="(.*)".*<div class="rl-title".*><a.*>(.*)</a></div>.*<div class="rl-details">(?:<div .*</div>)?(\d.*) \-<span class="rl-date">(.*) - </span>#iU';
			
			$response = str_replace ( array ("\r", "\n" ), '', $response );
			$count = preg_match_all ( $pattern, $response, $matches, PREG_SET_ORDER );
			if ($count == 0) {
				$this->sendMsg ( $line, $args, 'Your search - ' . BOLD . $args ['query'] . BOLD . ' - did not match any documents.' );
				return;
			}
			
			$numResults = ($count < $this->max_video_results) ? $count : $this->max_video_results;
			for($i = 0; $i < $numResults; $i ++) {
				$this->sendMsg ( $line, $args, trim ( $matches [$i] [1] ) . ' - ' . BOLD . trim ( strip_tags ( html_entity_decode ( $matches [$i] [2] ) ) ) . BOLD . ' ( ' . trim ( $matches [$i] [3] ) . ' - ' . trim ( $matches [$i] [4] ) . ' )' );
			}
		} else {
			$this->sendMsg ( $line, $args, 'Google says NO! (server didn\'t respond)' );
		}
	}
	
	public function sendMsg($line, $args, $message) {
		switch ($this->response_type) {
			case 0 :
				$this->ircClass->privMsg ( $line ['to'], $message );
				break;
			
			case 1 :
				$this->ircClass->privMsg ( $line ['fromNick'], $message );
				break;
			
			case 2 :
				$this->ircClass->notice ( $line ['fromNick'], $message );
				break;
			
			default :
				$this->ircClass->privMsg ( $line ['to'], $message );
		}
	}

}

?>