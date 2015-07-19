# hl recon mod

This is the first release of the Half-Life RCON Module for PHP-IRC 2.2.1

At the moment it's really basic, but it can be expanded and customized to great depth

Current functionalities:
- Getting live server logs from a HL1 game server
- Parsing the logs into kills and chat messages (be it from players or the server itself)
- Send the parsed info to a specified irc channel
- Send chat messages from irc to the server

Installation notes:
- Unzip hlserver.ini, hlserver.conf and hlserver.php to "Your bot's directory"/modules/hlserver
- Edit hlserver.ini (This should be pretty self explaining, but see below for more detailed information)
- Edit the function.conf file, add "include modules/hlserver/hlserver.conf" (without the quotes)
- (re-)start / reload the bot

Editing hlserver.ini:
[server]
ip=1.2.3.4							The game server ip address
port=27015							The game server port
password=mypass					The RCON password (you won't be able to run this script without the correct rcon password)
channel=#mychannel			The channel you want to send the logs to

[local]
ip=5.6.7.8							The internet address (WAN IP) of the server running your bot
port=7130								The port to which the logs need to be send, make sure this port is open and routed to the right server

[logging]
kills=0									Log kills to irc (0=off / 1=on)
says=1									Log chat messages to irc (0=off / 1=on)
teamsays=1							Log team chat messages to irc (0=off / 1=on)
serversays=1						Log server chat messages to irc (0=off / 1=on)

[colors]								Used to change a player's name on irc
blue=12									I created this script using TFC and i dont know the team names of other mods
red=4										You will have to check some logs to find this out and change it accordingly
												!!!! ALWAYS use lowercase team names, else this wont work !!!!