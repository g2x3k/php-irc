#tld_mod

Just add to your function.conf:

include modules/tld_mod/tld_mod.conf

If "commands_mod" (http://www.phpbots.org/modinfo.php?mod=19) is NOT installed, open tld_mod.conf, uncomment the "priv" line and remove/comment the last line

##readme.txt
Top Level Domain display
Readme

v0.1 (c) 2007 by SubWorx (sub@subworx.ath.cx , once i get it to work. for now, use the php-irc forums)

Changelog

2007.03.11 v0.1 - initial release


This module shows the country/organization belonging to a given Top Level Domain

!tld <domain> - return the country/organization


If "commands_mod" (http://www.phpbots.org/modinfo.php?mod=19) is NOT installed, open tld_mod.conf, uncomment the "priv" line and remove/comment the last line
