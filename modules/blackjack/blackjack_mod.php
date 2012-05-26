<?

class blackjack_mod extends module {

  public $title = "simple blackjack game";
  public $author = "g2x3k";
  public $version = "0.1";
  private $delay = 0;

  public function init() {
    // stuff you want to run on (re)load


    $this->bold = chr(2);
    $this->color = chr(3);
    $this->normal = chr(15);
    $this->Reversed = chr(22);
    $this->underlined = chr(31);

    $this->cards = array(
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "J" , "Q" , "K" , "ACE" ,
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "J" , "Q" , "K" , "ACE" ,
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "J" , "Q" , "K" , "ACE" ,
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "J" , "Q" , "K" , "ACE");

    $this->values = array(
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "10" , "10" , "10" , "11" ,
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "10" , "10" , "10" , "11" ,
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "10" , "10" , "10" , "11" ,
    "2" , "3" , "4" , "5" , "6" , "7" , "8" , "9" , "10" , "10" , "10" , "10" , "11");

    $this->aces = array(12,25,38,51);

  }


  public function cardout($card) {

    if ($card > 51) return "SHiT $card .. how?";
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

    // draw two random cards to test the stack :)
    for ($i = 0; $i < 5; $i++) {
      //$num = rand(1000,9999);
      mt_srand ((double) microtime() * 9999999);
      $card = mt_rand (0,51);

      if (count($this->usedcards[$id]) >= 51) {
        // all cards used, new deck
        $this->usedcards[$id] = array();
        $this->ircClass->privMsg($channel, "[BJ/$nick] New Deck");
      }
      while (in_array($card,$this->usedcards[$id])) {
        mt_srand ((double) microtime() * 9999999);
        $card = mt_rand (0,51);
      }

      array_push($this->usedcards[$id],$card);
      $usercards[$i] = $card;

    }
    // print the cards
    foreach ($usercards as $usercard) {
      $card = $this->cardout($usercard);
      $value = $this->values[$usercard];
      $deck = 51-count($this->usedcards[$id]);
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

    $this->ircClass->privMsg($channel, "[BJ/$nick] so far so good but iam not done yet -.- $id");

  }
  public function priv_hit($line, $args) {

  }
  public function priv_stand($line, $args) {

  }

}

?>