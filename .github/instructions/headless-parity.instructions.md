---
name: Headless parity
description: Require explicit headless parity assessment and delivery for shared WordPress functionality and UI.
applyTo: "headless/**,wordpress/wp-content/plugins/worldgraph/**,wordpress/wp-content/themes/worldgraph-child/**,about/**"
---

# Headless Parity Instructions

Before planning, implementing, reviewing, testing, or documenting a change in
scope, read and maintain the
[Headless Parity Deliverable](../../headless/PARITY.md).

- Headless is optional to deploy but parity is a required repository deliverable
  for applicable delivered functionality and user-facing behavior.
- Treat applicable WordPress and headless work as one change. Update the
  adapter/DTO, types, UI states, browser-user authorization, cache or
  revalidation behavior, tests, and parity ledger as needed.
- Parity is equivalent capability and outcome, not copied wp-admin markup. A
  WordPress-only exception must be explicit in the ledger with a narrow
  rationale; an absent headless implementation is not an exception.
- Prioritize functional coverage over visual perfection. Implement plain,
  accessible controls that complete the real authorized workflow before visual
  refinement. Polished placeholders, inert buttons, and simulated success do
  not count as parity.
- Update `headless/PARITY.md` whenever capability coverage, known gaps,
  exceptions, or validation status changes. A newly added or changed applicable
  behavior is incomplete until its headless path is delivered; a ledger update
  cannot waive the requirement. Pre-existing debt need not be retired by an
  unrelated change, but touched behavior must not widen it without an explicitly
  approved and recorded deferral.
- Treat `worldgraph/v1` as the established REST compatibility surface, not the
  automatic product model or contract for new headless work. Reuse shared
  PHP/domain behavior and make any new API contract an explicit architecture
  and specification decision.
- Do not promote bundled prototypes, experimental integrations, or source-only
  scaffolds into parity commitments unless their delivery status also changes.
- Follow the [project build instructions](./instructions.md) and
  [testing guide](../testing/testing.md), validating both affected runtimes with
  the narrowest relevant checks.
