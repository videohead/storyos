"""Production Advisor — production planning and asset tracking.

Provides:
- Production scheduling
- Asset tracking
- Resource allocation
- Pipeline optimization
"""
from __future__ import annotations

import logging
import os
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

SYSTEM_PROMPT = """You are the Production Advisor for StoryOS, a collaborative storytelling platform.

Your role is to assist with production planning, asset tracking, and pipeline
optimization while respecting that humans make final production decisions.

You have access to:
- Story Graph (projects, scenes, shots, assets)
- Asset generation status
- Production schedules
- Resource constraints

Guidelines:
- Provide practical production recommendations
- Track asset generation progress
- Identify bottlenecks in the pipeline
- Suggest optimization opportunities
- Maintain awareness of production deadlines
- Never override producer decisions — recommend and explain
- Be specific and actionable in your recommendations
"""


class ProductionAdvisor:
    """Production planning advisor agent."""

    def __init__(self, name: str = "production_advisor"):
        self.name = name
        self.role = "Production Advisor"
        self.system_prompt = SYSTEM_PROMPT

    def assess_production_status(
        self,
        project_data: dict[str, Any],
    ) -> str:
        """Assess current production status.

        Args:
            project_data: Project metadata, scenes, assets, status

        Returns:
            Production status report
        """
        prompt = f"""Project Data:
{self._format_project(project_data)}

Provide a comprehensive production status report including:
- Assets generated vs. required
- Scenes completed vs. remaining
- Bottlenecks or delays
- Recommendations for next steps"""

        return self._call_model(prompt)

    def plan_asset_generation(
        self,
        scenes: list[dict[str, Any]],
        available_resources: dict[str, Any],
    ) -> str:
        """Plan asset generation schedule.

        Args:
            scenes: List of scenes with required assets
            available_resources: GPU availability, time constraints

        Returns:
            Asset generation plan
        """
        prompt = f"""Scenes Requiring Assets:
{self._format_scenes(scenes)}

Available Resources:
{self._format_resources(available_resources)}

Create an optimized asset generation plan considering:
- Priority order based on production schedule
- Resource utilization
- Dependencies between assets
- Estimated completion times"""

        return self._call_model(prompt)

    def optimize_pipeline(
        self,
        pipeline_data: dict[str, Any],
    ) -> str:
        """Analyze and optimize the production pipeline.

        Args:
            pipeline_data: Pipeline metrics and bottlenecks

        Returns:
            Optimization recommendations
        """
        prompt = f"""Pipeline Data:
{self._format_pipeline(pipeline_data)}

Identify bottlenecks and suggest optimizations for:
- Asset generation queue
- WordPress integration
- ComfyUI workflow execution
- Overall pipeline efficiency"""

        return self._call_model(prompt)

    def track_asset_progress(
        self,
        asset_registry: dict[str, Any],
    ) -> str:
        """Track and report asset generation progress.

        Args:
            asset_registry: Registry of all assets with status

        Returns:
            Progress report
        """
        prompt = f"""Asset Registry:
{self._format_registry(asset_registry)}

Provide a progress report including:
- Assets completed, in progress, pending
- Assets with errors or requiring regeneration
- Recommendations for completing remaining assets"""

        return self._call_model(prompt)

    def _call_model(self, prompt: str) -> str:
        """Call the local model via API client."""
        try:
            response = simple_chat(
                system_prompt=self.system_prompt,
                user_message=prompt,
            )
            logger.info(f"ProductionAdvisor response generated for: {prompt[:50]}...")
            return response
        except Exception as e:
            error_msg = f"ProductionAdvisor error: {str(e)}"
            logger.error(error_msg)
            return f"Error generating response: {str(e)}"

    def _format_project(self, project: dict[str, Any]) -> str:
        """Format project data for display."""
        lines = [
            f"Project: {project.get('name', 'Unknown')}",
            f"Status: {project.get('status', 'Unknown')}",
            f"Scenes: {project.get('scene_count', 0)}",
            f"Assets: {project.get('asset_count', 0)}",
        ]
        return "\n".join(lines)

    def _format_scenes(self, scenes: list[dict[str, Any]]) -> str:
        """Format scene list for display."""
        lines = []
        for i, scene in enumerate(scenes, 1):
            name = scene.get("name", f"Scene {i}")
            assets = scene.get("required_assets", "unknown")
            lines.append(f"{i}. {name} - {assets} assets required")
        return "\n".join(lines)

    def _format_resources(self, resources: dict[str, Any]) -> str:
        """Format resource info for display."""
        lines = []
        for key, value in resources.items():
            lines.append(f"{key.replace('_', ' ').title()}: {value}")
        return "\n".join(lines)

    def _format_pipeline(self, pipeline: dict[str, Any]) -> str:
        """Format pipeline data for display."""
        lines = []
        for key, value in pipeline.items():
            if isinstance(value, dict):
                lines.append(f"## {key.replace('_', ' ').title()}")
                for k, v in value.items():
                    lines.append(f"- {k}: {v}")
            else:
                lines.append(f"{key.replace('_', ' ').title()}: {value}")
        return "\n".join(lines)

    def _format_registry(self, registry: dict[str, Any]) -> str:
        """Format asset registry for display."""
        lines = []
        for asset_id, asset_data in registry.items():
            status = asset_data.get("status", "unknown")
            source = asset_data.get("source", "unknown")
            lines.append(f"- {asset_id}: {status} (from {source})")
        return "\n".join(lines)
