---
layout: default
title: AI features
nav_order: 6
parent: Usage
---

# AI features

Part-DB can utilize large language Models (LLMs) to provide AI-powered features that can assist you in managing your parts and projects.
For now this is mostly the ability to extract part information from websites without any structured data.

## AI platforms

Part-DB is platform agnostic and can work with different AI platforms, both locally and in the cloud. They can be configured in the "AI" tab in the system settings.
Currently, the following platforms are supported:

### OpenRouter

[OpenRouter](https://openrouter.ai/) is a platform that provides access to various LLMs, including models from OpenAI, Anthropic, and more. 
You can use OpenRouter to connect to different LLMs and use them for Part-DB's AI features.
You need to supply an API key for OpenRouter to use it as an AI platform in Part-DB.

### LMStudio

[LMStudio](https://lmstudio.ai/) is a local LLM hosting solution that allows you to run LLMs on your own hardware. You can use LMStudio to host your own LLM and connect it to Part-DB for AI features.
Currently only LMStudio without any authentication is supported. Supply your LMStudio instance URL (including the port) to use it as an AI platform in Part-DB.
You have to set a model by hand, as suggestions currently do not work yet. Ensure the context length is suitable for your application.

### Ollama

[Ollama](https://ollama.com/) is another local LLM hosting solution that allows you to run LLMs on your own hardware. You can use Ollama to host your own LLM and connect it to Part-DB for AI features.
Supply your Ollama instance URL (including the port) and an optional API key for authentication to use it as an AI platform in Part-DB. The model selector should give you suggestions about available models.
Ensure the context length is suitable for your application.

### Generic (OpenAI compatible)

Many providers and self-hosted LLM gateways/proxies (e.g. [LiteLLM](https://www.litellm.ai/), [vLLM](https://github.com/vllm-project/vllm), or OpenAI itself) expose an API that is compatible with the OpenAI API specification.
Supply the base URL of the endpoint (e.g. `https://api.openai.com/` or the URL of your gateway) and an API key, which will be sent using the standard `Authorization: Bearer <API_KEY>` header, to use it as an AI platform in Part-DB.
You have to set a model by hand, as suggestions currently do not work yet.

If your endpoint uses non-standard paths for the chat completions and embeddings endpoints, you can override them. Leave these fields empty to use the defaults (`/v1/chat/completions` and `/v1/embeddings`).
