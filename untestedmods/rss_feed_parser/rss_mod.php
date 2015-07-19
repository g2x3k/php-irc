<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC RSS Mod 0.2
|   ========================================================
|   (c) 2005 by Grigor Josifov (SilverShield)
|   Contact: grisha_at_mail_dot_bg
|   irc: #forci@irc.unibg.org
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

class rss_mod extends module {

	public $title = "RSS Mod";
	public $author = "SilverShield";
	public $version = "0.2";
	public $dontShow = true;

	
	private $rss_masters = array ( // List of Nick allowed to control RSS, no matter their mode.
		'Manick', 
		'Nemesis128', 
		'ho0ber', 
		'nefus', 
		'Davetha',
		'SilverShield' 
		);

	private $rssDB;

	public function init()
	{
		$this->rssDB = new ini("modules/rss/rss.ini");
	}
	
	public function priv_rss($line, $args)
	{

		$channel = $line['to'];
		
		// Begin list all RSS feeds in the help
		$all_sites = $this->rssDB->getSections();
		
		foreach ($all_sites AS $sites)
		{
			$site_list .= '/' . $sites;
		}
		$site_list = ltrim($site_list, '/');

		$msg_rss_help = "To see the RSS feeds type !rss {" . $site_list . "} [number] [cyr/lat]";
		// End list all RSS feeds in the help

		if ($args['nargs'] == 0)
		{
			$this->ircClass->privMsg($channel, $msg_rss_help);
		}
		else
		{
			$rss_site_name = strtolower($args['arg1']);
			// Begin fix RSS news limits
			$rss_num = ( $args['arg2'] == '' ) ? 1 : intval($args['arg2']);
			$rss_num = ( $rss_num <= 5 ) ? $rss_num : 5;
			$rss_num = ( $rss_num > 0 ) ? $rss_num : 1;
			// End fix RSS news limits
			
			// Begin get URL from DB
			$rss_site_url = $this->rssDB->getIniVal($rss_site_name, 'URL');
			if ($rss_site_url == false || $rss_site_url == '')
			{
				$this->ircClass->privMsg($channel, "The RSS source you are looking for not found!");
				$this->ircClass->privMsg($channel, $msg_rss_help);
			}
			// End get URL from DB

			#$rss = new cafeRSS();
			$this->assign('items', $rss_num);
			$this->assign('use_cache', 1);

			$rss_body = $this->display($rss_site_url);

			// Begin fix &amp; and other html ....
			$t = $rss_body;
			$i = 0;
			$html_sym = array(
				'&amp;','&quot;','&lt;','&gt;','&nbsp;');
			$normal_sym = array(
				'&','"','<','>',' ');
			while ($i<count($cyr)) {
				$t = str_replace($html_sym[$i],$normal_sym[$i],$t);
				$i++;
			}
			$rss_body = $t;
			// End fix &amp; and other html ....

			// Begin converting cyrilic symbols to latin
			if ( strtolower($args['arg2']) == 'lat' || strtolower($args['arg3']) == 'lat' )
			{
				$t = $rss_body;
				$i = 0;
				$cyr = array(
					'À','Á','Â','Ã','Ä','Å',"Æ","Ç","È","É",
					"Ê","Ë","Ì","Í","Î","Ï","Ð","Ñ","Ò","Ó",
					"Ô","Õ","Ö","×","Ø","Ù","Ú","Ü","Þ","ß",
					'à','á','â','ã','ä','å','æ','ç','è','é',
					'ê','ë','ì','í','î','ï','ð','ñ','ò','ó',
					'ô','õ','ö','÷','ø','ù','ú','ü','þ','ÿ');
				$lat = array(
					'A','B','V','G','D','E',"J","Z","I","J",
					"K","L","M","N","O","P","R","S","T","U",
					"F","H","C","CH","SH","SHT","Y","I","IU","IA",
					'a','b','v','g','d','e','j','z','i','j',
					'k','l','m','n','o','p','r','s','t','u',
					'f','h','c','ch','sh','sht','y','i','iu','ia');
				while ($i<count($cyr)) {
					$t = str_replace($cyr[$i],$lat[$i],$t);
					$i++;
				}
				$rss_body = $t;
			}
			// End converting cyrilic symbols to latin

			// Begin printing RSS lines to the channel
			$rss_body = split("\n", $rss_body);
			while( list($key, $value)=each($rss_body))
			{
				$this->ircClass->privMsg($channel, $value);
				
			}
			// End printing RSS lines to the channel
		}
	}
	
	public function priv_add_rss($line, $args)
	{
		// Begin Is fromNick on the masters list
		foreach ($this->rss_masters as $master) { 
			if ($line['fromNick'] == $master) { 
				$obey_master = true;
				break; 
			} 
		}
		// End Is fromNick on the masters list

		// Begin add RSS if obey master
		if ( $obey_master == true ) {
			$channel = $line['to'];
			if ($args['nargs'] <= 1)
			{
				$this->ircClass->privMsg($channel, 'To add RSS feed type !addrss {SITENAME} {RSS URL}');
			}
			else
			{
				$sitename = strtolower($args['arg1']);
				$siteurl = strtolower($args['arg2']);
				
				// Begin add or update RSS
				$rss_site_url = $this->rssDB->getIniVal($sitename, 'URL');
				if ($rss_site_url == false || $rss_site_url == '')
				{
					$this->rssDB->setIniVal($sitename, 'URL', $siteurl);
					$this->rssDB->writeIni();
					$this->ircClass->privMsg($channel, 'The RSS feed ' . $sitename . ' has been added.');
				}
				else
				{
					$this->rssDB->setIniVal($sitename, 'URL', $siteurl);
					$this->rssDB->writeIni();
					$this->ircClass->privMsg($channel, 'The RSS feed for ' . $sitename . ' has been updated.');
				}
				// End add or update RSS
			}
		}
		// Del add RSS if obey master
	}
	
	public function priv_del_rss($line, $args)
	{
		// Begin Is fromNick on the masters list
		foreach ($this->rss_masters as $master) { 
			if ($line['fromNick'] == $master) { 
				$obey_master = true;
				break; 
			} 
		}
		// End Is fromNick on the masters list

		// Begin del RSS if obey master
		if ( $obey_master == true ) {
			$channel = $line['to'];
			if ($args['nargs'] < 1)
			{
				$this->ircClass->privMsg($channel, 'To remove RSS feed type !delrss {SITENAME}');
			}
			else
			{
				$sitename = strtolower($args['arg1']);
				
				// Begin del RSS if record exist
				$rss_site_url = $this->rssDB->getIniVal($sitename, 'URL');
				if ($rss_site_url == false || $rss_site_url == '')
				{
					$this->ircClass->privMsg($channel, 'RSS feed does not exist.');
				}
				else
				{
					$this->rssDB->deleteSection($sitename);
					$this->rssDB->writeIni();
					$this->ircClass->privMsg($channel, 'The RSS feed ' . $sitename . ' has been deleted.');
				}
				// End del RSS if record exist
			}
		}
		// End del RSS if obey master
	}
	
	
	
/*
The code below Based on original CaféRSS 1.5
by Michel Valdrighi Copyright (C) 2002
BarkerJr, magu Copyright (C) 2004
and modified by Grigor Josifov (SilverShield) Copyright (C) 2005
*/
	

	var $url;
	var $debugtimer;

	/* defaut values */
	var $items = 'all';
	var $template_string = '';
	var $template_file = './modules/rss/rss_mod.tpl';
	var $use_cache = 1;
	var $cache_dir = './modules/rss/cache'; # if you want to cache, chmod a directory 777 and put its name here
	var $refresh_time = 900; # in seconds - has no effect if $use_cache = 0;
	var $rss_echo = 1;
	var $debug = 0;
	var $rss_patch = 0; # if set to 1, will fix all titles and descriptions generated by typepad (recommended)
	var $feednumber = 0; /* if you use javascript on the template file to open one window per source feed,
						    use {$rss_feednumber} on the template to track wich source opens in what window.
							Confused? check the template to understand...
							magu's Note: I use it to open just one window for Wired.com's news, other just for 
							Slashdot.org's news, etc.
						 */

	/* usage: $this->assign('var','value'); */

	function assign($var, $value) {
		$this->$var = $value;
	}


	/* usage: $this->display('url' [, those optional parameters below ]); */

	function display($rss_file = 'blah', $rss_items = 'blah', $rss_template_string = 'blah', $rss_template_file = 'blah', $rss_use_cache= 'blah', $rss_cache_dir = 'blah', $rss_refresh_time = 'blah', $rss_echo = 'blah', $rss_debug = 'blah', $rss_feednumber = 'blah', $rss_patch = 'blah') {

		if ($rss_file == 'blah') { $rss_file = $this->url; }
		if ($rss_items == 'blah') { $rss_items = $this->items; }
		if ($rss_template_string == 'blah') { $rss_template_string = $this->template_string; }
		if ($rss_template_file == 'blah') { $rss_template_file = $this->template_file; }
		if ($rss_use_cache == 'blah') { $rss_use_cache = $this->use_cache; }
		if ($rss_cache_dir == 'blah') { $rss_cache_dir = $this->cache_dir; }
		if ($rss_refresh_time == 'blah') { $rss_refresh_time = $this->refresh_time; }
		if ($rss_echo == 'blah') { $rss_echo = $this->echo; }
		if ($rss_debug == 'blah') { $rss_debug = $this->debug; }
		if ($rss_feednumber == 'blah') { $rss_feednumber = $this->feednumber; }
		if ($rss_patch == 'blah') { $rss_patch = $this->rss_patch; }

		$rss_cache_file = $rss_cache_dir.'/'.preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $rss_file).'.cache';


		if (preg_match('/</', $rss_file)) {
			$content = $rss_file;
		} else {


			/* the secret cache ops, part I */

			$isCached = false;
			if (($rss_cache_dir != '') && ($rss_use_cache)) {
				clearstatcache();
				$get_rss = 1;
				$cache_rss = 1;
				if (file_exists($rss_cache_file) && file_exists($rss_cache_file)) {
					if ((time() - filemtime($rss_cache_file)) < $rss_refresh_time) {
						$get_rss = 0;
						$isCached = true;
					}
				}
			} else {
				$get_rss = 1;
				$cache_rss = 0;
			}


			/* opens the RSS file */

			$this->timer_start();
			if ($get_rss) {
				if (file_exists($rss_cache_file) && file_exists($rss_cache_file)) {
					$f = fopen("$rss_cache_file", 'r');
					$opts = array(
						'http' => array(
							'header' => 'If-Modified-Since: ' . fgets($f) . "\r\n"
						)
					);
					fclose($f);
					$context = stream_context_create($opts);
@					$f = fopen($rss_file, 'r', false, $context) or $isCached = true;
				}
				else
				{
@					$f = fopen($rss_file, 'r') or $nocon = true;
					if ($nocon)
					{
						echo '<p><i>(error displaying RSS feed)</i></p>';
						return;
					}
				}
				if (!$isCached)
				{
					$meta = stream_get_meta_data($f);
					foreach ($meta['wrapper_data'] as $row)
						if (substr($row, 0, 14) == 'Last-Modified:')
						{
							$c = fopen($rss_cache_file, 'w');
							touch($rss_cache_file);
							fwrite($c, substr($row, 15));
							fclose($c);
							break;
						}
					while (!feof($f))
						$content .= fgets($f, 4096);
					fclose($f);
				}
			}
			$debugfopentime = $this->timer_stop(0);

			if ($isCached)
			{
				$this->timer_start();
				$f = fopen($rss_cache_file, 'r');
				$content = fread($f, filesize($rss_cache_file));
				fclose($f);
				$debugfopencachetime = $this->timer_stop(0);
			}


			/* the secret cache ops, part II */

			if (($cache_rss) && ($rss_use_cache) && (!$isCached)) {
				$this->timer_start();
				$f = fopen($rss_cache_file, 'w+');
				fwrite($f, $content);
				fclose($f);
				$debugcachetime = $this->timer_stop(0);
			} else {
				$debugcachetime = 0;
			}

		}


		/* gets RSS channel info and RSS items info */

		$this->timer_start();
		preg_match_all("'<channel( .*?)?>(.*?)<title>(.*?)</title>(.+?)</channel>'si",$content,$rss_title);
		preg_match_all("'<channel( .*?)?>(.*?)<link>(.*?)</link>(.+?)</channel>'si",$content,$rss_link);
		preg_match_all("'<channel( .*?)?>(.*?)<description>(.*?)</description>(.*?)</channel>'si",$content,$rss_description);
		preg_match_all("'<channel( .*?)?>(.*?)<lastBuildDate>(.*?)</lastBuildDate>(.*?)</channel>'si",$content,$rss_lastBuildDate);
		preg_match_all("'<channel( .*?)?>(.*?)<docs>(.*?)</docs>(.*?)</channel>'si",$content,$rss_docs);
		preg_match_all("'<channel( .*?)?>(.*?)<managingEditor>(.*?)</managingEditor>(.*?)</channel>'si",$content,$rss_managingEditor);
		preg_match_all("'<channel( .*?)?>(.*?)<webMaster>(.*?)</webMaster>(.*?)</channel>'si",$content,$rss_webMaster);
		preg_match_all("'<channel( .*?)?>(.*?)<language>(.*?)</language>(.*?)</channel>'si",$content,$rss_language);
		preg_match_all("'<image>(.*?)<title>(.*?)</title>(.*?)</image>'si",$content,$rss_image_title);
		preg_match_all("'<image>(.*?)<url>(.*?)</url>(.*?)</image>'si",$content,$rss_image_url);
		preg_match_all("'<image>(.*?)<link>(.*?)</link>(.*?)</image>'si",$content,$rss_image_link);
		preg_match_all("'<item( .*?)?>(.*?)<title>(<!\[CDATA\[)?(.+?)(\]\]>)?</title>(.*?)</item>'si",$content,$rss_item_titles);
		preg_match_all("'<item( .*?)?>(.*?)<link>(<!\[CDATA\[)?(.+?.*?)(\]\]>)?</link>(.*?)</item>'si",$content,$rss_item_links);
		preg_match_all("'<item( .*?)?>(.*?)<description>(.*?)</description>(.*?)</item>'si",$content,$rss_item_descriptions);
		$rss_title = $rss_title[3][0];
		$rss_link = $rss_link[3][0];
		$rss_description = $rss_description[3][0];
		$rss_lastBuildDate = $rss_lastBuildDate[3][0];
		$rss_docs = $rss_docs[3][0];
		$rss_managingEditor = $rss_managingEditor[3][0];
		$rss_webMaster = $rss_webMaster[3][0];
		$rss_language = $rss_language[3][0];
		$rss_image_title = $rss_image_title[2][0];
		$rss_image_url = $rss_image_url[2][0];
		$rss_image_link = $rss_image_link[2][0];
		$debugparsersstime = $this->timer_stop(0);



		/* gets the template */

		$this->timer_start();
		if (empty($rss_template_string)) {
			$f = fopen($rss_template_file,'r');
			$rss_template = fread($f, filesize($rss_template_file));
			fclose($f);
		} else {
			$rss_template = $rss_template_string;
		}
		$debugfopentemplatetime = $this->timer_stop(0);
		$rss_template = str_replace('{BOLD}',BOLD, $rss_template);
		$rss_template = str_replace('{UNDERLINE}',UNDERLINE, $rss_template);
		$rss_template = str_replace('{COLOR}',COLOR, $rss_template);
		preg_match_all("'{rss_items}(.+?){/rss_items}'si",$rss_template,$rss_template_loop);
		$rss_template_loop = $rss_template_loop[1][0];

		$rss_template = str_replace('{rss_items}','',$rss_template);
		$rss_template = str_replace('{/rss_items}','',$rss_template);



		/* processes the template - rss channel info */

		$this->timer_start();
		$rss_template = str_replace('{$rss_title}',$rss_title, $rss_template);
		$rss_template = str_replace('{$rss_link}',$rss_link, $rss_template);
		$rss_template = str_replace('{$rss_description}',$rss_description, $rss_template);
		$rss_template = str_replace('{$rss_lastBuildDate}',$rss_lastBuildDate, $rss_template);
		$rss_template = str_replace('{$rss_docs}',$rss_docs, $rss_template);
		$rss_template = str_replace('{$rss_managingEditor}',$rss_managingEditor, $rss_template);
		$rss_template = str_replace('{$rss_webMaster}',$rss_webMaster, $rss_template);
		$rss_template = str_replace('{$rss_language}',$rss_language, $rss_template);



		/* processes the template - rss image info */

		if ($rss_image_url != '') {
			$rss_template = str_replace('{rss_image}','',$rss_template);
			$rss_template = str_replace('{/rss_image}','',$rss_template);
			$rss_template = str_replace('{$rss_image_title}',$rss_image_title, $rss_template);
			$rss_template = str_replace('{$rss_image_link}',$rss_image_link, $rss_template);
			$rss_template = str_replace('{$rss_image_url}',$rss_image_url, $rss_template);
		} else {
			$rand = md5(rand(1,5)); /* now there's an ugly hack that I'll have to fix */
			$rss_template = preg_replace('/(\015\012)|(\015)|(\012)/', $rand, $rss_template);
			$rss_template = preg_replace('/{rss_image}(.*?){\/rss_image}/', '', $rss_template);
			$rss_template = preg_replace("/$rand/", "\n", $rss_template);
		}



		/* processes the template - rss items info */

		$rss_template_loop_processed = '';
		$k = count($rss_item_titles[4]);
		$j = (($rss_items == 'all') || ($rss_items > $k)) ? $k : intval($rss_items);
		for ($i = 0; $i<$j; $i++) {
			$tmp_template = $rss_template_loop;
			$tmp_title = $rss_item_titles[4][$i];
			$tmp_link = $rss_item_links[4][$i];
			$tmp_description = $rss_item_descriptions[3][$i];
			if ($tmp_description == '') {
				$tmp_description = '-';
			}
			if ($tmp_title == '') {
				$tmp_title = substr($tmp_description,0,20);
				if (strlen($tmp_description) > 20) {
					$tmp_title .= '...';
				}
			}
			$tmp_title = $this->patch($tmp_title);
			$tmp_description = $this->patch($tmp_description);
			$tmp_link = str_replace('&amp;','&',$tmp_link);
			$tmp_template = str_replace('{$rss_item_title}',$tmp_title, $tmp_template);
			$tmp_template = str_replace('{$rss_item_link}',$tmp_link, $tmp_template);
			$tmp_template = str_replace('{$rss_item_description}',$tmp_description, $tmp_template);
			$tmp_template = str_replace('{$rss_feednumber}',$rss_feednumber, $tmp_template);
			$rss_template_loop_processed .= $tmp_template;
		}
		$rss_template = str_replace($rss_template_loop, $rss_template_loop_processed, $rss_template);
		$debugprocesstemplatetime = $this->timer_stop(0);

		clearstatcache();
		
		
		/* echoes or returns the processed template :) */

		if ($rss_echo = 0) {
			echo $rss_template;
			if ($rss_debug) {
				echo '<p>';
				echo $debugfopentime.' seconds to load the remote RSS file.<br />';
				echo $debugparsersstime.' seconds to parse the RSS.<br />';
				echo $debugfopentemplatetime.' seconds to load the template file.<br />';
				echo $debugprocesstemplatetime.' seconds to process the template.<br />';
				if ($cache_rss) {
					echo $debugcachetime.' seconds to cache the parsing+processing.<br />';
				}
				echo '<br />';
				$debugtotaltime = ($debugfopentime+$debugparsersstime+$debugfopentemplatetime+$debugfopentemplatetime+$debugprocesstemplatetime+$debugcachetime);
				echo 'Total: '.$debugtotaltime.' seconds.';
				echo '</p>';
			}
		} else {
			return $rss_template;
		}

	}

	function timer_start() {
		$mtime = microtime();
		$mtime = explode(" ",$mtime);
		$mtime = $mtime[1] + $mtime[0];
		$this->debugtimer = $mtime;
		return true;
	}

	function timer_stop($display=0,$precision=3) {
		$mtime = microtime();
		$mtime = explode(" ",$mtime);
		$mtime = $mtime[1] + $mtime[0];
		$this->debugtimer = $mtime - $this->debugtimer;
		if ($display)
			echo number_format($this->debugtimer,$precision);
		return($this->debugtimer);
	}
	
	function patch($text) {
		$t = $text;
		if ($this->rss_patch) {
			$i = 0;
			$faults = array(
				'Ã§','Ã©','Ãª','Ã¡','Ã£','Ã³', 	# fix for typepad RDF files 
				'Ãµ','Ã‰','Ã­', 				# sadly, they screw up the encoding
				' &amp; ','&amp;iacute;','&apos;', # this is for some strange appearances.. =)
				'á','Á','à','À','â','Â','ã','Ã',
				'é','É','è','È','ê','Ê','ë','Ë',
				'í','Í',
				'ó','Ó','ò','Ò','ô','Ô','õ','Õ',
				'ú','Ú','ü','Ü');
			$fixes = array(
				'&ccedil;','&eacute;','&ecirc;','&aacute;','&atilde;','&oacute;',
				'&otilde;','&Eacute;','&iacute;',
				' & ','&iacute;',"'",
				'&aacute;','&Aacute;','&agrave;','&Agrave;','&acirc;','&Acirc;','&atilde;','&Atilde;',
				'&eacute;','&Eacute;','&egrave;','&Egrave;','&ecirc;','&Ecirc;','&euml;','&Euml;',
				'&iacute;','&Iacute;',
				'&oacute;','&Oacute;','&ograve;','&Ograve;','&ocirc;','&Ocirc;','&otilde;','&Otilde;',
				'&uacute;','&Uacute;','&uuml;','&Uuml;');
			while ($i<count($faults)) {
				$t = str_replace($faults[$i],$fixes[$i],$t);
				$i++;
			}
		}
		return $t;
	}	
}

?>
