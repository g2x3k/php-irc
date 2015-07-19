#The Chopper (INI)

Short for channel opper, the Chopper automatically gives channel modes to specific people as they join the channel. The list of channels, hostmasks, and modes are stored in an INI file.

##REAME
Chopper INI for PHP-IRC
Jason Hines <jason@greenhell.com>
----------------------------------

Quite a simple module.  When a user joins the channel, it matches
the user's hostmask with a list stored in INI format.  If it finds 
a match, it gives that user the appropriate channel mode.

The format of hosts.ini:

[#channel]
jourmom@somedomain.com = o
mymom@anotherplace.net = o
hismom@footown.net = v

You can define as many channels and hostmasks as you like.  The only
drawback is that the hostmask much be exact. (no patterns or wildcards)
It relies on PHP-IRC's IRC::hostMasksMatch() which doesn't provide this
support. .. And I'm lazy.

