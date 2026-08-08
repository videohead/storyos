**Copilot Bridge**: Configure VS Code Copilot Chat to use an OpenAI-compatible local endpoint.

# VSCode Copilot Chat Sample Configuration:
[
	{
		"name": "Custom Endpoint",
		"vendor": "customendpoint",
		"apiType": "chat-completions",
		"models": [
			{
				"id": "qwen3.6:35b-a3b-q4_K_M",
				"name": "qwen3.6:35b-a3b-q4_K_M",
				"url": "http://localhost:11434",
				"toolCalling": true,
				"vision": true,
				"maxInputTokens": 128000,
				"maxOutputTokens": 16000
			}
		]
	}
]

