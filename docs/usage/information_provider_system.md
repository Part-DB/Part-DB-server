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

## Bulk import

If you want to update the information of multiple parts, you can use the bulk import system: Go to a part table and select
the parts you want to update. In the bulk actions dropdown select "Bulk info provider import" and click "Apply".
You will be redirected to a page, where you can select how part fields should be mapped to info provider fields, and the 
results will be shown.

## Browser plugin
There is a browser plugin available for [Chrome](https://chromewebstore.google.com/detail/part-db-page-submitter/bckkfkpidiiibmjdhjakleoagjmepioi) and [Firefox](https://addons.mozilla.org/de/firefox/addon/part-db-page-submitter/)
that allows to submit a website from your browser with one click to Part-DB, which then utilizes the Generic Web URL or the AI Web Provider to extract the part information from the page and pre-fill the part creation form.
The advantage is that it also works for pages behind logins, CAPTCHAs, or bot-blocking sites, as the plugin sends the already loaded page HTML to Part-DB.
The plugin is open source and available on [GitHub](https://github.com/Part-DB/browser-plugin).

To use it install it in your browser, enable one or more of the web page providers in Part-DB and allow the plugin support
in Part-DB settings. After that you can submit any product page to Part-DB with one click and the part creation form will be pre-filled with the information from the page.

## Data providers

The system tries to be as flexible as possible, so many different information sources can be used.
Each information source is called an "info provider" and handles the communication with the external source.
The providers are just a driver that handles the communication with the different external sources and converts them
into a common format Part-DB understands.
That way it is pretty easy to create new providers as they just need to do very little work.

Normally the providers utilize an API of a service, and you need to create an account at the provider and get an API key.
Also, there are limits on how many requests you can do per day or month, depending on the provider and your contract
with them.

Data providers can be either configured in the system settings (in the info provider tab) or on the settings page which is
reachable via the cogwheel symbol next to the provider in the provider list. It is also possible to configure them via
environment variables. See below for the available configuration options. API keys configured via environment variables
are redacted in the settings interface.

The following providers are currently available and shipped with Part-DB:

(All trademarks are property of their respective owners. Part-DB is not affiliated with any of the companies.)

### Generic Web URL Provider
The Generic Web URL Provider can extract part information from any webpage that contains structured data in the form of
[Schema.org](https://schema.org/) format. Many e-commerce websites use this format to provide detailed product information
for search engines and other services. Therefore it allows Part-DB to retrieve rudimentary part information (like name, image and price)
from a wide range of websites without the need for a dedicated API integration.
To use the Generic Web URL Provider, simply enable it in the information provider settings. No additional configuration
is required. Afterwards you can enter any product URL in the search field, and Part-DB will attempt to extract the relevant part information
from the webpage.

Please note that if this provider is enabled, Part-DB will make HTTP requests to external websites to fetch product data, which
may have privacy and security implications.

Following env configuration options are available:
* `PROVIDER_GENERIC_WEB_ENABLED`: Set this to `1` to enable the Generic Web URL Provider (optional, default: `0`)

### AI Web Extractor
The AI web extractor provider can extract part information from any webpage using AI-based techniques. It is designed to handle unstructured data and can extract relevant information even from websites that do not use structured data formats like Schema.org. 
This provider can be particularly useful for extracting information from websites that have complex layouts or do not follow standard e-commerce practices.
It also potentially extracts more detailed information than the Generic Web URL Provider, as it is not limited to the fields defined in the Schema.org format.

To use the AI Web Extractor, you need to setup an AI platform, in the AI settings tab, and chose a model, which support structured output.
For many use cases a small and cheap model like `google/gemini-2.5-flash-lite` will be sufficient, coming down to costs like 0.001$ per request.
For more complex websites, or if you wanna use the LLM for translation purposes too, you should consider a more powerful model.

You can add some additional instructions for the model, which gets added to the system prompt, to tweak the output of the model.

The provider will download the HTML of the given URL, convert it to markdown and send it to the LLM toghether with structured data extracted from the webpage via conventional methods.

### AI Document Extractor (Create part from file)
The AI document extractor creates a part from an uploaded file, like a datasheet. You can find it under
"Create part from file (AI)" in the dropdown of the "New part" button, or in the tools tree.
PDF files, images (PNG, JPEG, WebP, GIF), text and Markdown files are supported.

It has its own settings ("AI File Extractor" in the info provider settings), independent of the AI Web Extractor.
To use it, select an AI platform (set up in the AI settings tab) and a model which supports structured output. You can
also configure the maximum content length, the output language and additional instructions for the model there.

How the file is passed to the LLM is chosen for every upload with the "Input mode" field:

* **Auto** (default): The text of PDF, text and Markdown files is extracted on the server and sent to the LLM. Images
  and scanned PDF documents (without a text layer) are sent to the LLM as they are.
* **Extract text only**: Only the extracted text is sent, so it works with every model which supports structured output.
  Images and scanned documents are not supported.
* **Send file to model**: PDF files and images are always sent to the LLM as they are, which lets the model see the
  layout of tables, diagrams and pictures. Text files are still sent as text. Note that this usually uses much more
  tokens than the extracted text, and that the maximum content length does not apply.

Sending files has to be enabled with the "Allow sending files to the model" option in the AI File Extractor settings
(disabled by default). As long as it is disabled, the input mode can not be chosen, only the extracted text is used and
images can not be uploaded.

Sending files requires a model which supports image and/or PDF input. With OpenRouter, PDF files are also processed
for models without native PDF support, but then OpenRouter only passes their text on, which does not work for scanned documents. Ollama can only process images, not PDF
files, and LM Studio and other OpenAI compatible servers depend on the model and server.

Extracted text longer than the maximum content length configured in the AI File Extractor settings is truncated,
which usually is not a problem, as the most relevant information of a datasheet is at its beginning.
The uploaded file is added as attachment to the created part (as datasheet, if the datasheet attachment type allows the
file). It is only stored when the part is saved, and you can remove it in the form like any other attachment. Until then
it is kept temporarily (2 hours) in `var/share/<env>/uploaded_documents`. Files of uploads, for which no part was
created, are deleted automatically on the next upload after that time. Files bigger than the maximum attachment size are
not attached.

You can give additional context together with the file, like the exact part number to use, if the datasheet
covers multiple variants of a part. The model then describes exactly this variant.

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

The TME provider uses the API of [TME](https://www.tme.eu/) to search for parts and get shopping information from
them.
To use it you have to create an account at TME and get an API key on the [TME API page](https://developers.tme.eu/en/).
You have to generate a new application and new private key there  and enter the key and secret in the Part-DB env configuration (see
below). Follow the instructions of [TME](https://developers.tme.eu/en/how-to-start/download) for more informations

The following env configuration options are available:

* `PROVIDER_TME_KEY`: The API key you got from TME (mandatory)
* `PROVIDER_TME_SECRET`: The API secret you got from TME (mandatory)
* `PROVIDER_TME_CURRENCY`: The currency you want to get prices in (optional, default: `EUR`)
* `PROVIDER_TME_LANGUAGE`: The language you want to get the descriptions in (`en`, `de` and `pl`) (optional,
  default: `en`)
* `PROVIDER_TME_COUNTRY`: The country you want to get the prices for (optional, default: `DE`)

### Farnell / Element14 / Newark

The Farnell provider uses the [Farnell API](https://partner.element14.com/) to search for parts and get shopping
information from [Farnell](https://www.farnell.com/).
You have to create an account at Farnell and get an API key on the [Farnell API page](https://partner.element14.com/).
Register a new application there (settings do not matter, as long as you select the "Product Search API") and you will
get an API key.

The following env configuration options are available:

* `PROVIDER_ELEMENT14_KEY`: The API key you got from Farnell (mandatory)
* `PROVIDER_ELEMENT14_STORE_ID`: The store ID you want to use. This decides the language of results, currency and
  country of prices (optional, default: `de.farnell.com`,
  see [here](https://partner.element14.com/docs/Product_Search_API_REST__Description) for available values)

### Mouser

The Mouser provider uses the [Mouser API](https://www.mouser.de/api-home/) to search for parts and get shopping
information from [Mouser](https://www.mouser.com/).
You have to create an account at Mouser and register for an API key for the Search API on
the [Mouser API page](https://www.mouser.de/api-home/).
You will receive an API token, which you have to put in the Part-DB env configuration (see below):
At the registration you choose a country, language, and currency in which you want to get the results.

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

**Please note that the use of this internal API is not intended or endorsed by LCSC and it could break at any time. So use it at your own risk.**

An API key is not required, it is enough to enable the provider using the following env configuration options:

* `PROVIDER_LCSC_ENABLED`: Set this to `1` to enable the LCSC provider
* `PROVIDER_LCSC_CURRENCY`: The currency you want to get prices in (see LCSC webshop for available currencies, default: `EUR`)

### OEMsecrets

The oemsecrets provider uses the [oemsecrets API](https://www.oemsecrets.com/) to search for parts and get shopping
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

### Reichelt

The reichelt provider uses webscraping from [reichelt.com](https://reichelt.com/) to get part information.
This is not an official API and could break at any time. So use it at your own risk.

The following env configuration options are available:  
* `PROVIDER_REICHELT_ENABLED`: Set this to `1` to enable the Reichelt provider
* `PROVIDER_REICHELT_CURRENCY`: The currency you want to get prices in. Only possible for countries which use Non-EUR (optional, default: `EUR`)
* `PROVIDER_REICHELT_COUNTRY`: The country you want to get the prices for (optional, default: `DE`)
* `PROVIDER_REICHELT_LANGUAGE`: The language you want to get the descriptions in (optional, default: `en`)
* `PROVIDER_REICHELT_INCLUDE_VAT`: If set to `1`, the prices will be gross prices (including tax), otherwise net prices (optional, default: `1`)

### Pollin

The pollin provider uses webscraping from [pollin.de](https://www.pollin.de/) to get part information.
This is not an official API and could break at any time. So use it at your own risk.

The following env configuration options are available:
* `PROVIDER_POLLIN_ENABLED`: Set this to `1` to enable the Pollin provider

### Pololu

The Pololu provider uses webscraping from [pololu.com](https://www.pololu.com/) to get part information.
This is not an official API and could break at any time. So use it at your own risk.

You can search by keyword or by the Pololu item number (e.g. `2130`). Besides the basic infos, the provider returns
all product pictures, the price breaks (in USD), the available stock, the files from the "Resources" tab (datasheets,
dimension diagrams, 3D models, drill guides, ...) and the specifications from the "Specs" tab as parameters.

Getting the details of a product needs up to three page requests (product page, specs and resources). To be polite to
the shop, the provider waits a configurable time between two page requests, and stops sending requests for some time
when the website answers with an error indicating that requests are blocked or rate limited (HTTP 403, 429 or 503).

The following env configuration options are available:
* `PROVIDER_POLOLU_ENABLED`: Set this to `1` to enable the Pololu provider
* `PROVIDER_POLOLU_REQUEST_DELAY`: The minimum time in seconds between two page requests to pololu.com (optional, default: `1`)

### Buerklin

The Buerklin provider uses the [Buerklin API](https://www.buerklin.com/en/services/eprocurement/) to search for parts and get information.
To use it you have to request access to the API.
You will get an e-mail with the client ID and client secret, which you have to put in the Part-DB configuration (see below).

Please note that the Buerklin API is limited to 100 requests/minute per IP address and 
access to the Authentication server is limited to 10 requests/minute per IP address

The following env configuration options are available:

* `PROVIDER_BUERKLIN_CLIENT_ID`: The client ID you got from Buerklin (mandatory)
* `PROVIDER_BUERKLIN_SECRET`: The client secret you got from Buerklin (mandatory)
* `PROVIDER_BUERKLIN_USERNAME`: The username you got from Buerklin (mandatory)
* `PROVIDER_BUERKLIN_PASSWORD`: The password you got from Buerklin (mandatory)
* `PROVIDER_BUERKLIN_CURRENCY`: The currency you want to get prices in if available (optional, 3 letter ISO-code, default: `EUR`).
* `PROVIDER_BUERKLIN_LANGUAGE`: The language you want to get the descriptions in. Possible values: `de` = German, `en` = English. (optional, default: `en`)

### Conrad

The conrad provider the [Conrad API](https://developer.conrad.com/) to search for parts and retried their information.
To use it you have to request access to the API, however it seems currently your mail address needs to be allowlisted before you can register for an account.
The conrad webpages uses the API key in the requests, so you might be able to extract a working API key by listening to browser requests.
That method is not officially supported nor encouraged by Part-DB, and might break at any moment.

The following env configuration options are available:
* `PROVIDER_CONRAD_API_KEY`: The API key you got from Conrad (mandatory)

### Canopy / Amazon
The Canopy provider uses the [Canopy API](https://www.canopyapi.co/) to search for parts and get shopping information from Amazon. 
Canopy is a third-party service that provides access to Amazon product data through their API. Their trial plan offers 100 requests per month for free, 
and they also offer paid plans with higher limits. To use the Canopy provider, you need to create an account on the Canopy website and obtain an API key. 
Once you have the API key, you can configure the Canopy provider in Part-DB using the web UI or environment variables:

* `PROVIDER_CANOPY_API_KEY`: The API key you got from Canopy (mandatory)

As Canopy bills per request, retrieving data for all Amazon parts of a large inventory can be expensive. With the
*Fetch data when a part is viewed* option in the provider settings, Part-DB only asks Canopy about an Amazon part, when
somebody opens its info page for the first time (see [Fetching data when a part is viewed](#fetching-data-when-a-part-is-viewed)).
This applies to parts which have an orderdetail linking to a product page of the configured Amazon marketplace, and the
number of requests caused this way is capped by *Max. requests per day when viewing parts* of the provider settings.

### SparkFun

The SparkFun provider retrieves product information from [sparkfun.com](https://www.sparkfun.com/). You can search by
keyword or by SparkFun SKU (e.g. `DEV-13975` or just `13975`). The provider returns the name, description, category,
product images, prices (including quantity discounts), weight and the documents linked on the product page
(schematics, datasheets, hookup guides, Eagle files, etc.). The SKU is used as manufacturer part number.

The data is read from the GraphQL endpoint of the SparkFun shop and from the product page, as SparkFun offers no
official API for this. It could break at any time, so use it at your own risk.

To be polite to the shop, the provider waits a configurable time between any two requests it sends (to the GraphQL
endpoint and to the product pages), requests which come too early are delayed. A search needs one request and the
details of a product need two, so a lookup can take some time. If the shop refuses a request (HTTP 403, 429 or 503),
the provider sends no requests at all for one hour, and lookups fail with a message telling until when it is paused.

The following env configuration options are available:
* `PROVIDER_SPARKFUN_ENABLED`: Set this to `1` to enable the SparkFun provider
* `PROVIDER_SPARKFUN_REQUEST_DELAY`: The minimum time in seconds between two requests to sparkfun.com (optional,
  default: `5`)

### TrustedParts

The TrustedParts provider uses the [TrustedParts.com Inventory API](https://www.trustedparts.com/en/docs/api/trustedparts-api)
to search for parts. TrustedParts.com is operated by the Electronic Components Industry Association (ECIA) and
aggregates the offers (stock and prices) of authorized distributors, similar to Octopart. Besides the offers, it also
provides specifications for many parts, which Part-DB imports as parameters.

Please note that the TrustedParts API does not return any product images, so parts created with this provider have no
preview image. You can generate one with the built-in component image generator (the button on the image placeholder of
the part page), which works well with the package information this provider supplies.

The API is free of charge, but you have to
[request access](https://www.trustedparts.com/en/docs/api/trustedparts-api/credentials) for it: Register an account on
TrustedParts.com, verify your mail address and request API access on the "Additional Features" tab of your account.
After your request was approved, you find the company ID and the API key on the "API Key" tab of the "My Account" page.

Please note the [API terms of use](https://www.trustedparts.com/en/docs/api/trustedparts-api/terms-of-use), especially:
The data may only be used for internal purchasing decisions, must not be published or resold, has to be attributed to
TrustedParts.com and must not be cached for longer than one week (Part-DB caches the results for four days at most).
There are also [rate limits](https://www.trustedparts.com/en/docs/api/trustedparts-api/requests), so you should only
make as many requests as you actually need.

The following env configuration options are available:

* `PROVIDER_TRUSTEDPARTS_COMPANY_ID`: The company ID of your TrustedParts.com account (mandatory)
* `PROVIDER_TRUSTEDPARTS_API_KEY`: The API key of your TrustedParts.com account (mandatory)
* `PROVIDER_TRUSTEDPARTS_CURRENCY`: The currency you want to get prices in if available (optional, 3 letter ISO-code,
  default: `EUR`). Distributors which do not support the requested currency return their prices in their own currency.
* `PROVIDER_TRUSTEDPARTS_COUNTRY`: The country you want to get the prices for (optional, 2 letter ISO-code, default: `DE`)
* `PROVIDER_TRUSTEDPARTS_LANGUAGE`: The language the specifications should be translated to. Possible values: `en`,
  `de`, `es`, `fr`, `it`, `pt`, `ja` (optional, default: `en`)
* `PROVIDER_TRUSTEDPARTS_SEARCH_LIMIT`: The maximum number of results to return per search (optional, default: `25`)
* `PROVIDER_TRUSTEDPARTS_IN_STOCK_ONLY`: If set to `1`, only offers of distributors which currently have the part in
  stock are returned (optional, default: `0`)
* `PROVIDER_TRUSTEDPARTS_USE_CACHED_DATA`: If set to `1`, TrustedParts.com answers with cached stock and price data
  instead of querying the distributors in real time. This is faster and does not count against the rate limits, but the
  data can be outdated (optional, default: `0`)

### Adafruit

The Adafruit provider uses the public product API of [adafruit.com](https://www.adafruit.com/) to search for products
and get information. No API key or account is required.

The API has no search endpoint, it only offers the whole product catalog as one large list. Part-DB therefore
downloads the catalog once, caches it for one day and searches it locally. You can search by keyword (all words must
occur in the product name, model or manufacturer) or by the Adafruit product ID (e.g. `4062` or `ADA4062`), which is also used as provider ID.
Product URLs like `https://www.adafruit.com/product/4062` are recognized too.

When retrieving the details of a product, the product API supplies name, description, manufacturer, prices with quantity
breaks (in USD) and the stock level. By default the product page is read too, as only it contains all product images, the
technical details (which are offered as parameters), the category and the links to the learn guides. If you want
to avoid this second request per part, you can disable it.

To be polite to the website, the provider waits a configurable time between any two requests it sends (catalog
download, product API and product pages), requests which come too early are delayed, so a lookup can take some time.
If the website refuses a request (HTTP 403, 429 or 503), the provider sends no requests at all for one hour, and
lookups fail with a message telling until when it is paused. The cached catalog can still be searched in that time.

The following env configuration options are available:

* `PROVIDER_ADAFRUIT_ENABLED`: Set this to `1` to enable the Adafruit provider
* `PROVIDER_ADAFRUIT_FETCH_PRODUCT_PAGE`: Set this to `0` to only use the product API and not read the product page
  (optional, default: `1`)
* `PROVIDER_ADAFRUIT_REQUEST_DELAY`: The minimum time in seconds between two requests to adafruit.com (optional,
  default: `5`)


### Bambu Lab

The Bambu Lab provider uses the API of the [Bambu Lab store](https://store.bambulab.com/) website to search for
filaments, printer parts and accessories and to retrieve their images, prices, options and documents (like the technical
and safety data sheets of the filaments). No account or API key is required.
This is not an official API and could break at any time. So use it at your own risk.

Most products of the store are available in multiple variants (e.g. the colors of a filament, with or without spool),
which differ in their images, prices and codes. If you search for a product name (like `PETG HF`), you get the
products as a whole. To get a certain variant, search for its code (like the filament code `32101`, which is printed
on the spool and the box), or use the URL of the selected variant (`.../products/petg-translucent?id=...`) in the
"Create part from URL" feature. For variants the code is used as manufacturer part number.

To be polite to the store, the provider waits a configurable time between any two requests it sends (to the API and
to the product pages), requests which come too early are delayed. A search needs one request (plus one per listed
product if you search for a code), the details of a product need two, so a lookup can take some time. If the store
refuses a request (HTTP 403, 429 or 503), the provider sends no requests at all for one hour, and lookups fail with a
message telling until when it is paused. Product data which is already cached is still served.

The following env configuration options are available:
* `PROVIDER_BAMBULAB_ENABLED`: Set this to `1` to enable the Bambu Lab provider
* `PROVIDER_BAMBULAB_REGION`: The regional store which should be used. This determines the available products, the
  prices and their currency. Possible values: `US`, `CA`, `MX`, `EU`, `UK`, `AU`, `JP`, `KR`, `GLOBAL` (optional,
  default: `US`)
* `PROVIDER_BAMBULAB_REQUEST_DELAY`: The minimum time in seconds between two requests to the store (optional,
  default: `5`)

### Custom providers

To create a custom provider, you have to create a new class implementing the `InfoProviderInterface` interface. As long
as it is a valid Symfony service, it will be automatically loaded and can be used.
Besides some metadata functions, you have to implement the `searchByKeyword()` and `getDetails()` functions, which do
the actual API requests and return the information to Part-DB.
See the existing providers for examples.
If you created a new provider, feel free to create a pull request to add it to the Part-DB core.

## Fetching data when a part is viewed

Parts which were created by hand or by an import have no info provider data. Instead of looking all of them up at once
(which costs money at providers billing per request, and is a lot of traffic for the website of a small store), Part-DB
can retrieve the data of such a part when somebody opens its info page for the first time: the page is shown as usual,
says that data is being fetched from the provider, and reloads with the new data once it is there.

This is off by default. Select the providers which should do this under *Fetch data when a part is viewed* in the
general info provider settings, or list their keys in the `PROVIDER_FETCH_ON_VIEW` environment variable (comma separated,
e.g. `PROVIDER_FETCH_ON_VIEW=lcsc,pollin`). Only active providers are used. The Canopy provider additionally has its
own switch for this in its settings.

A part is only looked up if it has no info provider reference yet and one of its orderdetails leads to one of the
selected providers, checked in this order:

1. The product URL of the orderdetail is a product page of the provider (this works for the providers which can
   create a part from an URL, and for Amazon URLs with Canopy). The URL identifies the product.
2. The supplier of the orderdetail has the same name as the provider (ignoring case, spaces and punctuation) and the
   orderdetail has a supplier part number. The provider is searched for that number, and the result is only used if
   its ID, manufacturer part number or order number is exactly the supplier part number (ignoring case and a vendor
   prefix, so `4062` matches `ADA4062` and `13975` matches `DEV-13975`). Part-DB never picks a merely similar product.

Parts with neither are left alone, and checking this does not contact the provider.

As nobody reviews the result, only missing data is filled in (description, notes, manufacturer, manufacturer part
number, pictures and datasheets, parameters, and prices if the orderdetail has none); existing data is never changed.
Afterwards the part carries the info provider reference, so it is not looked up again, and it can be updated from the
provider with the normal tools later.

The number of parts looked up this way is capped per provider by *Max. lookups per day when viewing parts*
(`PROVIDER_FETCH_ON_VIEW_DAILY_LIMIT`, default 100, 0 for no limit); when it is reached, the page says so and the part
is tried again on a later view. If the provider fails, refuses the request, or does not know the supplier part number,
the page says that the data could not be fetched and the part is not tried again for 24 hours. Everybody who is allowed
to view a part triggers the lookup.

## Result caching

To reduce the number of API calls against the providers, the results are cached:

* The search results (exact search term) are cached for 7 days
* The product details are cached for 4 days

If you need a fresh result, you can clear the cache by running `php .\bin\console cache:pool:clear info_provider.cache`
on the command line.
The default `php bin/console cache:clear` also clears the result cache, as it clears all caches.

## Rate limiting

Info providers enforce rate limits per account, usually in short windows (TrustedParts, for example, allows 50
requests per 10 seconds and 150 per minute), and many of them bill per request. Part-DB can easily produce
bursts above that: a bulk import or an update of many parts walks through them as fast as the network allows.

Part-DB therefore paces its own requests to each provider. The limits are configured under
*System settings → Info providers → General*:

* **Max. provider requests per 10 seconds** (default 35)
* **Max. provider requests per minute** (default 120)
* **Max. wait for a free slot** (default 30 seconds)

Requests are delayed, not rejected - a bulk operation simply takes a little longer. Only if a single request
would have to wait longer than the configured maximum does it fail, so that a request started from the user
interface cannot hang indefinitely. Such a failure answers with HTTP 429 and a `Retry-After` header naming the
seconds until the next free slot, so an automated caller can tell "too fast, come back shortly" apart from a
real error. Setting a limit to 0 disables that window.

Each provider is counted separately, since the limits belong to a provider account, and cached responses are not
counted at all - only requests which really reach the provider.

The defaults deliberately stay below what providers allow. The reason is that the provider counts *your account*,
not Part-DB: if an external script uses the same API credentials (for example a job which maintains parts
through the API), its requests and Part-DB's add up, and neither side can see the other's count. The remaining
headroom is what keeps the two from pushing each other over the limit. If you know that nothing else uses the
account, you can raise the values.

Certain providers (like Adafruit, Pololu) have their own rate limits, configurable in the provider settings. 
These are enforced in addition to the global limits, and are usually lower than the global limits, as no offical API
exists and the provider is scraping the website and have to be polite to the shop.
