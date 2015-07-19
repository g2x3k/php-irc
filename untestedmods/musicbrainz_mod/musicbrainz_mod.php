<?php
/**
 * MUSICBRAINZ module for PHP-IRC
 * Copyright (c) 20056Jason Hines <jason@greenhell.com>
 * $Rev: 532 $
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

class musicbrainz_mod extends module {

	public $title = "musicbrainz";
	public $author = "oweff";
	public $version = "0.1";
    private $mbQuery;

	// initialize the module on startup
	public function init() {
        set_include_path(get_include_path().":".dirname(__FILE__)."/modules/musicbrainz_mod/php-musicbrainz/");
        require_once("phpBrainz.class.php");
        $this->mbQuery = new mbQuery;
	}

	// main method
	public function priv_mb($line, $args) {
		if ($line['to'] == $this->ircClass->getNick()) {
			return;
		}

        if ($args['nargs'] == 0) {
            $this->ircClass->notice($line['fromNick'], "Usage: !mb [--artist|--album|--track] <phrase>");
            return;
        }

        $time_start = microtime(true);

        $query = $args['query'];
        switch ($args['arg1']) {
            case "--album":
                $query = trim(str_replace("--album","",$query));
                $this->findAlbum($query,$line);
            break;
            case "--track":
                $query = trim(str_replace("--track","",$query));
                $this->findTrack($query,$line);
            break;
            default:
            case "--artist":
                $query = trim(str_replace("--artist","",$query));
                $this->findArtist($query,$line);
            break;
        }

        $time_end = microtime(true);
        $time = $time_end - $time_start;
        $this->ircClass->notice($line['fromNick'], "Search for \"{$query}\" completed in {$time} seconds.");
    }

    function findArtist($artist,$line) {
        $result = $this->mbQuery->getArtistByName($artist,3);
        if (empty($result)) {
            $this->ircClass->notice($line['fromNick'], "No results found.");
            return;
        }

        $this->ircClass->privMsg($line['to'], "[mbz] Searching artists: {$artist}");
        foreach ($result as $i=>$A) {
    		$this->ircClass->privMsg($line['to'], $i . ": " . $A['title']);
        }

		$this->ircClass->log("Searching MusicBrainz for artist: {$artist}");
	}

    function findTrack($track,$line) {
        $result = $this->mbQuery->getTrackByName($track,5);
        if (empty($result)) {
            $this->ircClass->notice($line['fromNick'], "No results found.");
            return;
        }

        $this->ircClass->privMsg($line['to'], "[mbz] Searching tracks: {$track}");
        foreach ($result as $i=>$A) {
    		$this->ircClass->privMsg($line['to'], $i . ": \"{$A['title']}\" by {$A['creator']['title']}");
        }

		$this->ircClass->log("Searching MusicBrainz for track: {$track}");
	}

    function findAlbum($album,$line) {
        $result = $this->mbQuery->getAlbumByName($album,5);
        if (empty($result)) {
            $this->ircClass->notice($line['fromNick'], "No results found.");
            return;
        }

        $this->ircClass->privMsg($line['to'], "[mbz] Searching albums: {$album}");
        foreach ($result as $i=>$A) {
    		$this->ircClass->privMsg($line['to'], $i . ": \"{$A['title']}\" by {$A['creator']['title']}");
        }

		$this->ircClass->log("Searching MusicBrainz for album: {$album}");
	}

}
?>
