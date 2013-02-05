#!/bin/sh

# bot starts if it doesn't operate.
if (( `ps -ef | grep bot.php | grep -v grep | wc -l` < 1 ))
then
    /usr/local/bin/php /usr/local/apache2/htdocs/batch/bot.php &
fi
if (( `ps -ef | grep bot.php | grep -v grep | wc -l` > 1 ))
then
    pkill -f 'bot.php'
fi