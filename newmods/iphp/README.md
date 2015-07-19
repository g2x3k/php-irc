+---------------------------------------------------------------------------
|  iPHP (PHP-IRC) Mod v1.0b
|   ========================================================
|   by Joseph Crawford
|   (c) 2006 by http://www.josephcrawford.com/
|   Contact: codebowl@gmail.com
|   irc: #manekian@irc.rizon.net
|   ========================================
|   Special Contributions were made by:
|   Manick
+---------------------------------------------------------------------------
|   > License
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
|   > code, email me at codebowl@gmail.com with the change, and I
|   > will look to adding it in as soon as I can.
+---------------------------------------------------------------------------

1. 	Introduction and Release Notes
1-a.	What's new in 1.0b!
1-b.    Command Reference
1-c.    Installation


=================================
1. Introduction and Release Notes
=================================
iPHP is a module for the popular PHP IRC bot PHP-IRC.  I created this module for use 
in php related help channels, this module will allow users to lookup information on php 
functions without having to load the php.net website.

=================================
1-a. What's new in 1.0b!
=================================
The largest change in this version is that I have added database support.  You can turn 
the database support on or off.  With the addition of the iphp_config.php file you have
the ability to turn database support or caching on or off.

When it comes to caching, the module will store the function information locally so that
it can be used again when the function is called again.  If you are using the database 
support, the cached items will be stored until you reset the cache.  However if you are not
using the database support, the cache is reset everytime the bot is shutdown, and when you
reload the modules using the reloadfun command.

I have also added the aiblity to tell someone else the function information.  Sometimes someone
will ask a question and rather than tell them how to use the bot or explain the answer it is 
easier to just have the bot tell them the information.  The syntax for this command is

!iphp tell <nickname> about <function>

Here is some example output.

The command
!iphp tell Asylum about strpos

This came to me
[07:59] 	-IdleBot-	[iPHP] - telling Asylum about strpos

This went to Asylum
[07:59] 	-IdleBot-	[iPHP] - Idle0ne wants you to know about strpos
[07:59] 	-IdleBot-	[iPHP] - php.net response for strpos
[07:59] 	-IdleBot-	[iPHP] - strpos -- Find position of first occurrence of a string
[07:59] 	-IdleBot-	[iPHP] - PHP 3, PHP 4, PHP 5
[07:59] 	-IdleBot-	[iPHP] - int strpos ( string haystack, mixed needle [, int offset] )
[07:59] 	-IdleBot-	[iPHP] - http://us2.php.net/manual/en/function.strpos.php

Some statistical information is also stored about each function call, i added a hit counter
however it is not in use yet.

=================================
1-a. Command Reference
=================================
Public Commands
!iphp - This is the public channel command
    Syntax: !iphp <function>, !iphp tell <nickname> about <function>
   
DCC Chat Commands
.iphp - This is the private DCC command
    the following commands are available
    .iphp cache list
    .iphp cache reset
    .iphp version
    
=================================
1-a. Installation
=================================
1.) Place these files in /modules/iphp/
2.) edit your bot's function.conf and add this line
    include modules/iphp/iphp_mod.conf
3.) If you are going to use the database support you need
    to create the following db table.
    

CREATE TABLE `iphp_cache` (
  `id` int(11) NOT NULL auto_increment,
  `query` varchar(25) default NULL,
  `fromWho` varchar(30) default NULL,
  `toWho` varchar(30) default NULL,
  `mask` varchar(150) default NULL,
  `channel` varchar(30) default NULL,
  `function` varchar(25) default NULL,
  `library` varchar(25) NOT NULL,
  `versions` varchar(40) default NULL,
  `defenition` varchar(100) default NULL,
  `description` varchar(255) default NULL,
  `url` varchar(150) default NULL,
  `matches` text,
  `hits` int(11) default '1',
  `timestamp` int(11) default NULL,
  PRIMARY KEY  (`id`)
);