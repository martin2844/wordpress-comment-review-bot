# WordPress Comment Review Bot

AI-powered, non-blocking comment moderation for WordPress using OpenAI. Comments are saved immediately as pending and reviewed asynchronously; decisions and reasoning are logged for transparency.

## Features

- Non-blocking moderation: users see “pending” immediately; AI review runs in the background
- GPT-5 reasoning control: low/medium/high effort (only shown for GPT‑5 family models)
- Confidence threshold: auto-approve/reject/spam only above your chosen bar
- Pending Review workflow: low-confidence cases persist as `pending_review` (no re-analysis until manual review)
- Rich logging: info/warning/error/debug logs stored in DB and visible in admin, with context
- Admin UI for Settings, Decisions, Logs
- Docker-based local development with phpMyAdmin

## Repository Layout

- `docker-compose.yml` — WordPress, MySQL, phpMyAdmin stack
- `wp-config.php` — Dev settings (WP_DEBUG, etc.)
- `plugin/` — WordPress plugin source
  - `wordpress-review-bot.php` — plugin bootstrap
  - `includes/class-wrb-comment-manager.php` — moderation flow, scheduling, cron/tick, logging
  - `admin/` — admin pages and templates (Settings, Decisions, Logs)
  - `assets/` — compiled assets
- `src/` — Tailwind/Vite source for styles and scripts

## Prerequisites

- Docker and Docker Compose
- Node 18+ (for building assets; optional if you use the prebuilt assets)

## Quick Start

1. Clone the repo and install dependencies (optional for rebuilds)

```bash
# optional: only if you want to rebuild assets
npm install
```

2. Start the stack

```bash
# optional shortcut via package.json
npm run docker:up

# or with docker compose directly
# docker-compose up -d
```

3. Open WordPress

- App: http://localhost:8080/
- phpMyAdmin: http://localhost:8081/

Complete the WordPress installer (site name, admin user, etc.).

4. Activate and configure the plugin

- Log into WP Admin → Plugins → Activate “WordPress Comment Review Bot”
- Go to Review Bot → Settings
  - Enter your OpenAI API key
  - Choose a model (e.g., GPT‑5 Mini recommended)
  - (GPT‑5 only) Set Reasoning Effort (low/medium/high)
  - Set Confidence Threshold
  - Enable “Auto‑Moderation” and choose which comment types to moderate
  - Save

5. Try it out

- Post a new comment on a post/page
- It should appear as pending immediately
- Within seconds to a minute (depending on cron/tick timing and model), AI will decide and update the comment status
- Review outcomes in:
  - Review Bot → Decisions
  - Review Bot → Logs

## How It Works

- Submission: Filter `pre_comment_approved` sets the comment to `0` (pending) instantly
- Scheduling: Action `comment_post` schedules a per‑comment single event (`wrb_single_moderate_comment`) ~5 seconds later
- Execution: The event handler calls OpenAI, saves a decision (`approve`/`spam`/`reject` or `pending_review`), and updates the comment
- Observability: Each step logs to `wrb_logs` for audit and troubleshooting
- Safety: A manual tick (`process_due_moderation_events`) runs on page loads to execute due events if WP‑Cron loopback is unreliable (e.g., in Docker)

## Admin Pages

- Settings
  - OpenAI API key, model selection, reasoning effort (GPT‑5 only), confidence threshold, auto‑moderation toggles
- Decisions
  - History with filters and a “Needs Manual Review” box for low‑confidence items
- Logs
  - Errors/Warnings/Info/Debug with context; summary counts and filters

## Development

Build/watch assets locally (optional):

```bash
npm run dev   # watch CSS & JS and bring up Docker
# or
npm run build # one‑time build
```

Useful scripts (optional):

```bash
npm run docker:logs               # follow WordPress logs
npm run wp:plugins:list           # list plugins via WP‑CLI in the container
npm run wp:plugin:activate        # activate this plugin via WP‑CLI
```

## Troubleshooting

- No decisions appearing:
  - Verify OpenAI API key in Settings
  - Check Review Bot → Logs for errors; look for “Decision saved” info entries
- Comment remains pending too long:
  - The safety tick runs on page loads; browse any page to spur due events
  - In dev containers, loopback cron can be flaky; the safety tick is designed to mitigate this
- Low confidence results:
  - Appear as `pending_review` and are not re‑analyzed until you handle them

## Security Notes

- All internal actions are server‑side; asynchronous processing avoids exposing secrets client‑side
- Where used, signatures (HMAC) are based on WordPress salts to prevent tampering

## License

MIT

## Changelog & Analysis

- See `IMPLEMENTATION_ANALYSIS.md` for a detailed analysis of the async overhaul and logging improvements.
