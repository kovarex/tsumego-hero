# Changelog fragments

Each user-visible change gets **one small markdown file** in this directory. The file is added as part of the PR that introduces the change. See `.local/docs/changelog-whats-new-design.md` for the full design.

## Naming

One file per change, sorted by filename:

```
YYYY-MM-DD-<short-slug>.md
```

- `YYYY-MM-DD` = the commit/write date (not the deploy date). It's **cosmetic** — ordering comes from the ship timestamp `ts` derived from git, not the filename.
- Use a short, kebab-case slug describing the change. Gaps and same-day ordering don't matter.

## Content

One user-facing line, category-prefixed (Keep a Changelog style); the body is **markdown** (links inline) and rendered as such.

```markdown
Fixed: Set accuracy no longer counts misplays on problems you've already solved.
```

You may optionally pin the change to a specific commit via front-matter, useful when the fragment is added in a **separate** commit from the change it describes (e.g. backfilling a change someone else merged without a fragment):

```markdown
---
commit: 1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b
---
Added: Dedicated set editing page.
```

The `commit` SHA is resolved through git and its committer date becomes the entry's ship timestamp. Omit it when the fragment is committed together with its change.

Categories:

- `Added:` new user-facing feature
- `Fixed:` user-visible bug fix
- `Changed:` behavior change users notice
- `Performance:` speed improvement
- `Removed:` something taken away

## Writing style

These entries are written for **players**, so describe the outcome, not the implementation. Keep a Changelog's core rule is "changelogs are for humans, not machines" — never dump a git log.

- **Write a full sentence, not a noun phrase.** Commit subjects ("Add burst animation") are dev shorthand; announce the result instead.
- **Lead with what the player gets** — what's now possible, faster, or different from their point of view.
- **One sentence per change, short and scannable.** Two sentences usually means two changes (split into separate fragments).
- **Avoid internal jargon** (SGF, RD, semai, "solved attempts" logic). Name features the way the UI does.
- **Stay honest and concrete** — no marketing fluff ("exciting", "amazing") and **no bare qualifiers**. "Correctly", "properly", "the right order", "better" tell players nothing. Name the **where and the what**: the page/feature and the exact behavior that changed.
- **Tone:** warm, direct, and specific — like a friendly announcement to players, not a dev changelog. Each line reads as one clear, confident sentence.

Examples:

| Commit-style (avoid) | Player-facing (prefer) |
|---|---|
| Dedicated set editing page. | Sets now have their own dedicated editing page. |
| Burst animation when favoriting a problem. | Favoriting a problem now plays a burst animation. |
| Set accuracy counts misplays on solved attempts. | Set accuracy no longer counts misplays on problems you've already solved. |
| Sets now appear in the correct order. | The sets a problem belongs to now always appear in the same order. |

**Reveal:** what players can do, what changed, what got faster or more reliable.
**Don't reveal:** internal refactors, tests/CI, dependency bumps, commit/PR hashes, or implementation details that don't change what a player sees.

## Rules

- One file per user-visible change (`feat` / `fix` / `perf` / user-visible `change`).
- Only add an entry for a change that's actually **complete and usable** by players — don't advertise half-finished work. Add the entry when the feature genuinely works (e.g., once mobile tsumego play is solid, not just the mobile shell).
- No entry for `refactor` / `chore` / `test` / `ci` / `docs`.
- **User-facing text only** — describe the change for users. Do NOT reference PR numbers or GitHub URLs (those are dev-facing).
- **Link to a change's own page when it helps** a player go try or read it (e.g. `[about page](/sites/about)`, `[legal notice page](/sites/impressum)`, `[your profile](/me)`). Use short, stable internal routes; `/me` redirects to the logged-in user's profile (`/me/solve-history`, `/me/contributions`), so they're safe id-agnostic links. **Link to the page where the feature actually lives** — a feature on a specific item's page (e.g. a particular set `/sets/view/<id>`) has no generic link, so leave it plain. Also **verify the target is a real page** (a route can return 200 yet be a stub, e.g. `/tags`). Don't link to commit/PR URLs (dev-facing) or unverified forum threads.
- You may link to a relevant forum thread with `[discussion]` as the link text (`/forums/viewtopic.php?t=N`). Forum threads are sometimes deleted, so **verify the link works** (`viewtopic.php?t=N`) before adding it; if the thread's gone, drop the link rather than leave it broken. Never invent a title.

## Generation

`scripts/changelog-generate.mjs` reads these fragments and writes `changelog/index.json` (`ts`, `date`, `category`, `text`, `file`), ordered by ship time (`ts` = the master commit that landed each file). Run it in the deploy/CI step:

`node scripts/changelog-generate.mjs`

## Archiving

Fragments accumulate (one per change) — that's expected and fine. To keep the active folder tidy, move older, frozen fragments into `changelog/archive/<year>/` (one file per change, kept separate) and have the generator read both `changelog/` and `changelog/archive/**`. Do **not** consolidate them into a single file — that breaks the per-change model and reintroduces conflicts. No need to do this until the folder actually feels cluttered.
