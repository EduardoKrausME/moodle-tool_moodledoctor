# Moodle Doctor (`tool_moodledoctor`)

Moodle Doctor is a read-only administration tool for Moodle that collects deterministic diagnostic facts first and
uses AI only to interpret those facts. It never applies fixes automatically.

## Features

Moodle Doctor provides six workflows:

1. **Health check** — Moodle/PHP/database runtime details, Moodle environment checks, Check API summaries, cron state,
   task failures, installed plugins/dependencies, selected safe configuration, and a cache-store summary.
2. **Explain error** — accepts an administrator-pasted error or stack trace, sanitizes it, and shows the exact AI-bound
   message before anything is sent.
3. **Diagnose cron** — checks `tool_task/lastcronstart`, expected cron frequency, delayed scheduled tasks, problematic
   adhoc tasks, and recent failed task runs.
4. **Diagnose plugin** — compares the component registered by Moodle with the files on disk and shows declared plugin
   dependencies, dependency satisfaction, status, enabled state, and recent failed task metadata.
5. **Diagnose environment** — Moodle environment checks plus status/security/performance Check API summaries.
6. **Investigation checklist** — builds a concise system context and asks the AI for a structured investigation
   checklist.

The deterministic facts are always displayed separately from AI interpretation.

## Security model

Moodle Doctor is intentionally read-only. There is no endpoint that executes shell commands, arbitrary commands,
AI-generated SQL, configuration changes, cache purges, plugin updates, task execution, or automatic remediation.

### Data that is deliberately not collected

The collector never reads these fields into AI-bound facts:

- `task_log.output`;
- `task_adhoc.customdata`;
- database hostname, database name, database username, or database password;
- `config.php` as a whole;
- cookies, session contents, request headers, API keys, provider credentials, or authentication tokens.

For Moodle Universal Cache (MUC), only store name/plugin/class/default flags are collected. Raw cache store
configuration is not collected because Redis/Memcached store configuration can contain credentials and internal
connection information.

### Sanitizer

Every AI-bound value passes through `\tool_moodledoctor\sanitizer`. The sanitizer works recursively on structured
arrays/objects and also scrubs pasted free text. It redacts common forms of:

- `password=`, `passwd`, `dbpass`, `dbpassword`;
- `token=`, access/refresh/auth tokens;
- `api_key`, `apikey`, secrets and client secrets;
- `sesskey`, `MoodleSession`, `PHPSESSID`, session IDs;
- `Authorization` headers and standalone `Bearer ...` credentials;
- `Cookie` and `Set-Cookie` headers;
- DSN credentials such as `user=...;password=...`;
- URLs containing `scheme://user:password@host`;
- common command-line credential arguments such as `--password` and `--token`.

Sanitization is performed when the preview is created and again immediately before calling `local_ai_bridge`. The
sanitizer is designed to be idempotent because the exact previewed messages should remain unchanged by the second pass.

No sanitizer can prove that an arbitrary unknown secret format is safe. Administrators should not intentionally paste
credentials. The safest design is still to avoid collecting sensitive fields in the first place, which is why the
deterministic collectors use allowlists and omit known high-risk fields.

## Preview before AI

AI requests use a two-step flow:

1. Moodle Doctor creates sanitized messages and stores them temporarily in the current Moodle session under a random
   nonce.
2. The administrator sees the exact message array that the plugin will pass to `local_ai_bridge` and must explicitly
   confirm the send.

Previews expire after 15 minutes, are bound to the current Moodle user/session, are limited per session, and are
consumed after one send. Moodle Doctor does not persist prompts or AI responses in its own database tables.

`local_ai_bridge` may add the system instruction configured for the `moodledoctor-diagnose` purpose. That bridge-managed
instruction is not diagnostic data collected by Moodle Doctor.

## AI contract

The AI receives structured diagnostic data and is instructed to return:

- Symptoms;
- Hypotheses;
- Evidence;
- Recommended checks;
- Possible solutions;
- Confidence (`Low`, `Medium`, or `High`).

It is explicitly instructed not to invent missing events/settings/runtime details, not to present hypotheses as collected
facts, and not to claim a definitive root cause without evidence.

## Capability

The capability is:

```text
tool/moodledoctor:use
```

It is defined at system context and has no default role archetype assignment, so site administrators have access by
default while ordinary roles do not. If an institution intentionally delegates the capability, the admin navigation page
can also be used by that role.
