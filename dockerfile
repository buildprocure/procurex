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

# Install Site24x7 APM Insight PHP Agent
RUN apt-get update && apt-get install -y lsb-release procps curl unzip gnupg && \
    wget -O /tmp/InstallAgentPHP.sh https://staticdownloads.site24x7.com/apminsight/agents/AgentPHP/linux/InstallAgentPHP.sh && \
    chmod +x /tmp/InstallAgentPHP.sh && \
    bash -x /tmp/InstallAgentPHP.sh -lk "${S247_LICENSE_KEY}" -zpa.application_name "ilifes" -phpbin /usr/local/bin/php && \
    if [ -d /opt/site24x7/apminsight/php ]; then \
      echo "✅ Site24x7 agent installed"; \
      echo "extension=/opt/site24x7/apminsight/php/phpagent.so" > /usr/local/etc/php/conf.d/99-apminsight.ini && \
      echo "apminsight.configfile=/opt/site24x7/apminsight/php/agent.conf" >> /usr/local/etc/php/conf.d/99-apminsight.ini && \
      echo "apminsight.loglevel=DEBUG" >> /usr/local/etc/php/conf.d/99-apminsight.ini && \
      echo "apminsight.logfile=/opt/site24x7/apminsight/php/logs/agent.log" >> /usr/local/etc/php/conf.d/99-apminsight.ini; \
    else \
      echo "❌ Site24x7 PHP agent not found in same RUN block"; \
      find / -name 'phpagent.so' || true; \
      ls -laR /opt/site24x7 || true; \
    fi

# Optional: View generated config for verification
RUN if [ -f /usr/local/etc/php/conf.d/99-apminsight.ini ]; then \
      cat /usr/local/etc/php/conf.d/99-apminsight.ini; \
    fi

# Install Site24x7 Data Exporter (optional)
RUN wget -O /tmp/InstallDataExporter.sh https://staticdownloads.site24x7.com/apminsight/S247DataExporter/linux/InstallDataExporter.sh && \
    chmod +x /tmp/InstallDataExporter.sh && \
    sh /tmp/InstallDataExporter.sh -root -nsvc -lk "${S247_LICENSE_KEY}" || echo "⚠️ Site24x7 Data Exporter install script failed"

# Verify Data Exporter install (optional)
RUN sh /tmp/InstallDataExporter.sh && \
    echo "✅ Installer ran" && \
    find /opt -type f -iname "*S247DataExporter*" || echo "❌ S247 files not found"

RUN ls -la /opt/site24x7/S247DataExporter/bin/ || echo "❌ site24x7/S247DataExporterDaemon folder missing"
RUN ls -la /opt/S247DataExporter/bin/ || echo "❌ S247DataExporterDaemon folder missing"

RUN test -x /opt/site24x7/S247DataExporter/bin/S247DataExporterDaemon || echo "❌ Daemon binary missing or not executable"

# Show Site24x7 agent install logs
RUN cat /opt/site24x7/apminsight/install.log || echo "⚠️ Install log not found"

# Cleanup
RUN rm -f /tmp/InstallAgentPHP.sh /tmp/InstallDataExporter.sh

# Install PHP extensions
RUN docker-php-ext-install mysqli

# Expose ports
EXPOSE 80 443

# Entrypoint
COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["sh", "/entrypoint.sh"]
