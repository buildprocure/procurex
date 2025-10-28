#!/bin/bash
set -e
echo "Starting BuildProcure combined container as $(whoami)"

# Start PHP-FPM directly (not as a service)
if command -v php-fpm8.2 >/dev/null 2>&1; then
  echo "Starting PHP-FPM..."
  php-fpm -D
else
  echo "PHP-FPM not found!"
  exit 1
fi

# Start Apache in foreground
echo "Starting Apache in foreground..."
exec apachectl -D FOREGROUND
