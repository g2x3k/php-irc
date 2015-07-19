
leo_mod

Now supports 4 languages
German - English/French/Spanish/Italian

Japanese currently outputs gibberish on (m)IRC, UTF-8 might help but that currently is beyond my scope

Just add to your function.conf:

include modules/leo_mod/leo_mod.conf

If "commands_mod" (http://www.phpbots.org/modinfo.php?mod=19) is NOT installed, open leo_mod.conf and follow the instructions in there.


dict.leo.org Translator
Readme

v0.3 (c) 2007-2009 by SubWorx (hiphopman@gmx.net , #zauberpilz @ ranger.de.eu.phat-net.de )

Changelog

2007.03.14 v0.1 - initial release
2008.??.?? v0.2 - The "Could be extended easily" release
                  added fr(ench) and es(panol)
2008.05.02 v0.3 - The "Sorry, forgot to release this to the public" release
                  added it(alian) translation


This module enables the channel users to translate words (de-en/fr/es/it) using leo.dict.cc

!leo <string> - translate string. 5 translations are listed (can be changed with $max_results in leo_mod.php), alias !eng
!fra <string> - translate german-french.
!esp <string> - translate german-spanish
!ita <string> - translate german-italian

If "commands_mod" (http://www.phpbots.org/modinfo.php?mod=19) is NOT installed, open leo_mod.conf and follow the instructions there.
