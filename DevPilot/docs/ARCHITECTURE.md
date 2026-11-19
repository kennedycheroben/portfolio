# DevPilot Architecture

## Overview

DevPilot is a private AI Development Operations Assistant designed to manage, inspect, test, and operate multiple software projects through a unified, extensible interface.

## Core Principles

1. **Read-Only Discovery**: Discovery never modifies project files or executes code
2. **Zero-Cost Operations**: All core functionality works offline with free tools
3. **Security First**: Path validation, secret redaction, environment separation
4. **Extensibility**: Adapter pattern allows new project types without core changes

## Architecture Components

### 1. Project Registry

The Registry is the single source of truth for all registered projects.

- **Storage**: JSON file (`registry/projects.json`) - no external database required
- **Location**: `./registry/projects.json`
- **Schema**: Array of Project objects with metadata

### 2. Discovery Engine

Read-only project inspection using static file analysis.

- **Method**: Scans for known marker files (composer.json, package.json, etc.)
- **Output**: Technology stack, framework, database, Docker, Git status
- **No Execution**: Never runs build scripts, tests, or migrations

### 3. Adapter Framework

Universal adapter contract that all project-type adapters implement.

```
AdapterContract (interface)
└── AbstractAdapter (base class)
    └── Type-specific adapters (PHP, Laravel, Node.js, Python, etc.)
```

### 4. CLI Interface

Command-line interface for all operations.

- **Location**: `cli/devpilot`
- **Commands**: register, list, discover, check, show, unregister, find

## Data Flow

```
User Command (CLI)
    ↓
Config (paths, settings)
    ↓
Registry (load/save projects)
    ↓
(Adapter) ← Discovery (optional, for new projects)
    ↓
Result → Output + Log
```

## Security Boundaries

### Path Validation
All paths are validated:
- Must be an existing directory
- Cannot be empty
- Path traversal patterns (`../`) are blocked

### Secret Redaction
Logs automatically redact:
- Passwords
- API keys
- Tokens
- Secrets

### Environment Separation
- Each project has an `environment` field
- Default: `development`
- Options: `staging`, `production`

### Session Security
- CSRF protection in portfolio integration
- Rate limiting on authentication
- Secure session cookies

## File Structure

```
DevPilot/
├── adapters/              # Project type adapters
│   ├── Contract.php      # Interface definition
│   └── php/
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
│   ├── Logger.php      # Operation logging
│   ├── Project.php     # Project model
│   └── Registry.php    # Project persistence
├── tests/               # Unit tests
│   ├── ConfigTest.php
│   ├── DiscoveryTest.php
│   ├── RegistryTest.php
│   └── TestCase.php
├── .claude/             # Claude settings (if any)
├── CLAUDE.md            # This architecture doc
├── cli/
├── composer.json
└── phpunit.xml
```

## Adding a New Adapter

1. Create adapter directory: `adapters/<type>/<Type>Adapter.php`
2. Implement `AdapterContract` interface
3. Extend `AbstractAdapter` for common functionality
4. Add discovery markers to `Discovery.php`

## Extension Points

### Phase 1B Planned Extensions

1. **Demo Data Generation**: Adapter methods for synthetic data
2. **Test Execution**: Safe test runner integration
3. **Deployment Automation**: CLI-driven deploy helpers
4. **API Key Management**: Secure credential storage
5. **Project Templates**: Generate new project structures

### Future Integration Points

1. **Portfolio Dashboard**: Web UI for DevPilot functions
2. **LiteLLM Integration**: AI-assisted operations
3. **Multi-environment Sync**: Cross-environment project management
4. **Notification System**: Build status alerts

## Performance Considerations

- Registry loads into memory for fast operations
- Discovery uses shallow directory traversal (default 5 levels)
- All operations are blocking and synchronous (simplicity over performance)
- No background processes during discovery