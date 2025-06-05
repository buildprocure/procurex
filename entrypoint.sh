#!/bin/sh
#Start S247DataExporter
sh /opt/S247DataExporter/bin/service.sh start
#
#Your code
#
#php-fpm
exec apache2-foreground