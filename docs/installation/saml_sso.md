---
title: Single Sign-On via SAML
layout: default
parent: Installation
nav_order: 12
---

# Single Sign-On via SAML

Part-DB supports Single Sign-On via SAML. This means that you can use your existing SAML identity provider to log in to Part-DB. 
Using an intermediate SAML server like [Keycloak](https://www.keycloak.org/), also allows you to connect Part-DB to a LDAP or Active Directory server.

{: .important }
> This feature is for advanced users only. Single Sign-On is useful for large organizations with many users, which are already using SAML for other services.
> If you have only one or a few users, you should use the built-in authentication system of Part-DB.
> This guide assumes that you already have an SAML identity provider set up and working, and have a basic understanding of how SAML works.

{: .warning }
> This feature is currently in beta. Please report any bugs you find.
> So far it has only tested with Keycloak, but it should work with any SAML 2.0 compatible identity provider.

This guide will show you how to configure Part-DB with [Keycloak](https://www.keycloak.org/) as the SAML identity provider,
but it should work with any SAML 2.0 compatible identity provider. 

This guide assumes that you have a working Keycloak installation with some users. If you don't, you can follow the [Keycloak Getting Started Guide](https://www.keycloak.org/docs/latest/getting_started/index.html).

## Configure basic SAML connection

### Create a new SAML client
1. First, you need to configure a new SAML client in Keycloak. Login in to your Keycloak admin console and go to the `Clients` page.
2. Click on `Create client` and select `SAML` as type from the dropdown menu. For the client ID, you can use anything you want, but it should be unique. 
*It is recommended to set this value to the domain name of your Part-DB installation, with an attached `/sp` (e.g. `https://partdb.yourdomain.invalid/sp`)*.
The name field should be set to something human-readable, like `Part-DB`.
3. Click on `Save` to create the new client.

### Configure the SAML client

1. Now you need to configure the SAML client. Go to the `Settings` tab and set the following values:
    * Set `Home URL` to the homepage of your Part-DB installation (e.g. `https://partdb.yourdomain.invalid/`).
    * Set `Valid redirect URIs` to your homepage with a wildcard at the end (e.g. `https://partdb.yourdomain.invalid/*`).
    * Set `Valid post logout redirect URIs` to `+` to allow all urls from the `Valid redirect URIs`.
    * Set `Name ID format` to `username`
    * Ensure `Force POST binding` is enabled.
    * Ensure `Sign documents` is enabled.
    * Ensure `Front channel logout` is enabled.
    * Ensure `Signature Algorithm` is set to `RSA_SHA256`.

   Click on `Save` to save the changes.
2. Go to the `Advanced` tab and set the following values:
    * Assertion Consumer Service POST Binding URL to your homepage with `/saml/acs` at the end (e.g. `https://partdb.yourdomain.invalid/saml/acs`).
    * Logout Service POST Binding URL to your homepage with `/logout` at the end (e.g. `https://partdb.yourdomain.invalid/logout`).
3. Go to Keys tab and ensure `Client Signature Required` is enabled.
4. In the Keys tab click on `Generate new keys`. This will generate a new key pair for the SAML client. The private key will be downloaded to your computer.

### Configure Part-DB to use SAML
1. Open the `.env.local` file of Part-DB (or the docker-compose.yaml) for edit
2. Set the `SAMLP_SP_PRIVATE_KEY` environment variable to the content of the private key file you downloaded in the previous step. It should start with `MIEE` and end with `=`.
3. Set the `SAML_SP_X509_CERT` environment variable to the content of the Certificate field shown in the `Keys` tab of the SAML client in Keycloak. It should start with `MIIC` and end with `=`.
4. Set the `SAML_SP_ENTITY_ID` environment variable to the entityID of the SAML client in Keycloak (e.g. `https://partdb.yourdomain.invalid/sp`).
5. In Keycloak navigate to `Realm Settings` -> `SAML 2.0 Identity Provider` (by default something like `https://idp.yourdomain.invalid/realms/master/protocol/saml/descriptor) to show the SAML metadata.
6. Copy the `entityID` value from the metadata to the `SAML_IDP_ENTITY_ID` configuration variable of Part-DB (by default something like `https://idp.yourdomain.invalid/realms/master`).
7. Copy the `Single Sign-On Service` value from the metadata to the `SAML_IDP_SINGLE_SIGN_ON_SERVICE` configuration variable of Part-DB (by default something like `https://idp.yourdomain.invalid/realms/master/protocol/saml`).
8. Copy the `Single Logout Service` value from the metadata to the `SAML_IDP_SINGLE_LOGOUT_SERVICE` configuration variable of Part-DB (by default something like `https://idp.yourdomain.invalid/realms/master/protocol/saml/logout`).
9. Copy the `X.509 Certificate` value from the metadata to the `SAML_IDP_X509_CERT` configuration variable of Part-DB (it should start with `MIIC` and should be pretty long).
10. Set the `DEFAULT_URI` to the homepage (on the publicly available domain) of your Part-DB installation (e.g. `https://partdb.yourdomain.invalid/`). It must end with a slash.
11. Set the `SAML_ENABLED` configuration in Part-DB to 1 to enable SAML authentication.

When you access the Part-DB login form now, you should see a new button to log in via SSO. Click on it to be redirected to the SAML identity provider and log in.

In the following sections, you will learn how to configure that Part-DB uses the data provided by the SAML identity provider to fill out user informations.

### Set user information based on SAML attributes
Part-DB can set basic user information like the username, the real name and the email address based on the SAML attributes provided by the SAML identity provider.
To do this, you need to configure your SAML identity provider to provide the following attributes:

* `email` or `urn:oid:1.2.840.113549.1.9.1` for the email address
* `firstName` or `urn:oid:2.5.4.42` for the first name
* `lastName` or `urn:oid:2.5.4.4` for the last name
* `department` for the department field of the user

You can omit any of these attributes, but then the corresponding field will be empty (but can be overriden by an administrator).
These values are written to Part-DB database, whenever the user logs in via SAML (the user is created on the first login, and updated on every login).

To configure Keycloak to provide these attributes, you need to go to the `Client scopes` page and select the `sp-dedicatd` client scope (or create a new one).
In the scope configuration page, click on `Add mappers` and `From predefined mappers`. Select the following mappers:
* `X500 email`
* `X500 givenName`
* `X500 surname`

and click `Add`. Now Part-DB will be provided with the email, first name and last name of the user based on the Keycloak user database.