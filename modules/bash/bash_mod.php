<?php

/*
+---------------------------------------------------------------------------
|   PHP-IRC Bash Parsing Mod 0.1
|   ========================================================
|   by Manick
|   (c) 2001-2004 by http://phpbots.sf.net
|   Contact: manick@manekian.com
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

class bash_mod extends module
{

    public $title = "Bash Parser";
    public $author = "Manick";
    public $version = "0.1";

    private $delay = 0;

    public function priv_bash($line, $args)
    {
        $channel = $line['to'];
        $random = false;

        if (strpos($channel, "#") === false) {
            return;
        }

        if ($this->ircClass->getStatusRaw() != STATUS_CONNECTED_REGISTERED) {
            return;
        }

        if (time() < $this->delay) {
            $this->ircClass->notice($line['fromNick'], "Please wait " . ($this->delay - time()) . " seconds before using this function again.", 0);
            return;
        }

        //?search=test&sort=0&show=25

        if ($args['nargs'] > 0) {
            if ($args['arg1'] == "+") {
                $random = true;
                $query = "random1&show=1";
            } else if ($args['arg1'] == "-") {
                $this->ircClass->notice($line['fromNick'], "This is an unsupported function.");
                return;
            } else {
                $num = $args['arg1'];
                if (substr($num, 0, 1) == "#") {
                    $num = substr($num, 1);
                }
                $num = intval($num);

                if ($num <= 0) {
                    $search = urlencode($args['query']);
                    $random = true;
                    $query = "search=" . $search . "&show=1&sort=0";
                } else {
                    $query = "$num";
                }
            }
        } else {
            $random = true;
            $query = "random&show=1";
        }

        $line['channel'] = $channel;
        $line['random'] = $random;

        $host = "www.bash.org";
        $port = 80;
        $path = "/";

        $getQuery = socket::generateGetQuery($query, $host, $path, "1.0");

        $this->ircClass->addQuery($host, $port, $getQuery, $line, $this, "priv_bash_stageTwo");


    }

    public function priv_bash_stageTwo($line, $args, $result, $data)
    {
        if ($result == QUERY_ERROR) {
            $this->ircClass->notice($line['fromNick'], "An error was encountered: " . $data);
            return;
        }

        $channel = $line['channel'];
        $random = $line['random'];

        $data = preg_replace("/\r/i", "", $data);
        preg_match_all("/<p class=\"quote\">(.+?)<\/p><p class=\"qt\">(.+?)<\/p>/is", $data, $dataArray, PREG_PATTERN_ORDER);

        $num = 0;

        $t_count = count($dataArray[2]);

        $our = rand(0, $t_count - 1);

        for ($i = 0; $i < $our - 1; $i++) {
            array_shift($dataArray[2]);
            array_shift($dataArray[1]);
        }

        foreach ($dataArray[2] AS $index => $item) {
            if (trim($item) != "") {
                $item = str_replace("<br />", "", $item);
                $item = html_entity_decode($item, ENT_QUOTES);

                $newLines = substr_count($item, "\n");

                if ($random == true) {
                    if ($newLines > 20) {
                        continue;
                    }
                } else {
                    if ($newLines > 20) {
                        $this->ircClass->notice($line['fromNick'], "The quote to be displayed was over the required 20 line limit. ($newLines lines)");
                        return;
                    }
                }

                $num = $dataArray[1][$index];
                $num = preg_replace("/<(.+?)>/i", "", $num);
                $offsetA = strpos($num, "(");
                $offsetB = strpos($num, ")");
                $rating = substr($num, $offsetA + 1, $offsetB - $offsetA - 1);
                $num = intval(trim(substr($num, 1, strpos($num, chr(32)))));

                break;

            }

        }
        if ($num == 0) {
            if ($random == true) {
                $this->ircClass->notice($line['fromNick'], "None of the data returned was short enough to display.  Try again.");

            } else {
                $this->ircClass->notice($line['fromNick'], "No Data was Returned.");
            }
        } else {
            $count = 0;
            $items = explode("\n", $item);
            foreach ($items AS $item) {
                $this->ircClass->privMsg($channel, BOLD . "Bash #" . $num . BOLD . " (Rating: " . $rating . "): " . $item);
                $count++;
            }
        }

        $this->delay = time() + $count + 3;
    }

}

?>
