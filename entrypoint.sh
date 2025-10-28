#!/bin/bash
echo "Starting BuildProcure combined container as $(whoami)"

# Start PHP-FPM
service php8.2-fpm start

# Start Apache (foreground)
exec apache2-foreground
