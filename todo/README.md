# Task Management System

## Directory Structure

```
todo/
├── README.md              ← You are here (how this system works)
├── prds/                  ← Product Requirements Documents
│   └── {name}.md          ← Detailed specs for large features
├── sprints/               ← Sprint/phase execution plans
│   └── {name}.md          ← Actionable task lists for agents
├── backlog.md             ← All pending work, prioritized
├── archive.md             ← Completed work log (moved from backlog)
└── notes/                 ← Scratch notes, debug logs, code snippets
    └── {topic}.md
```

## How It Works

### For You (Developer)
- **backlog.md** is your single source of truth for "what needs to be done"
- Scan the top — highest priority items are first
- When starting work, create a sprint file or tell the agent which backlog items to tackle
- Completed items move to archive.md monthly (keep backlog clean)

### For Agents
- Read **backlog.md** to understand priorities
- Read the relevant **PRD** for full context on a feature
- Read the relevant **sprint** file for the specific task list
- Mark tasks done in the sprint file as you complete them
- Always read **CLAUDE.md** for coding standards

## Conventions

### Backlog Format
```markdown
## Module Name

### P0 — Must Do Now
- [ ] Task description `#tag`

### P1 — Should Do Soon
- [ ] Task description `#tag`

### P2 — Nice to Have
- [ ] Task description `#tag`
```

### Sprint File Format
```markdown
# Sprint: {name}
PRD: [link to PRD if applicable]
Branch: `feature/{name}`
Status: 🟡 In Progress | 🟢 Done | 🔴 Blocked

## Tasks
- [ ] Task 1
- [ ] Task 2
  - [ ] Sub-task
```

### Tags
- `#migration` — database schema change
- `#action` — business logic action class
- `#ui` — Filament resource/page change
- `#test` — test coverage
- `#cleanup` — tech debt / refactor
- `#bug` — bug fix

### Priority Levels
- **P0** — Blocking other work or critical path. Do first.
- **P1** — Important, do this sprint/phase.
- **P2** — Nice to have, do when P0/P1 are clear.
- **P3** — Someday/maybe. Park it.
