FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install mysqli

# Install necessary packages
RUN apt-get update && apt-get install -y wget unzip procps default-mysql-client curl

# Enable Apache modules
RUN a2enmod rewrite ssl headers

# Copy Apache config
COPY ./apache-config.conf /etc/apache2/sites-available/000-default.conf

# Copy application source code
COPY . /var/www/html

# ---- Site24x7 APM Agent Installation ----

# Download and install Site24x7 PHP agent
RUN wget -O InstallAgentPHP.sh https://staticdownloads.site24x7.com/apminsight/agents/AgentPHP/linux/InstallAgentPHP.sh && \
    sh InstallAgentPHP.sh -lk "us_67a7588da2ed65d41bfa4ab405a81bc6" -zpa.application_name "ilifes"

# Download and install Site24x7 Data Exporter (required background service)
RUN wget -O InstallDataExporter.sh https://staticdownloads.site24x7.com/apminsight/S247DataExporter/linux/InstallDataExporter.sh && \
    sh InstallDataExporter.sh -root -nsvc -lk "us_67a7588da2ed65d41bfa4ab405a81bc6"

# Optional: Clean up installers
RUN rm -f InstallAgentPHP.sh InstallDataExporter.sh

# Expose ports
EXPOSE 80
EXPOSE 443

# Entrypoint to keep DataExporter running in background (if needed)
COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT [ "sh", "/entrypoint.sh" ]
