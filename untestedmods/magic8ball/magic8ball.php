<?php
/*
+---------------------------------------------------------------------------
|   PHP-IRC Magic 8-Ball Mod
|   =======================================
|	Version 0.1
|	by yook
|	E-mail: yook@yooksauce.com
+---------------------------------------------------------------------------

This Magic 8-Ball script chooses randomly from a set of 20 possible responses.
The set of responses is the exact same that you would find inside an original Tyco Magic 8-Ball.

Usage: !8ball <question>

*/

class magic8ball extends module {

   	public $title = "Magic 8-Ball Mod";
   	public $author = "yook";
   	public $version = "0.1";

  	public function priv_8ball($line, $args)
	{
		$channel = $line['to'];

		if ($args['nargs'] < 1)
		{
			$this->ircClass->notice($line['fromNick'], 'Usage: !8ball <question>');
			return;
		}

		$responses[] .= 'Yes.';
		$responses[] .= 'Signs point to yes.';
		$responses[] .= 'Reply hazy, try again.';
		$responses[] .= 'Without a doubt.';
		$responses[] .= 'My sources say no.';
		$responses[] .= 'As I see it, yes.';
		$responses[] .= 'You may rely on it.';
		$responses[] .= 'Concentrate and ask again.';
		$responses[] .= 'Outlook not so good.';
		$responses[] .= 'It is decidedly so.';
		$responses[] .= 'Better not tell you now.';
		$responses[] .= 'Very doubtful.';
		$responses[] .= 'Yes - definitely.';
		$responses[] .= 'It is certain.';
		$responses[] .= 'Cannot predict now.';
		$responses[] .= 'Most likely.';
		$responses[] .= 'Ask again later.';
		$responses[] .= 'My reply is no.';
		$responses[] .= 'Outlook good.';
		$responses[] .= 'Don\'t count on it.';

		$num = rand(0, 19);

		$msg = BOLD.$line['fromNick'].BOLD.': '.$responses[$num];

		$this->ircClass->privMsg($channel, $msg);
		return;

	}

}

?>

