# ARG must be declared before usage
ARG S247_LICENSE_KEY

FROM php:8.2-apache

# Re-declare ARG inside build stage
ARG S247_LICENSE_KEY

# Install dependencies
RUN apt-get update && \
    apt-get install -y wget unzip procps default-mysql-client curl vim net-tools gcc make && \
    rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite ssl headers

# Configure Apache
COPY ./apache-config.conf /etc/apache2/sites-available/000-default.conf

# Copy application code
COPY . /var/www/html

# Optional: Debugging
RUN echo "🔑 Site24x7 License Key: ${S247_LICENSE_KEY}"
# Install prerequisites
RUN apt-get update && apt-get install -y wget unzip procps

# Install PHP agent
RUN wget -O InstallAgentPHP.sh https://staticdownloads.site24x7.com/apminsight/agents/AgentPHP/linux/InstallAgentPHP.sh
RUN sh InstallAgentPHP.sh -lk "us_67a7588da2ed65d41bfa4ab405a81bc6" -zpa.application_name "ilifes"

# Install S247DataExporter
RUN wget -O InstallDataExporter.sh https://staticdownloads.site24x7.com/apminsight/S247DataExporter/linux/InstallDataExporter.sh
RUN sh InstallDataExporter.sh -root -nsvc -lk "us_67a7588da2ed65d41bfa4ab405a81bc6"

# Install PHP extensions
RUN docker-php-ext-install mysqli

# Expose ports
EXPOSE 80 443

# Entrypoint
COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["sh", "/entrypoint.sh"]
