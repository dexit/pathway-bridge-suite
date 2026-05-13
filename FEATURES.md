# FEATURES.md
## Defined Solution Features

### 1. Unified Modular Architecture
- Central Singleton core.
- Dynamic module loading (Forms, Posts, Routes).
- Shared dependency management.

### 2. Routes Bridge
- Dynamic registration of WP REST API endpoints.
- Custom PHP snippet execution for data processing.
- Mapping UI for DTO/ETL transformations.

### 3. Forms Bridge (Enhanced)
- Native Elementor Form auto-capture.
- Support for multiple form providers.
- Workflow job chaining.

### 4. Posts Bridge
- CPT-to-External sync logic.

### 5. Workflow Engine
- DTO/ETL pipeline with dot-notation nested mapping support.
- Multi-recipient webhook dispatching.
- Persistent Job Monitoring with per-execution logging.
- Manual Retry capability for failed workflow jobs.

### 6. Advanced Connectivity
- HTTP Job with dynamic header and payload mapping.
- Support for complex JSON object transformations.
- Integrated Rate Limiting per entry point.
