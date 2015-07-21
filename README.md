### quick instruction until i get a new readme written

fastest way to get the bot up and running:
´sudo apt-get install git
´git clone https://github.com/g2x3k/php-irc.git
´sudo apt-get install php5-cli php5-curl php5-mysqlnd
´nano /etc/php5/cli/php.ini
change these values
 - short_open_tag = On
´cd php-irc/
´php bot.php bot.conf

thats it shuld be up and running now (tested on debian 7/8)

