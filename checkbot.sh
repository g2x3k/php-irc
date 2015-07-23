#!/bin/sh
# Check if bot is runing then exit otherwise restart
# in crontab
# 0,10,20,30,40,50 * * * * /home/your_bot_folder/checkbot.sh


MYPATH=/home/your_bot_folder/

PID=0

if test -r $MYPATH/php-irc.pid; then
    PID=$(cat $MYPATH/php-irc.pid)
fi

if [ 0 -ne $PID ]; then
    running=`ps --pid $PID | grep $PID |wc -l`

    if [ $running -eq 1 ]; then
        exit 1
    fi
fi

cd $MYPATH
./bot.sh & >/dev/null