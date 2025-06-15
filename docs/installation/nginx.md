---
title: Nginx
layout: default
parent: Installation
nav_order: 10
---

# Nginx

You can also use [nginx](https://www.nginx.com/) as webserver for Part-DB. Setup Part-DB with apache is a bit easier, so
this is the method shown in the guides. This guide assumes that you already have a working nginx installation with PHP
configured.

## Setup

1. Install composer and yarn as described in the [apache guide]({% link installation/installation_guide-debian.md
   %}#install-composer).
2. Create a folder for Part-DB and install and configure it as described
3. Instead of creating the config for apache, add the following snippet to your nginx config:

```nginx
server {
    # Redirect all HTTP requests to HTTPS
    listen 80;
    # Change this to your domain
    server_name parts.example.com;
    return 301 https://$host$request_uri;
}
server {
#   listen 80;
    listen 443 ssl;
    
    # Change this to your domain
    server_name parts.example.com;
    # /var/www/partdb/ should be the path to the folder where you installed Part-DB
    root /var/www/partdb/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;

        internal;
    }

    location ~ \.php$ {
        return 404;
    }
    
    # Set Content-Security-Policy for svg files, to block embedded javascript in there
    location ~* \.svg$ {
        add_header Content-Security-Policy "default-src 'self'; script-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none';";
    }

    error_log /var/log/nginx/parts.error.log;
    access_log /var/log/nginx/parts.access.log;

    # SSL parameters
    ssl_certificate /var/www/certs/SSL/domain.cert.pem;
    ssl_certificate_key /var/www/certs/SSL/private.key.pem;
    ssl_trusted_certificate /var/www/certs/SSL/intermediate.cert.pem;

    ssl_session_timeout 5m;

    ssl_protocols TLSv1.2;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
}
```

4. Restart nginx with `sudo systemctl restart nginx` and you should be able to access Part-DB under your configured
   domain.