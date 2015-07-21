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
