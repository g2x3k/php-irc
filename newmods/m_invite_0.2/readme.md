Unpack m_invite.tar.gz to your modules-dir, then
add this line in your function.conf:

include modules/m_invite/m_invite.conf

to make this mod work...


All it does, is catch and invite, and respond to it by joining the requested channel.
No exist- or validity-checking is done.


For the v0.2-release the catched invite-channel is now also maintained and listed in the DCC interface.
See http://www.phpbots.org/showtopic.php?tid=110&page=1#post483 for contributor.

Ty Aragno for the suggestion and update.

//Rikard