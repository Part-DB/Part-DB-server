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

{: .important }
> Part-DB associates local users with SAML users by their username. That means if the username of a SAML user changes, a new local user will be created (and the old account can not be accessed).
> You should make sure that the username of a SAML user does not change. If you use Keycloak make sure that the possibility to change the username is disabled (which is by default).
> If you really have to rename a SAML user, a Part-DB admin can rename the local user in the Part-DB in the admin panel, to match the new username of the SAML user.

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

### Configure permissions for SAML users
On the first login of a SAML user, Part-DB will create a new user in the database. This user will have the same username as the SAML user, but no password set. The user will be marked as a SAML user, so he can only login via SAML in the future. However in other aspects the user is a normal user, so Part-DB admins can set permissions for SAML users like for any other user and override permissions assigned via groups.

However for large organizations you maybe want to automatically assign permissions to SAML users based on the roles or groups configured in the identity provider. For this purpose Part-DB allows you to map SAML roles or groups to Part-DB groups. See the next section for details.

### Map SAML roles to Part-DB groups
Part-DB allows you to configure a mapping between SAML roles or groups and Part-DB groups. This allows you to automatically assign permissions to SAML users based on the roles or groups configured in the identity provider. For example if a user at your SAML provider has the role `admin`, you can configure Part-DB to assign the `admin` group to this user. This will give the user all permissions of the `admin` group.

For this you need first have to create the groups in Part-DB, to which you want to assign the users and configure their permissions. You will need the IDs of the groups, which you can find in the `System->Group` page of Part-DB in the Info tab.

The map is provided as [JSON](https://en.wikipedia.org/wiki/JSON) encoded map between the SAML role and the group ID, which has the form `{"saml_role": group_id, "*": group_id, ...}`. You can use the `*` key to assign a group to all users, which are not in any other group. The map is configured via the `SAML_ROLE_MAPPING` environment variable, which you can configure via the `.env.local` or `docker-compose.yml` file. Please note that you have to enclose the JSON string in single quotes here, as JSON itself uses double quotes (e.g. `SAML_ROLE_MAPPING='{ "*": 2, "editor": 3, "admin": 1 }`).

For example if you want to assign the group with ID 1 (by default admin) to every SAML user which has the role `admin`, the role with ID 3 (by default editor) to every SAML user with the role `editor` and everybody else to the group with ID 2 (by default readonly), you can configure the following map:

```
SAML_ROLE_MAPPING='{"admin": 1, "editor": 3, "*": 2}'
```

Please not that the order of the mapping is important. The first matching role will be assigned to the user. So if you have a user with the roles `admin` and `editor`, he will be assigned to the group with ID 1 (admin) and not to the group with ID 3 (editor), as the `admin` role comes first in the JSON map.
This mean that you should always put the most specific roles (e.g. admins) first of the map and the most general roles (e.g. normal users) later.

If you want to assign users with a certain role to a empty group, provide the group ID -1 as the value. This is not a valid group ID, so the user will not be assigned to any group.

The SAML roles (or groups depending on your configuration), have to be supplied via a SAML attribute `group`. You have to configure your SAML identity provider to provide this attribute. For example in Keycloak you can configure this attribute in the `Client scopes` page. Select the `sp-dedicated` client scope (or create a new one) and click on `Add mappers`. Select `Role mapping` or `Group membership`, change the field name and click `Add`. Now Part-DB will be provided with the groups of the user based on the Keycloak user database.

By default, the group is assigned to the user on the first login and updated on every login based on the SAML attributes. This allows you to configure the groups in the SAML identity provider and the users will automatically stay up to date with their permissions. However, if you want to disable this behavior (and let the Part-DB admins configure the groups manually, after the first login), you can set the `SAML_UPDATE_GROUP_ON_LOGIN` environment variable to `false`. If you want to disable the automatic group assignment completly (so not even on the first login of a user), set the `SAML_ROLE_MAPPING` to `{}` (empty JSON object).

### Overview of possible SAML attributes used by Part-DB
The following table shows all SAML attributes, which can be usedby Part-DB. If your identity provider is configured to provide these attributes, you can use to automatically fill the corresponding fields of the user in Part-DB.

| SAML attribute                            | Part-DB user field | Description                                                       |
|-------------------------------------------|-------------------|-------------------------------------------------------------------|
| `urn:oid:1.2.840.113549.1.9.1` or `email` | email             | The email address of the user.                                    |
| `urn:oid:2.5.4.42` or `firstName`         | firstName         | The first name of the user.                                       |
| `urn:oid:2.5.4.4` or `lastName`           | lastName          | The last name of the user.                                        |
| `department`                              | department        | The department of the user.                                       |
| `group`                                   | group             | The group of the user (determined by `SAML_ROLE_MAPPING` option). |

### Use SAML Login for existing users
Part-DB distinguishes between local users and SAML users. Local users are users, which can login via Part-DB login form and which use the password (hash) saved in the Part-DB database. SAML users are stored in the database too (they are created on the first login of the user via SAML), but they use the SAML identity provider to authenticate the user and have no password stored in the database. When you try you will get an error message.

For security reasons it is not possible to authenticate via SAML as a local user (and vice versa). So if you have existing users in your Part-DB database and want them to be able to login via SAML in the future, you can use the `php bin/console partdb:user:convert-to-saml-user username` command to convert them to SAML users. This will remove the password hash from the database and mark them as SAML users, so they can login via SAML in the future.

The reverse is also possible: If you have existing SAML users and want them to be able to login via the Part-DB login form, you can use the `php bin/console partdb:user:convert-to-saml-user --to-local username` command to convert them to local users. You have to set an password for the user afterwards.

{: .important }
> It is recommended that you let the original admin user (ID: 2) be a local user, so you can still login to Part-DB if the SAML identity provider is not available.