<?php
//+---------------------------------------------------------------------------
//|   PHP-IRC dice rolling  Mod 0.1
//|   =======================================
//+---------------------------------------------------------------------------

class dice_mod extends module {

   public $title = "Die Roller";
   public $author = "snotling";
   public $version = "0.1";


   # Public Classes

   public function roll_stats($line, $args) {
      $channel = $line['to'];
      $msg = "Rolling...";

      # roll 6 times, once for each stat
      for ($x = 0; $x < 6; $x++ ) {
         #roll 4d6 and take the higest one
         $roll = maxdice();
         $msg = $msg . " " . $roll;
      }

      $this->ircClass->privMsg($channel, $msg);
   }

   public function roll_die($line, $args) {
      $channel = $line['to'];
      if ($args['nargs'] <= 0) {
          $msg = "Usage: !roll <#dice>d<#sides> (+value) - ex: !roll 3d6, or !roll 2d6 +1";
          $this->ircClass->privMsg($channel, $msg);
          return;
      }
      $string = $args['arg1'];
      list($num, $side) = split("d", $string);
      $number = intval($num);
      $sides = intval($side);

      if (($number > 100) or ($sides > 100)) {
          $msg = "Sorry, but that seems a bit much, I do not think you ment to roll that.";
          $this->ircClass->privMsg($channel, $msg);
          return;
      }

      $result = RollDie($number, $sides);
      $result = $result + $args['arg2'];
      $msg = "Rolling ".$string . $args['arg2'] . " : ".$result;
      $this->ircClass->privMsg($channel, $msg);
      return;
   }
}

#
# Helper Functions
#

function RollDie ( $number_of_die, $number_of_sides ) {
  # Simple Die Roller.
  $total = 0;
  if ($number_of_sides == 0) { return($total); }
  for ($times = 0; $times < $number_of_die; $times++) {
    $roll = rand(1, $number_of_sides);
    $total = $total + $roll;
   }
  return($total);
}

function maxdice () {
  $total=0;

  $roll1 = RollDie(1,6);
  $roll2 = RollDie(1,6);
  $roll3 = RollDie(1,6);
  $roll4 = RollDie(1,6);

  $min = min($roll1, $roll2, $roll3, $roll4);
  if ($min == $roll1) {
     $total = $roll2 + $roll3 + $roll4;
  } else if ($min == $roll2) {
     $total = $roll1 + $roll3 + $roll4;
  } else if ($min == $roll3) {
     $total = $roll1 + $roll2 + $roll4;
  } else if ($min == $roll4) {
     $total = $roll1 + $roll2 + $roll3;
  }
  return $total;
}

?>