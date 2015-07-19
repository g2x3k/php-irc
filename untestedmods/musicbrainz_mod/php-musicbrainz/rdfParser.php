<?php
/**
* Musicbrainz RDF parser functions.
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
* Require the rdf parser class
*/
require_once('class_rdf_parser.php');

/**
* Sends the RDF response to the RDF parser object.
* @access private
*/
function mbrainz_RDF_parse($rawRdf) {
	$parser = new Rdf_parser();
	$parser->rdf_parser_create(NULL);
	$parser->rdf_set_statement_handler('mbrainz_RDF_handler');
	$parser->rdf_set_base('');
	$parser->rdf_set_user_data($results);
	$status = $parser->rdf_parse($rawRdf, strlen($rawRdf), TRUE);
	$parser->rdf_parser_free();

	if($status) {
		return mbrainz_build_result_array($results);
	} else {
		return FALSE;
	}
}

/**
* This merges all the data from the RDF array into a result that is easy to digest.
* @access private
*/
function mbrainz_build_result_array($rawArray) {
	$cleanArray = array('Artists' => array(), 'Albums' => array(), 'Tracks' => array(), 'QuickTrackInfo' => array());
	$artists = 1;
	$albums = 1;
	$tracks = 1;

	//have to use (have to = much easier to) multiple loops to merge information, still ~O(N) so not too bad really

	foreach($rawArray as $subject => $data) {
		if($data['type'] == 'http://musicbrainz.org/mm/mq-1.1#ArtistResult') {
			$rawArray[$data['artist']]['relevance'] = $data['relevance'];
		}
		if($data['type'] == 'http://musicbrainz.org/mm/mq-1.1#AlbumResult') {
			$rawArray[$data['album']]['relevance'] = $data['relevance'];
		}
		if($data['type'] == 'http://musicbrainz.org/mm/mq-1.1#AlbumTrackResult') {
			$rawArray[$data['track']]['relevance'] = $data['relevance'];
			$rawArray[$data['track']]['albumid'] = $data['album'];
		}
	}

	foreach($rawArray as $subject => $data) {
		if($data['type'] == 'http://musicbrainz.org/mm/mm-2.1#Artist') {
			$cleanArray['Artists'][$artists] = $data;
			$cleanArray['Artists'][$artists]['artistid'] = $subject;
			$artists++;
		}
	}

	foreach($rawArray as $subject => $data) {
		if($data['type'] == 'http://musicbrainz.org/mm/mm-2.1#Album') {
			$cleanArray['Albums'][$albums] = $data;
			$cleanArray['Albums'][$albums]['albumid'] = $subject;

			if(isset($data['creator'])) {
				if($data['creator'] == 'http://musicbrainz.org/artist/'.VARIOUS_ARTISTS) {
					$cleanArray['Albums'][$albums]['creator'] = array();
					$cleanArray['Albums'][$albums]['creator']['type'] = 'http://musicbrainz.org/mm/mm-2.1#Artist';
					$cleanArray['Albums'][$albums]['creator']['title'] = 'Various Artists';
					$cleanArray['Albums'][$albums]['creator']['sortName'] = 'Various Artists';
					$cleanArray['Albums'][$albums]['creator']['artistid'] = VARIOUS_ARTISTS;
				} else {
					foreach($cleanArray['Artists'] as $artistTemp) {
						if($data['creator'] == $artistTemp['artistid']) {
							$cleanArray['Albums'][$albums]['creator'] = $artistTemp;
						}
					}
				}
			}
			if(isset($data['cdindexidList'])) {
				$cleanArray['Albums'][$albums]['cdindexidList'] = $rawArray[$data['cdindexidList']];
				unset($cleanArray['Albums'][$albums]['cdindexidList']['type']);
			}
			if(isset($data['Asin'])) {
				$cleanArray['Albums'][$albums]['coverArt'] = array();
				$cleanArray['Albums'][$albums]['coverArt']['large'] = 'http://images.amazon.com/images/P/'.$data['Asin'].'.01.LZZZZZZZ.jpg';
				$cleanArray['Albums'][$albums]['coverArt']['medium'] = 'http://images.amazon.com/images/P/'.$data['Asin'].'.01.MZZZZZZZ.jpg';
				$cleanArray['Albums'][$albums]['coverArt']['tiny'] = 'http://images.amazon.com/images/P/'.$data['Asin'].'.01.TZZZZZZZ.jpg';
			}
			if(isset($data['releaseDateList'])) {
				$cleanArray['Albums'][$albums]['releaseDateList'] = $rawArray[$data['releaseDateList']];
				unset($cleanArray['Albums'][$albums]['releaseDateList']['type']);
				foreach($cleanArray['Albums'][$albums]['releaseDateList'] as $index => $releasePointer) {
					$cleanArray['Albums'][$albums]['releaseDateList'][$index] = $rawArray[$releasePointer];
				}
			}
			if(isset($data['trackList'])) {
				$cleanArray['Albums'][$albums]['trackList'] = $rawArray[$data['trackList']];
				unset($cleanArray['Albums'][$albums]['trackList']['type']);
				$cleanArray['Albums'][$albums]['trackCount'] = count($cleanArray['Albums'][$albums]['trackList']);
			}
			$albums++;
		}
	}

	foreach($rawArray as $subject => $data) {
		if($data['type'] == 'http://musicbrainz.org/mm/mm-2.1#Track') {
			$cleanArray['Tracks'][$tracks] = $data;
			$cleanArray['Tracks'][$tracks]['trackid'] = $subject;

			if(isset($data['creator'])) {
				if($data['creator'] == 'http://musicbrainz.org/artist/'.VARIOUS_ARTISTS) {
					$cleanArray['Tracks'][$tracks]['creator'] = array();
					$cleanArray['Tracks'][$tracks]['creator']['type'] = 'http://musicbrainz.org/mm/mm-2.1#Artist';
					$cleanArray['Tracks'][$tracks]['creator']['title'] = 'Various Artists';
					$cleanArray['Tracks'][$tracks]['creator']['sortName'] = 'Various Artists';
					$cleanArray['Tracks'][$tracks]['creator']['artistid'] = VARIOUS_ARTISTS;
				} else {
					foreach($cleanArray['Artists'] as $artistTemp) {
						if($data['creator'] == $artistTemp['artistid']) {
							$cleanArray['Tracks'][$tracks]['creator'] = $artistTemp;
						}
					}
				}
			}
			if(isset($data['trmidList'])) {
				$cleanArray['Tracks'][$tracks]['trmidList'] = $rawArray[$data['trmidList']];
				unset($cleanArray['Tracks'][$tracks]['trmidList']['type']);
			}

			//loop through each album looking for current album and track
			foreach($cleanArray['Albums'] as $albumTemp) {
				if(isset($data['albumid']) AND $albumTemp['albumid'] == $data['albumid']) {
					for($p = 1; $p < count($albumTemp['trackList']); $p++) {
						if($albumTemp['trackList'][$p] == $subject) {
							$cleanArray['Tracks'][$tracks]['trackNum'] = $p;
						}
					}
					$cleanArray['Tracks'][$tracks]['album'] = $albumTemp;
				}
			}
			$tracks++;
		}
	}

	foreach($rawArray as $subject => $data) {
		if($data['type'] == 'http://musicbrainz.org/mm/mq-1.1#Result' AND isset($data['artistName'])) {
			$cleanArray['QuickTrackInfo'] = $data;
			unset($cleanArray['QuickTrackInfo']['status']);
			if(isset($data['releaseDateList'])) {
				$cleanArray['QuickTrackInfo']['releaseDateList'] = $rawArray[$data['releaseDateList']];
				unset($cleanArray['QuickTrackInfo']['releaseDateList']['type']);
				foreach($cleanArray['QuickTrackInfo']['releaseDateList'] as $index => $releasePointer) {
					$cleanArray['QuickTrackInfo']['releaseDateList'][$index] = $rawArray[$releasePointer];
				}
			}
		}
	}

	return $cleanArray;
}

/**
* Receives events from the RDF parser and builds an array of the raw data.
* @access private
*/
function mbrainz_RDF_handler(&$user_data, $subject_type, $subject, $predicate, $ordinal,
				$object_type, $object, $xml_lang) {

	if($subject_type == RDF_SUBJECT_TYPE_URI OR $subject_type == RDF_SUBJECT_TYPE_ANONYMOUS) {
		$arrayName = strrchr($predicate, '#');
		if($arrayName === FALSE) {
			$arrayName = strrchr($predicate, '/');
		}
		if($arrayName === FALSE) {
			return;
		}
		$arrayName = substr($arrayName, 1);
		if(substr($arrayName, 0, 1) == '_')
			$arrayName = substr($arrayName, 1);
		$user_data[$subject][$arrayName] = $object;
	}
}
?>
