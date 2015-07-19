<?php

/*
+---------------------------------------------------------------------------
|   PHP-IRC v2.2.0
|   ========================================================
|   by Manick
|   (c) 2001-2005 by http://phpbots.sf.net/
|   Contact: manick@manekian.com
|   irc: #manekian@irc.rizon.net
|   ========================================
+---------------------------------------------------------------------------
|   > quotes with mysql module
|   > Module written by Manick
|   > Module Version Number: 0.1b
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

Installation:

Run the following query in your database:
create table quotes (id int default null, deleted tinyint(1) default 0, author varchar(50) default "", quote text, channel varchar(100) default "", time int, key(id));
*/

class quotes_mod extends module
{

    public $title = "Quotes Mod with MySQL";
    public $author = "Manick";
    public $version = "0.1";

    //Methods here:

    public function priv_quote($line, $args)
    {
        $channel = $line['to'];

        if ($channel == $this->ircClass->getNick()) {
            return;
        }

        if ($args['nargs'] == 0) {
            $randomQuoteRaw = $this->db->query("SELECT * FROM quotes WHERE channel='[1]' and deleted='0' ORDER BY RAND() LIMIT 1", $channel);
            if ($this->db->numRows($randomQuoteRaw)) {
                $quote = $this->db->fetchArray($randomQuoteRaw);
                $msg = BOLD . "Quote " . $quote['id'] . ": " . BOLD . $quote['quote'];
            } else {
                $msg = "No quotes in the database.";
            }

            $this->ircClass->privMsg($channel, $msg);
        } else {
            $num = intval($args['arg1']);

            $quoteRaw = $this->db->query("SELECT * FROM quotes WHERE id='" . $num . "' AND channel='[1]' and deleted='0'", $channel);

            if ($this->db->numrows($quoteRaw)) {
                $quote = $this->db->fetchArray($quoteRaw);

                if ($args['nargs'] == 2 && $args['arg2'] == "author") {
                    $msg = "Quote " . BOLD . $quote['id'] . BOLD . " was authored by " . BOLD . $quote['author'] . BOLD . ".";
                } else {
                    $msg = BOLD . "Quote " . $quote['id'] . ": " . BOLD . $quote['quote'];
                }

                $this->ircClass->privMsg($channel, $msg);
            } else {
                $this->ircClass->privMsg($channel, "Error, that quote does not exist.");
            }

        }


    }


    public function priv_addquote($line, $args)
    {
        $channel = $line['to'];

        if ($channel == $this->ircClass->getNick()) {
            return;
        }

        if ($args['nargs'] == 0) {
            $this->ircClass->notice($line['fromNick'], "To add a quote, use '!addquote quote'");
        } else {
            $quoteText = $args['query'];

            $values = array($quoteText,
                $channel,
            );

            $quoteRaw = $this->db->query("SELECT * FROM quotes WHERE quote='[1]' AND channel='[2]' and deleted='0'", $values);


            if (!$this->db->numRows($quoteRaw)) {

                $quoteRaw = $this->db->query("SELECT id FROM quotes WHERE channel='[1]' AND deleted='1' ORDER BY id ASC LIMIT 1", $channel);


                $values = array($line['fromNick'],
                    $quoteText,
                    $channel,
                );

                if ($this->db->numRows($quoteRaw)) {
                    $quote = $this->db->fetchArray($quoteRaw);
                    $newid = $quote['id'];

                    $this->db->query("UPDATE quotes SET author='[1]',time='" . time() . "',quote='[2]',deleted='0' WHERE id='$newid' AND channel='[3]'", $values);
                } else {
                    $quoteRaw = $this->db->query("SELECT id FROM quotes WHERE channel='[1]' ORDER BY id DESC LIMIT 1", $channel);
                    if ($this->db->numRows($quoteRaw)) {
                        $quote = $this->db->fetchArray($quoteRaw);
                        $newid = $quote['id'] + 1;
                    } else {
                        $newid = 1;
                    }

                    $this->db->query("INSERT INTO quotes (id,author,quote,channel,deleted,time) VALUES ('$newid', '[1]', '[2]', '[3]', '0', '" . time() . "')", $values);
                }

                $this->ircClass->privMsg($channel, "Thank you for your submission.  Your quote has been added as number " . BOLD . $newid . BOLD . ".");
            }

        }

    }


    public function priv_undelete($line, $args)
    {
        $channel = $line['to'];

        if ($channel == $this->ircClass->getNick()) {
            return;
        }

        if (!$this->ircClass->isMode($line['fromNick'], $channel, "o")) {
            return;
        }

        if ($args['nargs'] == 0) {
            $this->ircClass->notice($line['fromNick'], "You must specify a quote id to undelete.");
        } else {
            $num = intval($args['arg1']);

            $quoteRaw = $this->db->query("SELECT * FROM quotes WHERE id='" . $num . "' AND channel='[1]' AND deleted='1'", $channel);
            if ($this->db->numRows($quoteRaw)) {
                $this->db->query("UPDATE quotes SET deleted='0' WHERE id='" . $num . "' AND channel='[1]'", $channel);
                $this->ircClass->privMsg($channel, "Quote successfully un-deleted.");
            } else {
                $this->ircClass->privMsg($channel, "Error, quote does not exist.");
            }

        }

    }


    public function priv_delquote($line, $args)
    {
        $channel = $line['to'];

        if ($channel == $this->ircClass->getNick()) {
            return;
        }

        if (!$this->ircClass->isMode($line['fromNick'], $channel, "o")) {
            return;
        }

        if ($args['nargs'] == 0) {
            $this->ircClass->notice($line['fromNick'], "You must specify a quote id to delete.");
        } else {
            $num = intval($args['arg1']);

            $quoteRaw = $this->db->query("SELECT * FROM quotes WHERE id='" . $num . "' AND channel='[1]' AND deleted='0'", $channel);
            if ($this->db->numRows($quoteRaw)) {
                $this->db->query("UPDATE quotes SET deleted='1' WHERE id='" . $num . "' AND channel='[1]'", $channel);
                $this->ircClass->privMsg($channel, "Quote successfully deleted.");
            } else {
                $this->ircClass->privMsg($channel, "Error, quote does not exist.");
            }

        }
    }

    public function priv_lastquote($line, $args)
    {
        $channel = $line['to'];

        if ($channel == $this->ircClass->getNick()) {
            return;
        }

        $quoteRaw = $this->db->query("SELECT * FROM quotes WHERE channel='[1]' AND deleted='0' ORDER BY time DESC,id DESC LIMIT 1", $channel);
        if ($this->db->numrows($quoteRaw)) {
            $quote = $this->db->fetchArray($quoteRaw);
            $this->ircClass->privMsg($channel, BOLD . "Last Quote (" . $quote['id'] . "): " . BOLD . $quote['quote']);
        } else {
            $this->ircClass->privMsg($channel, "No quotes in the database.");
        }
    }

    public function priv_findquote($line, $args)
    {
        $channel = $line['to'];

        if ($channel == $this->ircClass->getNick()) {
            return;
        }

        if ($args['nargs'] == 0) {
            $this->ircClass->notice($line['fromNick'], "Usage: " . $args['cmd'] . " <search>");
        } else {
            if (strlen($args['query']) <= 2) {
                $this->ircClass->notice($line['fromNick'], "Query must be more than 2 characters.");
                return;
            }

            $search = str_replace(" ", "%", $args['query']);

            $type = "author";
            if ($args['cmd'] == "!findquote") {
                $type = "quote";
            }

            $values = array($type,
                $search,
                $channel,
            );

            $quoteRaw = $this->db->query("SELECT id FROM quotes WHERE [1] like'%[2]%' AND channel='[3]' AND deleted='0' ORDER by id DESC", $values);

            if ($this->db->numRows($quoteRaw)) {
                $this->ircClass->notice($line['fromNick'], "Quotes matching your query:");
                $noticetxt = "";
                while ($quote = $this->db->fetchArray($quoteRaw)) {
                    $noticetxt .= $quote['id'] . " ";

                    if (strlen($noticetxt) >= 250) {
                        $this->ircClass->notice($line['fromNick'], $noticetxt);
                        $noticetxt = "";
                    }
                }

                if ($noticetxt != "") {
                    $this->ircClass->notice($line['fromNick'], $noticetxt);
                }
                $this->ircClass->notice($line['fromNick'], "End of results.");
            } else {
                $this->ircClass->notice($line['fromNick'], "No quotes matching your query.");
            }
        }
    }

    public function dcc_delquote($chat, $args)
    {
        $channel = $args['arg1'];
        $num = intval($args['arg2']);

        $quoteRaw = $this->db->query("SELECT * FROM quotes WHERE id='" . $num . "' AND channel='" . $channel . "' AND deleted='0'");
        if ($this->db->numRows($quoteRaw)) {
            $this->db->query("UPDATE quotes SET deleted='1' WHERE id='" . $num . "' AND channel='" . $channel . "'");
            $chat->dccSend("Quote successfully deleted.");
        } else {
            $chat->dccSend("Error, quote does not exist.");
        }
    }

}

?>
