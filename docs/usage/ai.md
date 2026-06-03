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
