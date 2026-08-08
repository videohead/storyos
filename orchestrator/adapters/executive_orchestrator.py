"""Executive Orchestrator — central coordination for all advisor agents.

Receives user requests, determines intent, routes work to appropriate
advisors, aggregates responses, and maintains project context.

Based on Microsoft Agent Framework (MAF) orchestration patterns.
"""
from __future__ import annotations

import logging
import time
from typing import Any
from datetime import datetime

from adapters.story_advisor import StoryAdvisor
from adapters.prompt_advisor import PromptAdvisor
from adapters.production_advisor import ProductionAdvisor
from adapters.technical_advisor import TechnicalAdvisor
from adapters.editorial_advisor import EditorialAdvisor

logger = logging.getLogger(__name__)

SYSTEM_PROMPT = """You are the Executive Orchestrator for StoryOS, a collaborative storytelling platform.

Your role is to coordinate a team of specialized advisor agents:

1. Story Advisor — narrative development, plot consistency, character arcs
2. Prompt Advisor — asset generation prompts for ComfyUI
3. Production Advisor — production planning, asset tracking, scheduling
4. Technical Advisor — API integration, system architecture, troubleshooting
5. Editorial Advisor — quality review, style consistency, curation

Guidelines:
- Receive user requests and determine which advisor(s) should handle them
- Route complex requests to multiple advisors when needed
- Aggregate responses from multiple advisors into coherent answers
- Maintain awareness of the overall project context
- Never override creator decisions — coordinate recommendations
- Be efficient: don't involve advisors unless their expertise is needed
- When multiple advisors contribute, synthesize their input clearly
"""


class ExecutiveOrchestrator:
    """Central coordinator for all StoryOS advisor agents."""

    def __init__(self):
        self.name = "executive_orchestrator"
        self.role = "Executive Orchestrator"
        self.system_prompt = SYSTEM_PROMPT

        # Initialize all advisors
        self.story_advisor = StoryAdvisor()
        self.prompt_advisor = PromptAdvisor()
        self.production_advisor = ProductionAdvisor()
        self.technical_advisor = TechnicalAdvisor()
        self.editorial_advisor = EditorialAdvisor()

        # Advisor registry for routing
        self.advisors = {
            "story": self.story_advisor,
            "prompt": self.prompt_advisor,
            "production": self.production_advisor,
            "technical": self.technical_advisor,
            "editorial": self.editorial_advisor,
        }

        # Conversation history for context
        self.conversation_history: list[dict[str, Any]] = []

    def process_request(
        self,
        user_request: str,
        context: dict[str, Any] = None,
        force_advisor: str = None,
    ) -> dict[str, Any]:
        """Process a user request by routing to appropriate advisor(s).

        Args:
            user_request: User's question or request
            context: Project context (story graph, assets, etc.)
            force_advisor: Force routing to specific advisor ('story', 'prompt',
                          'production', 'technical', 'editorial')

        Returns:
            Dict with response, advisors involved, and metadata
        """
        start_time = time.time()
        context = context or {}

        # Log the request
        logger.info(f"ExecutiveOrchestrator received request: {user_request[:100]}...")

        # Determine which advisor(s) to involve
        if force_advisor:
            advisors_to_involve = [force_advisor]
        else:
            advisors_to_involve = self._determine_advisors(user_request, context)

        # Route to advisors and collect responses
        responses = {}
        errors = []

        for advisor_name in advisors_to_involve:
            try:
                advisor = self.advisors.get(advisor_name)
                if not advisor:
                    errors.append(f"Unknown advisor: {advisor_name}")
                    continue

                response = self._route_to_advisor(
                    advisor=advisor,
                    advisor_name=advisor_name,
                    user_request=user_request,
                    context=context,
                )
                responses[advisor_name] = response

            except Exception as e:
                error_msg = f"{advisor_name} advisor error: {str(e)}"
                logger.error(error_msg)
                errors.append(error_msg)

        # Calculate processing time
        processing_time = time.time() - start_time

        # Build response
        result = {
            "request": user_request,
            "responses": responses,
            "processing_time": round(processing_time, 3),
            "advisors_involved": advisors_to_involve,
            "timestamp": datetime.now().isoformat(),
        }

        if errors:
            result["errors"] = errors

        # Store in conversation history
        self._store_conversation(
            user_request=user_request,
            result=result,
            context=context,
        )

        return result

    def ask_story(
        self,
        question: str,
        story_context: dict[str, Any],
    ) -> dict[str, Any]:
        """Ask the Story Advisor a narrative question.

        Args:
            question: Narrative question or request
            story_context: Story Graph context

        Returns:
            Story Advisor response
        """
        return self.process_request(
            user_request=question,
            context={"story_context": story_context, "type": "story"},
            force_advisor="story",
        )

    def generate_prompt(
        self,
        prompt_type: str,
        target_data: dict[str, Any],
        style_reference: str = "",
    ) -> dict[str, Any]:
        """Generate an asset generation prompt.

        Args:
            prompt_type: Type of prompt ('character', 'environment', 'storyboard')
            target_data: Target data (character, location, scene)
            style_reference: Style reference

        Returns:
            Prompt Advisor response with positive/negative prompts
        """
        context = {
            "prompt_type": prompt_type,
            "target_data": target_data,
            "type": "prompt",
        }

        if prompt_type == "character":
            result = self.process_request(
                user_request=f"Generate character prompt for: {target_data.get('name', 'unknown')}",
                context=context,
                force_advisor="prompt",
            )
        elif prompt_type == "environment":
            result = self.process_request(
                user_request=f"Generate environment prompt for: {target_data.get('name', 'unknown')}",
                context=context,
                force_advisor="prompt",
            )
        elif prompt_type == "storyboard":
            result = self.process_request(
                user_request=f"Generate storyboard prompt for scene",
                context=context,
                force_advisor="prompt",
            )
        else:
            result = self.process_request(
                user_request=f"Generate {prompt_type} prompt",
                context=context,
                force_advisor="prompt",
            )

        return result

    def check_production_status(
        self,
        project_data: dict[str, Any],
    ) -> dict[str, Any]:
        """Check production status and get recommendations.

        Args:
            project_data: Project metadata and status

        Returns:
            Production Advisor response
        """
        return self.process_request(
            user_request="Assess production status and provide recommendations",
            context={"project_data": project_data, "type": "production"},
            force_advisor="production",
        )

    def troubleshoot(
        self,
        service: str,
        error_message: str,
        context: dict[str, Any] = None,
    ) -> dict[str, Any]:
        """Troubleshoot a technical issue.

        Args:
            service: Service name (wordpress, comfyui, redis, celery)
            error_message: Error message
            context: Additional context

        Returns:
            Technical Advisor response
        """
        return self.process_request(
            user_request=f"Troubleshoot {service}: {error_message}",
            context={
                "service": service,
                "error_message": error_message,
                "context": context,
                "type": "technical",
            },
            force_advisor="technical",
        )

    def review_asset(
        self,
        asset: dict[str, Any],
        review_type: str = "quality",
    ) -> dict[str, Any]:
        """Review an asset for quality or narrative fit.

        Args:
            asset: Asset data
            review_type: Type of review ('quality', 'style', 'narrative')

        Returns:
            Editorial Advisor response
        """
        context = {
            "asset": asset,
            "review_type": review_type,
            "type": "editorial",
        }

        if review_type == "quality":
            request = "Review asset quality and provide feedback"
        elif review_type == "style":
            request = "Check asset style consistency"
        elif review_type == "narrative":
            request = "Evaluate asset narrative fit"
        else:
            request = f"Review asset for {review_type}"

        return self.process_request(
            user_request=request,
            context=context,
            force_advisor="editorial",
        )

    def multi_advisor_review(
        self,
        asset: dict[str, Any],
        story_context: dict[str, Any],
        project_data: dict[str, Any],
    ) -> dict[str, Any]:
        """Conduct a multi-advisor review of an asset.

        Invokes Story, Editorial, and Production advisors simultaneously.

        Args:
            asset: Asset to review
            story_context: Story Graph context
            project_data: Project metadata

        Returns:
            Aggregated responses from multiple advisors
        """
        return self.process_request(
            user_request="Conduct comprehensive multi-advisor review",
            context={
                "asset": asset,
                "story_context": story_context,
                "project_data": project_data,
                "type": "multi_review",
            },
            force_advisor=None,  # Let routing determine all relevant advisors
        )

    def _determine_advisors(
        self,
        request: str,
        context: dict[str, Any],
    ) -> list[str]:
        """Determine which advisors should handle this request.

        Uses keyword matching and context analysis to route.

        Args:
            request: User request text
            context: Request context

        Returns:
            List of advisor names to involve
        """
        request_lower = request.lower()
        advisors = []

        # Keyword-based routing
        story_keywords = [
            "story", "narrative", "character", "plot", "arc", "dialogue",
            "scene", "consistency", "worldbuilding",
        ]
        prompt_keywords = [
            "prompt", "generate", "image", "art", "visual", "comfyui",
            "stable diffusion", "negative prompt", "positive prompt",
        ]
        production_keywords = [
            "production", "schedule", "asset", "track", "progress",
            "resource", "pipeline", "bottleneck",
        ]
        technical_keywords = [
            "error", "troubleshoot", "api", "integration", "comfyui",
            "wordpress", "redis", "celery", "technical", "architecture",
            "debug", "fix",
        ]
        editorial_keywords = [
            "review", "quality", "style", "consistency", "curate",
            "evaluate", "feedback", "critique",
        ]

        # Check for story-related keywords
        if any(kw in request_lower for kw in story_keywords):
            advisors.append("story")

        # Check for prompt-related keywords
        if any(kw in request_lower for kw in prompt_keywords):
            advisors.append("prompt")

        # Check for production-related keywords
        if any(kw in request_lower for kw in production_keywords):
            advisors.append("production")

        # Check for technical-related keywords
        if any(kw in request_lower for kw in technical_keywords):
            advisors.append("technical")

        # Check for editorial-related keywords
        if any(kw in request_lower for kw in editorial_keywords):
            advisors.append("editorial")

        # Context-based routing
        context_type = context.get("type", "")
        if context_type == "story" and "story" not in advisors:
            advisors.append("story")
        elif context_type == "prompt" and "prompt" not in advisors:
            advisors.append("prompt")
        elif context_type == "production" and "production" not in advisors:
            advisors.append("production")
        elif context_type == "technical" and "technical" not in advisors:
            advisors.append("technical")
        elif context_type == "editorial" and "editorial" not in advisors:
            advisors.append("editorial")
        elif context_type == "multi_review":
            # Multi-review involves all relevant advisors
            if "story" not in advisors:
                advisors.append("story")
            if "editorial" not in advisors:
                advisors.append("editorial")
            if "production" not in advisors:
                advisors.append("production")

        # Default to story advisor if no keywords matched
        if not advisors:
            advisors.append("story")

        return list(set(advisors))  # Remove duplicates

    def _route_to_advisor(
        self,
        advisor: Any,
        advisor_name: str,
        user_request: str,
        context: dict[str, Any],
    ) -> str:
        """Route a request to a specific advisor.

        Args:
            advisor: Advisor instance
            advisor_name: Advisor name
            user_request: User request
            context: Request context

        Returns:
            Advisor's response text
        """
        logger.info(f"Routing to {advisor_name} advisor")

        # Route based on advisor type
        if advisor_name == "story":
            story_context = context.get("story_context", {})
            return advisor.analyze_story(
                story_context=story_context,
                question=user_request,
            )

        elif advisor_name == "prompt":
            prompt_type = context.get("prompt_type", "character")
            target_data = context.get("target_data", {})
            style_ref = context.get("style_reference", "")

            if prompt_type == "character":
                result = advisor.generate_character_prompt(
                    character=target_data,
                    style_reference=style_ref,
                )
            elif prompt_type == "environment":
                result = advisor.generate_environment_prompt(
                    location=target_data,
                    style_reference=style_ref,
                )
            elif prompt_type == "storyboard":
                result = advisor.generate_storyboard_prompt(
                    scene=target_data,
                    shot=target_data.get("shot", {}),
                )
            else:
                result = {"positive": "", "negative": ""}

            return f"Positive: {result.get('positive', '')}\n\nNegative: {result.get('negative', '')}"

        elif advisor_name == "production":
            project_data = context.get("project_data", {})
            return advisor.assess_production_status(
                project_data=project_data,
            )

        elif advisor_name == "technical":
            service = context.get("service", "unknown")
            error_message = context.get("error_message", user_request)
            return advisor.troubleshoot_integration(
                service=service,
                error_message=error_message,
                context=context.get("context"),
            )

        elif advisor_name == "editorial":
            asset = context.get("asset", {})
            review_type = context.get("review_type", "quality")
            return advisor.review_asset_quality(
                asset=asset,
            )

        else:
            raise ValueError(f"Unknown advisor: {advisor_name}")

    def _store_conversation(
        self,
        user_request: str,
        result: dict[str, Any],
        context: dict[str, Any],
    ):
        """Store conversation in history.

        Args:
            user_request: Original user request
            result: Response result
            context: Request context
        """
        entry = {
            "timestamp": result.get("timestamp"),
            "request": user_request,
            "advisors": result.get("advisors_involved", []),
            "processing_time": result.get("processing_time"),
        }
        self.conversation_history.append(entry)

        # Limit history to last 100 entries
        if len(self.conversation_history) > 100:
            self.conversation_history = self.conversation_history[-100:]

    def get_conversation_history(self, limit: int = 10) -> list[dict[str, Any]]:
        """Get recent conversation history.

        Args:
            limit: Number of recent entries to return

        Returns:
            List of conversation entries
        """
        return self.conversation_history[-limit:]

    def get_advisor_summary(self) -> dict[str, Any]:
        """Get summary of all advisors.

        Returns:
            Dict with advisor names and capabilities
        """
        return {
            "advisors": {
                name: {
                    "name": advisor.name,
                    "role": advisor.role,
                }
                for name, advisor in self.advisors.items()
            },
            "total_advisors": len(self.advisors),
            "conversation_history_length": len(self.conversation_history),
        }
