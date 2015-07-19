<?php
/**
 * SpotSec Framework
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 * @category PHP-IRC
 * @package SpotSec_Phpirc
 * @subpackage Module
 * @copyright Copyright (c) 2006 SpotSec Networks
 * @license GNU Public License
 * @link http://spotsec.com
 */

/**
 * Controller plugin for authorization
 *
 * @author Geoffrey Tran
 * @license GNU Public License
 * @category PHP-IRC
 * @package SpotSec_Phpirc
 * @subpackage Module
 * @copyright Copyright 2006, SpotSec Networks
 */
class throw_mod extends module
{
     public $title = 'Throw Mod';
     public $author = 'Geoffrey Tran';
     public $version = "0.1";

     public function init()
     {}

     public function priv_throw( $line, $args )
     {
          $chan = irc::myStrToLower( $line[ 'to' ] );
          $nick = irc::myStrToLower( $line[ 'fromNick' ] );

          if( $args[ 'nargs' ] < 3 )
          {
               $this->ircClass->notice( $nick, "Syntax: !throw <item> at <user>" );
          }
          else
          {

               $this->ircClass->action( $chan, "throws {$args['query']}");
          }

     }
}
?>
