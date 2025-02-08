---
title: Information provider system
layout: default
parent: Usage
---

# Information provider system

Part-DB can create parts based on information from external sources: For example, with the right setup you can just
search for a part number
and Part-DB will query selected distributors and manufacturers for the part and create a part with the information it
found.
This way your Part-DB parts automatically get datasheet links, prices, parameters, and more, with just a few clicks.

## Usage

Before you can use the information provider system, you have to configure at least one information provider, which act
as data source.
See below for a list of available information providers and available configuration options.
For many providers it is enough, to set up the API keys in the env configuration, some require an additional OAuth
connection.
You can list all enabled information providers in the browser
at `https://your-partdb-instance.tld/tools/info_providers/providers` (you need the right permission for it, see below).

To use the information provider system, your user need to have the right permissions. Go to the permission management
page of
a user or a group and assign the permissions of the "Info providers" group in the "Miscellaneous" tab.

If you have the required permission you will find in the sidebar in the "Tools" section the entry "Create part from info
provider".
Click this and you will land on a search page. Enter the part number you want to search for and select the information
providers you want to use.

After you click Search, you will be presented with the results and can select the result that fits best.
With a click on the blue plus button, you will be redirected to the part creation page with the information already
filled in.

![image]({% link assets/usage/information_provider_system/animation.gif %})

If you want to update an existing part, go to the parts info page and click on the "Update from info provider" button in
the tools tab. You will be redirected to a search page, where you can search the info providers to automatically update this
part.

## Alternative names

Part-DB tries to automatically find existing elements from your database for the information it got from the providers
for fields like manufacturer, footprint, etc.
For this, it searches for an element with the same name (case-insensitive) as the information it got from the provider. So
e.g. if the provider returns "EXAMPLE CORP" as the manufacturer,
Part-DB will automatically select the element with the name "Example Corp" from your database.

As the names of these fields differ from provider to provider (and maybe not even normalized for the same provider), you
can define multiple alternative names for an element (on their editing page).
For example, if you define a manufacturer "Example Corp" with the alternative names "Example Corp.", "Example Corp", "Example
Corp. Inc." and "Example Corporation",
then the provider can return any of these names and Part-DB will still automatically select the right element.

If Part-DB finds no matching element, it will automatically create a new one, when you do not change the value before
saving.

## Attachment types

The information provider system uses attachment types to differentiate between datasheets and image attachments.
For this it will create a "Datasheet" and "Image" attachment type on the first run. You can change the names of these
types in the attachment type settings (as long as you keep the "Datasheet"/"Image" in the alternative names field).

If you already have attachment types for images and datasheets and want the information provider system to use them, you
can
add the alternative names "Datasheet" and "Image" to the alternative names field of the attachment types.

## Data providers

The system tries to be as flexible as possible, so many different information sources can be used.
Each information source is called am "info provider" and handles the communication with the external source.
The providers are just a driver that handles the communication with the different external sources and converts them
into a common format Part-DB understands.
That way it is pretty easy to create new providers as they just need to do very little work.

Normally the providers utilize an API of a service, and you need to create an account at the provider and get an API key.
Also, there are limits on how many requests you can do per day or month, depending on the provider and your contract
with them.

The following providers are currently available and shipped with Part-DB:

(All trademarks are property of their respective owners. Part-DB is not affiliated with any of the companies.)

### Octopart

The Octopart provider uses the [Octopart / Nexar API](https://nexar.com/api) to search for parts and get information.
To use it you have to create an account at Nexar and create a new application on
the [Nexar Portal](https://portal.nexar.com/).
The name does not matter, but it is important that the application has access to the "Supply" scope.
In the Authorization tab, you will find the client ID and client secret, which you have to put in the Part-DB env
configuration (see below).

Please note that the Nexar API in the free plan is limited to 1000 results per month.
That means if you search for a keyword and results in 10 parts, then 10 will be subtracted from your monthly limit. You
can see your current usage on the Nexar portal.
Part-DB caches the search results internally, so if you have searched for a part before, it will not count against your
monthly limit again, when you create it from the search results.

The following env configuration options are available:

* `PROVIDER_OCTOPART_CLIENT_ID`: The client ID you got from Nexar (mandatory)
* `PROVIDER_OCTOPART_SECRET`: The client secret you got from Nexar (mandatory)
* `PROVIDER_OCTOPART_CURRENCY`: The currency you want to get prices in if available (optional, 3 letter ISO-code,
  default: `EUR`). If an offer is only available in a certain currency,
  Part-DB will save the prices in their native currency, and you can use Part-DB currency conversion feature to convert
  it to your preferred currency.
* `PROVIDER_OCTOPART_COUNTRY`: The country you want to get prices in if available (optional, 2 letter ISO-code,
  default: `DE`). To get the correct prices, you have to set this and the currency setting to the correct value.
* `PROVIDER_OCTOPART_SEARCH_LIMIT`: The maximum number of results to return per search (optional, default: `10`). This
  affects how quickly your monthly limit is used up.
* `PROVIDER_OCTOPART_ONLY_AUTHORIZED_SELLERS`: If set to `true`, only offers
  from [authorized sellers](https://octopart.com/authorized) will be returned (optional, default: `false`).

**Attention**: If you change the Octopart clientID after you have already used the provider, you have to remove the
OAuth token in the Part-DB database. Remove the entry in the table `oauth_tokens` with the name `ip_octopart_oauth`.

### Digi-Key

The Digi-Key provider uses the [Digi-Key API](https://developer.digikey.com/) to search for parts and get shopping
information from [Digi-Key](https://www.digikey.com/).
To use it you have to create an account at Digi-Key and get an API key on
the [Digi-Key API page](https://developer.digikey.com/).
You must create an organization there and create a "Production app". Most settings are not important, you just have to
grant access to the "Product Information" API.
You will get a Client ID and a Client Secret, which you have to put in the Part-DB env configuration (see below).

**Attention**: Currently only the "Product Information V3 (Deprecated)" is supported by Part-DB. 
Using "Product Information V4" will not work.

The following env configuration options are available:

* `PROVIDER_DIGIKEY_CLIENT_ID`: The client ID you got from Digi-Key (mandatory)
* `PROVIDER_DIGIKEY_SECRET`: The client secret you got from Digi-Key (mandatory)
* `PROVIDER_DIGIKEY_CURRENCY`: The currency you want to get prices in (optional, default: `EUR`)
* `PROVIDER_DIGIKEY_LANGUAGE`: The language you want to get the descriptions in (optional, default: `en`)
* `PROVIDER_DIGIKEY_COUNTRY`: The country you want to get the prices for (optional, default: `DE`)

The Digi-Key provider needs an additional OAuth connection. To do this, go to the information provider
list (`https://your-partdb-instance.tld/tools/info_providers/providers`),
go to Digi-Key provider (in the disabled page), and click on the "Connect OAuth" button. You will be redirected to
Digi-Key, where you have to log in and grant access to the app.
To do this your user needs the "Manage OAuth tokens" permission from the "System" section in the "System" tab.
The OAuth connection should only be needed once, but if you have any problems with the provider, just click the button
again, to establish a new connection.

### TME

The TME provider uses the API of [TME](https://www.tme.eu/) to search for parts and getting shopping information from
them.
To use it you have to create an account at TME and get an API key on the [TME API page](https://developers.tme.eu/en/).
You have to generate a new anonymous key there and enter the key and secret in the Part-DB env configuration (see
below).

The following env configuration options are available:

* `PROVIDER_TME_KEY`: The API key you got from TME (mandatory)
* `PROVIDER_TME_SECRET`: The API secret you got from TME (mandatory)
* `PROVIDER_TME_CURRENCY`: The currency you want to get prices in (optional, default: `EUR`)
* `PROVIDER_TME_LANGUAGE`: The language you want to get the descriptions in (`en`, `de` and `pl`) (optional,
  default: `en`)
* `PROVIDER_TME_COUNTRY`: The country you want to get the prices for (optional, default: `DE`)
* `PROVIDER_TME_GET_GROSS_PRICES`: If this is set to `1` the prices will be gross prices (including tax), otherwise net
  prices (optional, default: `0`)

### Farnell / Element14 / Newark

The Farnell provider uses the [Farnell API](https://partner.element14.com/) to search for parts and getting shopping
information from [Farnell](https://www.farnell.com/).
You have to create an account at Farnell and get an API key on the [Farnell API page](https://partner.element14.com/).
Register a new application there (settings does not matter, as long as you select the "Product Search API") and you will
get an API key.

The following env configuration options are available:

* `PROVIDER_ELEMENT14_KEY`: The API key you got from Farnell (mandatory)
* `PROVIDER_ELEMENT14_STORE_ID`: The store ID you want to use. This decides the language of results, currency and
  country of prices (optional, default: `de.farnell.com`,
  see [here](https://partner.element14.com/docs/Product_Search_API_REST__Description) for available values)

### Mouser

The Mouser provider uses the [Mouser API](https://www.mouser.de/api-home/) to search for parts and getting shopping
information from [Mouser](https://www.mouser.com/).
You have to create an account at Mouser and register for an API key for the Search API on
the [Mouser API page](https://www.mouser.de/api-home/).
You will receive an API token, which you have to put in the Part-DB env configuration (see below):
At the registration you choose a country, language, and currency in which you want to get the results.

*Attention*: Currently (January 2024) the mouser API seems to be somewhat broken, in the way that it does not return any
information about datasheets and part specifications. Therefore Part-DB can not retrieve them, even if they are shown
at the mouser page. See [issue #503](https://github.com/Part-DB/Part-DB-server/issues/503) for more info.

Following env configuration options are available:

* `PROVIDER_MOUSER_KEY`: The API key you got from Mouser (mandatory)
* `PROVIDER_MOUSER_SEARCH_LIMIT`: The maximum number of results to return per search (maximum 50)
* `PROVIDER_MOUSER_SEARCH_OPTION`: You can choose an option here to restrict the search results to RoHs compliant and
  available parts. Possible values are `None`, `Rohs`, `InStock`, `RohsAndInStock`.
* `PROVIDER_MOUSER_SEARCH_WITH_SIGNUP_LANGUAGE`: A bit of an obscure option. The original description of Mouser is: Used
  when searching for keywords in the language specified when you signed up for Search API.

### LCSC

[LCSC](https://www.lcsc.com/) is a Chinese distributor of electronic parts. It does not offer a public API, but the LCSC
webshop uses an internal JSON based API to render the page. Part-DB can use this inofficial API to get part information
from LCSC. 

**Please note, that the use of this internal API is not intended or endorsed by LCS and it could break at any time. So use it at your own risk.**

An API key is not required, it is enough to enable the provider using the following env configuration options:

* `PROVIDER_LCSC_ENABLED`: Set this to `1` to enable the LCSC provider
* `PROVIDER_LCSC_CURRENCY`: The currency you want to get prices in (see LCSC webshop for available currencies, default: `EUR`)

### OEMsecrets

The oemsecrets provider uses the [oemsecrets API](https://www.oemsecrets.com/) to search for parts and getting shopping
information from them. Similar to octopart it aggregates offers from different distributors.

You can apply for a free API key on the [oemsecrets API page](https://www.oemsecrets.com/api/) and put the key you get
in the Part-DB env configuration (see below).

The following env configuration options are available:

* `PROVIDER_OEMSECRETS_KEY`: The API key you got from oemsecrets (mandatory)
* `PROVIDER_OEMSECRETS_COUNTRY_CODE`: The two-letter code of the country you want to get the prices for
* `PROVIDER_OEMSECRETS_CURRENCY`: The currency you want to get prices in (optional, default: `EUR`)
* `PROVIDER_OEMSECRETS_ZERO_PRICE`: If set to `1`, parts with a price of 0 will be included in the search results, otherwise
  they will be excluded (optional, default: `0`)
* `PROVIDER_OEMSECRETS_SET_PARAM`: If set to `1`, the provider will try to extract parameters from the part description
* `PROVIDER_OEMSECRETS_SORT_CRITERIA`: The criteria to sort the search results by. If set to 'C', it further sorts by 
completeness (prioritizing items with the most detailed information). If set to 'M', it further sorts by manufacturer name.
If set to any other value, no sorting is performed.

### Custom provider

To create a custom provider, you have to create a new class implementing the `InfoProviderInterface` interface. As long
as it is a valid Symfony service, it will be automatically loaded and can be used.
Besides some metadata functions, you have to implement the `searchByKeyword()` and `getDetails()` functions, which do
the actual API requests and return the information to Part-DB.
See the existing providers for examples.
If you created a new provider, feel free to create a pull request to add it to the Part-DB core.

## Result caching

To reduce the number of API calls against the providers, the results are cached:

* The search results (exact search term) are cached for 7 days
* The product details are cached for 4 days

If you need a fresh result, you can clear the cache by running `php .\bin\console cache:pool:clear info_provider.cache`
on the command line.
The default `php bin/console cache:clear` also clears the result cache, as it clears all caches.
