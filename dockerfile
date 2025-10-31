# =========================================================
# Combined Apache + PHP-FPM image for BuildProcure
# Base: PHP 8.2 with Site24x7 Agent
# =========================================================

FROM ilifesregistry.azurecr.io/ilifes/php-base:AB21_apache-fpm-setup-on-base-image

ARG S247_LICENSE_KEY
ARG ENV_NAME
ARG REPO_NAME

USER root

# Configure Apache for PHP-FPM socket
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Copy SSL certificates
COPY certs/ /usr/local/apache2/conf/ssl/

# Copy application code
COPY . /var/www/html

# Permissions
RUN chown -R buildprocure:www-data /var/www/html

# Run Site24x7 agent setup
RUN /InstallAgentPHP.sh -lk "${S247_LICENSE_KEY}" -zpa.application_name "Buildprocure-${REPO_NAME}-${ENV_NAME}" && \
    /InstallDataExporter.sh -root -nsvc -lk "${S247_LICENSE_KEY}"

# Copy entrypoint
COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80 443

WORKDIR /var/www/html
ENTRYPOINT ["/entrypoint.sh"]
