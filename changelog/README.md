# Changelog fragments

Each user-visible change gets one small markdown file in this directory. Add the file as part of the PR that introduces the change.

## Naming

One file per change, sorted by filename:

```
YYYY-MM-DD-<short-slug>.md
```

- `YYYY-MM-DD` = the commit/write date (not the deploy date). It's cosmetic; ordering comes from the ship timestamp `ts` derived from git, not the filename.
- Use a short, kebab-case slug describing the change. Gaps and same-day ordering don't matter.

## Content

One user-facing line per change, category-prefixed (Keep a Changelog style); the body is **markdown** (links inline) and rendered as such. A single file may list several changes, one per line, when they shipped together.

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

These entries are written for **players**, so describe the outcome, not the implementation. Keep a Changelog's core rule is "changelogs are for humans, not machines"; never dump a git log.

- **Write a full sentence, not a noun phrase.** Don't copy the commit message, which is terse dev shorthand ("Add burst animation"); tell the player what they get ("Favoriting a problem now plays a burst animation").
- **Lead with what the player gets**; what's now possible, faster, or different from their point of view.
- **One sentence per change, short and scannable.**
- **Avoid internal jargon.** Name the feature and the page the way the UI does, and say the exact behavior that changed, not how the code does it.
- **Stay honest and concrete**; no marketing fluff ("exciting", "amazing") and **no bare qualifiers**. "Correctly", "properly", "the right order", "better" tell players nothing.
- **Tone:** warm, direct, and specific; like a friendly announcement to players, not a dev changelog. Each line reads as one clear, confident sentence.

Examples:

| Commit-style (avoid) | Player-facing (prefer) |
|---|---|
| Dedicated set editing page. | Sets now have their own dedicated editing page. |
| Burst animation on favorite. | Favoriting a problem now plays a burst animation. |
| Count misplays on solved attempts. | Set accuracy no longer counts misplays on problems you've already solved. |
| Sets in correct order. | The sets a problem belongs to now always appear in the same order. |

**Reveal:** what players can do, what changed, what got faster or more reliable.
**Don't reveal:** internal refactors, tests/CI, dependency bumps, commit/PR hashes, or implementation details that don't change what a player sees.

## Rules

- One file per change is a good default. If several distinct changes shipped together, they can share a file (one entry per line), and all get the same date.
- Only add an entry for a change that's actually **complete and usable** by players; don't advertise half-finished work. Add the entry when the feature genuinely works.
- Do NOT reference PR numbers or GitHub URLs (those are dev-facing).
- **Link to a change's own page when it helps** a player go try or read it (e.g. `[about page](/sites/about)`,  `[your profile](/me)`). Use short, stable internal routes; `/me` redirects to the logged-in user's profile (`/me/solve-history`), so they're safe id-agnostic links. Don't link to commit/PR URLs (dev-facing) or unverified forum threads.
- You may link to a relevant forum thread with `[discussion]` as the link text (`/forums/viewtopic.php?t=N`).
