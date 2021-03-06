#!/bin/bash

mkdir -p /etc/teslalogger/backup
mkdir -p /etc/teslalogger/Exception

NOW=$(date +%Y%m%d%H%M%S)
if test -f "/tmp/teslalogger-DOCKER"; then
    mysqldump -uroot -pteslalogger -hdatabase --single-transaction --routines --triggers teslalogger | gzip > /etc/teslalogger/backup/mysqldump$NOW.gz
else
    mysqldump -uroot -pteslalogger  --single-transaction --routines --triggers teslalogger | gzip > /etc/teslalogger/backup/mysqldump$NOW.gz
fi
cd /etc/teslalogger/Exception 
tar -czf ex_$(date +%Y%m%d%H%M%S).tar.gz --remove-files *.txt 
