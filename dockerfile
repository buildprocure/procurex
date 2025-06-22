FROM ilifesregistry.azurecr.io/ilifes/php-base:latest

ARG S247_LICENSE_KEY
ARG ENV_NAME
ARG REPO_NAME

# ✅ Create buildprocure user & group
RUN groupadd -g 1000 buildprocure && \
    useradd -u 1000 -g buildprocure -m -s /bin/bash buildprocure

# Apache config and certificates
COPY ./apache-config.conf /etc/apache2/sites-available/000-default.conf
RUN mkdir -p /etc/apache2/ssl
COPY certs/*.crt /etc/apache2/ssl/
COPY certs/*.key /etc/apache2/ssl/

# Enable Apache modules
RUN a2enmod ssl rewrite && \
    a2ensite 000-default.conf

# Application code
COPY . /var/www/html

# ✅ Fix permissions so new user owns the files
RUN chown -R buildprocure:buildprocure /var/www/html

# Install Site24x7 Agent
RUN /InstallAgentPHP.sh -lk "${S247_LICENSE_KEY}" -zpa.application_name "Buildprocure-${REPO_NAME}-${ENV_NAME}" && \
    /InstallDataExporter.sh -root -nsvc -lk "${S247_LICENSE_KEY}"

# Entrypoint
COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# ✅ Switch to non-root user
USER buildprocure

EXPOSE 80 443

ENTRYPOINT ["sh", "/entrypoint.sh"]
