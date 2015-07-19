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
|   > Slap Mod
|   > Module written by Xikeon
|   > Module Version Number: 0.1
|   > Irc: #ubernet@irc.rizon.net
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
|   > code, email me at xikeon@gmail.com with the change, and I
|   > will look to adding it in as soon as I can.
+---------------------------------------------------------------------------
*/

class slap_mod extends module
{
     public $title = "Slap Mod";
     public $author = "Xikeon";
     public $version = "0.1";
     
     public $allow = array( "~xikeon@Ubernet.Owner", "UPP@bugs.manekian.com" );

     public function init()
     {
          $this->getSlaps();
     }
     
     public function getSlaps()
     {
          $slaps = new ini( "./modules/slap/slaps.ini" );
          
          if( $slaps->getError( ) )
          {
               $this->ircClass->notice( $nick, "Error while getting slaps." );
               return;
          }
          
          $sections = $slaps->getSections( );
          
          foreach( $sections as $slap )
          {
               $msg = $slaps->getIniVal( $slap, "msg" );
               $status = $slaps->getIniVal( $slap, "status" );
               
               $argArray = array('msg'	=> $msg,
                         'status' => $status);
          }
          
          $this->slaps = $slaps;
     }

     public function priv_slap( $line, $args )
     {
          $chan = irc::myStrToLower( $line[ 'to' ] );
          $nick = irc::myStrToLower( $line[ 'fromNick' ] );
          
          if( $args[ 'nargs' ] < 1 )
          {
               $this->ircClass->notice( $nick, "Syntax: !slap <user>" );
          }
          else
          {
               $getSlaps = $this->slaps->getSection( "all" );
               $theSlap = array_rand( $getSlaps );
               $theSlap2 = preg_replace( "/\{NICK\}/i", $args[ 'arg1' ], $theSlap );
               $theFinal = preg_replace( "/\{USER\}/i", $nick, $theSlap2 );
               $this->ircClass->action( $chan, $theFinal );
          }

     }
     
     public function priv_addslap( $line, $args )
     {
          $chan = irc::myStrToLower( $line[ 'to' ] );
          $nick = irc::myStrToLower( $line[ 'fromNick' ] );

          if( $args[ 'nargs' ] < 1 )
          {
               $this->ircClass->notice( $nick, "Syntax: !addslap <msg>" );
          }
          else
          {
               $this->slaps->setIniVal( "all", $args[ 'query' ], "wait" );
               $this->slaps->writeIni( );
               $this->ircClass->notice( $nick, "We have recieved your slap. A administrator wil approve it as soon as possible." );
               $this->ircClass->notice( $nick, "Thank you!" );
          }
     }
     
     public function priv_adminslap( $line, $args )
     {
          $chan = irc::myStrToLower( $line[ 'to' ] );
          $nick = irc::myStrToLower( $line[ 'fromNick' ] );
          $host = $line[ 'fromIdent' ] . "@" . $line[ 'fromHost' ];

          if( !in_array( $host, $this->allow ) )
          {
               $this->ircClass->notice( $nick, "You don't have access." );
               return;
          }
          
          if( $args[ 'nargs' ] < 1 )
          {
               if( !$this->slaps->getSection( "all" ) )
               {
                    $this->ircClass->notice( $chan, "None." );
                    return;
               }
               $approve = $this->slaps->getSection( "all" );

               $this->ircClass->notice( $nick, "The following slaps aren't approved yet:" );

               foreach( $approve as $msg => $approved )
               {
                    if( $approved == "wait" )
                    {
                         $this->ircClass->notice( $nick, $msg );
                    }
               }
          }
          else
          {
               if( $args[ 'nargs' ] < 2 )
               {
                    $this->ircClass->notice( $nick, "Syntax: !adminslap <ok|del> <slap>" );
               }
               else
               {
                    $approve = $this->slaps->getSection( "all" );
                    $newQ = stristr( $args[ 'query' ], " " );
                    $slapLen = strlen( $newQ );
                    $newSlap = substr( $newQ, 1, $slapLen );

                    if( $args[ 'arg1' ] == "ok" )
                    {
                         if( $approve[ $newSlap ] == "wait" )
                         {
                              $this->slaps->setIniVal( "all", $newSlap, "approved" );
                              $this->slaps->writeIni( );
                              $this->ircClass->notice( $nick, "The slap is now approved." );
                         }
                         else
                         {
                              $this->ircClass->notice( $nick, "That slap already is approved or does not exist." );
                         }
                    }
                    else if( $args[ 'arg1' ] == "del" )
                    {
                         if( isset( $approve[ $newSlap ] ) )
                         {
                              $this->slaps->deleteVar( "all", $newSlap );
                              $this->slaps->writeIni( );
                              $this->ircClass->notice( $nick, "The slap has been deleted!" );
                         }
                         else
                         {
                              $this->ircClass->notice( $nick, "That slap does not exit." );
                         }
                    }
                    else
                    {
                         $this->ircClass->notice( $nick, "Syntax: !adminslap <ok|del> <slap>" );
                    }
               }
          }
     }

}

?>
