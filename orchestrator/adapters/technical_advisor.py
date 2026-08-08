"""Technical Advisor — technical implementation and integration support.

Provides:
- API integration guidance
- ComfyUI workflow optimization
- WordPress integration
- System architecture advice
"""
from __future__ import annotations

import logging
import os
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

SYSTEM_PROMPT = """You are the Technical Advisor for StoryOS, a collaborative storytelling platform.

Your role is to assist with technical implementation, integration, and system
architecture while respecting that humans make final technical decisions.

You have access to:
- StoryOS architecture documentation
- API specifications
- ComfyUI workflow configurations
- WordPress CPT/SCF schemas
- System health and metrics

Guidelines:
- Provide technical guidance and best practices
- Help troubleshoot integration issues
- Suggest architecture improvements
- Explain technical trade-offs
- Never make breaking changes — recommend and explain
- Be specific with code examples when helpful
"""


class TechnicalAdvisor:
    """Technical implementation advisor agent."""

    def __init__(self, name: str = "technical_advisor"):
        self.name = name
        self.role = "Technical Advisor"
        self.system_prompt = SYSTEM_PROMPT

    def troubleshoot_integration(
        self,
        service: str,
        error_message: str,
        context: dict[str, Any] = None,
    ) -> str:
        """Troubleshoot integration issues.

        Args:
            service: Service name (wordpress, comfyui, redis, celery)
            error_message: Error message or description
            context: Additional context (logs, config, etc.)

        Returns:
            Troubleshooting guidance
        """
        context_str = self._format_context(context or {})
        prompt = f"""Service: {service}

Error Message:
{error_message}

Context:
{context_str}

Diagnose this issue and provide:
- Root cause analysis
- Step-by-step troubleshooting
- Recommended fix
- Prevention measures"""

        return self._call_model(prompt)

    def optimize_workflow(
        self,
        workflow_config: dict[str, Any],
        target_metric: str = "speed",
    ) -> str:
        """Optimize ComfyUI workflow configuration.

        Args:
            workflow_config: Current workflow configuration
            target_metric: Optimization target (speed, quality, memory)

        Returns:
            Optimization recommendations
        """
        prompt = f"""Current Workflow Configuration:
{self._format_config(workflow_config)}

Target Metric: {target_metric}

Suggest optimizations for {target_metric} while maintaining acceptable quality.
Consider:
- Node ordering and dependencies
- Memory usage
- Parallelization opportunities
- Parameter tuning"""

        return self._call_model(prompt)

    def design_api_endpoint(
        self,
        requirements: dict[str, Any],
    ) -> str:
        """Design a new API endpoint.

        Args:
            requirements: Endpoint requirements and constraints

        Returns:
            Endpoint design specification
        """
        prompt = f"""API Endpoint Requirements:
{self._format_requirements(requirements)}

Design a RESTful API endpoint that:
- Follows REST best practices
- Includes proper validation
- Has appropriate error handling
- Documents expected inputs/outputs
- Considers security and rate limiting"""

        return self._call_model(prompt)

    def review_architecture(
        self,
        current_architecture: dict[str, Any],
        focus_area: str = "scalability",
    ) -> str:
        """Review and suggest architecture improvements.

        Args:
            current_architecture: Current system architecture
            focus_area: Focus area (scalability, performance, security)

        Returns:
            Architecture review and recommendations
        """
        prompt = f"""Current Architecture:
{self._format_architecture(current_architecture)}

Focus Area: {focus_area}

Review this architecture and provide:
- Strengths and weaknesses
- Potential bottlenecks
- Scalability concerns
- Specific improvement recommendations"""

        return self._call_model(prompt)

    def _call_model(self, prompt: str) -> str:
        """Call the local model via API client."""
        try:
            response = simple_chat(
                system_prompt=self.system_prompt,
                user_message=prompt,
            )
            logger.info(f"TechnicalAdvisor response generated for: {prompt[:50]}...")
            return response
        except Exception as e:
            error_msg = f"TechnicalAdvisor error: {str(e)}"
            logger.error(error_msg)
            return f"Error generating response: {str(e)}"

    def _format_context(self, context: dict[str, Any]) -> str:
        """Format context for display."""
        lines = []
        for key, value in context.items():
            if isinstance(value, (list, dict)):
                lines.append(f"## {key.replace('_', ' ').title()}")
                lines.append(str(value))
            else:
                lines.append(f"{key.replace('_', ' ').title()}: {value}")
        return "\n\n".join(lines)

    def _format_config(self, config: dict[str, Any]) -> str:
        """Format configuration for display."""
        lines = []
        for key, value in config.items():
            if isinstance(value, (list, dict)):
                lines.append(f"### {key.replace('_', ' ').title()}")
                lines.append(str(value))
            else:
                lines.append(f"{key.replace('_', ' ').title()}: {value}")
        return "\n\n".join(lines)

    def _format_requirements(self, requirements: dict[str, Any]) -> str:
        """Format requirements for display."""
        lines = []
        for key, value in requirements.items():
            if isinstance(value, (list, dict)):
                lines.append(f"## {key.replace('_', ' ').title()}")
                lines.append(str(value))
            else:
                lines.append(f"{key.replace('_', ' ').title()}: {value}")
        return "\n\n".join(lines)

    def _format_architecture(self, architecture: dict[str, Any]) -> str:
        """Format architecture for display."""
        lines = []
        for component, details in architecture.items():
            lines.append(f"## {component.replace('_', ' ').title()}")
            if isinstance(details, dict):
                for k, v in details.items():
                    lines.append(f"- {k}: {v}")
            else:
                lines.append(str(details))
        return "\n\n".join(lines)
