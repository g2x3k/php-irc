<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC v2.2.1 Service Release
|   ========================================================
|   by Manick
|   (c) 2001-2005 by http://phpbots.sf.net/
|   Contact: manick@manekian.com
|   irc: #manekian@irc.rizon.net
|   ========================================
+---------------------------------------------------------------------------
|   > database module
|   > Module written by Manick
|   > Module Version Number: 2.1.1
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
|   > If you wish to suggest or submit an update/change to the source
|   > code, email me at manick@manekian.com with the change, and I
|   > will look to adding it in as soon as I can.
+---------------------------------------------------------------------------
*/

// *** Modified by Nemesis128_at_atarax_dot_org

class postgresql {

    private $dbRes;
    private $prefix;
    private $numQueries = 0;
    private $isConnected = false;
    private $error = false;

    private $user;
    private $pswd;
    private $dbase;
    private $host;
    private $port;

    public function __construct ($user,$pswd,$dbase,$prefix,$host = null,$port = 5432) {

        $this->user     = $user;
        $this->pswd     = $pswd;
        $this->dbase     = $dbase;
        $this->prefix     = $prefix;
        $this->host     = $host;
        $this->port     = $port;

        $conn_str = '';

        if (!is_null($host)) { // connect thru TCP/IP
            $conn_str .= 'host='.$host;
            $conn_str .= ' port='.$port;
        } // else thru intern sockets
        $conn_str .= ' user='.$user;
        $conn_str .= ' password='.$pswd;
        $conn_str .= ' dbname='.$dbase;

        $this->dbRes = pg_connect($conn_str);

        if (!is_resource($this->dbRes)) {
            $this->error = 'PgSQL Connection error';
            return;
        }

        $this->isConnected = true;
    }

    public function getError () {
        if ($this->error) {
            $err = $this->error."\n\n";
            return ($err.@pg_last_error($this->dbIndex));
        } else {
            return null;
        }
    }

    public function isConnected () {
        return $this->isConnected;
    }

    public static function esc ( $var ) {
        return pg_escape_string ( $var );
    }

    public function query ( $query_str ) {

        if (pg_connection_status($this->dbRes) === PGSQL_CONNECTION_BAD) {
            if (!pg_connection_reset($this->dbRes)) {
                $this->error = 'Connection lost';
                $this->isConnected = false;
                return false;
            }
        }

        $this->numQueries++;

        $res = @pg_query($this->dbRes,$query_str);

        if (!$res) {
            $this->error = 'Query failed: '.pg_last_error().' ('.$query_str.')';
            return false;
        }

        return $res;
    }

    public function fetchArray ( $toFetch ) {
          return pg_fetch_assoc($toFetch);
    }

    public function fetchObject ( $toFetch ) {
          return pg_fetch_object($toFetch);
    }

    public function fetchRow ( $toFetch ) {
        return pg_fetch_row($toFetch);
    }

    public function numRows ( $toFetch ) {
        return pg_num_rows($toFetch);
    }

    public function numQueries () {
        return $this->numQueries;
    }

    public function close () {
        @pg_close($this->dbRes);
    }

}

?>