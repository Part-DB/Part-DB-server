---
title: Information provider system
layout: default
parent: Usage
---

# Information provider system

Part-DB can create parts based on information from external sources: For example with the right setup you can just search for a part number
and Part-DB will query selected distributors and manufacturers for the part and create a part with the information it found.
This way your Part-DB parts automatically get datasheet links, prices, parameters and more, with just a few clicks.

## Usage

Before you can use the information provider system, you have to configure at least one information provider, which act as data source.
See below for a list of available information providers and available configuration options.
For many providers it is enough, to setup the API keys in the env configuration, some require an additional OAuth connection.
You can list all enabled information providers in the browser at `https://your-partdb-instance.tld/tools/info_providers/providers` (you need the right permission for it, see below).

To use the information provider system, your user need to have the right permissions. Go to the permission management page of 
a user or a group and assign the permissions of the "Info providers" group in the "Miscellaneous" tab.

If you have the required permission you will find in the sidebar in the "Tools" section the entry "Create part from info provider".
Click this and you will land on a search page. Enter the part number you want to search for and select the information providers you want to use. 

After you click Search, you will be presented with the results and can select the result that fits best. 
With a click on the blue plus button, you will be redirected to the part creation page with the information already filled in.

![image]({% link assets/usage/information_provider_system/animation.gif %})

## Data providers

The system tries to be as flexible as possible, so many different information sources can be used.
Each information source is called am "info provider" and handles the communication with the external source.
The providers are just a driver which handles the communication with the different external sources and converts them into a common format Part-DB understands.
That way it is pretty easy to create new providers as they just need to do very little work.

Normally the providers utilize an API of a service, and you need to create a account at the provider and get an API key. 
Also there are limits on how many requests you can do per day or months, depending on the provider and your contract with them.

The following providers are currently available and shipped with Part-DB:

### Digi-Key
The Digi-Key provider uses the [Digi-Key API](https://developer.digikey.com/) to search for parts and getting shopping information from [Digi-Key](https://www.digikey.com/).
To use it you have to create an account at Digi-Key and get an API key on the [Digi-Key API page](https://developer.digikey.com/). 
You must create an organization there and create a "Production app". Most settings are not important, you just have to grant access to the "Product Information" API.
You will get an Client ID and a Client Secret, which you have to enter in the Part-DB env configuration (see below).

Following env configuration options are available:
* `PROVIDER_DIGIKEY_CLIENT_ID`: The client ID you got from Digi-Key (mandatory)
* `PROVIDER_DIGIKEY_CLIENT_SECRET`: The client secret you got from Digi-Key (mandatory)
* `PROVIDER_DIGIKEY_CURRENCY`: The currency you want to get prices in (optional, default: `EUR`)
* `PROVIDER_DIGIKEY_LANGUAGE`: The language you want to get the descriptions in (optional, default: `en`)
* `PROVIDER_DIGIKEY_COUNTRY`: The country you want to get the prices for (optional, default: `DE`)

The Digi-Key provider needs an additional OAuth connection. To do this, go to the information provider list (`https://your-partdb-instance.tld/tools/info_providers/providers`), 
go the Digi-Key provider (in the disabled page) and click on the "Connect OAuth" button. You will be redirected to Digi-Key, where you have to login and grant access to the app.
To do this your user needs the "Manage OAuth tokens" permission from the "System" section in the "System" tab.
The OAuth connection should only be needed once, but if you have any problems with the provider, just click the button again, to establish a new connection.

### TME
The TME provider use the API of [TME](https://www.tme.eu/) to search for parts and getting shopping information from them.
To use it you have to create an account at TME and get an API key on the [TME API page](https://developers.tme.eu/en/).
You have to generate a new anonymous key there and enter the key and secret in the Part-DB env configuration (see below).

Following env configuration options are available:
* `PROVIDER_TME_API_KEY`: The API key you got from TME (mandatory)  
* `PROVIDER_TME_API_SECRET`: The API secret you got from TME (mandatory)
* `PROVIDER_TME_CURRENCY`: The currency you want to get prices in (optional, default: `EUR`)
* `PROVIDER_TME_LANGUAGE`: The language you want to get the descriptions in (`en`, `de` and `pl`) (optional, default: `en`)
* `PROVIDER_TME_COUNTRY`: The country you want to get the prices for (optional, default: `DE`)
* `PROVIDER_TME_GET_GROSS_PRICES`: If this is set to `1` the prices will be gross prices (including tax), otherwise net prices (optional, default: `0`)

### Farnell / Element14 / Newark
The Farnell provider uses the [Farnell API](https://partner.element14.com/) to search for parts and getting shopping information from [Farnell](https://www.farnell.com/).
You have to create an account at Farnell and get an API key on the [Farnell API page](https://partner.element14.com/). 
Register a new application there (settings does not matter, as long as you select the "Product Search API") and you will get an API key.

Following env configuration options are available:
* `PROVIDER_ELEMENT14_KEY`: The API key you got from Farnell (mandatory)
* `PROVIDER_ELEMENT14_STORE_ID`: The store ID you want to use. This decides the language of results, currency and country of prices (optional, default: `de.farnell.com`, see [here](https://partner.element14.com/docs/Product_Search_API_REST__Description) for availailable values)


### Custom provider
To create a custom provider, you have to create a new class implementing the `InfoProviderInterface` interface. As long as it is a valid Symfony service, it will be automatically loaded and can be used.
Besides some metadata functions, you have to implement the `searchByKeyword()` and `getDetails()` functions, which do the actual API requests and return the information to Part-DB.
See the existing providers for examples.
If you created a new provider, feel free to create a pull request to add it to the Part-DB core.