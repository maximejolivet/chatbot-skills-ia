---
name: phpstan
description: "Run PHPStan on the backend and fix what it finds, following this repo's rules: never lower the level, never use @phpstan-ignore, prioritize real type/nullable/param bugs over style noise, and explain every correction. Use whenever the user asks to run PHPStan, fix static analysis errors, or reduce/clean up the PHPStan baseline in backend/."
---

PHPStan runs at **level 9** against `backend/src` and `backend/tests` (`backend/phpstan.neon`), backed by `backend/phpstan-baseline.neon`. The baseline exists to grandfather pre-existing debt, not to hide new bugs — a shrinking baseline is a goal, a growing one is a regression.

## Hard rules (non-negotiable, from the user)

1. **Never add `@phpstan-ignore*` comments.** If an error is real, fix the code. If PHPStan is factually wrong for a specific line (see "When PHPStan is wrong" below), that's a signal to re-verify the fix, not to silence the message.
2. **Never lower `level:` in `phpstan.neon`.** Level 9 stays level 9.
3. **Never widen `phpstan-baseline.neon` to cover a *new* error** just introduced by fresh code — the baseline only grows when explicitly regenerated after a deliberate, reviewed decision (see Workflow below), never as a quick way to make a new change pass.
4. **Explain every correction** — what was wrong, why, and what the fix does. Don't just paste diffs.

## Running it

Backend runs in Docker; there's no local `php`/`composer` on the host.

```bash
docker exec chatbot-symfony composer phpstan          # the real, baseline-aware check (what CI/the dev would run)
docker exec chatbot-symfony vendor/bin/phpstan analyse --no-progress --memory-limit=1G <path>   # ad hoc, one file/dir
docker exec chatbot-symfony composer phpstan:baseline  # regenerate the baseline (see Workflow)
```

`composer phpstan` passing with "[OK] No errors" does **not** mean the codebase is clean — it means nothing *new* was introduced beyond what's baselined. Before doing any work, check how big the baseline actually is (`wc -l backend/phpstan-baseline.neon`) and consider surfacing that to the user rather than assuming "0 errors" is the whole story.

## Priority order when fixing

The user's stated priority, in this order:

1. **Type errors** (`argument.type`, `return.type`) — wrong type flowing into a function/method/constructor.
2. **Nullable method calls** — calling a method on something typed `?Foo`, or `Cannot call method X on ...|null`.
3. **Missing/wrong parameters** — arity or type mismatches on calls.

Lower priority, generally fine to leave baselined unless trivial or directly adjacent to a fix already being made: `phpstan-strict-rules` style noise — `booleanNot.exprNotBoolean`, `ternary.shortNotAllowed`, `cast.useless`, `booleanAnd.*`, `if.condNotBoolean`, loose-comparison bans (`equal.notAllowed`/`notEqual.notAllowed`), `missingType.generics`/`missingType.iterableValue` on legacy code. These don't represent bugs, just this repo's stricter style preferences — deliberately out of scope unless asked for explicitly, so real bugs stay findable in the noise.

To scope a fixing pass, diff the full (unbaselined) error list against the baselined one and bucket by `identifier` (PHPStan's `--error-format=json` includes it per message) — this tells you exactly how many of each category exist before deciding how deep to go. For a large baseline, say so and let the user pick the scope (all debt vs. a bounded pass) rather than assuming — see the AskUserQuestion example that motivated this skill.

## Root-cause fixes worth checking first

These two apply repo-wide and can resolve dozens of errors in one shot before touching any individual file — check them before doing per-file fixes:

- **`doctrine.objectManagerLoader` in `phpstan.neon`.** If missing, phpstan-doctrine can't resolve entity metadata, so every `Repository::findX()`/`getResult()` call types as `mixed` instead of `Entity[]`, cascading into dozens of unrelated `return.type`/`offsetAccess`/`generics.interfaceConflict` errors. Fix: point it at a script that boots the kernel and returns the `ManagerRegistry`'s manager (`backend/tests/object-manager.php` already does this — keep it in sync with `App\Kernel`'s constructor signature, and if PHPStan starts warning about the bootstrap script itself, cast/validate `$_SERVER` values the same way as everywhere else, don't cast `mixed` blindly).
- **Missing `@implements Interface<T>` on repositories implementing a generic interface** (e.g. `Sylius\Resource\Doctrine\Persistence\RepositoryInterface`) alongside `@extends ServiceEntityRepository<T>`. Without the explicit `@implements`, PHPStan infers the interface's template param from its bound (often a much wider type than the entity), producing `generics.interfaceConflict`. Fix: add `@implements SyliusRepositoryInterface<Entity>` next to the existing `@extends` line, once per repository.

## Fix patterns that come up constantly

**Nullable Doctrine id after a guaranteed persist/flush.** `$entity->getId()` is `?int` by Doctrine convention even when the entity is definitely persisted at that point in the code (just fetched from a repository, or `flush()`ed a few lines earlier). Don't weaken the target type — narrow at the call site instead:
```php
$documentId = $document->getId() ?? throw new \LogicException('Document must be persisted.');
```
This documents the invariant instead of hiding it, and still fails loudly if it's ever actually violated.

**`mixed` from loosely-typed config/JSON/request data flowing into a strict function.** PHPStan (with strict-rules) disallows casting `mixed` directly — `(string) $mixedValue` errors with `cast.string`/`cast.int`/`cast.double` even though the runtime cast would "work". Don't fight this with a cast; validate and fall back explicitly:
```php
// bad — still errors, and silently mangles arrays/objects at runtime
$method = mb_strtoupper($config['method'] ?? 'GET');

// good — validates, with a real fallback or a clear failure
$method = mb_strtoupper(\is_string($config['method'] ?? null) ? $config['method'] : 'GET');
```
For values genuinely expected to be present and well-formed (e.g. a URL a workflow step can't run without), prefer throwing a clear exception over silently defaulting — that's a real robustness improvement, not just appeasing the tool. This repo's `WorkflowExecutionService` has small private helpers for this shape (`expectConfigString`, `expectConfigStringMap`, `expectConfigArray`) — reuse that pattern instead of scattering ad hoc `is_string`/`is_array` checks when a file needs several.

**`preg_replace()`/`json_encode()`/`file_get_contents()` returning `string|false`/`string|null`.** These fail on malformed input/regex errors — don't assume success:
```php
$clean = preg_replace($pattern, '', $text) ?? $text;   // PCRE error -> keep original
$json = json_encode($data) ?: '{}';                     // encode failure -> safe fallback
```

**`getOneOrNullResult()`/`getSingleResult()` on a raw (non-repository) `QueryBuilder`.** Unlike `getResult()`, these aren't covered by phpstan-doctrine's return-type inference even with `objectManagerLoader` set, and come back `mixed`. Narrow explicitly:
```php
$result = $qb->getQuery()->getOneOrNullResult();
return $result instanceof Entity ? $result : null;
```

**A `match` over a backed enum that's missing a newer case.** If PHPStan reports `match.unhandled` for an enum case, don't just satisfy the type checker — check whether the missing arm is a **real product bug** (a case added since the match was written, meaning that code path throws `UnhandledMatchError` at runtime today). This repo actually had one: an admin display helper's `match` over `WorkflowStepType` never got updated when `SetConversation` was added. Treat this class of error as high-priority, not stylistic.

**PHPUnit reflection helpers returning `mixed`.** A private-method-invoking test helper (`(new \ReflectionMethod(...))->invoke(...)`) is untyped by nature — don't weaken the helper's declared return type. Narrow at each call site instead, right after invoking:
```php
$result = $this->invokePrivate($service, 'someMethod', [...]);
self::assertIsArray($result);   // narrows for every subsequent use in this test
self::assertCount(2, $result);
```
(phpstan-phpunit isn't installed in this repo, so `assertArrayHasKey`/similar don't narrow types — only `assertIsArray`/`is_array()` checks do.)

**An attribute/class referenced in a `use` statement that doesn't actually resolve.** `method_exists()`/`class_exists()` checks and IDE autocomplete won't always catch a stale import (e.g. a class that moved namespaces between framework versions) because PHP resolves attribute classes lazily. PHPStan's `class.notFound` on an attribute is worth treating as a real bug, not noise — verify with `docker exec chatbot-symfony php -r "require 'vendor/autoload.php'; var_dump(class_exists('Fully\Qualified\Name'));"` before assuming it's a stub-resolution quirk.

**A union return type referencing an optional dependency's class that isn't installed** (e.g. `Symfony\Component\Cache\Adapter\RedisAdapter::createConnection()`'s return type includes `Predis\ClientInterface`, but `predis/predis` isn't required). This is a legitimate case for `composer require --dev <package>` purely so the type surface resolves — it's not error suppression, it's making the analysis accurate to a real (if currently unused) capability. Don't reach for this if the class is genuinely unrelated to the codebase; only when the referencing type is something this project's own dependencies declare as a real alternative.

## When PHPStan is wrong — don't blindly obey it

PHPStan's inference can be less precise than the actual domain model, especially for DQL scalar `SELECT ... GROUP BY` results. If satisfying PHPStan's suggestion would introduce a behavior change, stop and re-derive the correct fix from the entity/domain model, not from the error message.

Real example from this repo: PHPStan flagged `$row['feedback']?->value ?? 'none'` as an unnecessary nullsafe (`nullsafe.neverNull`), implying `$row['feedback']` is never null. But `Message::$feedback` is genuinely `?MessageFeedback` — "no feedback given" is the common case, not an edge case. Removing the `?->` as suggested would have caused a fatal error on every message without feedback. The correct fix was an explicit `instanceof` check that's correct regardless of what static inference says:
```php
$key = $row['feedback'] instanceof MessageFeedback ? $row['feedback']->value : 'none';
```
When in doubt, check the entity's actual property type/docblock before trusting a query-result type inference.

## Workflow for a fixing pass

1. Run `docker exec chatbot-symfony composer phpstan`. If it's clean, check the baseline size (`wc -l backend/phpstan-baseline.neon`) before declaring "nothing to do" — surface it to the user if it's non-trivial (see the recommended/reduced-scope/no-op options pattern: confirm scope before touching many files).
2. If attacking baseline debt, temporarily analyse *without* the baseline include to see the real error set (`grep -v 'includes:' phpstan.neon`-style scratch config, or just diff `phpstan-baseline.neon` conceptually) and bucket by `identifier` via `--error-format=json`.
3. Check the two root-cause items above first — they can collapse dozens of errors before any per-file work starts.
4. Fix argument.type / return.type / nullable-call / missing-param errors file by file, using the patterns above. Leave the strict-rules style noise baselined unless trivial.
5. After each file (or small batch), re-run PHPStan scoped to just that file/those files to confirm the fix and check nothing new appeared.
6. **Run the test suite** (`docker exec chatbot-symfony php bin/phpunit`) — these fixes touch real logic (added validation, exception throws, narrowed types), not just annotations. A green PHPStan run means nothing if it broke behavior.
7. Regenerate the baseline to lock in the reduced debt: `docker exec chatbot-symfony composer phpstan:baseline`, then confirm `composer phpstan` is still clean and the level in `phpstan.neon` is untouched.
8. Explain what was fixed, file by file or by pattern, including *why* each fix is correct (not just "satisfies PHPStan").
