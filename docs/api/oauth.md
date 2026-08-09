---
title: OAuth2
layout: default
parent: API
nav_order: 3
---

# OAuth2

{: .new }
> This feature was added recently and might still change in future versions.

Besides [API tokens]({% link api/authentication.md %}), Part-DB can act as an **OAuth 2.0 authorization server**.
This lets an external application or AI agent obtain its own access token for a user by sending that user through a
normal login-and-consent flow, instead of the user having to manually create and paste an API token into the
application. This is especially useful for MCP clients and other apps that can auto-provision their own credentials.

{: .warning }
> The OAuth2 server is **disabled by default**. Only enable it if you actually have applications that support
> OAuth2, and understand that (depending on your configuration) it may allow applications to register themselves
> automatically, see [Dynamic Client Registration](#dynamic-client-registration-optional) below.

## Enabling the OAuth2 server

The OAuth2 server is controlled by environment variables in your `.env`/`.env.local` file:

* **`OAUTH2_ENCRYPTION_KEY`**: A secret key used to encrypt authorization codes and refresh tokens.
  {: .important }
  > **Change `OAUTH2_ENCRYPTION_KEY` before enabling the OAuth2 server in production.** The value shipped in
  > Part-DB's default `.env` file is the same for every installation and is publicly known, so leaving it in place
  > would let anyone decrypt authorization codes and refresh tokens issued by your instance.
  >
  > Generate your own value with:
  > ```bash
  > bin/console partdb:oauth:generate-secret
  > ```
  > and add the printed line to your `.env.local`. Once you start using the OAuth2 server, keep the key secret and
  > do **not** change it again — doing so invalidates all outstanding refresh tokens and any authorization codes
  > that are currently in flight.

* **`OAUTH_SERVER_ENABLED`** (default `0`): The master switch for the whole feature. Set it to `1` to enable the
  `/oauth/authorize` and `/oauth/token` endpoints, the `/tools/oauth_clients` admin overview, the OAuth discovery endpoints
  under `/.well-known/`, and OAuth2 bearer token authentication in general. While disabled, all of these are
  unreachable and Part-DB behaves as if the feature didn't exist.

* **`OAUTH_DCR_ENABLED`** (default `0`): Additionally enables open, unauthenticated **Dynamic Client Registration**
  (see below). Has no effect unless `OAUTH_SERVER_ENABLED` is also set to `1`. Regardless of this setting, an
  administrator can always register OAuth clients by hand via the admin UI.

After enabling the server (or on first install), an administrator needs to generate the RSA keypair that is used to
sign access tokens, by running:

```bash
bin/console partdb:oauth:generate-keys
```

This creates `uploads/oauth_private.key` and `uploads/oauth_public.key`. The command will warn you if
`OAUTH2_ENCRYPTION_KEY` is not set. Use `--force` to overwrite an existing keypair (this invalidates all existing
OAuth tokens).

## Supported flow

Part-DB only implements the parts of OAuth2 needed for user-facing applications (like AI agents or third-party
tools acting on behalf of a logged-in user):

* **Authorization Code grant with PKCE** — the standard browser/redirect based login-and-consent flow. PKCE is
  required for all clients (Part-DB only issues "public" clients without a client secret, see below).
* **Refresh Token grant** — used to obtain new access tokens without repeating the login flow, until the refresh
  token itself expires or is revoked.

Other OAuth2 grant types (Client Credentials, Resource Owner Password, Implicit, Device Code) are **not**
supported, since they don't fit Part-DB's user-centric permission model.

Token lifetimes are fixed:

| Token type | Lifetime |
|---|---|
| Access token | 1 hour |
| Refresh token | 30 days |
| Authorization code | 10 minutes |

## Registering an OAuth client

Before an application can use the OAuth2 flow, it needs to be registered as a **client** of your Part-DB instance.
Only public clients are supported: no client secret is issued or required, and the client authenticates the
authorization code exchange using PKCE instead.

Redirect URIs must be one of:

* an `https://` URL,
* a loopback `http://` URL (`127.0.0.1`, `localhost` or `::1`, any port) — for local/CLI tools, or
* a private-use URI scheme in reverse-DNS style, e.g. `com.example.app:/callback` — for native/mobile apps.

There are two ways to register a client:

### Manual registration (admin)

An administrator with the **Manage OAuth clients** permission (under **System**) can register, view and delete
OAuth clients on the **`/tools/oauth_clients`** admin page. This page also shows how many live tokens each
registered client currently holds, which is useful for spotting abandoned or abusive clients.

### Dynamic Client Registration (optional)

If `OAUTH_DCR_ENABLED=1`, applications can self-register a client without any administrator interaction, by
sending an unauthenticated `POST /oauth/register` request following
[RFC 7591](https://www.rfc-editor.org/rfc/rfc7591) (Dynamic Client Registration). For example:

```bash
curl -X POST https://your-part-db.local/oauth/register \
  -H "Content-Type: application/json" \
  -d '{
        "client_name": "My App",
        "redirect_uris": ["https://myapp.example.com/callback"]
      }'
```

This endpoint is rate-limited to 20 requests per hour per IP address to prevent abuse. Since registering a client
grants it no access by itself (a user still has to explicitly log in and consent on `/oauth/authorize` before any token
is issued), this is considered safe to expose publicly. Registered clients — whether self-registered or created by
an admin — still show up on the `/tools/oauth_clients` admin page and can be deleted there at any time.

## Discovery

Clients that support it can auto-configure themselves using the standard discovery metadata endpoints (only
reachable when `OAUTH_SERVER_ENABLED=1`):

* `/.well-known/oauth-authorization-server` — [RFC 8414](https://www.rfc-editor.org/rfc/rfc8414) authorization
  server metadata (`authorization_endpoint`, `token_endpoint`, supported scopes/grants, and the
  `registration_endpoint` if Dynamic Client Registration is enabled).
* `/.well-known/oauth-protected-resource` — [RFC 9728](https://www.rfc-editor.org/rfc/rfc9728) protected resource
  metadata.

## Login and consent

Once a client is registered, it can start the standard OAuth2 Authorization Code flow by redirecting the user's
browser to `/oauth/authorize` with the usual query parameters (`client_id`, `redirect_uri`, `response_type=code`,
`code_challenge`, `code_challenge_method=S256`, optionally `scope` and `state`).

* If the user isn't logged in yet, they are sent through the normal login (and two-factor, if enabled) flow first,
  then redirected back to `/oauth/authorize`.
* The user is then shown a consent screen where they choose:
  * the **scope** to grant the application (see below), defaulting to the client's requested scope but never
    higher than what the requesting user is themselves allowed to do,
  * an optional **friendly name** for the connection, to recognize it later,
  * how long the resulting **refresh token** should stay valid (never / 1 / 7 / 30 / 90 / 365 days).
* After the user approves, Part-DB redirects back to the client's `redirect_uri` with an authorization code, which
  the client then exchanges for an access token (and, if granted, a refresh token) at `/oauth/token`.

## Scopes and permissions

OAuth2 scopes use the same four tiers as [API token scopes]({% link api/authentication.md %}#token-permissions-and-scopes):
`read_only`, `edit`, `admin` and `full`, with the same meaning. Internally, they grant the exact same roles a
Personal Access Token of the corresponding level would grant, so they are limited by the user's own permissions in
exactly the same way — an OAuth token can never do more than the user who approved it is allowed to do.

{: .warning }
> As with API tokens, treat an approved OAuth connection like a password: anyone holding the resulting access or
> refresh token can act as the user, within the granted scope, until it expires or is revoked.

## Using an OAuth access token

Once issued, an OAuth2 access token is used exactly like an [API token]({% link api/authentication.md %}): pass it
as a bearer token in the `Authorization` header, e.g. `Authorization: Bearer <access_token>`. It is accepted
anywhere an API token is, namely the [REST API]({% link api/intro.md %}) (`/api`, `/kicad-api`) and the
[MCP server]({% link api/mcp.md %}) (`/mcp`), subject to the same permission checks (for example, the **Use MCP
tools** permission is still required to use MCP).

## Managing connected apps

Users can see and revoke the OAuth applications they have connected to on their user settings page, under
**Connected apps**. Revoking access there immediately invalidates the application's access and refresh tokens for
that user.
