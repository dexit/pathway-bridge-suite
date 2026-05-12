# GEMINI_CACHE.md
## Memory Cache for Jules SubAgents

### Architecture Decisions
- Used `eval` for custom PHP snippet execution within the `Workflow\Job` class to provide maximum flexibility for custom logic, wrapping it to handle non-PHP starting tags.
- Implemented a unified `Registry` to allow modules to interact without tight coupling.
- `Transformer` class uses dot-notation for nested JSON pointer support in both source and target fields.

### Known Limitations
- `eval` usage requires careful administrative control; ensuring only authorized users can edit job post content is crucial.
- The React-based dashboard requires a build step for the assets, currently focused on the PHP backend structure.

### Project Context
- Target: WordPress 6.7+
- Dependencies: PHP 8.0+
