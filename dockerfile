FROM php:8.2-apache

# Install necessary PHP extensions and tools
RUN docker-php-ext-install mysqli
# Install necessary packages (e.g., MySQL client)
RUN apt-get update && apt-get install -y default-mysql-client

# Enable Apache modules
RUN a2enmod rewrite ssl headers

# Copy custom Apache configuration
COPY ./apache-config.conf /etc/apache2/sites-available/000-default.conf

# Copy your application code into the container
COPY . /var/www/html
COPY ./public /var/www/html/public
# Expose ports
EXPOSE 80
EXPOSE 443
