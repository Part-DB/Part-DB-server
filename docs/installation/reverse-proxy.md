---
title: Reverse proxy
layout: default
parent: Installation
nav_order: 11
---

# Reverse proxy

If you want to put Part-DB behind a reverse proxy, you have to configure Part-DB correctly to make it work properly.

You have to set the `TRUSTED_PROXIES` environment variable to the IP address of your reverse proxy
(either in your `docker-compose.yaml` in the case of docker, or `.env.local` in case of direct installation).
If you have multiple reverse proxies, you can set multiple IP addresses separated by a comma (or specify a range).

For example, if your reverse proxy has the IP address `192.168.2.10`, your value should be:

```
TRUSTED_PROXIES=192.168.2.10
```

Set the `DEFAULT_URI` environment variable to the URL of your Part-DB installation, available from the outside (so via
the reverse proxy).

## Part-DB in a subpath via reverse proxy

If you put Part-DB into a subpath via the reverse proxy, you have to configure your webserver to include `X-Forwarded-Prefix` in the request headers.
For example if you put Part-DB behind a reverse proxy with the URL `https://example.com/partdb`, you have to set the `X-Forwarded-Prefix` header to `/partdb`.

In apache, you can do this by adding the following line to your virtual host configuration:

```
RequestHeader set X-Forwarded-Prefix "/partdb"
```

and in nginx, you can do this by adding the following line to your server configuration:

```
proxy_set_header X-Forwarded-Prefix "/partdb";
```