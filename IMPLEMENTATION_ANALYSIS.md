# AI Moderation Overhaul — Implementation Analysis (Nov 2025)

## Executive Summary

Target behavior (now effective):

1. A user submits a comment.
2. The page returns immediately; the comment is saved in WordPress as pending (status 0).
3. The AI Review Bot analyzes the comment asynchronously (no blocking of the user request).
4. A decision is persisted and logged; the comment is updated accordingly (approve/spam/reject), or marked `pending_review` for manual attention when confidence is low.

## Problems Observed Initially

- Blocking UX: Comment submission waited for the OpenAI request to finish, causing slow/variable page loads.
- API call issues: Early request payload mismatches with the Responses API schema.
- Low-confidence handling: Uncertain decisions weren’t persisted distinctly and could be reprocessed.
- Logging inconsistency: Levels and records were not consistently stored or visible in admin.
- Cron reliability in Docker: Loopback-based cron was flaky, leading to missed background runs.

## Key Improvements Implemented

### 1) Non‑Blocking Moderation Architecture

- Hold immediately (no API calls inline):
  - Hooked `pre_comment_approved` to `hold_comment_for_ai_review` and return numeric `0` to set pending cleanly.
- Schedule per-comment async processing:
  - Hooked `comment_post` to `maybe_trigger_async_after_comment` which always schedules a `wrb_single_moderate_comment` single-event 5s later.
- Batch processing still supported:
  - Existing cron job `wrb_process_held_comments` processes held comments in small batches (up to 10 per run) when due.
- Manual safety tick (cron fallback):
  - Added `process_due_moderation_events`, invoked on `init`, that inspects the core cron array and executes any due events for:
    - `wrb_single_moderate_comment` (per comment)
    - `wrb_process_held_comments` (batch)
  - Prevents missed runs in environments where WP-Cron loopback fails (common in containers).

Effect: Comment submissions are fast and predictable; AI review happens shortly after via scheduled execution, not inline.

### 2) OpenAI Responses API Compliance & Controls

- Request payload switched to the Responses API format with `input[]` and explicit `role` fields.
- Removed unsupported parameters (e.g., `temperature` when not applicable to the chosen model) in the call site.
- Reasoning effort control:
  - New settings option `reasoning_effort` (low/medium/high), persisted and used as `reasoning: { effort: <value> }`.
  - UI hides this field unless the model starts with `gpt-5`.

### 3) Decision Persistence & Pending Review

- Low-confidence outcomes are saved as `pending_review`, preventing needless reprocessing.
- Decisions page UI adds stats, filters, and a “Needs Manual Review” box (recent pending items).

### 4) Logging Overhaul & Admin Visibility

- Database logs table `wrb_logs` with levels: `error`, `warning`, `info`, `debug` (normalized to lowercase).
- Logging integration:
  - Success: minimal `info` (e.g., “Decision saved”).
  - Low-confidence: `warning` with context (confidence, threshold, model, time).
  - Errors/exceptions: `error` with details.
  - Lifecycle tracing: filter invocation, scheduling, single-event execution, manual tick processing.
- Logs page enhancements:
  - Summary counts (Errors, Warnings, Info, Pending Review mentions).
  - Filters by level and comment ID; pagination; context details.

## Data Model

- `wrb_ai_decisions`: Stores moderation decisions including `pending_review` entries; no schema change required.
- `wrb_logs`: Centralized plugin logs with indexed columns for fast filtering.

## Control Flow (Happy Path)

- Submission: `pre_comment_approved` ⇒ return `0` (pending).
- Scheduling: `comment_post` ⇒ schedule `wrb_single_moderate_comment` (5s).
- Execution: handler ⇒ `process_async_moderation()` ⇒ OpenAI ⇒ save decision ⇒ update comment status.
- Observability: Multiple `log_event()` calls record status and details; visible in the admin Logs page.

## Edge Cases & Mitigations

- No API key: Skips processing; logged.
- Post type not enabled: Skips; logged.
- Existing decision: Skips to prevent re-analysis; logged.
- WP-Cron loopback fails: Manual `process_due_moderation_events()` tick processes due plugin events; logged.
- Low confidence: Saves `pending_review`; comment remains pending; logged as `warning`.

## UI/UX Updates

- Settings: Reasoning Effort (GPT‑5 only), Max Tokens (no upper cap), Confidence Threshold slider.
- Decisions: Pending review stats, filter, and a box listing recent low-confidence cases.
- Logs: Quick counts for Errors, Warnings, Info, and Pending Review mentions; context drill-down.

## Verification Steps Used

- Ensured comment save is not blocked (immediate pending status).
- Observed scheduling logs and subsequent moderation logs.
- Confirmed decisions persisted (approve/spam/reject/pending_review) and reflected in admin.
- Confirmed Logs page shows `info` on success, `warning` for low-confidence, and `error` for failures.

## Known Limitations / Future Enhancements

- Add an admin toggle to enable/disable the manual safety tick.
- Provide a status widget for queued/scheduled moderation jobs.
- Add one-click reprocess for `pending_review` entries.
- WP-CLI commands for bulk moderation and diagnostics.

## Files Most Impacted

- `plugin/includes/class-wrb-comment-manager.php` — core moderation flow, cron/tick/scheduling, logging.
- `plugin/admin/templates/settings-page.php` — model/effort controls and conditional UI visibility.
- `plugin/admin/templates/decisions-page.php` — pending_review UI and stats.
- `plugin/admin/templates/logs-page.php` — summary counts and normalized levels.

## Outcome

The plugin now respects the non-blocking workflow, gives clear operator control over reasoning effort and confidence handling, and provides robust logging and admin visibility to diagnose issues quickly—especially in Docker-based dev setups where loopback cron can be unreliable.
