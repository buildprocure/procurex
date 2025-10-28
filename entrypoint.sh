#!/bin/bash
set -e
echo "Starting BuildProcure combined container as $(whoami)"

# Start PHP-FPM
if command -v php-fpm8.2 >/dev/null 2>&1; then
  echo "Starting PHP-FPM..."
  service php8.2-fpm start
else
  echo "PHP-FPM not found, skipping."
fi

# Start Apache (Debian package uses apachectl)
echo "Starting Apache in foreground..."
exec apachectl -D FOREGROUND
