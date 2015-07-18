<?php
class uptime_mod extends module
{

    public $title = "Uptime";
    public $author = "g2x3k";
    public $version = "0.1";
    private $delay = 0;

    public function init()
    {
    

    }


    public function priv_uptime($line, $args)
    {
        $nick = $line['fromNick'];
        $channel = $line["to"];

        // failsafes ...
        if (strpos($channel, "#") === false)
            return;
        if ($this->ircClass->getStatusRaw() != STATUS_CONNECTED_REGISTERED)
            return;

        $this->ircClass->privMsg($channel, "PHP-IRC v" . VERSION . " [" . VERSION_DATE .
            "] by Manick, maintained by g2x3k (visit https://github.com/g2x3k/php-irc to download)");
        $this->ircClass->privMsg($channel, "total running time of " . $this->ircClass->
            timeFormat($this->ircClass->getRunTime(),
            "%d days, %h hours, %m minutes, and %s seconds."));


        //$chat->dccSend("PHP-IRC v" . VERSION . " [".VERSION_DATE."] by Manick (visit http://phpbots.sf.net/ to download)");
        //$chat->dccSend("total running time of " . $this->ircClass->timeFormat($this->ircClass->getRunTime(), "%d days, %h hours, %m minutes, and %s seconds."));

        $fd = @fopen("/proc/" . $this->ircClass->pid() . "/stat", "r");
        if ($fd !== false) {
            $stat = fread($fd, 1024);
            fclose($fd);

            $stat_array = explode(" ", $stat);

            $pid = $stat_array[0];
            $comm = $stat_array[1];
            $utime = $stat_array[13];
            $stime = $stat_array[14];
            $vsize = $stat_array[22];
            $meminfo = number_format($vsize, 0, '.', ',');
            $u_time = number_format($utime / 100, 2, '.', ',');
            $s_time = number_format($stime / 100, 2, '.', ',');

            $fd = @fopen("/proc/stat", "r");
            if ($fd !== false) {
                $stat = fread($fd, 1024);
                fclose($fd);

                $stat = str_replace("  ", " ", $stat);
                $stat_array_2 = explode(" ", $stat);
                $totalutime = $stat_array_2[1];
                $totalstime = $stat_array_2[3];
                $u_percent = number_format($utime / $totalutime, 6, '.', ',');
                $s_percent = number_format($stime / $totalstime, 6, '.', ',');

                $this->ircClass->privMsg($channel, "cpu usage: " . $u_time . "s user (" . $u_percent .
                    "%), " . $s_time . "s system (" . $s_percent . "%) memory usage: " . $meminfo .
                    " bytes");
                $out = true;
            }
            if (!$out)
                $this->ircClass->privMsg($channel, "memory usage: " . $meminfo . " bytes");
            $this->ircClass->privMsg($channel, $this->getStatus());

        }

    }

    private function getStatus()
    {
        $sqlCount = 0;
        $bwStats = $this->ircClass->getStats();

        if (is_object($this->db)) {
            $sqlCount = $this->db->numQueries();
        }

        $bwUp = irc::intToSizeString($bwStats['BYTESUP']);
        $bwDown = irc::intToSizeString($bwStats['BYTESDOWN']);

        $fileBwUp = irc::intToSizeString($this->dccClass->getBytesUp());
        $fileBwDown = irc::intToSizeString($this->dccClass->getBytesDown());

        $txtQueue = $this->ircClass->getTextQueueLength() + 1;

        $ircStat = $this->ircClass->getStatusString($this->ircClass->getStatusRaw());
        $permin = round($sqlCount / $this->ircClass->getRunTime(), 1);
        $kbs = ($bwStats['BYTESUP'] + $bwStats['BYTESDOWN']) / 1024;
        $kbs = round($kbs / $this->ircClass->getRunTime(), 2);
        $status = "Status: " . number_format($sqlCount) . " SQL / $permin Q/sec (irc BW: " .
            $bwUp . " up, " . $bwDown . " down avg ~$kbs kbps)";
        return $status;
    }
}
?>