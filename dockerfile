FROM ilifesregistry.azurecr.io/ilifes/php-base:latest

ARG S247_LICENSE_KEY
ARG ENV_NAME
ARG REPO_NAME

# Create the group and user
RUN groupadd -r buildprocure && useradd -r -g buildprocure buildprocure

# Apache config and certificates
COPY ./apache-config.conf /etc/apache2/sites-available/000-default.conf
RUN mkdir -p /etc/apache2/ssl
COPY certs/*.crt /etc/apache2/ssl/
COPY certs/*.key /etc/apache2/ssl/

# Enable Apache modules
RUN a2enmod ssl rewrite && a2ensite 000-default.conf

# Application code
COPY . /var/www/html

# Permissions
RUN chown -R buildprocure:buildprocure /var/www/html

# Run Site24x7 Agent
RUN /InstallAgentPHP.sh -lk "${S247_LICENSE_KEY}" -zpa.application_name "Buildprocure-${REPO_NAME}-${ENV_NAME}" && \
    /InstallDataExporter.sh -root -nsvc -lk "${S247_LICENSE_KEY}"

# Entrypoint
COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

USER buildprocure
EXPOSE 80 443

ENTRYPOINT ["sh", "/entrypoint.sh"]
