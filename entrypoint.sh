#!/bin/sh
#Start S247DataExporter
echo "Starting S247DataExporter..."
sudo sh /opt/S247DataExporter/bin/service.sh start
#
#Your code
#
#php-fpm
echo "Starting PHP-FPM..."
# Start PHP-FPM
exec php-fpm