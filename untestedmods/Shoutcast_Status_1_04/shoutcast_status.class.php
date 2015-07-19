<?PHP
/*******************************************************************************
  * PHP-Script:
  *
  * Retrieve Information from ShoutcastServers
  *
  * - Optional with Proxy- and 'user:pass@proxy:port'-Support
  * - Automatic handling of accidential connections to a stream instead to the HTML-Infopage!
  *   (Maybe happen, if you automatically retrieve URLs from Playlists and pass them to the class)
  * - can be used in web-environment or as commandline-script
  *
  * Basic-Class: hn_shoutcast
  * - hn_shoutcast($http_debug=FALSE,$socket_timeout=10)
  * - query_URL($url,$proxy=NULL) connect the $url (optional via proxy),
  *   retrieve all available Information and hold them internally in array $info:
  *   ('Server Status','Stream Status','Stream Title','Stream URL','Content Type','Bandwidth','Stream Genre','Current Song','Listeners','MaxListeners','Listener Peak','Average Listen Time','Stream AIM','Stream IRC','Stream ICQ')
  *
  * With ClassExtension: hn_shoutcastInfo
  * you have easy access to each single information:
  *   - is_online()
  *   - station()
  *   - url()
  *   - genre()
  *   - song()
  *   - bandwidth()
  *   - listeners()
  *   - maxlisteners()
  *   - listenerpeak()
  *   - content_type()
  *   - aim()
  *   - irc()
  *   - icq()
  *
  * For more Information, read the comments in class itself and
  * see/try the example files.
  *
  * If you don't have them, go to
  * - http://hn273.users.phpclasses.org/browse/author/45770.html
  * and select the desired classpage, or go directly to:
  * - http://hn273.users.phpclasses.org/browse/package/2049.html
  *
  ******************************************************************************
  *
  * @Author:    Horst Nogajski <horst@nogajski.de>
  * @Copyright: (c) 1999 - 2005
  * @Licence:   GNU GPL (http://www.opensource.org/licenses/gpl-license.html)
  * @Version:   1.0
  *
  * $Source: //BIKO/CVS_REPOSITORY/hn_php/hn_shoutcastinfo/hn_shoutcastinfo.class.php,v $
  * $Id: hn_shoutcastinfo.class.php,v 1.4 2005/01/05 23:44:25 horst Exp $
  *
  * Tabsize: 4
  *
  **/


// BASIC-CLASS
class hn_shoutcast
{

	// PARAMS for PUBLIC READ/WRITE-ACCESS
		var $http_debug;                    // BOOLEAN: switch debugging ON/OFF
		var $socket_timeout;                // INTEGER: seconds until SocketTimeout

	// PARAMS for PUBLIC READ-ACCESS
		var $online;                        // BOOLEAN: is TRUE if the Server is streaming
		var $info;                          // ARRAY:   holds all available Response-Informations

	// PARAMS for PRIVATE USAGE
		var $useragent;                     // STRING:  best it should be a known Webbrowser-UserAgentString
		var $statuscode;                    // INTEGER: HTTP-Response-Code
		var $redirections;                  // INTEGER: count redirections (Status-Codes 3xx)
		var $NotAvailable   = 'N/A';        // STRING:  is displayed instead of infos witch couldn't retrieved from Shoutcastserver
		var $is_stream_msg  = '<-- NO HTML-PAGE, MAYBE A STREAM -->'; // STRING: is stored in HTTP['body'], when accidentially a stream-URL was queried instead of the HTML-page


	// CONSTRUCTOR
		function hn_shoutcast($http_debug=FALSE,$socket_timeout=10)
		{
			$this->http_debug = $http_debug;
			$this->socket_timeout = $socket_timeout;
			$this->useragent = 'Mozilla/5.0 (Windows; U; Windows NT 5.0; rv:1.7.3) Gecko/20041001 Firefox/0.10.1';
		}



	// PUBLIC METHODS

		function query_URL($url,$proxy=NULL)
		{
			// RESET
			$this->online = FALSE;
			$this->statuscode = 0;
			$this->redirections = 0;
			$this->info = array();

			// QUERY
			$response = $this->_HTTP_REQUEST($url,$proxy);
			if($this->statuscode != 200)
			{
				$this->debug("\n- Query has failed! Status-Code: {$this->statuscode}");
				return FALSE;
			}

			// PARSE RESPONSE
			$this->_parse_ShoutcastInfo($response['body']);
		}



	// PRIVATE METHODS

		function _HTTP_REQUEST($query_url,$proxy=NULL,$default_PORT=80,$fullPath=TRUE,$try=0,$ExplicitlYUseDefaultport=FALSE)

		{


			// PARAMS
			$method   = 'GET';
			$HTTP     = '1.0';
			$response = '';
			$try++;

			// PARSE URL
			$url = trim($query_url);
			if(!preg_match("=://=", $url))
			{
				$url = "http://$url";
			}
			$url = parse_url($url);
			if(strtolower($url["scheme"]) != "http")
			{
				return FALSE;
			}
			if(!isset($url['port']) || $ExplicitlYUseDefaultport)
			{
				$url['port'] = $default_PORT;
			}
			if(!isset($url['path']))
			{
				$url['path'] = "/";
			}
			// BUILD PATH
			if($fullPath)
			{
				if(empty($proxy))
				{
					$path		= $url['path'];
				}
				else
				{
					$proxy_url	= parse_url($proxy);
					$path		= 'http://'.$url['host'].':'.$url['port'].$url['path'];
				}
				if(isset($url['query']))
				{
					$path .= '?'.$url['query'];
				}
				if(isset($url['fragment']))
				{
					$path .= '#'.$url['fragment'];
				}
			}
			else
			{
				if(empty($proxy))
				{
					$path 		= '/';
				}
				else
				{
					$proxy_url	= parse_url($proxy);
					$path		= 'http://'.$url['host'].':'.$url['port'].'/';
				}
			}


			// CONNECT
			$errstr = '';
			$errno = 0;
			if(empty($proxy))
			{
				$this->debug("\n- open Sock to Host: ".$url["host"]." Port: ".$url["port"]);
				$fp = @fsockopen($url["host"], $url["port"], $errno, $errstr, $this->socket_timeout);
			}
			else
			{
				$this->debug("\n- open Sock to Proxy: ".$proxy_url["host"]." Port: ".$proxy_url["port"]);
				$fp = @fsockopen($proxy_url['host'], $proxy_url['port'], $errno, $errstr, $this->socket_timeout);
			}

			// Check Connection-Success
			if($fp===FALSE)
			{
				$this->debug("\n- ERROR: connecting has failed!\n  REASON: ($errno) $errstr\n");
				return FALSE;
			}


			// BUILD HEADERS
			$headers = array();
			$headers[] = "$method $path HTTP/$HTTP";
			$headers[] = "Host: {$url['host']}";
			if(!empty($proxy))
			{
				if($proxy_url['user'] != '' && $proxy_url['pass'] != '')
				{
					$headers[] = 'Proxy-Authorization: Basic '.base64_encode($proxy_url['user'].':'.$proxy_url['pass']);
				}
				$headers[] = "Proxy-Connection: keep-alive";
			}
			if(isset($url['user']) && isset($url['pass']))
			{
				$headers[] = 'Authorization: Basic '.base64_encode($url['user'].':'.$url['pass']);
			}
			$headers[] = "Keep-Alive: 300";
			$headers[] = "User-Agent: {$this->useragent}";
			$headers[] = "Accept: text/html;q=0.9,text/plain;q=0.8,";
			$headers[] = "Accept-Language: de,en;q=0.5";
			$headers[] = "Accept-Charset: ISO-8859-1;q=0.9,Windows-1252;q=0.3,";
			//$headers[] = "Accept-Encoding: gzip,deflate";
			$headers[] = "Connection: keep-alive";

			$request = join("\r\n", $headers)."\r\n\r\n";


			// SEND REQUEST
			$this->debug("\n- QUERY: ".(empty($proxy) ? "http://{$url['host']}:{$url['port']}$path\n" : "$path\n"));
			$this->debug("\n- send Requestheaders:\n$request");
			fwrite($fp, $request);


			// GET RESPONSE
			$responseheaders = '';
			$file = '';
			$this->debug("\n- retrieve Responseheaders:\n");
			while($line = fgets($fp, 1024))
			{
				if(preg_match('/^\s*[\r\n|\r|\n]$/', $line))
				{
					// Have an empty line! All Headers retrieved now! Body begins with next line.
					break;
				}
				$responseheaders .= $line;
			}
			$this->debug($responseheaders);


			// PARSE REPONSEHEADERS
			$http = $this->_parse_ResponseHeader($responseheaders);


			// CHECK STATUSCODE, RETURN RESPONSE OR DO A RECURSIVECALL
			$this->statuscode = $http["Status-Code"];
			if($http["Status-Code"][0] == 2)
			{
				// A (YET UNKNOWN) RESOURCE IS AVAILABLE, START TO RETRIEVE IT
				$buffer = '';
				while(!feof($fp))
				{
					$buffer = fgets($fp,1024);
					$file .= $buffer;

					// validate that we have a HTML-page and not a media-stream:
					if((!stristr($buffer,'ICY 404')===FALSE) || (trim($buffer)=='ICY 200 OK') || (isset($http['content-type']) && $http['content-type']!='text/html'))
					{
						// LITTLE ACCIDENT: LOOKS LIKE WE HAVE A STREAM-HEADER IN FIRST TRY
						switch($try)
						{
							case 1:
							// we do a second try with the URL cutted down to root-path
							fclose($fp);
							$this->debug("\n- OOOps! Looks like we are connected to a media-stream\n  Try once more\n");
							return $this->_HTTP_REQUEST($query_url,$proxy,$default_PORT,FALSE,$try);
							break;

							case 2:
							// we do a third try with the URL cutted down to root-path AND explicitly using the ShoutCast-default-port
							fclose($fp);
							$this->debug("\n- OOOps! Looks like we are connected to a media-stream\n  Last try now!\n");
							return $this->_HTTP_REQUEST($query_url,$proxy,$default_PORT,FALSE,$try,TRUE);
							break;

							default:
							// BAD: HAVE NO VALID HTML-PAGE ALSO IN THIRD TRY
							fclose($fp);
							return array('header'=>$http, 'body'=>$this->is_stream_msg);
						}
					}
				}
				fclose($fp);
				return array('header'=>$http, 'body'=>$file);
			}
			elseif($http["Status-Code"][0] == 3)
			{
				// RESOURCE HAS MOVED! RECURSIVE CALL WITH NEW LOCATION
				if(++$this->redirections >= 5)
				{
					// MAXIMUM REDIRECTIONS REACHED! GIVE UP
					return array('header'=>$http, 'body'=>FALSE);
				}
				$location = '';
				$location = isset($http["Location"]) ? $http["Location"] : $location;
				$location = isset($http["location"]) ? $http["location"] : $location;
				return $this->_HTTP_REQUEST($location,$proxy,$default_PORT);
			}
			else
			{
				// RESOURCE IS NOT AVAILABLE!
				return array('header'=>$http, 'body'=>FALSE);
			}
		}


		function _parse_ResponseHeader($headers)
		{
			$matches = array();
			$http = array();
			preg_match("=^(HTTP/\d+\.\d+) (\d{3}) ([^\r\n]*)=", $headers, $matches);
			if(isset($matches[0])) $http["Status-Line"] = $matches[0];
			if(isset($matches[1])) $http["HTTP-Version"] = $matches[1];
			if(isset($matches[2])) $http["Status-Code"] = $matches[2];
			if(isset($matches[3])) $http["Reason-Phrase"] = $matches[3];

			$rclass = array("Informational", "Success", "Redirection", "Client Error", "Server Error");
			$http["Response-Class"] = $rclass[$http["Status-Code"][0] - 1];

			preg_match_all("=^(.+): ([^\r\n]*)=m", $headers, $matches, PREG_SET_ORDER);
			foreach($matches as $line) $http[$line[1]] = $line[2];

			return $http;
		}


		function _parse_ShoutcastInfo($body)
		{
			// VALIDATION
			if($body == $this->is_stream_msg || $body == FALSE)
			{
				$this->debug("\n- No Infos available:\n  $body");
				return FALSE;
			}
			if(!stristr($body,'ICY 404')===FALSE)
			{
				$this->debug("\n- No valid Infos available:\n<pre>\n".strip_tags($body)."</pre>\n");
				return FALSE;
			}
			$this->debug("\n- SUCCESS! We got a HTML-Page. Now parse it.");

			// Prepare string
			$body = str_replace(array('&nbsp;','</tr>','</TR>'),array(' ','</tr>'.chr(9),'</TR>'.chr(9)),$body);
			$body = strip_tags($body);
			// separate table with infos
			$body = preg_replace('/^.*?Current Stream Information/ism','',$body);
			$body = preg_replace('/Written by.*$/ism','',$body);
			// read all available infos into infoArray
			$lines = split(chr(9),$body);
			foreach($lines as $line)
			{
				if(trim($line)=='') continue;
				$temp = split(":",$line,2);
				$this->info[$temp[0]] = trim($temp[1]);
			}
			// strip down listener infos
			if(isset($this->info['Stream Status']) && strstr($this->info['Stream Status'],'Stream is up'))
			{
				$this->online = TRUE;
				$pattern = '/Stream is up at (.*?) with (.*?) of (.*?) listeners.*/i';
				$matches = array();
				preg_match($pattern,$this->info['Stream Status'],$matches);
				$this->info['Bandwidth'] = isset($matches[1]) ? $matches[1] : $this->NotAvailable;
				$this->info['Listeners'] = isset($matches[2]) ? $matches[2] : $this->NotAvailable;
				$this->info['MaxListeners'] = isset($matches[3]) ? $matches[3] : $this->NotAvailable;
			}
			// validate against needed infos
			$names = array('Server Status','Stream Status','Stream Title','Stream URL','Content Type','Bandwidth','Stream Genre','Current Song','Listeners','MaxListeners','Listener Peak','Average Listen Time','Stream AIM','Stream IRC','Stream ICQ');
			foreach($names as $v)
			{
				if(!isset($this->info[$v])) $this->info[$v] = $this->NotAvailable;
			}

			// debugging
			if($this->http_debug)
			{
				if(isset($_SERVER['HTTP_HOST'])) echo '<pre>';
				echo "\n- InfoArray now contains:\n";
				foreach($this->info as $k=>$v)
				{
					echo "\t$k => $v\n";
				}
				if(isset($_SERVER['HTTP_HOST'])) echo '</pre>';
			}
			return TRUE;
		}


		function debug($msg)
		{
			if(!$this->http_debug) return;
			if(isset($_SERVER['HTTP_HOST'])) echo '<pre>';
			echo $msg;
			if(isset($_SERVER['HTTP_HOST'])) echo '</pre>';
		}

} // END CLASS hn_shoutcast



// EXTENSION FOR EASY ACCESS TO EACH RETRIEVED SINGLE INFORMATION
class hn_ShoutcastInfo extends hn_Shoutcast
{

	// CONSTRUCTOR

		function hn_shoutcastInfo($http_debug=FALSE,$socket_timeout=10)
		{
			$this->hn_shoutcast($http_debug,$socket_timeout);
		}


	// PUBLIC METHODS

		function is_online()
		{
			return $this->online;
		}

		function station()
		{
			return isset($this->info['Stream Title']) ? $this->info['Stream Title'] : $this->NotAvailable;
		}

		function url()
		{
			return isset($this->info['Stream URL']) ? $this->info['Stream URL'] : $this->NotAvailable;
		}

		function genre()
		{
			return isset($this->info['Stream Genre']) ? $this->info['Stream Genre'] : $this->NotAvailable;
		}

		function song()
		{
			return isset($this->info['Current Song']) ? $this->info['Current Song'] : $this->NotAvailable;
		}

		function bandwidth()
		{
			return isset($this->info['Bandwidth']) ? $this->info['Bandwidth'] : $this->NotAvailable;
		}

		function listeners()
		{
			return isset($this->info['Listeners']) ? $this->info['Listeners'] : $this->NotAvailable;
		}

		function maxlisteners()
		{
			return isset($this->info['MaxListeners']) ? $this->info['MaxListeners'] : $this->NotAvailable;
		}

		function listenerpeak()
		{
			return isset($this->info['Listener Peak']) ? $this->info['Listener Peak'] : $this->NotAvailable;
		}

		function content_type()
		{
			return isset($this->info['Content Type']) ? $this->info['Content Type'] : $this->NotAvailable;
		}

		function aim()
		{
			return isset($this->info['Stream AIM']) ? $this->info['Stream AIM'] : $this->NotAvailable;
		}

		function irc()
		{
			return isset($this->info['Stream IRC']) ? $this->info['Stream IRC'] : $this->NotAvailable;
		}

		function icq()
		{
			return isset($this->info['Stream ICQ']) ? $this->info['Stream ICQ'] : $this->NotAvailable;
		}


} // END CLASS-EXTENSION hn_shoutcastInfo
?>