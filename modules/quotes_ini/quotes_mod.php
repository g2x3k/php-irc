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
|   > quotes without mysql module
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
*/


class quotes_mod extends module
{

    public $title = "Quotes Mod w/o MySQL";
    public $author = "Manick";
    public $version = "0.1b";

    private $qDB;
    private $quotesCache = array();

    public function init()
    {
        $this->qDB = new ini("modules/quotes_ini/quotes_database/quotes.ini");

        // Add your timer declarations and whatever
        // else here...
    }

    //Methods here:

    private function openDatabase($channel)
    {
        $channel = strtolower($channel);

        if (isset($this->quotesCache[$channel])) {
            return $this->quotesCache[$channel];
        } else {
            $cqDB = new ini("modules/quotes_ini/quotes_database/" . $channel . ".txt");
            if ($cqDB->getError()) {
                return false;
            }
            $this->quotesCache[$channel] = $cqDB;
            return $cqDB;
        }
    }

    public function priv_quote($line, $args)
    {
        $channel = $line['to'];

        if ($channel == $this->ircClass->getNick()) {
            return;
        }

        $cqDB = $this->openDatabase($channel);

        if (!$cqDB) {
            $this->ircClass->notice($line['fromNick'], "Error attempting to access quotes database!");
            return;
        }

        if ($args['nargs'] == 0) {
            $quoteRaw = $cqDB->randomSection();

            if ($quoteRaw != false) {
                $quote = $cqDB->getSection($quoteRaw);
                $msg = BOLD . "Quote " . $quoteRaw . ": " . BOLD . $quote['quote'];
            } else {
                $msg = "No quotes in the database.";
            }

            $this->ircClass->privMsg($channel, $msg);
        } else {
            $num = intval($args['arg1']);

            $quote = $cqDB->getSection($num);

            if ($quote != false) {

                if ($args['nargs'] == 2 && $args['arg2'] == "author") {
                    $msg = "Quote " . BOLD . $num . BOLD . " was authored by " . BOLD . $quote['author'] . BOLD . ".";
                } else {
                    $msg = BOLD . "Quote " . $num . ": " . BOLD . $quote['quote'];
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

        $cqDB = $this->openDatabase($channel);

        if (!$cqDB) {
            $this->ircClass->notice($line['fromNick'], "Error attempting to access quotes database!");
            return;
        }

        if ($args['nargs'] == 0) {
            $this->ircClass->notice($line['fromNick'], "To add a quote, use '!addquote quote'");
        } else {
            $quoteText = $args['query'];

            $quote = $cqDB->searchSectionsByVar("quote", $quoteText, EXACT_MATCH);

            if (!$quote) {
                $lowest = 1;
                while ($cqDB->sectionExists($lowest)) {
                    $lowest++;
                }

                $cqDB->setIniVal($lowest, "quote", $quoteText);
                $cqDB->setIniVal($lowest, "author", $line['fromNick']);
                $cqDB->writeIni();

                $last = $lowest;
                $num = $cqDB->numSections();

                $this->qDB->setIniVal($channel, "num", $num);
                $this->qDB->setIniVal($channel, "last", $last);

                $this->qDB->writeIni();

                $this->ircClass->privMsg($channel, "Thank you for your submission.  Your quote has been added as number " . BOLD . $lowest . BOLD . ".");
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

        $cqDB = $this->openDatabase($channel);

        if (!$cqDB) {
            $this->ircClass->notice($line['fromNick'], "Error attempting to access quotes database!");
            return;
        }

        if ($args['nargs'] == 0) {
            $this->ircClass->notice($line['fromNick'], "You must specify a quote id to delete.");
        } else {
            $num = intval($args['arg1']);

            $quote = $cqDB->getSection($num);

            if ($quote != false) {
                $cqDB->deleteSection($num);
                $cqDB->writeIni();

                $numQuotes = $this->qDB->getIniVal($channel, "num");
                $this->qDB->setIniVal($channel, "num", $numQuotes - 1);
                $this->qDB->writeIni();

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

        $cqDB = $this->openDatabase($channel);

        if (!$cqDB) {
            $this->ircClass->notice($line['fromNick'], "Error attempting to access quotes database!");
            return;
        }

        $lastQuote = $this->qDB->getIniVal($channel, "last");

        if ($lastQuote != false) {
            $quote = $cqDB->getSection($lastQuote);

            if ($quote != false) {
                $this->ircClass->privMsg($channel, BOLD . "Last Quote (" . $lastQuote . "): " . BOLD . $quote['quote']);
            } else {
                $this->ircClass->privMsg($channel, "Either the last quote was deleted, or there are no quotes in the database.");
            }

        } else {
            $this->ircClass->privMsg($channel, "Either the last quote was deleted, or there are no quotes in the database.");
        }

    }

    public function priv_findquote($line, $args)
    {
        $channel = $line['to'];

        if ($channel == $this->ircClass->getNick()) {
            return;
        }

        $cqDB = $this->openDatabase($channel);

        if (!$cqDB) {
            $this->ircClass->notice($line['fromNick'], "Error attempting to access quotes database!");
            return;
        }

        if ($args['nargs'] == 0) {
            $this->ircClass->notice($line['fromNick'], "Usage: " . $args['cmd'] . " <search>");
        } else {
            if (strlen($args['query']) <= 2) {
                $this->ircClass->notice($line['fromNick'], "Query must be more than 2 characters.");
                return;
            }

            $type = "author";
            if ($args['cmd'] == "!findquote") {
                $type = "quote";
            }

            $results = $cqDB->searchSectionsByVar($type, $args['query'], CONTAINS_MATCH);

            $count = count($results);

            if ($count > 0) {
                $this->ircClass->notice($line['fromNick'], "There are " . $count . " quotes matching your query:");

                sort($results);

                $resultArray = irc::multiLine(implode(" ", $results));

                foreach ($resultArray AS $quoteLine) {
                    $this->ircClass->notice($line['fromNick'], $quoteLine);
                }

                $this->ircClass->notice($line['fromNick'], "End of results.");
            } else {
                $this->ircClass->notice($line['fromNick'], "No quotes matching your query.");
            }
        }
    }

}

?>
