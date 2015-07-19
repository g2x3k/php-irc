A small module to to provide a generic !help for all modules (the ones you want to)

Requirements:

You need to change the typedefs.conf.
Change the priv section to:

type 	priv		~ ;----Used to process input of users in channels
	name		~
	active		~
	inform		~
	canDeactivate	~
	usage		~
	module		~
	function	~
	section		~	;---- String - section
	args		~   ;---- String - <arg1> <arg2> etc.  like dcc usage
	help			;---- String - Description of the function

The change to typedefs.conf will ofc make mods with their current config files from working as the conf file won't load right.  
Just add 'null "" ""' at the end of them and they will work as before.

Any function that has "" (empty string) for the help part will not display help (if there are functions you do not 
wish to advertise).	

You also need to add a section description to the module you are implementing help for.

See the commands_mod.conf for an example