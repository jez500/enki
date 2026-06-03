---
name: {{ $appSlug }}
description: "Install skills from the {{ $appName }} library. Activate when the user runs `/{{ $appSlug }} add {slug}`, `/{{ $appSlug }} search`, `/{{ $appSlug }} list`, `/{{ $appSlug }} info`, or `/{{ $appSlug }} categories` — or asks to install, discover, or browse skills."
version: 1.0.0
tags: [skills, install, library]
---

# {{ $appName }}

Your gateway to the team's AI skills library. Use the `/{{ $appSlug }}` sub-commands below to discover, browse, and install skills.

## First-Time Setup

After installing this skill, persist your credentials so they are available in future sessions. Add the following to `~/.claude/settings.json`:

```json
{
  "env": {
    "ENKI_URL": "<library-url-you-were-given>",
    "ENKI_TOKEN": "<api-token-you-were-given>"
  }
}
```

Replace the placeholder values with the URL and token from your install prompt.

---

## /{{ $appSlug }} add `<slug>`

Downloads and installs a skill from the library.

**1. Download the archive**

```bash
curl -fsSL \
  -H "Authorization: Bearer $ENKI_TOKEN" \
  "$ENKI_URL/api/skills/<slug>/download" \
  -o /tmp/enki-download.zip
```

**2. Extract to the skills directory**

```bash
SKILL_DIR=$(basename "<slug>")
mkdir -p ~/.claude/skills/$SKILL_DIR
unzip -o /tmp/enki-download.zip -d ~/.claude/skills/$SKILL_DIR
rm /tmp/enki-download.zip
```

For project-scoped installation, use `.claude/skills/` in the project root instead.

**3. Activate for this session**

```bash
cat ~/.claude/skills/$SKILL_DIR/SKILL.md
```

Read the SKILL.md so the new skill is immediately active, then confirm to the user what was installed.

---

## /{{ $appSlug }} list

Lists all available skills. Supports optional filtering.

```bash
# All skills (first page)
curl -fsSL \
  -H "Authorization: Bearer $ENKI_TOKEN" \
  "$ENKI_URL/api/skills" | jq '.data[] | {slug, name, summary, categories, tags}'

# Filter by category
curl -fsSL \
  -H "Authorization: Bearer $ENKI_TOKEN" \
  "$ENKI_URL/api/skills?category=coding" | jq '.data[] | {slug, name}'

# Page through results
curl -fsSL \
  -H "Authorization: Bearer $ENKI_TOKEN" \
  "$ENKI_URL/api/skills?page=2"
```

**Response shape:**

```json
{
  "data": [
    {
      "slug": "coding/code-reviewer",
      "name": "Code Reviewer",
      "summary": "Reviews pull requests for quality and correctness.",
      "categories": ["coding"],
      "author": "anthropic",
      "version": "1.2.0",
      "updatedAt": "2025-04-28",
      "ratings": 12,
      "tags": ["review", "git"],
      "installs": 47,
      "isExternal": false
    }
  ],
  "current_page": 1,
  "last_page": 3,
  "per_page": 30,
  "total": 72
}
```

**Query parameters:**

| Parameter  | Description                                    | Example           |
|------------|------------------------------------------------|-------------------|
| `q`        | Full-text search across name, slug, summary, tags | `q=review`     |
| `category` | Filter by category slug                        | `category=coding` |
| `sort`     | `recent` (default), `name`, or `stars`         | `sort=stars`      |
| `page`     | Page number                                    | `page=2`          |

---

## /{{ $appSlug }} search `<query>`

Searches the library by keyword. Shorthand for `/{{ $appSlug }} list` with a `q` parameter.

```bash
curl -fsSL \
  -H "Authorization: Bearer $ENKI_TOKEN" \
  "$ENKI_URL/api/skills?q=<query>" | jq '.data[] | {slug, name, summary}'
```

Present results as a numbered list with slug, name, and summary so the user can choose which to install with `/{{ $appSlug }} add <slug>`.

---

## /{{ $appSlug }} info `<slug>`

Shows the full readme and usage instructions for a skill without installing it.

```bash
curl -fsSL \
  -H "Authorization: Bearer $ENKI_TOKEN" \
  "$ENKI_URL/api/skills/<slug>" | jq '{name, version, summary, readme, usage}'
```

**Response shape:**

```json
{
  "slug": "coding/code-reviewer",
  "name": "Code Reviewer",
  "summary": "Reviews pull requests for quality and correctness.",
  "categories": ["coding"],
  "author": "anthropic",
  "version": "1.2.0",
  "updatedAt": "2025-04-28",
  "ratings": 12,
  "tags": ["review", "git"],
  "installs": 47,
  "isExternal": false,
  "readme": "# Code Reviewer\n\nFull readme content…",
  "usage": "Activate when the user asks to review a PR…"
}
```

Display the `readme` content to the user as formatted markdown.

---

## /{{ $appSlug }} categories

Lists all available skill categories so the user can browse by topic.

```bash
curl -fsSL \
  -H "Authorization: Bearer $ENKI_TOKEN" \
  "$ENKI_URL/api/categories" | jq '.[] | {id, label}'
```

**Response shape:**

```json
[
  { "id": "all", "label": "All skills", "icon": null, "color": null },
  { "id": "coding", "label": "Coding", "icon": "code", "color": { "bg": "#e3dfd1", "fg": "#4a4128" } }
]
```

After listing categories, offer to run `/{{ $appSlug }} list` filtered by the chosen category.

---

## Notes

- **Slug format:** Skills may have a category prefix (e.g. `coding/code-reviewer`). The install directory uses only the final segment (`code-reviewer`).
- **Errors:** If any request returns a non-2xx response, report the status code to the user and verify `ENKI_TOKEN` is set.
- **Scope:** `~/.claude/skills/` is global; `.claude/skills/` is project-local and takes precedence.
- **Private skills:** The API only returns skills you have access to — public skills and private skills you created.
