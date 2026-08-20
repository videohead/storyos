# Agent collaboration

World Graph Studio treats AI as an advisory layer around the Story Graph. The
creator remains the editor and final decision-maker; advisor output does not
silently change project records or submit generation jobs.

## Delivered collaboration

- Filmmaking-advisor definitions stored as portable `.agent.md` profiles.
- An advisor selector and automatic request routing by creative department.
- Story Graph context assembled from the record currently being edited.
- Chat, analysis, and continuity actions in the AI Editor and classic
  story-element workflow.
- Configurable local or hosted LLM access through WordPress.
- WordPress REST and Abilities surfaces protected by the relevant capabilities.
- In-session conversation display, with every response presented as a
  suggestion for the creator to accept, revise, or ignore.

These capabilities work across the registered story and production content
types. Connection and Template records remain configuration rather than
creative-advisor contexts.

## Human approval boundary

The delivered advisor workflow returns text and analysis. It does not apply
field edits, alter publication state, or launch external generation without a
separate user action. That boundary keeps WordPress content and revision
history authoritative.

## Session history

The current editor transcript lives in the browser tab. World Graph Studio does
not claim a persistent per-record collaboration thread, structured approval
ledger, or complete advisor audit history in the current release.

## Extension design

The following concepts are useful extension points, not pending release
commitments:

- persistent recommendations with rationale and confidence;
- accept/reject workflows that create normal WordPress revisions;
- explicit review states such as “needs revision” or “generation ready”;
- a pre-generation polish step with a visible before/after proposal;
- durable advisor transcripts and audit records linked to Story Graph records;
- multi-advisor handoffs with conflict resolution.

Any implementation of those extensions must preserve explicit human approval,
capability checks, provenance, and WordPress as the system of record.

## Related documentation

- [AI Editor](../AI_Editor.md)
- [Agent architecture](../Agent_Architecture.md)
- [Generation engine](GENERATION_ENGINE.md)
- [Delivery status](../Delivery_Status.md)
