### quick instruction until i get a new readme written

fastest way to get the bot up and running:
 ```
  sudo apt-get install git
  git clone https://github.com/g2x3k/php-irc.git
  sudo apt-get install php5-cli
  cd php-irc/
  nano bot.conf
  php bot.php bot.conf
```

thats it shuld be up and running now (tested on debian 7/8) 

note: some modules requires php5-curl and if you want to use database make sure to install driver for that php5-mysqlnd etc :)

### enable/disable mods
Take a look at function.conf default file used in bot.conf to define what modules are loaded

### reload changes in modules without restart
for this you need to set a new adminpass todo that:
* `php bot.php -c NEWPASS`
* this will give you a hash of the pass you can put in bot.conf
* to reload the bot simple msg it, `/msg BOT admin NEWPASS reloadfunc`


Happy Modding
