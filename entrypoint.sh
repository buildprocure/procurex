#!/bin/bash
set -e
echo "Starting BuildProcure combined container as $(whoami)"

# Start PHP-FPM (already in base image)
if command -v php-fpm >/dev/null 2>&1; then
  echo "Starting PHP-FPM..."
  php-fpm -D
else
  echo "ERROR: PHP-FPM not found in the container!"
  exit 1
fi

# Start Apache in foreground
echo "Starting Apache..."
exec apachectl -D FOREGROUND
