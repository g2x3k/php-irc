<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC PHP Function Reference
|   =============================-==========================
|   by Idle0ne
|   (c) 2006 by http://www.josephcrawford.com/
|   Contact: info@codebowl.com
|   irc: #manekian@irc.rizon.net
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

// TODO array_push returns &#38; need to fix that.

// TODO: added the ability to tell someone else the funciton ifnormation, caches the word tell instead of the actual function.

include_once('modules/iphp/iphp_config.php');
class iphp_mod extends module
{
    public $title = "iPHP";
    public $author = "Idle0ne";
    public $version = "1.0b";

    private $cache = array();
    private $query = null;
    private $useDb = FALSE;
    private $dbTable = '';
    private $useCache = TRUE;

    public function init()
    {
        // Add your timer declarations and whatever
        // else here...
        $this->useDb = IPHP_USE_DB;
        $this->dbTable = IPHP_DB_TABLE;
        $this->useCache = IPHP_USE_CACHE;
    }
	
    public function destroy()
    {
        // Put code here to destroy the timers that you created in init()
        // and whatever else cleanup code you want.
    }

    public function process_fetch( $line, $args )
    {
        if($args['nargs'] > 0)
        {
            if($args['nargs'] == 4)
            {
                $line['toWho'] = $args['arg2'];
                $line['toOther'] = TRUE;
                $this->query = strtolower(strip_tags($args['arg4']));
                if($this->query == "") $args['nargs'] = 0; 
            }
            elseif( $args['nargs'] == 1 )
            {
                $line['toWho'] = $line['fromNick'];
                $line['toOther'] = FALSE;
                $this->query = strtolower(strip_tags($args['arg1']));
                if($this->query == "") $args['nargs'] = 0;                
            }
            else 
            {
                $args['nargs'] = 0;
            }
        }

        if ($args['nargs'] <= 0)
        {
            $this->ircClass->notice($line['fromNick'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - Usage: !iphp <function>" . DARK);
            return;
        }

        if($line['toOther'] === TRUE ) $this->ircClass->notice($line['fromNick'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - telling ".$line['toWho']." about ".$this->query . DARK);
        else $this->ircClass->notice($line['fromNick'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - Processing, please wait..." . DARK);

        if($this->useDb === TRUE)
        {
            $this->fetch_function_db( $line, $args );
        }
        else
        {
            $this->fetch_function( $line, $args );
        }
    }

    private function fetch_function( $line, $args )
    {
        if (($this->useCache === TRUE) && (isset($this->cache[$this->query]) && is_array($this->cache[$this->query])))
        {
            // increment the hits counter
            $this->cache[$this->query]['hits'] += 1;
            
            if(isset($this->cache[$this->query]['function']))
            {
                if($line['toOther'] === TRUE ) $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - ".$line['fromNick']." wants you to know about ".$this->query . DARK);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - php.net response for ".$this->query . DARK);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - ".$this->cache[$this->query]['function']." Function Reference" . DARK);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - ".$this->cache[$this->query]['url'] . DARK);
            }
            elseif(isset($this->cache[$this->query]['matches']))
            {
                $this->ircClass->notice($line['fromNick'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - Could not find a perfect match for ".$this->query.", maybe one of the following (".$this->cache[$this->query]['count'].") suggestions: " . implode(', ', $this->cache[$this->query]['matches']) . "." . DARK);
            }
            else
            {
                // use the cached version
                if($line['toOther'] === TRUE ) $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - ".$line['fromNick']." wants you to know about ".$this->query . DARK);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - php.net response for ".$this->query . DARK);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - "  . $this->cache[$this->query]['function'] . ' -- ' . $this->cache[$this->query]['description']);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - " . $this->cache[$this->query]['versions']);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - " . $this->cache[$this->query]['defenition']);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - " . $this->cache[$this->query]['url']);
            }
        }
        else
        {
            $search = socket::generateGetQuery($this->query, "www.php.net", "/$this->query", "1.0");
            $this->ircClass->addQuery("www.php.net", 80, $search, $line, $this, "function_response");
        }
    }

    public function fetch_function_db( $line, $args )
    {
        $sql = "
            SELECT 
                id, query, fromWho, toWho, mask, channel, function, library, versions, defenition, description, url, matches, timestamp
            FROM
                ".$this->dbTable." 
            WHERE 
                query='".$this->query."'
        ";
        echo $sql;

        $cacheResult = $this->db->query($sql);

        if($this->db->numRows($cacheResult) > 0) {
            $cache = $this->db->fetchArray($cacheResult);
            
            // update hits
            $sql = "UPDATE ".$this->dbTable." SET hits = hits+1 WHERE id = ".$cache['id'];
            $this->db->query($sql);
        }

        if (($this->useCache === TRUE) && (isset($cache['query']) && $cache['query'] != ""))
        {
            if(isset($cache['library']) && $cache['library'] != "")
            {
                if($line['toOther'] === TRUE ) $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - ".$line['fromNick']." wants you to know about ".$this->query . DARK);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - php.net response for ".$this->query . DARK);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - ".$cache['library']." Function Reference" . DARK);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - ".$cache['url'] . DARK);
            }
            elseif(isset($cache['matches']) && $cache['matches'] != "")
            {
                $cache['matches'] = unserialize($cache['matches']);
                $cache['matchCount'] = count($cache['matches']);
                $message = DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - Could not find a perfect match for ".$this->query.".";
                if($cache['matchCount'] > 0) $message .= " Maybe one of the following (".$cache['matchCount'].") suggestions: " . implode(', ', $cache['matches']) . "." . DARK;
                else $message .= " I am unable to come up with any close matches, sorry." . DARK;
                
                $this->ircClass->notice($line['fromNick'], $message);
            }
            else
            {
                // use the cached version
                if($line['toOther'] === TRUE ) $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - ".$line['fromNick']." wants you to know about ".$this->query . DARK);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - php.net response for ".$this->query . DARK);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - "  . $cache['function'] . ' -- ' . $cache['description']);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - " . $cache['versions']);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - " . $cache['defenition']);
                $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - " . $cache['url']);
            }
        }
        else
        {
            $search = socket::generateGetQuery($this->query, "www.php.net", "/$this->query", "1.0");
            $this->ircClass->addQuery("www.php.net", 80, $search, $line, $this, "function_response");
        }
    }

    public function function_response($line, $args, $result, $site)
    {
        if ($result == QUERY_ERROR)
        {
            $this->ircClass->notice($line['fromNick'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - Error: " . $site);
            return;
        }

        $location = $this->checkLocation($site);
        if( count( $location ) >= 3 )
        {
            $domain = $location['scheme'] . '://' . $location['host'] . $location['path'];
            if(isset($location['query'])) {
                $domain .=  '?' . $location['query'];
                $query = $location['query'];
            }
            else $query = "";

            $search = socket::generateGetQuery($query, $location['host'], $location['path'], "1.0");
            $this->ircClass->addQuery($location['host'], 80, $search, $line, $this, "function_response");
        }
        else
        {
            //$site = html_entity_decode($site);
            $site = str_replace("\n", "", $site);
            $site = str_replace("\r", "", $site);

            preg_match_all('/<base href="(.*?)" \/>/is', $site, $matches);
            
            // TODO: Fix the page url, seems to contain HTML ONLY SEEMS TO HAPPEN WHEN YOU CALL A FUNCTION THAT RETURNS A SEARCH
            $page_url = isset($matches[1][0]) ? $this->parse_php_url($matches[1][0]) : null;
            $response = array(
            'query' => $this->query,
            'fromWho' => $line['fromNick'],
            'toWho' => '',
            'mask' => $line['fromHost'],
            'channel' => $line['to'],
            'function' => '',
            'versions' => '',
            'defenition' => '',
            'description' => '',
            'url' => $page_url,
            'matches' => '',
            'library' => '',
            'hits' => 1,
            'timestamp' => time()
            );
            
            if(isset($line['toWho'])) $response['toWho'] = $line['toWho'];
            
            if( preg_match('/<li class="header up"><a href="funcref.php">Function Reference<\/a><\/li>/is', $site))
            {
                // found a function reference page
                preg_match_all('/<li class="active"><a href="(.*?)">(.*?)<\/a><\/li>/is', $site, $matches, PREG_PATTERN_ORDER);

                $response['library'] = $matches[2][0];

                $this->doCacheCheck($response);

                if($line['toOther'] === TRUE ) $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - ".$line['fromNick']." wants you to know about ".$this->query . DARK);
                $this->ircClass->notice($response['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - php.net response for ".$this->query . DARK);
                $this->ircClass->notice($response['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - ".$response['library']." Function Reference" . DARK);
                $this->ircClass->notice($response['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - " . $response['url'] . DARK);
            }
            elseif( preg_match('/Sorry, but the function <b>'.$this->query.'<\/b> is not in the online manual/is', $site))
            {
                // parse out the closest matches if there are any
                preg_match_all('/<a href="\/manual\/en\/function.(.*?)"><b>(.*?)<\/b><\/a><br \/>/is', $site, $matches);

                if(isset($matches[2]) && $matches[2] != "")
                {
                    $response['matches'] = serialize($matches[2]);
                    $response['matchCount'] = count($matches[2]);
                }
                else
                {
                    $response['matches'] = serialize(array());
                    $response['matchCount'] = 0;
                }

                $this->doCacheCheck($response);

                $message = DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - Could not find a perfect match for ".$this->query.".";
                if($response['matchCount'] > 0) $message .= " Maybe one of the following (".$response['matchCount'].") suggestions: " . implode(', ', unserialize($response['matches'])) . "." . DARK;
                else $message .= " I am unable to come up with any close matches, sorry." . DARK;
                
                $this->ircClass->notice($line['toWho'], $message);
            }
            else
            {

                // Grab the rest of the info from the page
                preg_match_all("/<\/A><P>(.*?)\((.*?)\)<\/P>(.*?)--(.*?)<\/DIV><DIVCLASS=\"(.*?)\"><ANAME=\"(.*?)\"><\/A><H2>Description<\/H2>(.*?)<BR>/is", $site, $matches, PREG_PATTERN_ORDER);

                $response['versions'] = isset($matches[2][0]) ? html_entity_decode($matches[2][0]) : null;
                $response['function'] = isset($matches[3][0]) ? trim(str_replace('&nbsp;', '', strip_tags($matches[3][0]))) : null;
                $response['description'] = isset($matches[4][0]) ? trim(str_replace('&nbsp;', '', strip_tags($matches[4][0]))) : null;
                $response['defenition'] = isset($matches[7][0]) ? trim(str_replace('&nbsp;', '', strip_tags($matches[7][0]))) : null;
                //$response['url'] = $this->parse_php_url($page_url);

                $this->doCacheCheck( $response );

                if($line['toOther'] === TRUE ) $this->ircClass->notice($line['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - ".$line['fromNick']." wants you to know about ".$this->query . DARK);
                $this->ircClass->notice($response['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - php.net response for ".$this->query . DARK);
                $this->ircClass->notice($response['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - "  . $response['function'] . ' -- ' . $response['description']);
                $this->ircClass->notice($response['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - " . $response['versions']);
                $this->ircClass->notice($response['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - " . $response['defenition']);
                $this->ircClass->notice($response['toWho'], DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - " . $response['url']);
            }
        }
    }

    private function parse_php_url($url)
    {
        $url = preg_replace('/http:\/\/([a-z])+[0-9]/', 'http://www', $url);
        $url = str_replace('/manual/en/function.'.str_replace('_', '-', $this->query).'.php', '/'.$this->query, $url);
        $url = str_replace('/manual/en/ref.'.str_replace('_', '-', $this->query).'.php', '/'.$this->query, $url);
        return $url;
    }
    private function doCacheCheck( $response )
    {
        if( $this->useDb === TRUE && $this->useCache === TRUE ) $this->cache_response_db( $response );
        elseif( $this->useDb === FALSE && $this->useCache === TRUE ) $this->cache_response( $response );
    }

    private function cache_response_db( $response )
    {
        foreach($response as $key => $val) $response[$key] = mysql_real_escape_string($val);

        $sql = "
            INSERT INTO ".$this->dbTable." (query, fromWho, toWho, mask, channel, function, library, versions, defenition, description, url, matches, hits, timestamp) 
            VALUES ('".$this->query."', '".$response['fromWho']."', '".$response['toWho']."', '".$response['mask']."', '".$response['channel']."', '".$response['function']."', '".$response['library']."', '".$response['versions']."', '".$response['defenition']."', '".$response['description']."', '".$response['url']."', '".$response['matches']."', 1, ".$response['timestamp'].")
        ";
        $this->db->query($sql);
    }

    private function cache_response( $response )
    {
        $this->cache[$this->query] = array();
        foreach ($response as $key => $value) $this->cache[$this->query][$key] = $value;
    }

    private function checkLocation($site)
    {
        preg_match_all('/Location:(.*?)Content-Length:/is', $site, $matches);
        if( isset($matches[1][0]) && $matches[1][0] != "" )
        {
            if(strpos($matches[1][0], '%3F')) $parts = explode('%3F', $matches[1][0]);
            if(isset($parts[0])) $url = parse_url(trim($parts[0]));

            if( !isset($url) ) $url = parse_url(trim($matches[1][0]));

            if($url != "") return $url;
            else return FALSE;
        }
        else
        {
            preg_match_all('/Location:(.*?)Connection:/is', $site, $matches);
            if( isset($matches[1][0]) && $matches[1][0] != "" )
            {
                if(strpos($matches[1][0], '?')) $parts = explode('?', $matches[1][0]);
                $url = parse_url(trim($parts[0]));
                if($url != "") return $url;
                else return FALSE;
            }
        }
        return FALSE;
    }

    public function iphp_command($chat, $args)
    {
        switch($args['arg1'])
        {
            case 'cache':
                $this->cache($chat, $args['arg2']);
                break;
            case 'version':
                $chat->dccSend( DARK . "[" . BRIGHT . 'iPHP' . DARK . "] -  version: ".$this->version."." );
                break;
        }
    }

    private function cache($chat, $action)
    {
        switch($action)
        {
            case 'reset':
                if( $this->useCache === TRUE)
                {
                    if( $this->useDb === TRUE)
                    {
                        $this->db->query("DELETE FROM ".$this->dbTable);
                    }
                    else
                    {
                        $this->cache = null;
                        $this->cache = array();
                    }
                }
                $chat->dccSend( DARK . "[" . BRIGHT . 'iPHP' . DARK . "] -  The cache has been reset." );
                break;
            case 'list':
                if( $this->useCache === TRUE )
                {
                    if( $this->useDb === TRUE )
                    {
                        $cacheResult = $this->db->query("SELECT id FROM ". $this->dbTable);
                        $count = $this->db->numRows($cacheResult);
                    }
                    else
                    {
                        $count = count($this->cache);
                    }
                    if($count > 0) $chat->dccSend( DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - cached functions (".$count.")");
                    else $chat->dccSend( DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - There are 0 cached functions." );
                }
                else {
                    $chat->dccSend( DARK . "[" . BRIGHT . 'iPHP' . DARK . "] - Caching is not turned on." );
                }
                break;
        }
    }
}
?>