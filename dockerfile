# Use the new PHP-FPM base image
FROM ilifesregistry.azurecr.io/ilifes/php-base:latest

ARG S247_LICENSE_KEY
ARG ENV_NAME
ARG REPO_NAME

# Copy application code
COPY . /var/www/html

# Permissions
USER root
RUN chown -R buildprocure:buildprocure /var/www/html

# Run Site24x7 Agent
RUN /InstallAgentPHP.sh -lk "${S247_LICENSE_KEY}" -zpa.application_name "Buildprocure-${REPO_NAME}-${ENV_NAME}" && \
    /InstallDataExporter.sh -root -nsvc -lk "${S247_LICENSE_KEY}"

# Entrypoint
COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

USER buildprocure
WORKDIR /var/www/html

# PHP-FPM exposes 9000
EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
