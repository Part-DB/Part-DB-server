---
title: Authentication
layout: default
parent: API
nav_order: 2
---

# Authentication

To use API endpoints, the external application has to authenticate itself, so that Part-DB knows which user is accessing
the data and which permissions
the application should have during the access. Authentication is always bound to a specific user, so the external
applications is acting on behalf of a
specific user. This user limits the permissions of the application, so that it can only access data, which the user is
allowed to access.

The only method currently available for authentication is to use API tokens:

## API tokens

An API token is a long alphanumeric string, which is bound to a specific user and can be used to authenticate as this
user, when accessing the API.
The API token is passed via the `Authorization` HTTP header during the API request, like the
following: `Authorization: Bearer tcp_sdjfks....`.

{: .important }
> Everybody who knows the API token can access the API as the user, which is bound to the token. So you should treat the
> API token like a password
> and keep it secret. Only share it with trusted applications.

API tokens can be created and managed on the user settings page in the API token section. You can create as many API
tokens as you want and also delete them again.
When deleting a token, it is immediately invalidated and can not be used anymore, which means that the application can
not access the API anymore with this token.

### Token permissions and scopes

API tokens are ultimately limited by the permissions of the user, which belongs to the token. That means that the token
can only access data, which the user is allowed to access, no matter the token permissions.

But you can further limit the permissions of a token by choosing a specific scope for the token. The scope defines which
subset of permissions the token has, which can be less than the permissions of the user. For example, you can have a
user
with full read and write permissions, but create a token with only read permissions, which can only read data, but not
change anything in the database.

{: .warning }
> In general, you should always use the least possible permissions for a token, to limit the possible damage, which can
> be done with a stolen token or a bug in the application.
> Only use the full or admin scope, if you really need it, as they could potentially be used to do a lot of damage to
> your Part-DB instance.

Following token scopes are available:

* **Read-Only**: The token can only read non-sensitive data (like parts, but no users or groups) from the API and can
  not change anything.
* **Edit**: The token can read and write non-sensitive data via the API. This includes creating, updating and deleting
  data. This should be enough for most applications.
* **Admin**: The token can read and write all data via the API, including sensitive data like users and groups. This
  should only be used for trusted applications, which need to access sensitive data, and perform administrative actions.
* **Full**: The token can do anything the user can do, including changing the users password and create new tokens. This
  should only be used for highly trusted applications!!

Please note, that in early versions of the API, there might be no endpoints yet, to really perform the actions, which
would be allowed by the token scope.

### Expiration date

API tokens can have an expiration date, which means that the token is only valid until the expiration date. After that
the token is automatically invalidated and can not be used anymore. The token is still listed on the user settings page,
and can be deleted there, but the code can not be used to access Part-DB anymore after the expiration date.

### Get token information

When authenticating with an API token, you can get information about the currently used token by accessing
the `/api/tokens/current` endpoint.
It gives you information about the token scope, expiration date and the user, which is bound to the token and the last
time the token was used.