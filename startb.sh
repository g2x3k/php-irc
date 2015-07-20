#!/bin/bash
cd /home/your_bot_folder
php bot.php bot.conf > /dev/null &
PID=$! 
echo $PID > /home/your_bot_folder/php-irc.pid