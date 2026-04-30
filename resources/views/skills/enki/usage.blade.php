## Quick start

The `enki` skill is installed once via the [Agent Install](/help/agent-install) prompt. After that, it gives your agent the `/enki add` command for all future sessions.

## Adding a skill

Once the enki skill is active, install any library skill by slug:

```
/enki add coding/code-reviewer
```

The agent will download, extract, and immediately activate the skill so it's ready to use in the same session.

## Finding skills

Browse the full library to discover skills by name, category, or tag. Skills with a category prefix install using only the final segment of the slug — `coding/code-reviewer` installs as `code-reviewer`.

## Installation scope

By default skills install globally to `~/.claude/skills/`. To install for a specific project only, ask the agent to use `.claude/skills/` in the project root instead.
