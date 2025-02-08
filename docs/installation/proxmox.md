---
title: Proxmox VE LXC
layout: default
parent: Installation
nav_order: 6
---

# Proxmox VE LXC

{: .warning }
> The proxmox VE LXC script for Part-DB is developed and maintained by [Proxmox VE Helper-Scripts](https://community-scripts.github.io/ProxmoxVE/)
> and not by the Part-DB developers. Keep in mind that the script is not officially supported by the Part-DB developers.

If you are using Proxmox VE you can use the scripts provided by [Proxmox VE Helper-Scripts community](https://community-scripts.github.io/ProxmoxVE/scripts?id=part-db)
to easily install Part-DB in a LXC container.

## Usage

To create a new LXC container with Part-DB, you can use the following command in the Proxmox VE shell:

```bash
bash -c "$(wget -qLO - https://github.com/community-scripts/ProxmoxVE/raw/main/ct/part-db.sh)"
```

The same command can be used to update an existing Part-DB container.

See the [helper script website](https://community-scripts.github.io/ProxmoxVE/scripts?id=part-db) for more information.

## Bugreports

If you find issues related to the proxmox VE LXC script, please open an issue in the [Proxmox VE Helper-Scripts repository](https://github.com/community-scripts/ProxmoxVE).
