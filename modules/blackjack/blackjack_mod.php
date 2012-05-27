<?

class blackjack_mod extends module {

  public $title = "simple blackjack game";
  public $author = "g2x3k";
  public $version = "0.1";
  private $delay = 0;

  /*
  Rules:
  Players goal is to try and get close to 21 as possible but no more
  aces are worth 11 in case the sum goes over 21 its worth 1
  alternative goal, get 5 cards with a total of less then 21 and it will count as a blackjack
  */


  /*
  multiple decks ?
  tables ?
  */

  public function init() {
    // stuff you want to run on (re)load

    $this->bold = chr(2);
    $this->color = chr(3);
    $this->normal = chr(15);
    $this->reversed = chr(22);
    $this->underlined = chr(31);

    $this->cards = array( // readout name
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "J" , "Q" , "K" , "ACE" ,
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "J" , "Q" , "K" , "ACE" ,
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "J" , "Q" , "K" , "ACE" ,
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "J" , "Q" , "K" , "ACE");

    $this->values = array( // basic values aces are threated diff
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "10" , "10" , "10" , "11" ,
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "10" , "10" , "10" , "11" ,
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "10" , "10" , "10" , "11" ,
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "10" , "10" , "10" , "11");

    $this->aces = array(12,25,38,51); // aces ..

  }


  public function cardout($card) {
    if ($card > 52) return "SHiT $card .. how?";
    else if ($card <= 12) return $this->color.'4,0'. $this->bold." ".$this->cards[$card].' of Hearts '.$this->bold.$this->color;
    else if ($card <= 25) return $this->color.'1,0'. $this->bold." ".$this->cards[$card].' of Spades '.$this->bold.$this->color;
    else if ($card <= 38) return $this->color.'4,0'. $this->bold." ".$this->cards[$card].' of Diamonds '.$this->bold.$this->color;
    else return $this->color.'1,0'. $this->bold." ".$this->cards[$card].' of Clubs '.$this->bold.$this->color;

  }
  public function priv_resetdeck($line, $args) {
    $channel = $line["to"];
    $nick = $line["fromNick"];
    $id = md5($line["from"]);
    $this->usedcards[$id] = array();
    $this->ircClass->privMsg($channel, "[BJ/$nick] New Deck");
  }
  public function priv_drawran($line, $args) {
    $channel = $line["to"];
    $nick = $line["fromNick"];
    $id = md5($line["from"]);
    if (!isset($this->usedcards[$id])) $this->usedcards[$id] = array();

    // draw 5 random cards to test the stack :)
    $usercards = $this->drawcards(5, $id);

    // print the cards
    foreach ($usercards as $usercard) {
      $card = $this->cardout($usercard);
      $value = $this->values[$usercard];
      $deck = 52-count($this->usedcards[$id]);
      $this->ircClass->privMsg($channel, "[BJ/$nick] card: $card value: $value left in deck: $deck");
    }

  }

  public function priv_deal($line, $args) {
    $channel = $line["to"];
    $nick = $line["fromNick"];
    $id = md5($line["from"]);

    $bet = trim($args["arg1"]);
    if (!$bet) {
      $this->ircClass->privMsg($channel, "[BJ/$nick] no bet made .. use !deal <amount> to bet");
      return;
    }

    // start a game with id: $host
    //$this->ircClass->privMsg($channel, "[BJ/$nick] so far so good but iam not done yet -.- $id");

    //deal two cards for dealer
    $this->dealercards[$id] = $this->drawcards(2, $id);
    $dealertotal = $this->getTotal($this->dealercards[$id]);
    // two cards for player
    $this->usercards[$id] = $this->drawcards(2, $id);
    $usertotal = $this->getTotal($this->usercards[$id]);

    foreach ($this->usercards[$id] as $usercard) {
      if (!isset($ucards)) $ucards = $this->cardout($usercard);
      else  $ucards .= ", ".$this->cardout($usercard);
    }

    //$this->ircClass->privMsg($channel, "[BJ/$nick] Bots cards: $dcards - total: $dealertotal");
    $this->ircClass->privMsg($channel, "[BJ/$nick] Your cards: $ucards - total: $usertotal");
    if ($usertotal > 21)  {
      $this->ircClass->privMsg($channel, "[BJ/$nick] Busted ..");
    }
    elseif (count($this->usercards[$id]) == 5 or $usertotal == 21) {
      $this->ircClass->privMsg($channel, "[BJ/$nick] 21/Blackjack ..");
    }
    else {
      $this->ircClass->privMsg($channel, "[BJ/$nick] Avaible actions: !hit, !stand");
    }

  }
  public function priv_hit($line, $args) {
    $channel = $line["to"];
    $nick = $line["fromNick"];
    $id = md5($line["from"]);

    if (!isset($this->usercards[$id])) {
      $this->ircClass->privMsg($channel, "[BJ/$nick] No game in progress for $id");
      return;
    }
    //$this->usercards[$id][count($this->usercards[$id])] = $this->drawcards(1, $id);
    array_push($this->usercards[$id],$this->drawcards(1, $id));
    print_r($this->usercards[$id]);
    foreach ($this->usercards[$id] as $usercard) {
      if (!isset($ucards)) $ucards = $this->cardout($usercard);
      else  $ucards .= ", ".$this->cardout($usercard);
    }
    $usertotal = $this->getTotal($this->usercards[$id]);

    $this->ircClass->privMsg($channel, "[BJ/$nick] Your cards: $ucards - total: $usertotal");

    if ($usertotal > 21)  {
      $this->endgame($id, $channel, $nick);
    }
    elseif (count($this->usercards[$id]) == 5 AND $usertotal <= 21) {
      $this->ircClass->privMsg($channel, "[BJ/$nick] 21/Blackjack ..");
      $this->endgame($id, $channel, $nick);
    }
    elseif ($usertotal == 21) {
      $this->ircClass->privMsg($channel, "[BJ/$nick] 21/Blackjack ..");
      $this->endgame($id, $channel, $nick);
    }
    else {
      $this->ircClass->privMsg($channel, "[BJ/$nick] Avaible actions: !hit, !stand");
    }
  }

  public function endgame($id, $channel, $nick = false) {
    // endgame
    // called:
    // if player has 21
    // 5 cards with sum less then 21
    // player chooses to stand
    // action:
    // play the dealers turn, determine winner
    $usertotal = $this->getTotal($this->usercards[$id]);

    // dealers turn
    $dealertotal = $this->getTotal($this->dealercards[$id]);

    // draw card until reaches 17 or goes bust
    if ($usertotal >= 17 && $usertotal < 21) $beat = $usertotal;
    else $beat = 17;

    if ($dealertotal <= $beat && $dealertotal < 21) {
      while ($dealertotal <= $beat && $dealertotal < $usertotal) {
        $card = $this->drawcards(1);
        while (in_array($card,$this->usercards[$id])) {
          $card = $this->drawcards(1);
        }
        array_push($this->dealercards[$id],$card);
        $dealertotal = $this->getTotal($this->dealercards[$id]);
      }
    }

    // show dealer
    foreach ($this->dealercards[$id] as $dealercard) {
      if (!isset($dcards)) $dcards = $this->cardout($dealercard);
      else  $dcards .= ", ".$this->cardout($dealercard);
    }
    $this->ircClass->privMsg($channel, "[BJ/$nick] House cards: $dcards - total: $dealertotal - tried to beat: $beat");

    // WHO WON ?
    if ($usertotal < 22 && $dealertotal < 22) {
      if ($dealertotal == $usertotal) {
        $wld = "Draw";
      }
      if ($dealertotal > $usertotal) {
        $wld = "Dealer Wins";
      }
      if ($dealertotal < $usertotal) {
        $wld = "You Win";
      }
    }
    if ($usertotal >= 22 && $dealertotal < 22) {
      $wld = "Dealer Wins";
    }
    if ($usertotal <= 21 && $dealertotal >= 22) {
      $wld = "You Win";

    }
    if ($usertotal >= 22 && $dealertotal >= 22) {
      $wld = "Draw";

    }
    if ($usertotal >= 22) { $busted = "- Busted !"; }

    $this->ircClass->privMsg($channel, "[BJ/$nick] $wld $busted");

    $this->usercards[$id] = array();
    $this->dealercards[$id] = array();
  }

  public function priv_stand($line, $args) {
    $channel = $line["to"];
    $nick = $line["fromNick"];
    $id = md5($line["from"]);

    if (!isset($this->usercards[$id])) {
      $this->ircClass->privMsg($channel, "[BJ/$nick] No game in progress for $id");
      return;
    }
    $this->endgame($id, $channel, $nick);
  }

  // internal functions
  public function drawcards ($l = 2, $id) {
    for ($i = 0; $i < $l; $i++) {
      $num = rand(1000,9999); // random delay
      mt_srand ((double) microtime() * 9999999);
      $card = mt_rand (0,51);

      if (count($this->usedcards[$id]) >= 52) {
        // all cards used, new deck
        $this->usedcards[$id] = array();
        $this->ircClass->privMsg($channel, "[BJ/$nick] New Deck");
      }
      while (in_array($card,$this->usedcards[$id])) {
        mt_srand ((double) microtime() * 9999999);
        $card = mt_rand (0,51);
      }

      array_push($this->usedcards[$id],$card);
      if ($l > 1)
      $usercards[$i] = $card;
      else
      return $card;
    }

    return $usercards;
  }
  public function getTotal($cards) {
    $total = 0;
    for($i=0; $i < sizeof($cards); $i++) {
      $card = $cards[$i];
      $total = $total + $this->values[$card];
    }
    for($i=0; $i < sizeof($this->aces); $i++) {
      if (in_array($this->aces[$i],$cards)) {
        if ($total > 21) {
          $total = $total - 10;
        }
      }
    }
    return $total;
  }
}

?>