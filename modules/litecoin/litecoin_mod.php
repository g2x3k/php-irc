<?php
class litecoin_mod extends module
{
    public $title = "litecoin mod for php-irc bot";
    public $author = "by g2x3k";
    public $contributor = "Greedi";
    public $version = "0.6.9";

    public function init()
    {
        //make timer grab stats every 2 mins and cache to make faster ?
        $this->lcd = new BitcoinClient("http", "user", "pass",
            "localhost", 9332);

    }

    // !!-TODO: rewrite for #litecoin //
    public function priv_help($line, $args)
    {
        $channel = $line["to"];
        $nick = $line["fromNick"];
        $host = $line["from"];
        $lookup = str_replace(array(".", "!", "@"), "", trim($args["arg1"]));

        switch ($lookup) {
            case "u":
                $this->ircClass->privMsg("$channel", "$lookup [username] - send back info for given user, if no user given, looksup nickname");
                break;
            case "rate":
                $this->ircClass->privMsg("$channel", "$lookup <amount> <lcbcusd|lcusd - usdbclc|usdbclc> [buy|sell|last] - convert <amount> of ltc to usd using btc-e ltc->usd or ltc->btc->usd or other way around, default is last trade price");
                break;
            case "stats":
                $this->ircClass->privMsg("$channel", "$lookup - returns stats for the pool");
                break;
            case "Global":
                $this->ircClass->privMsg("$channel", "$lookup <Asset> - Will locking trading data from the asset on Litecoin Global");
                break;
            case "info":
                $this->ircClass->privMsg("$channel", "$lookup - returns network info, blocknumber and diff and current network rate");
                break;
            case "estimate":
                $this->ircClass->privMsg("$channel", "$lookup <khps> - returns estimated ltc per day and hour at <khps>, and time to find a block");
                break;
            case "ticker":
                $this->ircClass->privMsg("$channel", "$lookup - returns latest market data from btc-e.com litecoin market");
                break;
            case "up":
                $this->ircClass->privMsg("$channel", "$lookup - Check if a site is up/down");
                break;

            default:
                $this->ircClass->privMsg("$channel", "Commands for Bot is:");
                $this->ircClass->privMsg("$channel",
                    ".Global: returns latest market data from btc-e.com litecoin market ");
                $this->ircClass->privMsg("$channel", ".up: Check if a site is up/down");
                $this->ircClass->privMsg("$channel",
                    ".info: .info for network info, .estimate <khps> estimate given output at any khps");
                $this->ircClass->privMsg("$channel",
                    ".diff: See current difficulty & next estimate difficulty");
                $this->ircClass->privMsg("$channel", ".pools: See the pools current speed");
                $this->ircClass->privMsg("$channel",
                    ".market: .ticker <btc/usd> for market data, .rate <amount> <lcbcusd|lcusd> [buy|sell|last] convert amount of ltc to usd");
                $this->ircClass->privMsg("$channel",
                    "note: [arguments] is optional, <arguments> are required, you can lookup specific command with !help [command]");
                break;

        }

    }

    public function priv_info($line, $args)
    {
        $channel = $line["to"];
        $nick = $line["fromNick"];
        $host = $line["from"];
        $net_hashrate = $this->lcd->getnetworkhashps() / 1000000;

        $this->ircClass->privMsg("$channel", "network info [LTC] > Current Block: " . $this->
            lcd->getblockcount() . " Difficulty: " . $this->lcd->getdifficulty() .
            " Net hashrate: " . $net_hashrate . " Mh/s");

    }

    public function priv_ticker($line, $args)
    {
        $channel = $line["to"];
        $nick = $line["fromNick"];
        $host = $line["from"];
        $data = get_url_contents("https://btc-e.com/api/2/10/ticker");
        $data2 = get_url_contents("https://btc-e.com/api/2/ltc_usd/ticker");

        $data = json_decode($data["html"]);
        $data2 = json_decode($data2["html"]);

        $lookup = str_replace(array(".", "!", "@"), "", trim($args["arg1"]));

        //set + format_coins * fail btc-e api.patch
        $last = $this->format_coins($data->ticker->last);
        $high = $this->format_coins($data->ticker->high);
        $low = $this->format_coins($data->ticker->low);
        $avg = $this->format_coins($data->ticker->avg);
        $buy = $this->format_coins($data->ticker->buy);
        $sell = $this->format_coins($data->ticker->sell);


        $last2 = $this->format_coins($data2->ticker->last);
        $high2 = $this->format_coins($data2->ticker->high);
        $low2 = $this->format_coins($data2->ticker->low);
        $avg2 = $this->format_coins($data2->ticker->avg);
        $buy2 = $this->format_coins($data2->ticker->buy);
        $sell2 = $this->format_coins($data2->ticker->sell);

        $spread = number_format($buy - $sell, 8);

        $vol = number_format($data->ticker->vol, 3);
        $ltcvol = number_format($vol / $avg, 3);

        $spread2 = number_format($buy2 - $sell2, 8);

        $vol2 = number_format($data2->ticker->vol, 3);
        $ltcvol2 = number_format($vol2 / $avg2, 3);

        // more data
        /*$data = get_url_contents("https://btc-e.com/api/2/10/depth");
        $data = json_decode($data["html"]);*/

        // mm colors
        if ($last > $avg)
            $lc = 3; //green
        if ($last < $avg)
            $lc = 7; //orange
        if ($buy < $avg)
            $bc = 3;
        if ($buy > $avg)
            $bc = 7;
        if ($sell > $avg)
            $sc = 3;
        if ($sell < $avg)
            $sc = 7;
        if ($high < $avg)
            $ac = 9; // lightgreen !highlight when avg moves over high
        if ($low > $avg)
            $ac = 4; // red !highlight when avg goes under low

        // mm colors
        if ($last2 > $avg2)
            $lc2 = 3; //green
        if ($last2 < $avg2)
            $lc = 7; //orange
        if ($buy2 < $avg2)
            $bc2 = 3;
        if ($buy2 > $avg2)
            $bc2 = 7;
        if ($sell2 > $avg2)
            $sc2 = 3;
        if ($sell2 < $avg2)
            $sc2 = 7;
        if ($high2 < $avg2)
            $ac2 = 9; // lightgreen !highlight when avg moves over high
        if ($low2 > $avg2)
            $ac2 = 4; // red !highlight when avg goes under low
        //announce
        switch ($lookup) {
            case "btc":
                $this->ircClass->privMsg("$channel", "[BTC-E/TICKER/BTC] > Last Trade: $lc$last - lowest ask: $bc$buy highest bid: $sc$sell avg: $ac$avg - spread: $spread - high: $high low: $low volume: $vol BTC");
                break;
            case "usd":
                $this->ircClass->privMsg("$channel", "[BTC-E/TICKER/USD] > Last Trade: $lc2$last2 - lowest ask: $bc2$buy2 highest bid: $sc2$sell2 avg: $ac2$avg2 - spread: $spread2 - high: $high2 low: $low2 volume: $vol2 USD");
                break;
            default:
                $this->ircClass->privMsg("$channel", "check input");
                break;
        }
    }


    public function priv_rate($line, $args)
    {

        $strip = array('\'', "\"");
        $channel = $line["to"];
        $nick = $line["fromNick"];
        $host = $line["from"];
        $oamount = (int)str_replace($strip, "", trim($args["arg1"]));
        $convert = str_replace($strip, "", trim($args["arg2"]));
        $action = str_replace($strip, "", trim($args["arg3"]));

        // set rates

        switch ($action) {
            case "buy":
                $action = "buy";
                break;
            case "sell":
                $action = "sell";
                break;
            default:
                $action = "last";

                break;
        }

        switch ($convert) {
            case "lcbcusd":
                // get bcusd
                $bcusd = get_url_contents("https://btc-e.com/api/2/btc_usd/ticker");
                $bcusd = json_decode($bcusd["html"]);
                $bcusd = $bcusd->ticker->$action;
                // get lcbc
                $lcbc = get_url_contents("https://btc-e.com/api/2/ltc_btc/ticker");
                $lcbc = json_decode($lcbc["html"]);
                $lcbc = $lcbc->ticker->$action;
                // convert litecoins > bitcoins > usd
                $lamount = $this->format_coins($oamount * $lcbc);
                $uamount = round($lamount * $bcusd, 2);
                $this->ircClass->privMsg("$channel", "[BTC-E/rate/$action] $oamount LTC @ $lcbc = $lamount BTC @ $bcusd = $uamount USD");
                break;

            case "lcusd":
                // convert litecoins > usd
                $lcusd = get_url_contents("https://btc-e.com/api/2/ltc_usd/ticker");
                $lcusd = json_decode($lcusd["html"]);
                $lcusd = $lcusd->ticker->$action;

                $lamount = $this->format_coins($oamount * $lcusd);

                $this->ircClass->privMsg("$channel", "[BTC-E/rate/$action] $oamount LTC @ $lcusd = $lamount USD");
                break;

            case "usdlc":
                // convert litecoins > usd
                $lcusd = get_url_contents("https://btc-e.com/api/2/ltc_usd/ticker");
                $lcusd = json_decode($lcusd["html"]);
                $lcusd = $lcusd->ticker->$action;

                $lamount = $this->format_coins($oamount / $lcusd);

                $this->ircClass->privMsg("$channel", "[BTC-E/rate/$action] $oamount USD @ $lcusd = $lamount LTC");
                break;

            case "usdbclc":
                // get bcusd
                $bcusd = get_url_contents("https://btc-e.com/api/2/btc_usd/ticker");
                $bcusd = json_decode($bcusd["html"]);
                $bcusd = $bcusd->ticker->$action;
                // get lcbc
                $lcbc = get_url_contents("https://btc-e.com/api/2/ltc_btc/ticker");
                $lcbc = json_decode($lcbc["html"]);
                $lcbc = $lcbc->ticker->$action;
                // convert litecoins > bitcoins > usd
                $lamount = $this->format_coins($oamount / $bcusd);

                $uamount = round($lamount / $lcbc, 2);

                $this->ircClass->privMsg("$channel", "[BTC-E/rate/$action] $oamount USD @ $bcusd = $lamount BTC @ $lcbc = $uamount LTC");
                break;
            default:
                // show help
                $this->ircClass->privMsg("$channel",
                    "[BTC-E/rate] !rate amount <lcbcusd|lcusd - usdbclc|usdbclc> [sell|buy|last]");
                break;
        }
    }

    public function priv_estimate($line, $args)
    {

        $channel = $line["to"];
        $nick = $line["fromNick"];
        $host = $line["from"];
        $hash = trim(str_replace(",", ".", $args["arg1"]));
        if ($hash < 1 or $hash > 9999999999 or !preg_match("/^[0-9]+(\.?[0-9]+)?$/i", $hash)) {
            $this->ircClass->privMsg("$channel", "check input");
            return;
        }

        $diff = $this->lcd->getdifficulty();
        //$diff = 0.61881515;
        $blockamount = 50;
        $find_time_hours = $diff * bcpow(2, 32) / ($hash * 1000) / 3600;
        $coins_per_day = round((24 / $find_time_hours) * $blockamount, 2);
        $coins_per_hr = round($coins_per_day / 24, 2);
        $estimated_time_find = timeDiff(date("r", time() - ($find_time_hours * 60) * 60), false, true, false);

        $this->ircClass->privMsg("$channel", "The expected generation output, at $hash KHps, given current difficulty of $diff, is " .
            round($coins_per_day, 6) . " LTC per day, " . round($coins_per_hr, 6) .
            " LTC per hour, Estimated time to find a block is $estimated_time_find");
    }

    public function priv_diff($line, $args)
    {

        $channel = $line["to"];
        $nick = $line["fromNick"];
        $host = $line["from"];
        $hash = trim(str_replace(",", ".", $args["arg1"]));
        $busqueda_bloques = 120;
        $diff = $this->lcd->getdifficulty();
        $hashps = $this->lcd->getnetworkhashps();
        $diff1 = $hashps * 150 / pow(2, 32);

        $this->ircClass->privMsg("$channel", "Current difficulty: $diff  - Next estimate difficulty: $diff1");

    }


    public function priv_up($line, $args)
    {

        $channel = $line["to"];
        $nick = $line["fromNick"];
        $host = $line["from"];
        $hash = trim(str_replace(",", ".", $args["arg1"]));
        if (Visit("" . $args["arg1"] . "")) {

            $this->ircClass->privMsg("$channel", "" . $args["arg1"] . " looks up to me!");
        } else {

            $this->ircClass->privMsg("$channel", "" . $args["arg1"] . " looks down to me!");
        }
    }

    public function priv_global($line, $args)
    {

        $channel = $line["to"];
        $nick = $line["fromNick"];
        $host = $line["from"];
        $asset = $args["arg1"];
        $asset = strtoupper($asset);
        $global = GetJsonFeed("https://litecoinglobal.com/api/ticker/$asset");
        $global_ticker = $global["ticker"];
        $global_latest = $global["latest"];
        if ($global_latest == "--") {
            $global_latest = "N/A";
        }
        $global_bid = $global["bid"];
        if ($global_bid == "--") {
            $global_bid = "N/A";
        }
        $global_ask = $global["ask"];
        if ($global_ask == "--") {
            $global_ask = "N/A";
        }
        $global_24h_low = $global["24h_low"];
        if ($global_24h_low == "--") {
            $global_24h_low = "N/A";
        }
        $global_24h_high = $global["24h_high"];
        if ($global_24h_high == "--") {
            $global_24h_high = "N/A";
        }
        $global_24h_avg = $global["24h_avg"];
        if ($global_24h_avg == "--") {
            $global_24h_avg = "N/A";
        }
        $global_24h_vol = $global["24h_vol"];
        if ($global_24h_vol == "--") {
            $global_24h_vol = "N/A";
        }
        $global_7d_avg = $global["7d_avg"];
        if ($global_7d_avg == "--") {
            $global_7d_avg = "N/A";
        }
        $global_7d_vol = $global["7d_vol"];
        if ($global_7d_vol == "--") {
            $global_7d_vol = "N/A";
        }
        $global_total_vol = $global["total_vol"];
        if ($global_total_vol == "--") {
            $global_total_vol = "N/A";
        }
        $global_type = $global["type"];
        if ($global_type == "--") {
            $global_type = "N/A";
        }
        
                // mm colors
        if ($global_latest > $global_24h_avg)
            $lc = 3; //green
        if ($global_latest < $global_24h_avg)
            $lc = 7; //orange
        if ($global_bid < $global_24h_avg)
            $bc = 3;
        if ($global_bid > $global_24h_avg)
            $bc = 7;
        if ($global_ask > $global_24h_avg)
            $sc = 3;
        if ($global_ask < $global_24h_avg)
            $sc = 7;
        if ($global_24h_high < $global_24h_avg)
            $ac = 9; // lightgreen !highlight when avg moves over high
        if ($global_24h_low > $global_24h_avg)
            $ac = 4; // red !highlight when avg goes under low

        $this->ircClass->privMsg("$channel", "Lastest: $global_latest - Bid: $lc$global_bid - Ask: $lc$global_ask - 24h low: $global_24h_low - 24h high: $global_24h_high - 24h avg: $global_24h_avg - 24h vol: $global_24h_vol - 7d avg: $global_7d_avg - 7d vol: $global_7d_vol - Total vol: $global_total_vol - Type: $global_type  - URL: https://www.litecoinglobal.com/security/$asset");

    }


    public function priv_pools($line, $args)
    {

        $channel = $line["to"];
        $nick = $line["fromNick"];
        $host = $line["from"];
        $hash = trim(str_replace(",", ".", $args["arg1"]));
        $net_hashrate = $this->lcd->getnetworkhashps() / 1000000;
        $net_hashrate_new = number_format($net_hashrate, 2, '.', '');
        $Pool_X = GetJsonFeed("http://pool-x.eu/api");
        $Pool_X_hashrate = number_format($Pool_X["hashrate"] / 1000, 2);
        $data[] = array('Pool' => 'PooL-X', 'hashrate' => $Pool_X_hashrate);

        $bittruvianman = GetJsonFeed("http://bittruvianman.com/api");
        $bittruvianman_hashrate = number_format($bittruvianman["hashrate"] / 1000, 2);
        $data[] = array('Pool' => 'Bittruvianman', 'hashrate' => $bittruvianman_hashrate);

        $notroll = GetJsonFeed("http://www.notroll.in/api.php");
        $notroll_hashrate = number_format($notroll["hashrate"] / 1000, 2);
        $data[] = array('Pool' => 'Notroll.in', 'hashrate' => $notroll_hashrate);

        $kattare = GetJsonFeed("https://ltc.kattare.com/api.php?api_key=da6e3c588a8b29e3f31517030cdf38031ed24ace77ef18dccc9bcc0043f32dd9");
        $kattare_hashrate = number_format($kattare["pool_hashrate"] / 1000, 2);
        $data[] = array('Pool' => 'kattare', 'hashrate' => $kattare_hashrate);

        $litecoinpool = GetJsonFeed("http://www.litecoinpool.org/api");
        $litecoinpool_hashrate = number_format($litecoinpool["pool"]["hash_rate"] / 1000,
            2);
        $data[] = array('Pool' => 'Litecoinpool', 'hashrate' => $litecoinpool_hashrate);

        $ozco = GetJsonFeed("https://lc.ozco.in/api.php");
        $ozco_hashrate = number_format($ozco["hashrate"] / 1000, 2);
        $data[] = array('Pool' => 'Ozco', 'hashrate' => $ozco_hashrate);

        $p2p = GetJsonFeed("http://ltcfaucet.com:9327/global_stats");
        $p2p_hashrate = number_format($p2p["pool_hash_rate"] / 1000000, 2);
        $data[] = array('Pool' => 'P2Pool', 'hashrate' => $p2p_hashrate);

        $xurious = GetJsonFeed("http://ltc.xurious.com/api");
        $xurious_hashrate = number_format($xurious["pool"]["hash_rate"] / 1000000, 2);
        $data[] = array('Pool' => 'Xurious', 'hashrate' => $xurious_hashrate);

        $nushor = GetJsonFeed("http://ltc.nushor.net/api.php");
        $nushor_hashrate = number_format($nushor["hashrate"] / 1000, 2);
        $data[] = array('Pool' => 'Nushor', 'hashrate' => $nushor_hashrate);

        $Coinotron = GetJsonFeed("https://www.coinotron.com/coinotron/AccountServlet?action=api");
        $Coinotron_hashrate = number_format($Coinotron[2]["hashrate"] / 1000000, 2);
        $data[] = array('Pool' => 'Coinotron', 'hashrate' => $Coinotron_hashrate);

        foreach ($data as $key => $row) {
            $_hasrate[$key] = $row['hashrate'];
        }
        array_multisort($_hasrate, SORT_DESC, $data);

        foreach ($data as $key => $row) {
            $echo_string .= $row['Pool'] . " " . $row['hashrate'] . " Mh/s - ";

        }
        $echo_string .= "Network: " . $net_hashrate_new . " Mh/s";

        $this->ircClass->privMsg("$channel", $echo_string);

    }

    // internal functions
    public function format_coins($coins)
    {
        $coins = $coins * 100000000;
        $coins = floor($coins);
        $coins = $coins / 100000000;
        return $coins;
    }

}

?>
