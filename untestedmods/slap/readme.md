Slap Mod

With the slap mod you can entertain your channel users. You can have fun by teaching the bot some slaps, and then slap others.

Commands:
.slap <name>
That will slap <name>
.addslap <msg>
That will add a slap. Insert {NICK} for the name said in !slap <name> and {USER} for the name who told the bot to slap
.adminslap
This will show all un-approved slaps. Un-approved slaps will be used, but you can still see if you want to delete them.
.adminslap <ok|del> <slap>
That will approve or delete a slap.

To give yourself access to the admin, you must know your "ident". Edit slap_mod.php and look for $allow
In that var (array) add your own ident and you'll get access. Don't know your ident? Look in your Command Line, it's added before all mesages.

Have fun!