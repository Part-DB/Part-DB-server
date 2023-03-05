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

Set the `DEFAULT_URI` environment variable to the URL of your Part-DB installation, available from the outside (so via the reverse proxy).