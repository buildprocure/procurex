FROM ilifesregistry.azurecr.io/ilifes/php-base:latest

# Declare ARGs for Site24x7
ARG S247_LICENSE_KEY
ARG ENV_NAME
ARG REPO_NAME

RUN echo "Environment: ${ENV_NAME}"
RUN echo "Repository: ${REPO_NAME}"
RUN echo "Site24x7 License Key: ${S247_LICENSE_KEY}"

# Copy Apache config and application code
COPY ./apache-config.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html

# Install Site24x7 Agent with environment-specific name
RUN /InstallAgentPHP.sh -lk "${S247_LICENSE_KEY}" -zpa.application_name "Buildprocure-${REPO_NAME}-${ENV_NAME}" && \
    /InstallDataExporter.sh -root -nsvc -lk "${S247_LICENSE_KEY}"

# Entrypoint setup
COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80 443

ENTRYPOINT ["sh", "/entrypoint.sh"]
