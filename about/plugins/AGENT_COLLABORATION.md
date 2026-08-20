# World Graph Studio Agent Collaboration Architecture

> Agent Participation in CPT Creation, Editing, Review, and Generation

## Purpose

This document defines how World Graph Studio agents participate in the lifecycle of Story Graph entities (CPTs).

This is intentionally separate from the Generation Engine architecture.

The Generation Engine governs execution.

This document governs collaboration between users and AI agents during creation, editing, review, enrichment, and generation preparation.

---

# Architectural Principle

Agents collaborate on Story Graph entities.

Agents do not own Story Graph entities.

Humans remain the system of record.

Agents may:

- Analyze
- Recommend
- Review
- Enrich
- Validate
- Polish

Agents may not directly modify CPT content without explicit user approval.

---

# Supported CPT Types

Agents may participate in:

- Project
- Story World
- Character
- Location
- Prop
- Organization
- Episode
- Scene
- Shot
- Storyboard Frame
- Asset
- Production Entity
- Editorial Entity

Future CPTs automatically become agent-enabled through registration.

---

# CPT Collaboration Lifecycle

```text
Create
  ↓
Draft
  ↓
Agent Review
  ↓
Revision Suggestions
  ↓
User Approval
  ↓
Publish / Generate
```

---

# Agent Interaction Modes

## Create

Agents help create a new CPT.

Example:

Character Designer Agent assists with character creation.

Acceptance Criteria:

Given a new Character CPT
When a user requests agent assistance
Then the agent proposes structured character content.

---

## Edit

Agents review existing content.

Acceptance Criteria:

Given an existing CPT
When a user requests review
Then the agent provides structured recommendations.

---

## Review

Agents inspect content quality.

Examples:

- Historical accuracy
- Visual consistency
- Story continuity
- Production feasibility

Acceptance Criteria:

Given a CPT
When review is requested
Then findings are attached as recommendations.

---

## Polish

Polish is an optional pre-generation workflow.

Polish improves generation readiness.

Polish does not submit generation jobs.

Acceptance Criteria:

Given a generatable CPT
When a user selects Polish
Then World Graph Studio generates an improved generation request and presents changes before submission.

---

# CPT-Centric Expert Selection

## Character

Recommended Agents:

- Character Designer
- Costume Designer
- Art Director
- Historical Advisor

## Location

Recommended Agents:

- Production Designer
- Architecture Advisor
- Environment Artist

## Vehicle

Recommended Agents:

- Historical Vehicle Expert
- Mechanical Advisor
- Military Historian

## Prop

Recommended Agents:

- Prop Designer
- Art Director
- Continuity Advisor

## Costume

Recommended Agents:

- Costume Designer
- Fashion Advisor
- Historical Advisor

## Scene

Recommended Agents:

- Story Advisor
- Director
- Production Advisor

## Shot

Recommended Agents:

- Director
- Director of Photography
- Storyboard Artist

---

# Agent Chat Layer

Every agent-enabled CPT includes a collaboration thread.

```text
CPT
  ↓
Conversation
  ↓
Recommendations
  ↓
Approval Workflow
```

The chat becomes part of the CPT history.

---

# Revision Requests

Agents may request changes.

Example:

Historical Vehicle Expert:

"Vehicle configuration contains components from different historical periods."

Acceptance Criteria:

Given an agent recommendation
When content requires correction
Then the CPT enters Needs Review state until addressed or dismissed.

---

# Suggested Changes Model

Agents produce:

- Recommendation
- Rationale
- Suggested Revision
- Confidence Score

Agents do not directly edit content.

---

# Human Approval Requirement

Acceptance Criteria:

Given an agent-suggested change
When user approval has not occurred
Then the CPT remains unchanged.

Given user approval
When the change is applied
Then the revision is recorded in audit history.

---

# Generation Readiness Review

Before a Generation Request is submitted:

```text
CPT
 ↓
Agent Consultation
 ↓
Optional Polish
 ↓
Generation Ready
 ↓
Submit To Queue
```

---

# Agent Access To Context

Agents may access:

- CPT fields
- Story Graph relationships
- Related assets
- Previous revisions
- Style guides
- Project settings
- Template metadata

Agents should not rely solely on prompt text.

---

# Agent Registration Framework

Acceptance Criteria:

Given a new agent type
When registered with World Graph Studio
Then the agent may be associated with specific CPT types.

Example:

```text
HistoricalVehicleExpert
   → Vehicle CPT

ArtDirector
   → Character CPT
   → Prop CPT
   → Location CPT
```

---

# Review States

```text
Draft
Review Requested
Needs Revision
Polish Available
Generation Ready
Submitted
Archived
```

---

# Audit Requirements

All agent interactions must be recorded.

Audit history includes:

- Agent consulted
- Timestamp
- Recommendations
- Accepted revisions
- Rejected revisions
- User actions

---

# Definition of Done

- [ ] Agents can be attached to CPT types
- [ ] Users can request expert consultations
- [ ] Agents can submit structured recommendations
- [ ] Agents can request revisions
- [ ] Users remain final approvers
- [ ] CPT history records agent activity
- [ ] Polish workflow available before generation
- [ ] Generation readiness can include agent review
- [ ] Agent conversations remain linked to CPT history

## Strategic Goal

World Graph Studio agents participate throughout content creation and refinement, making expert knowledge available during CPT creation and editing before generation requests are sent to external providers.
