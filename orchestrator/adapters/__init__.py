"""Adapters package for StoryOS agent advisors.

This package contains specialized advisor agents that provide intelligent
assistance throughout the StoryOS production pipeline.

Adapters:
- story_advisor: Narrative development assistance
- prompt_advisor: Asset generation prompt creation
- production_advisor: Production planning and tracking
- technical_advisor: Technical implementation support
- editorial_advisor: Quality review and curation
"""
from __future__ import annotations

from .story_advisor import StoryAdvisor
from .prompt_advisor import PromptAdvisor
from .production_advisor import ProductionAdvisor
from .technical_advisor import TechnicalAdvisor
from .editorial_advisor import EditorialAdvisor
from .executive_orchestrator import ExecutiveOrchestrator

__all__ = [
    "StoryAdvisor",
    "PromptAdvisor",
    "ProductionAdvisor",
    "TechnicalAdvisor",
    "EditorialAdvisor",
    "ExecutiveOrchestrator",
]
