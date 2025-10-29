#!/bin/bash
set -e
echo "Starting BuildProcure combined container as $(whoami)"

# Start PHP-FPM with explicit config (ensures socket is created)
if command -v php-fpm8.2 >/dev/null 2>&1; then
  echo "Starting PHP-FPM..."
  php-fpm8.2 -y /etc/php/8.2/fpm/php-fpm.conf -D
else
  echo "Installing PHP-FPM runtime..."
  apt-get update && apt-get install -y php8.2-fpm
  php-fpm8.2 -y /etc/php/8.2/fpm/php-fpm.conf -D
fi

# Start Apache
echo "Starting Apache in foreground..."
exec apachectl -D FOREGROUND
