#!/usr/bin/php5
<?php
include_once("modules/wp_mod/userparse.inc");
function TextBetween($s1,$s2,$s){
  $s1 = strtolower($s1);
  $s2 = strtolower($s2);
  $L1 = strlen($s1);
  $scheck = strtolower($s);
  if($L1>0){$pos1 = strpos($scheck,$s1);} else {$pos1=0;}
  if($pos1 !== false){
   if($s2 == '') return substr($s,$pos1+$L1);
   $pos2 = strpos(substr($scheck,$pos1+$L1),$s2);
   if($pos2!==false) return substr($s,$pos1+$L1,$pos2);
  }
  return '';
};

function WhatpulseUserStats($userid,$engine="HTTP") {
switch($engine) {
case "HTTP":

$link="http://whatpulse.org/stats/users/".$userid."/normal/";
$openfile=fopen($link,'r');
$found=false;
while ($getfile=@fgets($openfile, 4096)) {
unset($nick);
$nick=explode(" ",$getfile);
If ($nick[1]=="has" AND $nick[2]=="been") {
$found=true;
$nickname=$nick[0];
$membersince=TextBetween("participant since "," (",$getfile);
$keycount=TextBetween("has typed "," keys,",$getfile);
$clickcount=TextBetween("clicked "," times and moved",$getfile);
fclose($openfile);
};
};
If ($found==true) {
$returnvar['nickname']=$nickname;
$returnvar['membersince']=$membersince;
$returnvar['keycount']=$keycount;
$returnvar['clickcount']=$clickcount;
return $returnvar;
} else{
return false;
};

break;
case "XML":

$stats = array();
$stats = readUserStats($userid);
return $stats;
break;
};
};
?>
