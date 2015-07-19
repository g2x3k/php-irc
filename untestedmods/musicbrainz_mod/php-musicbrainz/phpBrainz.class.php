<?php
/**
* Musicbrainz Query Class
*
* This program is free software; you can redistribute it and/or
* modify it under the terms of the GNU General Public License
* as published by the Free Software Foundation; either version 2
* of the License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*
* @package php-musicbrainz
* @author Chris Schwerdt <muti@afterglo.ws>
* @copyright Copyright (c) 2005, Chris Schwerdt
* @link http://projects.afterglo.ws/wiki/PhpMusicbrainzHome
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/

/**
* Require the rdf parsing class.
*/
require_once('rdfParser.php');

/**
* The MusicBrainz artist id used to indicate that an album is a various artist album.
*/
define('VARIOUS_ARTISTS', '89ad4ac3-39f7-470e-963a-56509c546377');

/**
* Class for querying musicbrainz using RDF.
* @package php-musicbrainz
*/
class mbQuery {

	/**#@+
	* Global variables.
	* @access private
	*/
	var $_mbSocket;
	var $_errorMsg;
	var $_encoding;
	/**#@-*/

	/**
	* Class constructor, initializes private members.
	* @access private
	*/
	function mbQuery() {
		$this->_errorMsg = '';
		$this->_encoding = 'UTF-8';
	}

	/**
	* Sets encoding type of queries and results
	* 
	* According to what I've read about Musicbrainz, the only valid encodings accepted
	* are "iso-5589-1" and "UTF-8"
	* @param string $encoding Encoding Type.
	* @return boolean Whether setting encoding type succeeded.
	*/
	function setEncoding($encoding) {
		if($encoding != 'UTF-8' AND $encoding != 'iso-8859-1') {
			$this->_errorMsg = 'Invalid encoding type';
			return FALSE;
		} else {
			$this->_encoding = $encoding;
			return TRUE;
		}
	}

	/**
	* Opens a socket to musicbrainz.org.
	* @access private
	*/
	function mbConnect() {
		$this->_mbSocket = fsockopen('musicbrainz.org', 80, $errno, $errstr, 10);
		if(!$this->_mbSocket) {
			$this->_errorMsg = $errstr;
			return FALSE;
		}
		return TRUE;
	}

	/**
	* Closes socket to musicbrainz.org
	* @access private
	*/
	function mbDisconnect() {
		fclose($this->_mbSocket);
	}

	/**
	* Sends an HTTP GET/POST to musicbrainz.org
	* @access private
	*/
	function sendQuery($query, $action) {
		fwrite($this->_mbSocket, $action);
		fwrite($this->_mbSocket, "Host: musicbrainz.org\r\n");
		fwrite($this->_mbSocket, "Accept: */*\r\n");
		fwrite($this->_mbSocket, "User-Agent: phpMbQuery\r\n");
		fwrite($this->_mbSocket, "Content-type: text/plain\r\n");
		fwrite($this->_mbSocket, "Content-length: ".strlen($query)."\r\n");
		fwrite($this->_mbSocket, "Connection: close\r\n\r\n");

		fwrite($this->_mbSocket, $query."\r\n\r\n");
	}

	/**
	* Fetches returned query data from musicbrainz.org
	* @access private
	*/
	function getQueryResponse() {
		$buffer = '';

		while(!feof($this->_mbSocket)) {
			$buffer .= fread($this->_mbSocket, 8192);
		}

		return $buffer;
	}

	/**
	* Converts characters into properly encoded XML entities.
	* @access private
	*/
	function xmlentities($string) {
		$text = htmlspecialchars($string, ENT_QUOTES);
		$text = preg_replace('/&#0*39;/', '&apos;', $text);
		return $text;
	}

	/**
	* Chops off URL portion of release information, leaving just the release data
	*
	* This is useful to convert strings returned from musicbrainz such as
	* http://musicbrainz.org/mm/mm-2.1#TypeSoundtrack to TypeSoundtrack.
	* @param string $releaseStr URL of release information.
	* @return string Condensed release information.
	*/
	function cutReleaseInfo($releaseStr) {
		$marker = strrpos($releaseStr, '#');
		if($marker === FALSE) {
			return '';
		}
		return substr($releaseStr, $marker+1);
	}

	/**
	* Chops off URL portion of Musicbrainz identifiers, leaving just the ID
	*
	* This will convert Musicbrainz ID's from a long URL such as
	* http://mm.musicbrainz.org/mm-2.1/track/b3c53a6f-1796-4c59-864a-856e15892897
	* to b3c53a6f-1796-4c59-864a-856e15892897.
	* @param string $bigID URL of Musicbrainz ID.
	* @return string Musicbrainz ID.
	*/
	function bigIDtoShortID($bigID) {
		$marker = strrpos($bigID, '/');
		if($marker === FALSE) {
			return '';
		}
		return substr($bigID, $marker+1);
	}       



	// QUERY SECTION

	/**
	* Sends a file lookup query to musicbrainz.org.
	*
	* <p>Lookup metadata for one file. This function can be used by tagging applications
	* to attempt to match a given track with a track in the database. The server will
	* attempt to match an artist, album and track during three phases. If at any one
	* lookup phase the server finds ONE item only, it will move on to to the next phase.
	* If more than one item is
	* returned, the end-user will have to choose one from the returned list and then
	* make another call to the server. To express the choice made by a user, the client
	* should leave the artistName/albumName empty and provide the artistId and/or albumId
	* on the subsequent call. Once an artistId or albumId is provided the server
	* will pick up from the given Ids and attempt to resolve the next phase.</p>
	* <p>The available arguments to be search for are specified in an associative
	* array.  You may leave any in-applicable fields empty.</p>
	* $args['trmid'], TRM of track<br>
	* $args['artistName'], Name of artist<br>
	* $args['albumName'], Name of album<br>
	* $args['trackName'], Name of track<br>
	* $args['trackNum'], Track number<br>
	* $args['duration'], Duration of track<br>
	* $args['fileName'], Name of file<br>
	* $args['artistid'], Artist ID<br>
	* $args['albumid'], Album ID<br>
	* $args['trackid'], Track ID<br>
	* $args['maxItems'], Number of returned results, defaults to 25<br>
	*
	* @param array $args Arguments to search for.
	* @return array
	* @link http://projects.afterglo.ws/wiki/FileInfoQuery See example return values.
	*/
	function fileInfoQuery($args) {
		$trmid		= (isset($args['trmid']) AND !empty($args['trimid']))		? $this->xmlentities($args['trmid'])		: '__NULL__';
		$artistName	= (isset($args['artistName']) AND !empty($args['artistName']))	? $this->xmlentities($args['artistName'])	: '__NULL__';
		$albumName	= (isset($args['albumName']) AND !empty($args['albumName']))	? $this->xmlentities($args['albumName'])	: '__NULL__';
		$trackName	= (isset($args['trackName']) AND !empty($args['trackName']))	? $this->xmlentities($args['trackName'])	: '__NULL__';
		$trackNum	= (isset($args['trackNum']) AND !empty($args['trackNum']))	? $this->xmlentities($args['trackNum'])		: '__NULL__';
		$duration	= (isset($args['duration']) AND !empty($args['duration']))	? $this->xmlentities($args['duration'])		: '__NULL__';
		$fileName	= (isset($args['fileName']) AND !empty($args['fileName']))	? $this->xmlentities($args['fileName'])		: '__NULL__';
		$artistid	= (isset($args['artistid']) AND !empty($args['artistid']))	? $this->xmlentities($args['artistid'])		: '__NULL__';
		$albumid	= (isset($args['albumid']) AND !empty($args['albumid']))	? $this->xmlentities($args['albumid'])		: '__NULL__';
		$trackid	= (isset($args['trackid']) AND !empty($args['trackid']))	? $this->xmlentities($args['trackid'])		: '__NULL__';
		$maxItems	= (isset($args['maxItems']) AND !empty($args['maxItems']))	? $this->xmlentities($args['maxItems'])		: '25';

		$rawQuery = 	'<?xml version="1.0" encoding="'.$this->_encoding.'"?>' .
				'<rdf:RDF xmlns:rdf = "http://www.w3.org/1999/02/22-rdf-syntax-ns#"' .
				'         xmlns:dc  = "http://purl.org/dc/elements/1.1/"' .
				'         xmlns:mq  = "http://musicbrainz.org/mm/mq-1.1#"' .
				'         xmlns:mm  = "http://musicbrainz.org/mm/mm-2.1#">' .
				'<mq:FileInfoLookup>' .
				'   <mm:trmid>'.$trmid.'</mm:trmid>' .
				'   <mq:artistName>'.$artistName.'</mq:artistName>' .
				'   <mq:albumName>'.$albumName.'</mq:albumName>' .
				'   <mq:trackName>'.$trackName.'</mq:trackName>' .
				'   <mm:trackNum>'.$trackNum.'</mm:trackNum>' .
				'   <mm:duration>'.$duration.'</mm:duration>' .
				'   <mq:fileName>'.$fileName.'</mq:fileName>' .
				'   <mm:artistid>'.$artistid.'</mm:artistid>' .
				'   <mm:albumid>'.$albumid.'</mm:albumid>' .
				'   <mm:trackid>'.$trackid.'</mm:trackid>' .
				'   <mq:maxItems>'.$maxItems.'</mq:maxItems>' .
				'</mq:FileInfoLookup>' .
				'</rdf:RDF>';

		if(!$this->mbConnect()) {
			return FALSE;
		}
		$this->sendQuery($rawQuery, "POST /cgi%2dbin/mq%5f2%5f1.pl HTTP/1.1\r\n");
		$rawResponse = $this->getQueryResponse();
		list($header, $rawXml) = preg_split("/\r\n\r\n/", $rawResponse, 2);
		$this->mbDisconnect();

		$results = mbrainz_RDF_parse($rawXml);
		if($results === FALSE) {
			$this->_errorMsg = 'Error Parsing RDF';
			return FALSE;
		}

		if(count($results['Tracks'])) {
			return $results['Tracks'];
		} else if(count($results['Albums'])) {
			return $results['Albums'];
		} else if(count($results['Artists'])) {
			return $results['Artists'];
		}
	}

	/**
	* Sends a TRM lookup query.
	*
	* <p>Use this query to return the metadata information (artistname, albumname, trackname,
	* tracknumber) for a given trm id. Optionally, you can also specifiy the basic artist
	* metadata, so that if the server cannot match on the TRM id, it will attempt to match
	* based on the basic metadata. In case of a TRM collision (where one TRM may point to
	* more than one track) this function will return more than on track. The user (or
	* tagging app) must decide which track information is correct.</p>
	* <p>The auxiliary metadata is specified in an associative array.  You may leave any
	* in-applicable fields empty.</p>
	* $args['trmid'], TRM of track<br>
	* $args['artistName'], Name of artist<br>
	* $args['albumName'], Name of album<br>
	* $args['trackName'], Name of track<br>
	* $args['trackNum'], Track number<br>
	* $args['duration'], Duration of track<br>
	*
	* @param array $args Arguments to search for.
	* @return array
	* @link http://projects.afterglo.ws/wiki/GetTrackFromTRM See example return values.
	*/
	function getTrackFromTRM($args) {
		$trmid		= (isset($args['trmid']) AND !empty($args['trmid']))		? $this->xmlentities($args['trmid'])		: '__NULL__';
		$artistName	= (isset($args['artistName']) AND !empty($args['artistName']))	? $this->xmlentities($args['artistName'])	: '__NULL__';
		$albumName	= (isset($args['albumName']) AND !empty($args['albumName']))	? $this->xmlentities($args['albumName'])	: '__NULL__';
		$trackName	= (isset($args['trackName']) AND !empty($args['trackName']))	? $this->xmlentities($args['trackName'])	: '__NULL__';
		$trackNum	= (isset($args['trackNum']) AND !empty($args['trackNum']))	? $this->xmlentities($args['trackNum'])		: '__NULL__';
		$duration	= (isset($args['duration']) AND !empty($args['duration']))	? $this->xmlentities($args['duration'])		: '__NULL__';

		$rawQuery =	'<?xml version="1.0" encoding="'.$this->_encoding.'"?>' .
				'<rdf:RDF xmlns:rdf = "http://www.w3.org/1999/02/22-rdf-syntax-ns#"' .
				'         xmlns:dc  = "http://purl.org/dc/elements/1.1/"' .
				'         xmlns:mq  = "http://musicbrainz.org/mm/mq-1.1#"' .
				'         xmlns:mm  = "http://musicbrainz.org/mm/mm-2.1#">' .
				'<mq:TrackInfoFromTRMId>' .
				'   <mm:trmid>'.$trmid.'</mm:trmid>' .
				'   <mq:artistName>'.$artistName.'</mq:artistName>' .
				'   <mq:albumName>'.$albumName.'</mq:albumName>' .
				'   <mq:trackName>'.$trackName.'</mq:trackName>' .
				'   <mm:trackNum>'.$trackNum.'</mm:trackNum>' .
				'   <mm:duration>'.$duration.'</mm:duration>' .
				'</mq:TrackInfoFromTRMId>' .
				'</rdf:RDF>';

		if(!$this->mbConnect()) {
			return FALSE;
		}
		$this->sendQuery($rawQuery, "POST /cgi%2dbin/mq%5f2%5f1.pl HTTP/1.1\r\n");
		$rawResponse = $this->getQueryResponse();
		list($header, $rawXml) = preg_split("/\r\n\r\n/", $rawResponse, 2);
		$this->mbDisconnect();

		$results = mbrainz_RDF_parse($rawXml);
		if($results === FALSE) {
			$this->_errorMsg = 'Error Parsing RDF';
			return FALSE;
		}

		return $results['Tracks'];
	}

	/**
	* Sends a Quick Track Information query.
	*
	* Use this query to return the basic metadata information (artistname, albumname,
	* trackname, tracknumber) for a given track Musicbrainz id.
	*
	* @param string $trackID
	* @param string $albumID
	* @return array
	* @link http://projects.afterglo.ws/wiki/GetQuickTrackInfoFromID See example return values.
	*/
	function getQuickTrackInfoFromID($trackID, $albumID) {
		$trackID = $this->xmlentities($trackID);
		$albumID = $this->xmlentities($albumID);

		$rawQuery =	'<?xml version="1.0" encoding="'.$this->_encoding.'"?>' .
				'<rdf:RDF xmlns:rdf = "http://www.w3.org/1999/02/22-rdf-syntax-ns#"' .
				'         xmlns:dc  = "http://purl.org/dc/elements/1.1/"' .
				'         xmlns:mq  = "http://musicbrainz.org/mm/mq-1.1#"' .
				'         xmlns:mm  = "http://musicbrainz.org/mm/mm-2.1#">' .
				'<mq:QuickTrackInfoFromTrackId>' .
				'   <mm:trackid>'.$trackID.'</mm:trackid>' .
				'   <mm:albumid>'.$albumID.'</mm:albumid>' .
				'</mq:QuickTrackInfoFromTrackId>' .
				'</rdf:RDF>';

		if(!$this->mbConnect()) {
			return FALSE;
		}
		$this->sendQuery($rawQuery, "POST /cgi%2dbin/mq%5f2%5f1.pl HTTP/1.1\r\n");
		$rawResponse = $this->getQueryResponse();
		list($header, $rawXml) = preg_split("/\r\n\r\n/", $rawResponse, 2);
		$this->mbDisconnect();

		$results = mbrainz_RDF_parse($rawXml);
		if($results === FALSE) {
			$this->_errorMsg = 'Error Parsing RDF';
			return FALSE;
		}

		return $results['QuickTrackInfo'];
	}

	/**
	* Sends an Artist lookup query.
	*
	* Retrieve an artistList from a given Artist id.
	*
	* @param string $artistID
	* @return array
	* @link http://projects.afterglo.ws/wiki/GetArtistFromID See example return values.
	*/
	function getArtistFromID($artistID) {
		$artistID = rawurlencode($artistID);

		if(!$this->mbConnect()) {
			return FALSE;
		}
		$this->sendQuery('', "GET /mm%2d2.1/artist/$artistID/2 HTTP/1.1\r\n");
		$rawResponse = $this->getQueryResponse();
		list($header, $rawXml) = preg_split("/\r\n\r\n/", $rawResponse, 2);
		$this->mbDisconnect();

		$results = mbrainz_RDF_parse($rawXml);
		if($results === FALSE) {
			$this->_errorMsg = 'Error Parsing RDF';
			return FALSE;
		}

		return isset($results['Artists'][1]) ? $results['Artists'][1] : array();
	}

	/**
	* Sends an album lookup query.
	*
	* Retrieve an albumList from a given Album id.
	*
	* @param string $albumID
	* @return array
	* @link http://projects.afterglo.ws/wiki/GetAlbumFromID See example return values.
	*/
	function getAlbumFromID($albumID) {
		$albumID = rawurlencode($albumID);

		if(!$this->mbConnect()) {
			return FALSE;
		}
		$this->sendQuery('', "GET /mm-2.1/album/$albumID/4 HTTP/1.1\r\n");
		$rawResponse = $this->getQueryResponse();
		list($header, $rawXml) = preg_split("/\r\n\r\n/", $rawResponse, 2);
		$this->mbDisconnect();

		$results = mbrainz_RDF_parse($rawXml);
		if($results === FALSE) {
			$this->_errorMsg = 'Error Parsing RDF';
			return FALSE;
		}

		return isset($results['Albums'][1]) ? $results['Albums'][1] : array();
	}

	/**
	* Sends a track lookup query.
	*
	* Retrieve an trackList from a given Track id.
	*
	* @param string $trackID
	* @return array
	* @link http://projects.afterglo.ws/wiki/GetTrackFromID See example return values.
	*/
	function getTrackFromID($trackID) {
		$trackID = rawurlencode($trackID);

		if(!$this->mbConnect()) {
			return FALSE;
		}
		$this->sendQuery('', "GET /mm%2d2.1/track/$trackID/4 HTTP/1.1\r\n");
		$rawResponse = $this->getQueryResponse();
		list($header, $rawXml) = preg_split("/\r\n\r\n/", $rawResponse, 2);
		$this->mbDisconnect();

		$results = mbrainz_RDF_parse($rawXml);
		if($results === FALSE) {
			$this->_errorMsg = 'Error Parsing RDF';
			return FALSE;
		}

		return isset($results['Tracks'][1]) ? $results['Tracks'][1] : array();
	}

	/**
	* Sends an artist lookup query.
	*
	* Use this query to find artists by name. This function returns an
	* artistList for the given artist name.
	*
	* @param string $artistName
	* @param integer $maxItems Maximum number of items to return.
	* @return array
	* @link http://projects.afterglo.ws/wiki/GetArtistByName See example return values.
	*/
	function getArtistByName($artistName, $maxItems) {
		$artistName = $this->xmlentities($artistName);
		$maxItems = $this->xmlentities($maxItems);

		$rawQuery =	'<?xml version="1.0" encoding="'.$this->_encoding.'"?>' .
				'<rdf:RDF xmlns:rdf = "http://www.w3.org/1999/02/22-rdf-syntax-ns#"' .
				'         xmlns:dc  = "http://purl.org/dc/elements/1.1/"' .
				'         xmlns:mq  = "http://musicbrainz.org/mm/mq-1.1#"' .
				'         xmlns:mm  = "http://musicbrainz.org/mm/mm-2.1#">' .
				'<mq:FindArtist>' .
				'   <mq:depth>2</mq:depth>' .
				'   <mq:artistName>'.$artistName.'</mq:artistName>' .
				'   <mq:maxItems>'.$maxItems.'</mq:maxItems>' .
				'</mq:FindArtist>' .
				'</rdf:RDF>';

		if(!$this->mbConnect()) {
			return FALSE;
		}
		$this->sendQuery($rawQuery, "POST /cgi%2dbin/mq%5f2%5f1.pl HTTP/1.1\r\n");
		$rawResponse = $this->getQueryResponse();
		list($header, $rawXml) = preg_split("/\r\n\r\n/", $rawResponse, 2);
		$this->mbDisconnect();

		$results = mbrainz_RDF_parse($rawXml);
		if($results === FALSE) {
			$this->_errorMsg = 'Error Parsing RDF';
			return FALSE;
		}
		return $results['Artists'];
	}

	/**
	* Sends an album lookup query.
	*
	* Use this query to find albums by name. This function returns an
	* albumList for the given album name.
	*
	* @param string $albumName
	* @param integer $maxItems Maximum number of items to return.
	* @return array
	* @link http://projects.afterglo.ws/wiki/GetAlbumByName See example return values.
	*/
	function getAlbumByName($albumName, $maxItems) {
		$albumName = $this->xmlentities($albumName);
		$maxItems = $this->xmlentities($maxItems);

		$rawQuery = 	'<?xml version="1.0" encoding="'.$this->_encoding.'"?>' .
				'<rdf:RDF xmlns:rdf = "http://www.w3.org/1999/02/22-rdf-syntax-ns#"' .
				'         xmlns:dc  = "http://purl.org/dc/elements/1.1/"' .
				'         xmlns:mq  = "http://musicbrainz.org/mm/mq-1.1#"' .
				'         xmlns:mm  = "http://musicbrainz.org/mm/mm-2.1#">' .
				'<mq:FindAlbum>' .
				'   <mq:depth>4</mq:depth>' .
				'   <mq:maxItems>'.$maxItems.'</mq:maxItems>' .
				'   <mq:albumName>'.$albumName.'</mq:albumName>' .
				'</mq:FindAlbum>' .
				'</rdf:RDF>';

		if(!$this->mbConnect()) {
			return FALSE;
		}
		$this->sendQuery($rawQuery, "POST /cgi%2dbin/mq%5f2%5f1.pl HTTP/1.1\r\n");
		$rawResponse = $this->getQueryResponse();
		list($header, $rawXml) = preg_split("/\r\n\r\n/", $rawResponse, 2);
		$this->mbDisconnect();

		$results = mbrainz_RDF_parse($rawXml);
		if($results === FALSE) {
			$this->_errorMsg = 'Error Parsing RDF';
			return FALSE;
		}

		return $results['Albums'];
	}

	/**
	* Sends a track lookup query.
	*
	* Use this query to find tracks by name. This function returns a
	* trackList for the given track name.
	*
	* @param string $trackName
	* @param integer $maxItems Maximum number of items to return.
	* @return array
	* @link http://projects.afterglo.ws/wiki/GetTrackByName See example return values.
	*/
	function getTrackByName($trackName, $maxItems) {
		$trackName = $this->xmlentities($trackName);
		$maxItems = $this->xmlentities($maxItems);

		$rawQuery =	'<?xml version="1.0" encoding="'.$this->_encoding.'"?>' .
				'<rdf:RDF xmlns:rdf = "http://www.w3.org/1999/02/22-rdf-syntax-ns#"' .
				'         xmlns:dc  = "http://purl.org/dc/elements/1.1/"' .
				'         xmlns:mq  = "http://musicbrainz.org/mm/mq-1.1#"' .
				'         xmlns:mm  = "http://musicbrainz.org/mm/mm-2.1#">' .
				'<mq:FindTrack>' .
				'   <mq:depth>4</mq:depth>' .
				'   <mq:maxItems>'.$maxItems.'</mq:maxItems>' .
				'   <mq:trackName>'.$trackName.'</mq:trackName>' .
				'</mq:FindTrack>' .
				'</rdf:RDF>';

		if(!$this->mbConnect()) {
			return FALSE;
		}
		$this->sendQuery($rawQuery, "POST /cgi%2dbin/mq%5f2%5f1.pl HTTP/1.1\r\n");
		$rawResponse = $this->getQueryResponse();
		list($header, $rawXml) = preg_split("/\r\n\r\n/", $rawResponse, 2);
		$this->mbDisconnect();

		$results = mbrainz_RDF_parse($rawXml);
		if($results === FALSE) {
			$this->_errorMsg = 'Error Parsing RDF';
			return FALSE;
		}

		return $results['Tracks'];
	}

	/**
	* Return last error message.
	* @return string
	*/
	function getErrorMsg() {
		return $this->_errorMsg;
	}
}
?>
