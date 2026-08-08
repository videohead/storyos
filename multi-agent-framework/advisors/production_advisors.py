"""Production Department Advisors — Core production management."""
from __future__ import annotations

import logging
from typing import Any

from api_client import simple_chat

logger = logging.getLogger(__name__)

__all__ = [
    "ExecutiveProducerAdvisor",
    "SecondADAdvisor",
    "ProductionCoordinatorAdvisor",
    "ProductionAccountantAdvisor",
    "UnitProductionManagerAdvisor",
]


class ExecutiveProducerAdvisor:
    """Executive Producer advisor — high-level production oversight."""

    def __init__(self, name: str = "executive_producer_advisor"):
        self.name = name
        self.role = "Executive Producer"
        self.system_prompt = """You are the Executive Producer Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with high-level production oversight, financing,
distribution strategy, and overall project management.

You have access to:
- Production budget and financial reports
- Schedule and progress reports
- Distribution and market analysis
- Contract and legal documentation

Guidelines:
- Protect the financial interests of the production
- Support the Producer and Director's vision
- Manage relationships with stakeholders
- Ensure delivery commitments are met
- Never compromise project viability for short-term gains"""

    def oversee_production(self, project: dict[str, Any]) -> str:
        """Oversee overall production progress.

        Args:
            project: Project overview and current status

        Returns:
            Production oversight report and recommendations
        """
        prompt = f"""Project Status:
{self._format_project(project)}

Oversee production by:
- Reviewing budget vs. actual spending
- Monitoring schedule adherence
- Assessing risk factors
- Coordinating with stakeholders
- Making high-level decisions on issues"""
        return self._call_model(prompt)

    def _format_project(self, project: dict) -> str:
        return f"""Title: {project.get('title', 'Unknown')}
Budget: {project.get('budget', 'Unknown')}
Current Spend: {project.get('current_spend', 'Unknown')}
Schedule Variance: {project.get('schedule_variance', 'Unknown')}
Status: {project.get('status', 'Unknown')}"""

    def manage_finances(self, financial_report: dict[str, Any]) -> str:
        """Manage production finances.

        Args:
            financial_report: Current financial status

        Returns:
            Financial management recommendations
        """
        prompt = f"""Financial Report:
{self._format_financial_report(financial_report)}

Manage finances by:
- Reviewing budget allocations
- Approving expenditures
- Identifying cost overruns
- Planning for contingencies
- Reporting to investors/stakeholders"""
        return self._call_model(prompt)

    def _format_financial_report(self, report: dict) -> str:
        return f"""Total Budget: {report.get('total_budget', 'Unknown')}
Spent to Date: {report.get('spent', 'Unknown')}
Remaining: {report.get('remaining', 'Unknown')}
Over/Under by Department: {report.get('department_variance', 'Unknown')}"""


class SecondADAdvisor:
    """Second AD advisor — second assistant director duties."""

    def __init__(self, name: str = "second_ad_advisor"):
        self.name = name
        self.role = "Second Assistant Director"
        self.system_prompt = """You are the Second AD Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with call sheet preparation, actor management,
and supporting the First AD's daily schedule.

You have access to:
- Shooting schedule and call sheets
- Actor availability and requirements
- Extra and background information
- Set logistics and access requirements

Guidelines:
- Prepare accurate call sheets
- Manage actor and extra call times
- Support the First AD's schedule
- Communicate clearly with all departments
- Never let scheduling errors delay shooting"""

    def prepare_call_sheet(self, shooting_day: dict[str, Any]) -> str:
        """Prepare the daily call sheet.

        Args:
            shooting_day: Day's shooting schedule

        Returns:
            Call sheet content and distribution plan
        """
        prompt = f"""Shooting Day:
{self._format_shooting_day(shooting_day)}

Prepare call sheet by:
- Listing call times for all cast and crew
- Identifying scenes to be shot
- Noting location and set details
- Including weather and wardrobe notes
- Distributing to all departments"""
        return self._call_model(prompt)

    def _format_shooting_day(self, day: dict) -> str:
        return f"""Date: {day.get('date', 'Unknown')}
Location: {day.get('location', 'Unknown')}
Scenes: {', '.join(day.get('scenes', []))}
Cast Required: {', '.join(day.get('cast_required', []))}
Extras Needed: {day.get('extras_count', 'Unknown')}"""

    def manage_actors(self, scene: dict[str, Any]) -> str:
        """Manage actor call times and preparation.

        Args:
            scene: Scene details with cast requirements

        Returns:
            Actor management plan
        """
        prompt = f"""Scene Cast:
{self._format_scene(scene)}

Manage actors by:
- Scheduling call times for each actor
- Coordinating with wardrobe and makeup
- Preparing actors for scenes in advance
- Managing extra and background talent
- Ensuring actors are ready on time"""
        return self._call_model(prompt)

    def _format_scene(self, scene: dict) -> str:
        return f"""Scene: {scene.get('scene_number', 'Unknown')}
Main Cast: {', '.join(scene.get('main_cast', []))}
Extras: {scene.get('extras_count', 'Unknown')}
Special Requirements: {', '.join(scene.get('special_requirements', []))}"""


class ProductionCoordinatorAdvisor:
    """Production Coordinator advisor — production logistics and administration."""

    def __init__(self, name: str = "production_coordinator_advisor"):
        self.name = name
        self.role = "Production Coordinator"
        self.system_prompt = """You are the Production Coordinator Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with production logistics, administration, and
coordinating between departments and offices.

You have access to:
- Production schedules and reports
- Department needs and requests
- Location and permit information
- Vendor and rental contacts

Guidelines:
- Keep production running smoothly
- Coordinate between departments efficiently
- Maintain accurate records
- Support the Producer and First AD
- Never let administrative issues delay shooting"""

    def coordinate_production(self, week_schedule: dict[str, Any]) -> str:
        """Coordinate production for a week.

        Args:
            week_schedule: Weekly shooting schedule

        Returns:
            Production coordination plan
        """
        prompt = f"""Weekly Schedule:
{self._format_week_schedule(week_schedule)}

Coordinate production by:
- Tracking department needs and requests
- Coordinating location access and permits
- Managing vendor and rental schedules
- Supporting First AD with daily logistics
- Reporting to Producer on progress"""
        return self._call_model(prompt)

    def _format_week_schedule(self, schedule: dict) -> str:
        return f"""Week: {schedule.get('week', 'Unknown')}
Shooting Days: {schedule.get('shooting_days', 'Unknown')}
Locations: {', '.join(schedule.get('locations', []))}
Special Requirements: {', '.join(schedule.get('special_requirements', []))}"""

    def manage_permits(self, location: dict[str, Any]) -> str:
        """Manage location permits and requirements.

        Args:
            location: Location details and permit needs

        Returns:
            Permit management plan
        """
        prompt = f"""Location:
{self._format_location(location)}

Manage permits by:
- Identifying required permits
- Coordinating with local authorities
- Ensuring all documentation is current
- Planning for permit conditions
- Tracking permit expiration dates"""
        return self._call_model(prompt)

    def _format_location(self, location: dict) -> str:
        return f"""Name: {location.get('name', 'Unknown')}
Permit Type: {location.get('permit_type', 'Unknown')}
Shooting Dates: {location.get('shooting_dates', 'Unknown')}
Special Conditions: {', '.join(location.get('conditions', []))}"""


class ProductionAccountantAdvisor:
    """Production Accountant advisor — production finance and payroll."""

    def __init__(self, name: str = "production_accountant_advisor"):
        self.name = name
        self.role = "Production Accountant"
        self.system_prompt = """You are the Production Accountant Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with production finance, payroll, cost reporting,
and maintaining accurate financial records.

You have access to:
- Production budget and cost reports
- Payroll information
- Invoice and expense tracking
- Department spending reports

Guidelines:
- Maintain accurate financial records
- Process payroll on time
- Monitor budget adherence
- Report cost overruns promptly
- Never compromise financial accuracy"""

    def manage_payroll(self, pay_period: dict[str, Any]) -> str:
        """Manage production payroll.

        Args:
            pay_period: Pay period information and staff list

        Returns:
            Payroll management plan
        """
        prompt = f"""Pay Period:
{self._format_pay_period(pay_period)}

Manage payroll by:
- Verifying staff hours and rates
- Processing payments on time
- Handling deductions and taxes
- Managing crew onboarding and offboarding
- Maintaining payroll records"""
        return self._call_model(prompt)

    def _format_pay_period(self, period: dict) -> str:
        return f"""Period: {period.get('period', 'Unknown')}
Staff Count: {period.get('staff_count', 'Unknown')}
Total Budget: {period.get('total_budget', 'Unknown')}
Department Breakdown: {period.get('department_breakdown', 'Unknown')}"""

    def track_costs(self, department: dict[str, Any]) -> str:
        """Track department costs and budget adherence.

        Args:
            department: Department spending information

        Returns:
            Cost tracking report
        """
        prompt = f"""Department Costs:
{self._format_department(department)}

Track costs by:
- Monitoring daily spending
- Comparing to budget allocations
- Identifying cost overruns
- Reporting to Production Accountant
- Suggesting cost-saving measures"""
        return self._call_model(prompt)

    def _format_department(self, dept: dict) -> str:
        return f"""Department: {dept.get('department', 'Unknown')}
Budget: {dept.get('budget', 'Unknown')}
Spent: {dept.get('spent', 'Unknown')}
Remaining: {dept.get('remaining', 'Unknown')}
Over/Under: {dept.get('variance', 'Unknown')}"""


class UnitProductionManagerAdvisor:
    """Unit Production Manager advisor — UPM duties and on-set management."""

    def __init__(self, name: str = "unit_production_manager_advisor"):
        self.name = name
        self.role = "Unit Production Manager"
        self.system_prompt = """You are the Unit Production Manager Advisor for StoryOS, a filmmaking AI assistant.

Your role is to assist with on-set production management, logistics,
and supporting the Producer and First AD in daily operations.

You have access to:
- Production budget and schedule
- Location and permit information
- Department reports and needs
- Vendor and rental contacts

Guidelines:
- Support the First AD's daily schedule
- Manage on-set logistics efficiently
- Handle production issues promptly
- Maintain budget awareness
- Never let production issues delay shooting"""

    def manage_unit(self, shooting_day: dict[str, Any]) -> str:
        """Manage production unit for a shooting day.

        Args:
            shooting_day: Day's shooting schedule and needs

        Returns:
            Unit management plan
        """
        prompt = f"""Shooting Day:
{self._format_shooting_day(shooting_day)}

Manage unit by:
- Coordinating with First AD on schedule
- Managing on-set logistics
- Handling production issues
- Supporting department needs
- Reporting to Producer on progress"""
        return self._call_model(prompt)

    def _format_shooting_day(self, day: dict) -> str:
        return f"""Date: {day.get('date', 'Unknown')}
Scenes: {', '.join(day.get('scenes', []))}
Location: {day.get('location', 'Unknown')}
Budget Concerns: {', '.join(day.get('budget_concerns', []))}
Issues: {', '.join(day.get('issues', []))}"""

    def handle_issues(self, issue: dict[str, Any]) -> str:
        """Handle production issues as they arise.

        Args:
            issue: Issue description and context

        Returns:
            Issue resolution plan
        """
        prompt = f"""Issue:
{self._format_issue(issue)}

Handle issue by:
- Assessing impact on schedule and budget
- Identifying resolution options
- Consulting with relevant departments
- Making timely decisions
- Documenting issue and resolution"""
        return self._call_model(prompt)

    def _format_issue(self, issue: dict) -> str:
        return f"""Type: {issue.get('type', 'Unknown')}
Description: {issue.get('description', 'Unknown')}
Impact: {issue.get('impact', 'Unknown')}
Urgency: {issue.get('urgency', 'Unknown')}"""
