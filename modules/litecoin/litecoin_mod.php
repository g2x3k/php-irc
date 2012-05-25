<?php
/*

  ###       THIS MODULE IS FAR FROM DONE AND SOME PROB WONT WORK :)         ####

*/


class litecoin_mod extends module
{

  public $title = "litecoin mod for php-irc bot";
  public $author = "by g2x3k";
  public $version = "0.1";

  public function init()
  {
    //make timer grab stats every 2 mins and cache to make faster ?
    $this->lcd = new BitcoinClient("http", "xxx", "xxx", "xxx", 9332);

    // block check/announce
    $args = new argClass();
    // how often (seconds) in between shuld check local daemons for changes
    $args->interval = 1;
    $args->timer = "blockcheck";
    //start timer once ..

    // !!-TODO: rewrite to pure diff check... //
    $this->timerClass->removeTimer($args->timer);
    $this->timerClass->addTimer($args->timer, $this, "timer_blockcheck", $args, $args->interval, false);

  }


  public function timer_blockcheck	($args) {
    // !!-TODO: rewrite to pure diff check... //

    $this->blw["timer"] = true;
    if (!$this->blockwatch["lastblock"]) {
      // reset // init
      $this->blockwatch["lastblock"] = $this->lcd->query("getblockcount");
      $this->blockwatch["lastwon"] = $this->lcd->query("getblockcount");
      /*$this->ircClass->privMsg("#pool-x","(re)started monitoring @ block: ". $this->blockwatch["lastblock"]);
      $this->ircClass->privMsg("#pool-x.eu","(re)started monitoring @ block: ". $this->blockwatch["lastblock"]);*/

    }
    // diff watcher
    $diff = $this->lcd->query("getdifficulty");
    if ($this->blockwatch["lastdiff"] == 0) $this->blockwatch["lastdiff"] = $diff;
    $odiff = $this->blockwatch["lastdiff"];

    if ($odiff != $diff) {
      // announce diff change
      if ($diff > $odiff) {
        $perc = $diff-$odiff;
        $perc = round($perc/$diff*100,2);
        $this->ircClass->privMsg("#pool-x.eu","[4Diff/+$perc%] Difficulity increased $odiff >> $diff");

      }
      else if ($diff < $odiff) {
        $perc = $odiff-$diff;
        $perc = round($perc/$diff*100,2);
        $this->ircClass->privMsg("#pool-x.eu","[9Diff/-$perc%] Difficulity decreased $odiff >> $diff");

      }

      $this->blockwatch["lastdiff"] = $diff;
    }

    $result = mysql_query("SELECT n.blockNumber, n.accountAddress, n.confirms, n.timestamp, n.difficulty, n.value, w.id, w.shareCount, w.username FROM winning_shares w, networkBlocks n WHERE w.blockNumber = n.blockNumber ORDER BY w.blockNumber DESC LIMIT 1");
    $lastwon = mysql_fetch_assoc($result);

    //echo "this: ". $this->lcd->query("getblockcount") ." last: ". $this->blockwatch["lastblock"] ." - our last: $lastwon[blockNumber]\r\n";

    //print_r($lastwon);

    if ($this->blockwatch["lastwon"] < $lastwon["blockNumber"]) {

      /*$_retries = 5;
      $_sleep = 1;
      for ($i=1; $shareCount == false or $i >= $retries;$i++) {


      }*/
      if ($lastwon["shareCount"] == 0) {
        sleep(1);
        $result = mysql_query("SELECT n.blockNumber, n.accountAddress, n.confirms, n.timestamp, n.difficulty, n.value, w.id, w.shareCount, w.username FROM winning_shares w, networkBlocks n WHERE w.blockNumber = n.blockNumber ORDER BY w.blockNumber DESC LIMIT 1");
        $lastwon = mysql_fetch_assoc($result);
        if ($lastwon["shareCount"] == 0) {
          sleep(1);
          $result = mysql_query("SELECT n.blockNumber, n.accountAddress, n.confirms, n.timestamp, n.difficulty, n.value, w.id, w.shareCount, w.username FROM winning_shares w, networkBlocks n WHERE w.blockNumber = n.blockNumber ORDER BY w.blockNumber DESC LIMIT 1");
          $lastwon = mysql_fetch_assoc($result);
          if ($lastwon["shareCount"] == 0) {
            sleep(1);
            $result = mysql_query("SELECT n.blockNumber, n.accountAddress, n.confirms, n.timestamp, n.difficulty, n.value, w.id, w.shareCount, w.username FROM winning_shares w, networkBlocks n WHERE w.blockNumber = n.blockNumber ORDER BY w.blockNumber DESC LIMIT 1");
            $lastwon = mysql_fetch_assoc($result);
          }
        }
      }


      $presult = mysql_query("SELECT n.blockNumber, n.accountAddress, n.confirms, n.timestamp, n.difficulty, w.shareCount, w.username FROM winning_shares w, networkBlocks n WHERE w.blockNumber = n.blockNumber ORDER BY w.blockNumber DESC LIMIT 1,1");
      $prevblock = mysql_fetch_assoc($presult);
      $duration = timeDiff(date("c",$prevblock["timestamp"]),date("c",$lastwon["timestamp"]),true,false);

      $username = explode(".", $lastwon["username"]); $worker=$username[1]; $username = $username[0];

      $ourhashrate = mysql_query("SELECT * FROM `settings` WHERE `setting` LIKE 'currenthashrate'");
      $ourhashrate = mysql_fetch_assoc($ourhashrate);
      /*$bres = mysql_query("SELECT * FROM ledger WHERE assocBlock = $block_no AND transType = 'BlockBonus'");
      $brow = mysql_fetch_assoc($bres);*/

      //$this->ircClass->privMsg("#pool-x.dev","Block $lastwon[blockNumber] solved found by $username in $lastwon[shareCount] shares over $duration");
      //$bonus = ($brow["amount"] ? "+1":"");
      //$this->ircClass->privMsg("#pool-x","[ANN] Block #$lastwon[blockNumber] found by $username in ".number_format($lastwon["shareCount"])." shares over $duration");


     // $this->ircClass->privMsg("#pool-x.eu","[7Block/#$lastwon[blockNumber]] Nr. $lastwon[id] Value: $lastwon[value] LTC Found by $username Slave $worker in ".number_format($lastwon["shareCount"])." shares over $duration current hashrate $ourhashrate[value] KH/s");

    }

    $this->blockwatch["lastwon"] = $lastwon["blockNumber"];


    return true;
  }
  // !!-TODO: rewrite for #litecoin //
  public function priv_help($line, $args) {
    $channel = $line["to"];
    $nick = $line["fromNick"];
    $host = $line["from"];
    $lookup = str_replace(array(".","!","@"),"",trim($args["arg1"]));

    switch ($lookup) {
      case "u":
        $this->ircClass->privMsg("$channel","$lookup [username] - send back info for given user, if no user given, looksup nickname");
        break;
      case "rate":
        $this->ircClass->privMsg("$channel","$lookup <amount> <lcbcusd|lcusd - usdbclc|usdbclc> [buy|sell|last] - convert <amount> of ltc to usd using btc-e ltc->usd or ltc->btc->usd or other way around, default is last trade price");
        break;
      case "stats":
        $this->ircClass->privMsg("$channel","$lookup - returns stats for the pool");
        break;
      case "info":
        $this->ircClass->privMsg("$channel","$lookup - returns network info, blocknumber and diff and current network rate");
        break;
      case "estimate":
        $this->ircClass->privMsg("$channel","$lookup <khps> - returns estimated ltc per day and hour at <khps>, and time to find a block");
        break;
      case "ticker":
        $this->ircClass->privMsg("$channel","$lookup - returns latest market data from btc-e.com litecoin market");
        break;

      default:
        $this->ircClass->privMsg("$channel", "Commands for Bot is:");
        $this->ircClass->privMsg("$channel", "info: !info for network info, !estimate <khps> estimate given output at any khps");
        $this->ircClass->privMsg("$channel", "market: !ticker for market data, !rate <amount> <lcbcusd|lcusd> [buy|sell|last] convert amount of ltc to usd");
        //$this->ircClass->privMsg("$channel", "note: [arguments] is optional, <arguments> are required, you can lookup specific command with !help [command]");
        break;

    }

  }

  public function priv_info($line, $args) {
    $channel = $line["to"];
    $nick = $line["fromNick"];
    $host = $line["from"];

    $this->ircClass->privMsg("$channel", "network info [LTC] > Current Block: " . $this->lcd->getblocknumber() . " Difficulty: " . $this->lcd->getdifficulty());

  }

  public function priv_ticker($line, $args) {
    $channel = $line["to"];
    $nick = $line["fromNick"];
    $host = $line["from"];
    $data = get_url_contents("https://btc-e.com/api/2/10/ticker");
    $data = json_decode($data["html"]);

    //set + format_coins * fail btc-e api.patch
    $last = $this->format_coins($data->ticker->last);
    $high = $this->format_coins($data->ticker->high);
    $low = $this->format_coins($data->ticker->low);
    $avg = $this->format_coins($data->ticker->avg);
    $buy = $this->format_coins($data->ticker->buy);
    $sell = $this->format_coins($data->ticker->sell);

    $spread = $this->format_coins(str_replace("-", "",round($sell,8)-round($buy,8)));

    $vol = number_format($data->ticker->vol,3);
    $ltcvol = number_format($vol/$avg,3);

    // more data
    /*$data = get_url_contents("https://btc-e.com/api/2/10/depth");
    $data = json_decode($data["html"]);*/

    // mm colors
    if ($last > $avg) $lc = 3; //green
    if ($last < $avg) $lc = 7; //orange
    if ($buy < $avg) $bc = 3;
    if ($buy > $avg) $bc = 7;
    if ($sell > $avg) $sc = 3;
    if ($sell < $avg) $sc = 7;
    if ($high < $avg) $ac = 9; // lightgreen !highlight when avg moves over high
    if ($low > $avg) $ac = 4; // red !highlight when avg goes under low
    //announce
    $this->ircClass->privMsg("$channel", "[BTC-E/ticker] > Last Trade: $lc$last - lowest ask: $bc$buy highest bid: $sc$sell avg: $ac$avg - spread: $spread - high: $high low: $low volume: $vol BTC");
  }
  public function priv_rate($line, $args) {

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
        $lamount = $this->format_coins($oamount*$lcbc);
        $uamount = round($lamount*$bcusd,2);
        $this->ircClass->privMsg("$channel", "[BTC-E/rate/$action] $oamount LTC @ $lcbc = $lamount BTC @ $bcusd = $uamount USD");
        break;

      case "lcusd":
        // convert litecoins > usd
        $lcusd = get_url_contents("https://btc-e.com/api/2/ltc_usd/ticker");
        $lcusd = json_decode($lcusd["html"]);
        $lcusd = $lcusd->ticker->$action;

        $lamount = $this->format_coins($oamount*$lcusd);

        $this->ircClass->privMsg("$channel", "[BTC-E/rate/$action] $oamount LTC @ $lcusd = $lamount USD");
        break;

      case "usdlc":
        // convert litecoins > usd
        $lcusd = get_url_contents("https://btc-e.com/api/2/ltc_usd/ticker");
        $lcusd = json_decode($lcusd["html"]);
        $lcusd = $lcusd->ticker->$action;

        $lamount = $this->format_coins($oamount/$lcusd);

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
        $lamount = $this->format_coins($oamount/$bcusd);

        $uamount = round($lamount/$lcbc,2);

        $this->ircClass->privMsg("$channel", "[BTC-E/rate/$action] $oamount USD @ $bcusd = $lamount BTC @ $lcbc = $uamount LTC");
        break;
      default:
        // show help
        $this->ircClass->privMsg("$channel", "[BTC-E/rate] !rate amount <lcbcusd|lcusd - usdbclc|usdbclc> [sell|buy|last]");
        break;
    }
  }

  public function priv_estimate($line, $args)  {

    $channel = $line["to"];
    $nick = $line["fromNick"];
    $host = $line["from"];
    $hash = trim(str_replace(",",".",$args["arg1"]));
    if ($hash < 1 or $hash > 9999999999 or !preg_match("/^[0-9]+(\.?[0-9]+)?$/i", $hash)) {
      $this->ircClass->privMsg("$channel", "check input");
      return;
    }

    $diff = $this->lcd->getdifficulty();
    //$diff = 0.61881515;
    $blockamount = 50;
    $find_time_hours = $diff * bcpow(2,32)/($hash*1000)/3600;
    $coins_per_day = round((24 / $find_time_hours) * $blockamount,2);
    $coins_per_hr = round($coins_per_day/24,2);
    $estimated_time_find = timeDiff(date("r",time()-($find_time_hours*60)*60),false,true,false);

    $this->ircClass->privMsg("$channel", "The expected generation output, at $hash KHps, given current difficulty of $diff, is " .
    round($coins_per_day, 6) . " LTC per day, " . round($coins_per_hr, 6) . " LTC per hour, Estimated time to find a block is $estimated_time_find");
  }

  // internal functions
  public function format_coins($coins) {
    $coins = $coins * 100000000;
    $coins = floor($coins);
    $coins = $coins/100000000;
    return $coins;
  }
}

?>
