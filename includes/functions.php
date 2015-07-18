<?
// size related
function mksize($bytes){
    if($bytes < 1000 * 1024){
        return number_format($bytes / (1024),2) . " kB";
    }
    elseif($bytes < 1000 * 1048576){
        return number_format($bytes / (1048576),2) . " MB";
    }
    elseif($bytes < 1000 * 1073741824){
        return number_format($bytes / (1073741824),2) . " GB";
    }
    else{
        return number_format($bytes / (1099511627776),2) . " TB";
    }
}

function get_size($path)
{
    if(!is_dir($path)) return filesize($path);
    if (($handle = opendir($path)) != FALSE) {
        $size = 0;
        while (false !== ($file = readdir($handle))) {
            if($file!='.' && $file!='..') {
                // function filesize has been deleted
                $size += get_size($path.'/'.$file);
            }
        }
        closedir($handle);
        return $size;
    }
}

// time related
function xtimer () {
    $a = explode (' ',microtime());
    return(double) $a[0] + $a[1];
}

function timeDiff($starttime, $endtime=false , $detailed=true, $short = true){
  if ($endtime == false) {
    $endtime = time();
  }
  if(!is_int($starttime)) $starttime = strtotime($starttime);
  if(!is_int($endtime)) $endtime = strtotime($endtime);

  $diff = ($starttime >= $endtime ? $starttime - $endtime : $endtime - $starttime);

  # Set the periods of time
  $periods = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
  $lengths = array(1, 60, 3600, 86400, 604800, 2630880, 31570560, 315705600);

  if($short){
    $periods = array("s", "m", "h", "d", "m", "y");
    $lengths = array(1, 60, 3600, 86400, 2630880, 31570560);
  }

  # Go from decades backwards to seconds
  $i = sizeof($lengths) - 1; # Size of the lengths / periods in case you change them
  $time = ""; # The string we will hold our times in
  while($i >= 0) {
    if($diff > @$lengths[$i-1]) { # if the difference is greater than the length we are checking... continue
      @$val = @floor(@$diff / @$lengths[@$i-1]);    # 65 / 60 = 1.  That means one minute.  130 / 60 = 2. Two minutes.. etc
      $time .= $val . ($short ? '' : ' ') . $periods[$i-1] . ((!$short && $val > 1) ? 's ' : ' ');  # The value, then the name associated, then add 's' if plural
      $diff -= ($val * $lengths[$i-1]);    # subtract the values we just used from the overall diff so we can find the rest of the information
      if(!$detailed) { $i = 0; }    # if detailed is turn off (default) only show the first set found, else show all information
    }
    $i--;
  }
  return trim($time);
}


function secDiff ($starttime, $endtime = false) {
	if ($endtime == false) {
		$endtime = time();
	}
	if(!is_int($starttime)) $starttime = strtotime($starttime);
	if(!is_int($endtime)) $endtime = strtotime($endtime);

	$diff = ($starttime >= $endtime ? $starttime - $endtime : $endtime - $starttime);

	return $diff;
}

// sql related
function sqlesc($x) {
	return "'".mysql_real_escape_string($x)."'";
}

// curl
function get_url_contents($url,$post=false,$ref=false,$timeout=10){
	global $urlconf;
	$crl = curl_init();
	$header[] = "Connection: keep-alive";
	$header[] = "keep-alive: $timeout";
	//$header[] = "Accept: text/html,application/xhtml+xml,application/xml, image/gif, image/x-bitmap, image/jpeg, image/pjpeg, image/jpg;q=0.9,*/*;q=0.8";
	$header[] = "Accept: */* ;q=0.9,*/*;q=0.8";
	$header[] = "Accept-Language: en-gb,en;q=0.5";
	$header[] = "Accept-Encoding: gzip,deflate";
	$header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
	$header[] = "Pragma: "; // browsers keep this blank.

	//if ($urlconf["usecookies"]) {
/*	$cookie = './cookies/cookie.txt';
	if (!file_exists("./cookies")) mkdir("./cookies");
	if (!file_exists($cookie)) die("i want cookies");

	curl_setopt($crl, CURLOPT_COOKIEFILE, $cookie);
	curl_setopt($crl, CURLOPT_COOKIEJAR, $cookie);
	*/
	
	curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
	curl_setopt($crl, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($crl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-GB; rv:1.9.2.3) Gecko/20100401 Firefox/3.6.3');
	//curl_setopt($crl, CURLOPT_HTTPHEADER, $header);
	curl_setopt($crl, CURLOPT_ENCODING, 'gzip,deflate');
	curl_setopt($crl, CURLOPT_URL,$url);
	curl_setopt($crl, CURLOPT_VERBOSE, false);
	curl_setopt($crl, CURLOPT_HEADER, false);
	curl_setopt($crl, CURLOPT_ENCODING, "");
	curl_setopt($crl, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($crl, CURLOPT_SSL_VERIFYPEER, false);

	if ($ref != false)
	curl_setopt($crl, CURLOPT_REFERER, $ref);


	curl_setopt($crl, CURLOPT_AUTOREFERER, 1);

	if ($post != false) {
		$postvars = $post;
		curl_setopt($crl, CURLOPT_POSTFIELDS, $postvars);
	}


	$ret["html"] = curl_exec($crl);
	$ret["url"] = curl_getinfo($crl, CURLINFO_EFFECTIVE_URL);
	$ret["speed"] = curl_getinfo($crl, CURLINFO_SPEED_DOWNLOAD);
	$ret["size"] = curl_getinfo($crl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
	$ret["type"] = curl_getinfo($crl, CURLINFO_CONTENT_TYPE);

	echo "\r\n - REQUEST - ". curl_getinfo($crl, CURLINFO_HEADER_OUT)."\r\n";

	$ret["redirtime"] = number_format(curl_getinfo($crl, CURLINFO_REDIRECT_TIME), 2);
	$ret["dnslookup"] = number_format(curl_getinfo($crl, CURLINFO_NAMELOOKUP_TIME), 2);
	$ret["connection"] = number_format(curl_getinfo($crl, CURLINFO_CONNECT_TIME), 2);

	curl_close($crl);
	return $ret;
}
?>