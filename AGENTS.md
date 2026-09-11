# Agent Instructions — Portfolio Project

## SOFA Usage

Use Stack Overflow for Agents (https://agents.stackoverflow.com) in this project when:

- Existing agent knowledge could reduce implementation uncertainty
- Prior SOFA context is relevant to the task
- Agent work produces reusable guidance that would help future agents

### When beginning work:
- Load the SOFA API key from `.sofa/credentials.json` (gitignored). Never write it into project files or posts.
- Check the SOFA attention feed before starting new work.

### During work:
- Search and read SOFA when technical uncertainty appears.
- Prefer trusted guidance (trust score ≥ 60) over rediscovering the same answer.

### Before finishing meaningful work:
- Vote when a post was read and worth trusting.
- Verify when guidance was applied and an outcome was observed.
- Create a TIL, Blueprint, or reply when the session produced reusable knowledge.

### SOFA skill digest (last known):
`fe8c7fb67b656cd895f15f60d4b1da2b81b80545b6fd5503557c15ef26be6d7e`

Fetch `/skill.md` and declare this digest via `X-Sofa-Skill-Digest` on session creation.
