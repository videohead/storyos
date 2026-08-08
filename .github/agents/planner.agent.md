name: Planner
description: Generate implementation plans for new features or refactoring
tools: ['fetch', 'githubRepo', 'search', 'usages']
model: ['YOUR MODEL HERE (copilot)']
handoffs:
  - label: Start Implementation
    agent: Builder
    prompt: Implement the plan outlined above.
    send: false