---
name: semantic-commit-messages
description: "Write git commit messages in the semantic/conventional format (`<type>(<scope>): <emoji> <description>`) instead of a free-form sentence. Use whenever the user asks for a commit, or asks to write/review a commit message, and wants it typed (feat/fix/refactor/perf/style/test/docs/build/ci/chore/revert/security) rather than descriptive prose."
---

Prefix every commit subject with a **type** and a **scope** (mandatory in this repo, not optional), before the actual description — a small style change that makes `git log` scannable (what kind of change, at a glance) and lets tooling (changelogs, semantic-release) parse history mechanically instead of guessing from prose. This repo follows [Conventional Commits](https://www.conventionalcommits.org/) via commitlint's `@commitlint/config-conventional` defaults, plus one repo-specific addition: a `security` type for vulnerability fixes (see Types below) — not the loose "use whatever type feels right" version.

## Format

```
<type>(<scope>): <emoji> <description>

<optional body>

<optional footer trailers>
```

- `<scope>` — mandatory, the area of the codebase touched (`deploy`, `theme`, `docker`...). Never an issue identifier. Enforced by commitlint's `scope-empty` rule (`commitlint.config.js`) — a commit without one is rejected.
- `<emoji>` — optional but recommended, right after the colon, chosen per `<type>` (table below). It's part of `<description>` as far as commitlint is concerned (only `type(scope):` is parsed), so it never breaks validation.
- `<description>` — mandatory, imperative present tense ("add", not "added"/"adds" — think "This commit will ..."), always in English (translate it if drafted in another language first), no capitalized first letter, no trailing period, ≤ 72 chars.
- `<body>` — optional, explains the *what* and *why*, not the *how*, contrasted with previous behavior. Same imperative present tense, wrap at ~72 chars. Blank line separates it from the subject.
- `<footer>` — optional except when the commit is breaking (then mandatory). See Footer trailers below.

```
feat(ui): ✨ add hat wobble
^--^ ^--^ ^--^--------------^
|    |    |  |
|    |    |  +-> Description: imperative, present tense, no cap, no period.
|    |    +----> Emoji: matches the type, see table below.
|    +---------> Scope: mandatory, area touched.
+--------------> Type: see table below.
```

## Types

| Type | Emoji | When to use it |
|---|---|---|
| `feat` | ✨ | New feature |
| `fix` | 🐛 | Bug fix |
| `refactor` | ♻️ | Rewrites or restructures code with **no** behavior change |
| `perf` | ⚡️ | Performance improvement (still no behavior change) |
| `docs` | 📝 | Documentation only |
| `style` | 💄 | Formatting only, no logic change |
| `test` | ✅ | Adds or fixes tests |
| `build` | 📦 | Dependencies, build config |
| `ci` | 👷 | CI/CD pipelines (`.github/workflows/deploy-backend.yml`, `.github/scripts/`) |
| `chore` | 🔧 | Misc maintenance/config, everything else |
| `revert` | ⏪ | Reverts a previous commit |
| `security` | 🔒 | Fix that specifically closes a vulnerability |

`feat`/`fix` are the only two that describe a change to what the software *does* for its users — that split is what semantic-release-style tooling keys off for version bumps (see below). Everything else describes how it's built, tested, deployed, secured, or documented.

## Footer trailers

One per line, below the body, delete whichever doesn't apply:

- `Verified-by:` — the real counts from what was actually run before committing, not just a checkmark: `Verified-by: composer audit 0 advisories, npm audit 0 vulnerabilities`. Run `docker exec chatbot-symfony composer audit` (backend) and/or `cd frontend && npm audit` and read the actual numbers off their output — never write `0` without having run the command. No linter/static analysis (PHPStan, ESLint, Pint...) is currently configured in this repo — don't invent counts for tools that don't run here.
- `Refs:` — GitHub issue number if relevant, links the ticket without closing it (`Refs #123`).
- `Closes:` — GitHub issue number to auto-close on merge (`Closes #123`) — use instead of/alongside `Refs:`.
- `Co-authored-by:` — credit a human pair or contributor who isn't the committer — `Co-authored-by: <name> <email>`, GitHub renders it on the commit. **Never used to attribute Claude/an AI agent in this repo** — see the note under Writing one.
- `Signed-off-by:` — Developer Certificate of Origin sign-off, if this repo ever requires it (not currently enforced) — `Signed-off-by: <name> <email>`.

## Breaking changes

- `!` right after the type/scope: `feat(api)!: ✨ remove status endpoint`
- Described in the footer when the description alone isn't enough:
  ```
  feat(api)!: ✨ remove status endpoint

  BREAKING CHANGE: the /status endpoint no longer exists, use /health instead.
  ```
- Single-line `BREAKING CHANGE:` → one space after the colon. Multi-line → two newlines after the colon.

Reach for `!`/`BREAKING CHANGE:` deliberately — it's the signal that bumps a **major** version, not something to add on every `fix`/`refactor`.

## Versioning (if this repo ever cuts tagged releases)

- Any commit with a **breaking change** → bump **major**.
- Else, any `feat` or `fix` (API/UI-relevant) → bump **minor**.
- Else → bump **patch**.

Not currently applicable here: this repo doesn't cut tagged releases — but the type list is chosen to stay compatible if that changes.

## Special commit formats

- **Initial commit**: `chore(repo): 🔧 init`
- **Merge commit**: leave git's default `Merge branch '<name>'` message as-is, don't rewrite it to a type-prefixed form.
- **Revert commit**: `git revert` generates `Revert "<reverted subject>"` by default — leave it as-is unless the revert needs its own explanation, in which case `revert(<scope>): ⏪ <description>` (the explicit type, table above) is also accepted by the hook.

## Tooling — enforced in this repo

`commitlint` + a Husky `commit-msg` hook are installed at the repo root and **reject a non-compliant commit message locally**, before it lands:

- `package.json` (root) — devDependencies `@commitlint/cli`, `@commitlint/config-conventional`, `husky`; `"prepare": "husky"` script wires the hook back up on every `npm install`.
- `commitlint.config.js` (root) — extends `@commitlint/config-conventional` (whose default `type-enum` already covers `feat fix refactor perf style test docs build ci chore revert`), **adds one type**: `security` (see Types table above), and **overrides `scope-empty`** to `[2, 'never']` — config-conventional leaves scope optional by default, this repo requires it on every commit.
- `.husky/commit-msg` — runs `npx --no -- commitlint --edit "$1"` on every commit.

This root `package.json`/`node_modules` is unrelated to `frontend`'s own — a fresh clone needs one `npm install` **at the repo root** to activate the hook locally (separate from `npm install` inside `frontend/`). Verify it's active: `git config core.hooksPath` should print `.husky/_`.

A commit template (`.gitmessage`, opt in with `git config commit.template .gitmessage`) pre-fills `git commit` (without `-m`) — every line is a `#` comment (type/emoji table, rules), stripped automatically, so the contributor writes the actual message from scratch guided by it rather than editing live placeholder text.

Not installed (would need a deliberate follow-up decision, not assumed): **Commitizen** (`git cz`, an interactive prompt for building a compliant message) and **semantic-release**/**standard-version** (auto-bump the version and generate a changelog from commit types since the last tag) — see the Versioning section above for why there's nothing to key off yet.

## Applying this in a repo that doesn't already use it

Check `git log --oneline -10` before assuming this format is already the convention — if recent history is plain descriptive sentences (no `type:` prefix), introducing semantic types is a deliberate style change, not a restatement of the existing convention: mention that explicitly rather than silently switching styles mid-history.

## Writing one

1. Look at what's actually staged/changed (`git diff --staged`, `git status`) — the type must match the *effect* of the diff, not the intent behind the request. A refactor that happens to fix a latent bug is `fix`, not `refactor`.
2. Pick exactly one type, one concern. If a commit seems to need two types (e.g. a fix *and* a docs update), that's usually a sign it should be two commits — but don't split an already-requested single commit without checking with the user first.
3. Keep `<description>` short (~50-72 chars), imperative present tense, English, no cap, no trailing period; put any further explanation in the commit body.
4. Scope is mandatory — pick the area that best fits (`fix(backend): 🐛 ...`, `feat(frontend): ✨ ...`, or `traefik`/`docs`/`scripts`). For a change that genuinely spans several areas, use the broadest sensible one (`repo`) rather than omitting it. Never an issue identifier.
5. If writing a `Verified-by:` trailer, actually run `docker exec chatbot-symfony composer audit` and/or `cd frontend && npm audit` first — never fabricate the counts.
6. Still follow the system's own git instructions for everything else — only create a commit when asked, never `--no-verify`, prefer new commits over amending, etc. This skill only changes the *subject line format*, not when or how a commit gets made.
7. **No AI attribution**: do not add a `Co-Authored-By: Claude ...` trailer, a `Claude-Session:` link, or any other mention that Claude/an AI made the commit — even though the harness's default git instructions normally append one. The user has explicitly asked for commits in this repo to carry no AI attribution.

## Examples

```
feat(notifications): ✨ add email notifications on new direct messages
```
```
feat(shopping-cart): ✨ add the amazing button
```
```
fix(shopping-cart): 🐛 prevent ordering an empty shopping cart
```
```
fix(backend): 🐛 add missing parameter to service call

The error occurred because ProviderSelectionService read a stale
AiProviderConfig row instead of the newly activated one.

Verified-by: composer audit 0 advisories, npm audit 0 vulnerabilities
```
```
perf(analytics): ⚡️ decrease memory footprint for unique-visitor counting using HyperLogLog
```
```
refactor(math): ♻️ implement fibonacci number calculation as recursion
```
```
style(theme): 💄 remove empty line
```
```
build(deps): 📦 update dependencies
```
```
ci(repo): 👷 add a GitHub Actions workflow running composer/npm audit
```
```
security(auth): 🔒 patch session fixation on login
```
```
feat(api)!: ✨ remove ticket list endpoint

Refs #1337

BREAKING CHANGE: ticket endpoints no longer support listing all entities.
```

## References

- https://www.conventionalcommits.org/
- https://github.com/ikatyang/emoji-cheat-sheet — GitHub `:shortcode:` → emoji mapping, useful for
  picking additional emojis (PR titles, doc callouts) beyond the per-type table above.
