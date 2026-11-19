# DevPilot — Universal AI Development Operations Assistant

## Architecture Overview

DevPilot is a technology-agnostic framework for managing software development projects across languages, frameworks, databases, and hosting providers. It provides discovery, inspection, and operation capabilities through a plugin architecture.

---

## Quick Start

```bash
cd /opt/lampp/htdocs/portfolio/DevPilot

# Run tests
php ./cli/run-tests

# Register a project
./cli/devpilot register /path/to/project "Project Name"

# Discover technology stack
./cli/devpilot discover <id|path>

# Run health check
./cli/devpilot check <id|path>
```

## A. Universal Core Architecture

### Project Lifecycle
- **Registry**: Central JSON-based project database with atomic persistence
- **State Management**: Environment-aware (development/staging/production)
- **Metadata Storage**: Language, framework, database, hosting platform, status

### Discovery Engine (Read-Only)
- **Technology Detection**: Analyzes project files without executing code
- **Marker-Based**: Uses composer.json, package.json, requirements.txt, etc.
- **Framework Identification**: Laravel, Django, React, Vue, etc.
- **Infrastructure Detection**: Docker, Git, database configuration

### Security Layer
- **Path Validation**: Blocks directory traversal, validates directories exist
- **Secret Redaction**: Logs automatically redact passwords, API keys, tokens
- **No Code Execution**: Discovery never runs build/test scripts
- **Explicit Authorization**: Mutating operations require approval

## B. Technology Intelligence

Discovery identifies:
- Primary language via project markers
- Frameworks/libraries via config files
- Database technology via .env patterns
- Build tools (Docker, CI configs)
- Package managers (npm, Composer, pip)

## C. Adapter and Plugin System

### Standardized Contract
All adapters implement `AdapterContract`:
- `discover()`: Read-only project analysis
- `checkHealth()`: Health/diagnostics checks
- `getAvailableCommands()`: Mutating operations available
- `generateDemoData()`: Synthetic data generation (requires authorization)
- `supports()`: Capability validation

### Adapter Discovery
- Auto-loaded from `adapters/` directory
- Namespace-based organization (php/, python/, node/, etc.)
- Support levels: Native (tested), Generic (standard interfaces), Experimental (discovered)

## D. Capability-Based Operations

Each operation declares its:
- **Requirements**: PHP version, extensions, tools
- **Permissions**: Read-only or mutating
- **Environment**: Local, staging, production
- **Provider**: Which AI model (when applicable)

Capabilities include:
- `inspect`: Read project structure and metadata
- `test`: Run project test suite
- `serve`: Start development server
- `diagnose`: Analyze errors and issues
- `demo-data`: Generate synthetic test data
- `deploy-plan`: Create deployment strategy
- `health-monitor`: Track project status

## E. Environment and Infrastructure Awareness

Projects are classified by:
- **Local**: Development machine
- **Server**: VPS, dedicated, shared hosting
- **Container**: Docker, Podman
- **Cloud**: AWS, GCP, Azure, DigitalOcean
- **CI/CD**: GitHub Actions, GitLab CI, etc.
- **Edge**: Static hosting, CDN, serverless

## F. AI Provider Independence

- Core DevPilot makes **zero AI calls** for deterministic operations
- AI integration (Phase 4) is a separate abstraction layer
- LiteLLM remains external to the core
- Providers configured per-operation, never implicit

---

## Universal Compatibility Strategy

### Support Levels

1. **Native Adapter**: Explicitly tested and maintained
2. **Generic Integration**: Standard interfaces work automatically
3. **Experimental**: Detected but not fully validated

Unknown technologies are reported honestly. Discovery ≠ Support guarantee.

### Extensibility Rules

- Add adapters without modifying core
- Drop-in replacement for any component
- Backward-compatible interface versions
- Automatic capability reporting

---

## Security and Authorization

### Explicit Authorization
- All mutating operations require approval
- Audit trail logs every operation
- Destructive actions gated

### Production Safety
- Production classification enforced
- No accidental deployment
- Data separation guaranteed

### Zero-Cost Constraint
- No paid services in core
- Free-tier APIs only
- Local execution preferred

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
- Documentation (`docs/`)
- Public contracts (`Contract.php`)

---

## Development Roadmap

| Phase | Scope | Status |
|-------|-------|--------|
| Phase 1A | Local core foundation | ✅ Complete |
| Phase 1B | Universal project integration | Ready for authorization |
| Phase 2 | Controlled operations & demo data | Pending |
| Phase 3 | Portfolio admin dashboard | Pending |
| Phase 4 | AI orchestration & multi-provider | Pending |
| Future | Automation & extensibility | Planning |

---

## Key Files

- `src/Project.php` - Project model with validation
- `src/Registry.php` - JSON persistence with file locking
- `src/Discovery.php` - Technology stack detection
- `src/Logger.php` - Operation logging with redaction
- `adapters/Contract.php` - Adapter interface definition
- `adapters/php/VanillaPhpAdapter.php` - Example adapter

## Current Projects as Validation Examples

These existing projects validate the architecture:
- **Portfolio Project**: PHP/MySQL/Laravel stack
- Additional projects will be added as new adapters

---

## Next Phase (1B) Planning

Recommended Phase 1B scope:
1. Demo data generation adapters
2. Test execution integration
3. Portfolio dashboard bridge (read-only)
4. LiteLLM model usage for AI-assisted operations