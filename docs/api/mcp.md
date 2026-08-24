---
title: MCP Server
layout: default
parent: API
nav_order: 4
---

# MCP Server

{: .new }
> This feature was added recently and might still change in future versions.

Part-DB ships a [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) server, which allows AI assistants and
agents (like Claude, ChatGPT, or AI-powered coding tools) to directly interact with your Part-DB inventory: they can
search for parts, look up categories, footprints, manufacturers, storage locations, suppliers, and projects, and even
query external info providers like Digikey, Mouser or LCSC, all using natural language, without you having to write
any code against the [REST API]({% link api/intro.md %}).

MCP is a standardized, widely supported protocol, so once your Part-DB MCP endpoint is set up, you can connect it to
many different AI clients and applications.

{: .warning }
> The MCP integration is currently **read-only**: an AI assistant can look up data, but it can not create, change or
> delete anything in your Part-DB instance via MCP.
> Still, giving an AI assistant access to your inventory means it can read everything the connected user account is
> allowed to see, so only connect trusted AI clients and keep your API token secret, just like you would for the
> [REST API]({% link api/authentication.md %}).

## Enabling the MCP server

The MCP server is disabled by default and has to be enabled by an administrator first:

1. Open the system settings and go to the **AI** tab.
2. In the **MCP (Model Context Protocol) Server** section, enable the **Enable MCP endpoint** checkbox.

This can also be controlled via the `MCP_ENABLED` environment variable.

Once enabled, the MCP server is reachable under the `/mcp` path of your Part-DB instance (e.g.
`https://your-part-db.local/mcp`). Unlike most other Part-DB pages, this path is **not** locale-prefixed (so it is
`/mcp`, not `/en/mcp`).

{: .note }
> If your MCP client gets a `Forbidden: Invalid Host header` response, set the `TRUSTED_HOSTS` environment variable
> (see the comments in `.env`) to include your Part-DB domain name. The MCP endpoint validates the `Host` header
> against this setting, just like the rest of Part-DB.

## Permissions

Users which should be allowed to use the MCP tools additionally need the **Use MCP tools (for AI agents)** permission
(under the **API** permission group). Granting it automatically also grants the base **Access API** permission.

Like the REST API, authentication against the MCP endpoint is done using an [API token]({% link
api/authentication.md %}) or an [OAuth2 access token]({% link api/oauth.md %}). Either way, a **Read-Only** scope
is enough to use all currently available MCP tools, as they only read data.

## Connecting an AI client

To connect an AI client to Part-DB, you need two things:

* The **MCP endpoint URL**, e.g. `https://your-part-db.local/mcp`. Once you have the required permission, you can
  also find it on the **API** panel of your user settings page, under "MCP endpoint", together with a
  copy-to-clipboard button.
* An **API token**. Create one on the same **API** panel of your user settings page (see
  [Authentication]({% link api/authentication.md %}) for details about tokens and scopes). A token with the
  **Read-Only** scope is sufficient. If the MCP client itself supports OAuth2 (many do, since it lets the client
  provision its own credentials interactively), you can instead let it connect via [OAuth2]({% link
  api/oauth.md %}) and skip creating an API token manually.

The client has to send this token as a bearer token in the `Authorization` header of every request:
`Authorization: Bearer tcp_<your-token>`. How exactly you configure this depends on the AI client you use; some
examples for common clients are shown below.

{: .note }
> Part-DB's MCP server only supports the **Streamable HTTP** transport (no stdio, no plain SSE). Most modern MCP
> clients support this transport directly. Clients that only support local, stdio-based MCP servers can be bridged to
> a remote HTTP server with a small proxy tool like [`mcp-remote`](https://www.npmjs.com/package/mcp-remote), as shown
> in the Claude Desktop example below.

MCP client configuration formats change frequently, so if the examples below don't quite match what you see in your
client, check the client's own documentation for how to add a remote MCP server with a custom `Authorization` header.

### Claude Code

Add the server with the Claude Code CLI:

```bash
claude mcp add part-db https://your-part-db.local/mcp \
  --transport http \
  --header "Authorization: Bearer tcp_<your-token>"
```

### Claude Desktop

Claude Desktop currently only launches local (stdio) MCP servers directly from its config file, so a remote server
like Part-DB's has to be bridged with the [`mcp-remote`](https://www.npmjs.com/package/mcp-remote) proxy. Open
Claude Desktop's configuration file (**Settings → Developer → Edit Config**) and add:

```json
{
  "mcpServers": {
    "part-db": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "https://your-part-db.local/mcp",
        "--header",
        "Authorization:${AUTH_HEADER}"
      ],
      "env": {
        "AUTH_HEADER": "Bearer tcp_<your-token>"
      }
    }
  }
}
```

{: .note }
> The header is split into `--header "Authorization:${AUTH_HEADER}"` plus an `env` entry, instead of the more obvious
> `--header "Authorization: Bearer tcp_<your-token>"`, to avoid a
> [known `mcp-remote`/Claude Desktop bug on Windows](https://github.com/Part-DB/Part-DB-server/issues/1480): Claude
> Desktop passes `args` to `npx.cmd` via `cmd /C`, which mishandles quoting when an argument contains a space. Since
> `npx` on Windows resolves to a `.cmd` file, this breaks whenever Node.js is installed at its default location
> (`C:\Program Files\nodejs`), because that path also contains a space. Keeping every argument space-free avoids the
> bug; `${AUTH_HEADER}` is expanded by `mcp-remote` itself from the `env` block, not by the shell, and the missing
> space after `Authorization:` is intentional — `mcp-remote` re-adds it when parsing the header. This form also works
> unchanged on macOS and Linux.

### Claude.ai (remote connector)

Unlike Claude Desktop's local-only config file (see above), Claude.ai (the web app, and the Claude mobile/desktop
apps once signed in) can add Part-DB directly as a **remote connector** over Streamable HTTP, with no `mcp-remote`
proxy and no manually created API token. Instead, Claude authenticates via [OAuth2]({% link api/oauth.md %}), so
this method requires the OAuth2 server to be enabled (`OAUTH_SERVER_ENABLED=1`).

{: .note }
> Custom connectors are only available on paid Claude plans (Pro, Max, Team or Enterprise), not the free plan.

1. In Claude, go to **Settings → Connectors → Add custom connector**.
2. Enter your Part-DB MCP endpoint URL, e.g. `https://your-part-db.local/mcp`, and confirm.
3. Claude redirects you to Part-DB's `/oauth/authorize` login-and-consent screen. If Dynamic Client Registration
   (`OAUTH_DCR_ENABLED=1`) is enabled, Claude registers itself as an OAuth client on the fly; otherwise an
   administrator first needs to register Claude as a client by hand on the `/tools/oauth_clients` admin page, using
   the redirect URI Claude shows at this step.
4. Log in (if you aren't already) and approve the requested scope — **Read-Only** is enough. Claude then obtains and
   manages its own access and refresh tokens, so there is nothing further to configure.

### ChatGPT (remote connector)

Similar to Claude.ai, ChatGPT can add Part-DB as a remote connector over Streamable HTTP, authenticating via
[OAuth2]({% link api/oauth.md %}) instead of a manually pasted token. Full, unrestricted tool access currently
requires enabling ChatGPT's **Developer mode**, since ChatGPT's regular built-in connectors restrict custom MCP
servers to search/fetch-style actions.

{: .note }
> Custom connectors require a paid ChatGPT plan (Plus, Pro, Team, Enterprise or Edu) and are not available on the
> free plan.

1. In ChatGPT, go to **Settings → Connectors**, open **Advanced settings**, and enable **Developer mode** (needed to
   use the full set of Part-DB's MCP tools, rather than only the restricted search/fetch actions regular connectors
   get).
2. Back on the **Connectors** page, choose **Create** and enter your Part-DB MCP endpoint URL, e.g.
   `https://your-part-db.local/mcp`, together with a name for the connector.
3. Select **OAuth** as the authentication method and save. ChatGPT redirects you to Part-DB's `/oauth/authorize`
   login-and-consent screen. As with Claude.ai, this requires the OAuth2 server to be enabled
   (`OAUTH_SERVER_ENABLED=1`); if Dynamic Client Registration (`OAUTH_DCR_ENABLED=1`) is off, an administrator first
   needs to register ChatGPT as a client by hand on the `/tools/oauth_clients` admin page, using the redirect URI
   ChatGPT shows at this step.
4. Log in (if you aren't already) and approve the requested scope — **Read-Only** is enough. ChatGPT then obtains
   and manages its own access and refresh tokens.
5. Enable the Part-DB connector for a chat via the tools/**+** menu to start using it.

### Google Antigravity

Open **Manage MCP Servers** and add the server via its JSON configuration:

```json
{
  "mcpServers": {
    "part-db": {
      "serverUrl": "https://your-part-db.local/mcp",
      "headers": {
        "Authorization": "Bearer tcp_<your-token>"
      }
    }
  }
}
```

### Cursor

Add the following to your `.cursor/mcp.json` (project-specific) or global Cursor MCP settings:

```json
{
  "mcpServers": {
    "part-db": {
      "url": "https://your-part-db.local/mcp",
      "headers": {
        "Authorization": "Bearer tcp_<your-token>"
      }
    }
  }
}
```

### VS Code (MCP support / GitHub Copilot)

Add the following to your `.vscode/mcp.json` (or use the **MCP: Add Server** command from the command palette):

```json
{
  "servers": {
    "part-db": {
      "type": "http",
      "url": "https://your-part-db.local/mcp",
      "headers": {
        "Authorization": "Bearer tcp_<your-token>"
      }
    }
  }
}
```

### Other clients

Any MCP client that supports the Streamable HTTP transport with custom headers can connect to Part-DB, you generally
just need to provide:

* **URL**: `https://your-part-db.local/mcp`
* **Transport**: Streamable HTTP
* **Header**: `Authorization: Bearer tcp_<your-token>`

## Available tools

The following MCP tools are currently available. All of them are read-only.

### Parts

* **search_parts** – Search for parts by a keyword, with toggles to control which fields are searched (name,
  description, comment, tags, storage location, supplier order number, MPN, IPN, supplier, manufacturer, footprint,
  category, database ID), and an optional regex mode.
* **get_part_details** – Get full details about a specific part by its database ID, including stock, prices,
  order details, attachments, parameters and EDA info.
* **get_part_preview_image** – Get the preview/thumbnail picture for a part by its database ID. Uses the same
  fallback logic as the part list in the web UI: the part's own master picture if set, otherwise the picture of its
  footprint, and otherwise the picture of the project it is built from. Returns a short text message instead of an
  image if no preview picture is available.

### Master data

Categories, footprints, manufacturers, storage locations, measurement units, suppliers and part custom states all
expose the same pair of tools:

* **list_categories** / **get_category_details**
* **list_footprints** / **get_footprint_details**
* **list_manufacturers** / **get_manufacturer_details**
* **list_storage_locations** / **get_storage_location_details**
* **list_measurement_units** / **get_measurement_unit_details**
* **list_suppliers** / **get_supplier_details**
* **list_part_custom_states** / **get_part_custom_state_details**

Each `list_*` tool accepts an optional `keyword`, matched against the name and comment. Without a keyword, all
elements are returned in hierarchical tree order; with a keyword, matching results are sorted by their full path, so
parent/child relationships can still be derived from the flat list. Each `get_*_details` tool takes the element's
database `id` and returns its full details.

### Projects

* **list_projects** / **get_project_details** – Same behavior as the master data tools above.
  `get_project_details` additionally returns the project's BOM entries, status, description and associated build
  part.

### Attachments

* **get_attachment_content** – Retrieve the actual file content of an attachment (e.g. a datasheet or picture) by its
  database `id`, as returned in the `attachments` field of `get_part_details` and the other `get_*_details` tools.
  Pictures are returned as an image, other files as a text or binary resource depending on their mime type. Only
  works for attachments whose file is stored internally (not for attachments that only reference an external URL),
  and files larger than 10 MB are rejected.

### Info Provider System

These tools query external part information providers (e.g. Digikey, Mouser, LCSC), see the
[Information provider system]({% link usage/information_provider_system.md %}) page for background:

* **list_info_providers** – List the info providers that are currently active and can be used with the two tools
  below.
* **search_info_providers** – Search one or more external info providers (or the configured default providers, if
  none are specified) for parts matching a keyword.
* **get_info_provider_part_details** – Get full details (datasheets, images, parameters, prices, ...) for a specific
  search result, identified by the `provider_key` and `provider_id` returned by `search_info_providers`.
