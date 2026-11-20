# DevPilot Architecture

## Universal Development Operations Assistant

DevPilot is a technology-agnostic platform for managing software development projects across languages, frameworks, databases, infrastructure providers, and development environments. It provides discovery, inspection, and operation capabilities through a plugin architecture.

---

## Core Design Principles

1. **Technology Independence**: Core is language/framework-agnostic
2. **Read-Only Discovery**: Discovery never modifies project files or executes code
3. **Zero-Cost Operations**: All core functionality works offline with free tools
4. **Security First**: Path validation, secret redaction, environment separation
5. **Extensibility**: Adapter pattern allows new project types without core changes
6. **Explicit Authorization**: Mutating operations require explicit approval
7. **Deterministic Operations**: No AI calls for deterministic tasks
6. **Fail Safe**: Unknown technologies reported honestly, not assumed supported

---

## A. Universal Core Architecture

### Project Lifecycle & Registry
- **Registry**: Central JSON-based project database with atomic persistence
- **State Management**: Environment-aware (development/staging/production)
- **Metadata Storage**: Language, framework, database, hosting platform, status
- **Concurrency**: File-locking with lock file separation for safe mutations

### Discovery Engine (Read-Only)
- **Technology Detection**: Analyzes project files without executing code
- **Marker-Based**: Uses composer.json, package.json, requirements.txt, etc.
- **Framework Identification**: Laravel, Django, React, Vue, Spring, etc.
- **Infrastructure Detection**: Docker, Git, database configuration
- **Evidence-Based**: Reports what was found, not assumptions

### Security Layer
- **Path Validation**: Blocks directory traversal, validates directories exist
- **Secret Redaction**: Logs automatically redact passwords, API keys, tokens
- **No Code Execution**: Discovery never runs build/test scripts
- **Explicit Authorization**: Mutating operations require approval

### Operation Orchestration
- **Capability Registry**: Each operation declares requirements/permissions
- **Execution Context**: Tracks environment, approvals, audit trail
- **Result Handling**: Standardized success/failure/partial results

### Logging & Audit Trails
- **Structured Logging**: JSON lines with timestamps and levels
- **Secret Redaction**: Recursive redaction by sensitive key names
- **Audit Trail**: Every operation logged with context

---

## B. Technology Intelligence

Discovery identifies:
- **Primary Language**: PHP, TypeScript, JavaScript, Python, Java, Go, Rust, C#, Ruby, etc.
- **Frameworks/Libraries**: Laravel, Django, React, Vue, Spring, etc.
- **Database Technology**: MySQL, PostgreSQL, SQLite, Supabase, MongoDB
- **Build Tools**: Docker, CI configs (GitHub Actions, GitLab CI)
- **Package Managers**: npm, Composer, pip, Cargo, Maven, Go modules

---

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

### Support Levels
1. **Native Adapter**: Explicitly tested and maintained
2. **Generic Integration**: Standard interfaces work automatically
3. **Experimental**: Detected but not fully validated

---

## D. Capability-Based Operations

Each operation declares:
- **Requirements**: PHP version, extensions, tools
- **Permissions**: Read-only or mutating
- **Environment**: Local, staging, production
- **Provider**: Which AI model (when applicable)

### Core Capabilities
| Capability | Type | Description |
|------------|------|-------------|
| `inspect` | Read-only | Read project structure and metadata |
| `test` | Mutating | Run project test suite |
| `serve` | Mutating | Start development server |
| `diagnose` | Read-only | Analyze errors and issues |
| `demo-data` | Mutating | Generate synthetic test data |
| `deploy-plan` | Read-only | Create deployment strategy |
| `health-monitor` | Read-only | Track project status |

---

## E. Environment and Infrastructure Awareness

Projects classified by:
- **Local**: Development machine
- **Server**: VPS, dedicated, shared hosting
- **Container**: Docker, Podman
- **Cloud**: AWS, GCP, Azure, DigitalOcean
- **CI/CD**: GitHub Actions, GitLab CI, etc.
- **Edge**: Static hosting, CDN, serverless

---

## F. AI Provider Independence

- **Core DevPilot makes ZERO AI calls** for deterministic operations
- **AI integration** is a separate abstraction layer (Phase 4+)
- **LiteLLM** remains external to the core
- **Providers configured per-operation**, never implicit
- **Fail safely** when providers unavailable
- **No silent fallback** to paid providers

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

## File Structure

```
DevPilot/
├── adapters/              # Project type adapters
│   ├── AdapterContract.php    # Interface definition
│   ├── AbstractAdapter.php    # Base implementation
│   └── Php/
│       └── VanillaPhpAdapter.php
├── cli/                 # Command-line interface
│   ├── devpilot
│   └── run-tests
├── docs/
│   └── ARCHITECTURE.md
├── registry/
│   └── projects.json    # Project data (created on first run)
├── src/
│   ├── Config.php      # Configuration management
│   ├── Discovery.php   # Project discovery
│   ├── Logger.php      # Operation logging with redaction
│   ├── Project.php     # Project model
│   └── Registry.php    # Project persistence with locking
├── tests/               # Unit tests
│   ├── ConfigTest.php
│   ├── DiscoveryTest.php
│   ├── RegistryTest.php
│   ├── AdapterCompatibilityTest.php
│   └── TestCase.php
├── .claude/             # Claude settings (if any)
├── CLAUDE.md            # This architecture doc
├── composer.json
├── phpunit.xml
└── .gitignore
```

---

## Adding a New Adapter

1. Create adapter directory: `adapters/<Type>/<Type>Adapter.php`
2. Implement `AdapterContract` interface
3. Extend `AbstractAdapter` for common functionality
4. Add discovery markers to `Discovery.php`

---

## Future Development Roadmap

| Phase | Scope | Status |
|-------|-------|--------|
| Phase 1A | Local core foundation | ✅ Complete |
| Phase 1B | Universal project integration | ⏳ In Progress (Reliability fixes complete) |
| Phase 2 | Controlled operations & demo data | Pending |
| Phase 3 | Portfolio admin dashboard | Pending |
| Phase 4 | AI orchestration & multi-provider | Pending |
| Future | Automation & extensibility | Planning |

**Current Status**: Phase 1A complete. Phase 1B reliability corrections and TypeScript detection completed (50 tests passing). Remaining Phase 1B items pending.

---

## Performance Considerations

- Registry loads into memory for fast operations
- Discovery uses shallow directory traversal (default 3 levels)
- Excludes vendor/, node_modules/, .git, build outputs
- All operations are blocking and synchronous (simplicity over performance)
- No background processes during discovery