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

class postgre
{

    private $dbIndex;
    private $prefix;
    private $queries = 0;
    private $isConnected = false;
    private $error;

    public function __construct($host, $database, $user, $pass, $prefix, $port = 5432)
    {
        $this->error = true;

        $connect = "host=" . $host . " " .
            "port=" . $port . " " .
            "dbname=" . $database . " " .
            "user=" . $user . " " .
            "password=" . $pass;

        $this->error = pg_connect($connect);

        if (!$this->error) {
            return;
        }

        $this->prefix = $prefix;
        $this->dbIndex = $this->error;
        $this->isConnected = true;
    }

    public function getError()
    {
        return $this->error === false ? true : false;
        //return (@mysql_error($this->dbIndex));
    }

    public function isConnected()
    {
        return $this->isConnected;
    }

    private function fixVar($id, $values)
    {
        return pg_escape_string($values[intval($id) - 1]);
    }

    public function query($query, $values = array())
    {

        if (!is_array($values))
            $values = array($values);

        $query = preg_replace('/\[([0-9]+)]/e', "\$this->fixVar(\\1, &\$values)", $query);

        $this->queries++;

        $data = pg_query($this->dbIndex, $query);

        if (!$data) {
            $this->error = $data;
            return false;
        }

        return $data;
    }


    public function queryFetch($query, $values = array())
    {

        if (!is_array($values))
            $values = array($values);

        $query = preg_replace('/\[([0-9]+)]/e', "\$this->fixVar(\\1, &\$values)", $query);

        $this->queries++;

        $data = pg_query($query, $this->dbIndex);

        if (!$data) {
            $this->error = false;
            return false;
        }

        return pg_fetch_array($data);
    }


    public function fetchArray($toFetch)
    {
        return pg_fetch_array($toFetch);
    }

    public function fetchRow($toFetch)
    {
        return pg_fetch_row($toFetch);
    }

    public function close()
    {
        @pg_close($this->dbIndex);
    }

    public function lastID()
    {
        //ehhh. don't use this.
        return null;
    }

    public function numRows($toFetch)
    {
        return pg_num_rows($toFetch);
    }

    public function numQueries()
    {
        return $this->queries;
    }

}

?>

