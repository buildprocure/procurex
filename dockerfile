# ARG must be declared before usage
ARG S247_LICENSE_KEY

FROM php:8.2-apache

# Re-declare ARG inside the build stage
ARG S247_LICENSE_KEY

RUN docker-php-ext-install mysqli
RUN apt-get update && apt-get install -y wget unzip procps default-mysql-client curl
RUN a2enmod rewrite ssl headers

COPY ./apache-config.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html


# Optional: Debugging - remove before production
RUN echo "Key is ${S247_LICENSE_KEY}"

# Install Site24x7 agent using ARG
RUN wget -O /tmp/InstallAgentPHP.sh https://staticdownloads.site24x7.com/apminsight/agents/AgentPHP/linux/InstallAgentPHP.sh && \
    sh /tmp/InstallAgentPHP.sh -lk "${S247_LICENSE_KEY}" -zpa.application_name "ilifes"

# ✅ Register the extension manually
RUN echo "extension=/opt/site24x7/apminsight/php/phpagent.so" > /etc/php/8.2/apache2/conf.d/99-apminsight.ini && \
    echo "apminsight.configfile=/opt/site24x7/apminsight/php/agent.conf" >> /etc/php/8.2/apache2/conf.d/99-apminsight.ini


RUN wget -O /tmp/InstallDataExporter.sh https://staticdownloads.site24x7.com/apminsight/S247DataExporter/linux/InstallDataExporter.sh && \
    sh /tmp/InstallDataExporter.sh -root -nsvc -lk "${S247_LICENSE_KEY}"

RUN rm -f /tmp/InstallAgentPHP.sh /tmp/InstallDataExporter.sh

EXPOSE 80 443

COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["sh", "/entrypoint.sh"]
