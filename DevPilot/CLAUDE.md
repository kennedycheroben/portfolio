# DevPilot — Local Development Operations Core

## Phase 1A: Complete

This directory contains the local development operations assistant foundation. It is isolated from the main portfolio and can be run independently.

## Quick Start

```bash
cd /opt/lampp/htdocs/portfolio/DevPilot

# Run tests
php ./cli/run-tests

# Register a project
./cli/devpilot register /path/to/project "Project Name"

# List projects
./cli/devpilot list

# Discover project metadata
./cli/devpilot discover <id|path>

# Run health check
./cli/devpilot check <id|path>
```

## Architecture Summary

| Component | Tech | Location |
|-----------|------|----------|
| Registry | JSON file | `registry/projects.json` |
| Discovery | Static file analysis | `src/Discovery.php` |
| CLI | PHP native | `cli/devpilot` |
| Config | Environment-based | `src/Config.php` |
| Tests | Native PHP assertions | `cli/run-tests` |

## Key Files

- `src/Project.php` - Project model with validation
- `src/Registry.php` - Project persistence (JSON-based)
- `src/Discovery.php` - Technology stack detection
- `src/Logger.php` - Operation logging with secret redaction
- `adapters/Contract.php` - Adapter interface definition
- `adapters/php/VanillaPhpAdapter.php` - Example adapter

## Security

- Path traversal protection
- Secret redaction in logs
- No automatic code execution
- Explicit authorization for destructive operations

---

## Git Collaboration Workflow

After completing an authorized implementation task:

1. Run relevant tests and inspect `git diff --check`
2. Stage only task-related changes: `git add <affected-files>`
3. Commit with descriptive message
4. Push to the current feature branch: `git push`
5. Never commit to main, force-push, merge, or deploy without approval

### What to Exclude

```
# Runtime data
registry/projects.json

# Logs
*.log

# Dependencies
vendor/
node_modules/

# Environment files
.env
.env.local
*.env*

# IDE and editor
.idea/
.vscode/
*.swp
.claude/

# Temporary files
*.tmp
*.cache
```

### What to Include

- Source code (`src/`)
- Adapters (`adapters/`)
- Tests (`tests/`)
- Configuration (`composer.json`, `phpunit.xml`)
- Documentation (`docs/`, `CLI.md`)
- Public contracts (`Contract.php`)

---

## Next Phase (1B) Planning

Recommended Phase 1B scope:
1. Demo data generation adapters
2. Test execution integration
3. Portfolio dashboard bridge (read-only)
4. LiteLLM model usage for AI-assisted operations