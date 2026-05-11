# AGENTS.md
## Developer/Agent Guidelines

### System Architecture
- **Core**: `Pathway_Bridge_Suite` class.
- **Modules**: Located in `includes/modules/`.
- **Logic**: Jobs are handled by the `Workflow_Engine`.

### How to add a new Bridge
1. Create a new module in `includes/modules/`.
2. Register it in `Pathway_Bridge_Suite::load_modules()`.
3. Implement the `Module` interface or extend the base module class.

### Code Standards
- Use PHP 8.0+ features.
- Follow WordPress Coding Standards.
- Maintain strict typing where possible.
