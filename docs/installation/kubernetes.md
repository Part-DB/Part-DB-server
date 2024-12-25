---
title: Installation using Kubernetes & Helm
layout: default
parent: Installation
nav_order: 5
---

# Kubernetes / Helm Charts

If you are using Kubernetes, you can use the [helm charts](https://helm.sh/) provided in this [repository](https://github.com/Part-DB/helm-charts).

## Usage

[Helm](https://helm.sh) must be installed to use the charts.  Please refer to
Helm's [documentation](https://helm.sh/docs) to get started.

Once Helm has been set up correctly, add the repo as follows:

`helm repo add part-db https://part-db.github.io/helm-charts`

If you had already added this repo earlier, run `helm repo update` to retrieve
the latest versions of the packages.  You can then run `helm search repo
part-db` to see the charts.

To install the part-db chart:

    helm install my-part-db part-db/part-db

To uninstall the chart:

    helm delete my-part-db

This repository is also available at [ArtifactHUB](https://artifacthub.io/packages/search?repo=part-db).

## Configuration

See the README in the [chart directory](https://github.com/Part-DB/helm-charts/tree/main/charts/part-db) for more
information on the available configuration options.

## Bugreports

If you find issues related to the helm charts, please open an issue in the [helm-charts repository](https://github.com/Part-DB/helm-charts).