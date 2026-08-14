# Multi-Agent Framework - Filmmaking Agents Package
# Agent definitions and orchestration for StoryOS.
#
# Agents are defined as YAML-frontmatter + markdown body files
# (*.agent.md) in this directory. The AgentRegistry loads them at runtime.

from .registry import AgentRegistry

__all__ = ["AgentRegistry"]
