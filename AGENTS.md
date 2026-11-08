# Agent Working Preferences

These are the default working rules for this repository. Follow them unless the user overrides.

## Edit mode (default)
- Make the requested change only; avoid unrelated edits.
- Prefer targeted edits (exact string replace) over rewriting whole files.
- Do not re-read files already seen in the session.
- Skip verification (build/lint/typecheck/tests) unless:
  - the user explicitly asks, or
  - the change is risky/high-impact and verification is clearly warranted.
- Do not commit, push, install dependencies, or run dev servers unless explicitly asked.

## Token efficiency
- When the user names the file(s) and/or function, go straight there; do not explore broadly.
- Search with grep/glob to pinpoint locations instead of reading whole files.
- For large command output, redirect to a file and grep rather than dumping everything.
- Read only the needed range of a file, not the entire file.

## Project commands
- Tech stack: (to be filled in by the user)
- Build: (none specified)
- Lint: (none specified)
- Test: (none specified)
