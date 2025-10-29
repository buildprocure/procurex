#!/bin/bash
set -e
echo "Starting BuildProcure combined container as $(whoami)"

# Start PHP-FPM
if command -v php-fpm >/dev/null 2>&1; then
  echo "Starting PHP-FPM..."
  php-fpm -D
else
  echo "Installing PHP-FPM runtime..."
  apt-get update && apt-get install -y php8.2-fpm
  php-fpm -D
fi

# Start Apache
echo "Starting Apache in foreground..."
exec apachectl -D FOREGROUND
