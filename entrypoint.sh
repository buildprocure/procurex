#!/bin/sh
#Start S247DataExporter
echo "Starting S247DataExporter..."
sh /opt/S247DataExporter/bin/service.sh start
#
#Your code
#
#php-fpm
echo "Starting PHP-FPM..."
exec apache2-foreground