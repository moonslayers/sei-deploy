# Skill Audit Report

- Date: 2026-11-19
- Scope: project only
- Threshold: 500 words
- Stale: 90 days

## Inventory

| Skill | Initial | Final | Refs |
|---|---:|---:|---:|
| despliegue-backends | 784 words / 1043 tokens | router 133 words / 177 tokens | 0 -> 6 |

## Criteria C1-C9

| ID | Criterion | Evidence | Result |
|---|---|---|---|
| C1 | nombre | `.opencode/skills/despliegue-backends/SKILL.md:2` | pass |
| C2 | descripción | `.opencode/skills/despliegue-backends/SKILL.md:3` | pass |
| C3 | AGENTS.md | `.opencode/skills/despliegue-backends/SKILL.md:5,10,26` | pass |
| C4 | threshold/router | `.opencode/skills/despliegue-backends/SKILL.md:1-26` | pass |
| C5 | inventario | `.opencode/skills/despliegue-backends/references/*` | pass |
| C6 | overlap | `.opencode/skills/despliegue-backends/SKILL.md` | pass |
| C7 | broken ref | `.opencode/skills/despliegue-backends/SKILL.md:26` | pass |
| C8 | stale | `.opencode/skills/despliegue-backends/references/*` | pass |
| C9 | reference architecture | `.opencode/skills/despliegue-backends/references/architecture.md` | pass |

## Residuals corrected (R1-R4)

- R1: SKILL.md L26 link `../../../../AGENTS.md` -> `../../../AGENTS.md`.
- R2: AGENTS.md appended `## Deployment Constraints` with 4 anchors.
- R3: shell-escaping.md single pointer + artifact lines removed.
- R4: criteria table aligned to C1-C9 original.

Final verdict: pass

## Decision Table

| Row | Decision | Action |
|---:|---|---|
| 1 | Apply | router split |
| 2 | Apply + re-confirm Manual | keep manual confirmation |
| 3 | Apply | references created |
| 4 | Apply | validation run |

## Validation Log

- YAML frontmatter fixed
- Shell escaping restored
- Router links converted to Markdown
- Reference map completed
- Lint passed

## Final Counts

- find refs: 6
- mindepth 2: empty
- links: 7/7

Next audit: 2027-02-17
