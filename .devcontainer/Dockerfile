FROM partdb-dev:local

ARG USERNAME=vscode
ARG USER_UID=1000
ARG USER_GID=${USER_UID}

RUN groupadd --gid "${USER_GID}" "${USERNAME}" \
    && useradd --uid "${USER_UID}" --gid "${USER_GID}" --create-home --shell /bin/bash "${USERNAME}" \
    && usermod --append --groups www-data "${USERNAME}" \
    && printf '%s ALL=(root) NOPASSWD:ALL\n' "${USERNAME}" > "/etc/sudoers.d/${USERNAME}" \
    && chmod 0440 "/etc/sudoers.d/${USERNAME}"

COPY .devcontainer/apache-ssl.conf /etc/apache2/sites-available/partdb-dev-ssl.conf

RUN mkdir -p /etc/apache2/ssl \
    && openssl req -x509 -newkey rsa:2048 -nodes -days 825 \
        -keyout /etc/apache2/ssl/partdb-dev.key \
        -out /etc/apache2/ssl/partdb-dev.crt \
        -subj "/CN=localhost" \
        -addext "subjectAltName=DNS:localhost,IP:127.0.0.1,IP:::1" \
    && chmod 0600 /etc/apache2/ssl/partdb-dev.key \
    && a2enmod ssl \
    && a2ensite partdb-dev-ssl

EXPOSE 443
