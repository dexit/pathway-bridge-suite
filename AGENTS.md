# AGENTS.md
## Developer & AI Guidelines

### Architecture: The Bridge Paradigm
The suite follows a unified "Bridge" paradigm where data flows from **Entry Points** to **Workflow Jobs**.

- **Entry Points**:
  - **REST Receiver Routes**: Dynamically registered via `includes/modules/routes/class-rest-server.php`.
  - **Form Submissions**: Captured via `includes/modules/forms/class-forms-module.php`.
  - **Post Transitions**: Triggered via `includes/modules/posts/class-posts-module.php`.
- **DTO/ETL Mapping**: All entry points support a `_pbs_mapping` meta field processed by the `Transformer`.
- **Workflow Engine**: Orchestrates execution of `Job` instances.

### Security & Compliance
- **Rate Limiting**: Use `Rate_Limiter::check()` at all entry points.
- **Authentication**: REST routes support `api_key`, `hubspot`, and `twilio` signature verification.
- **Data Safety**: `eval` is used for custom snippets; ensure post content is sanitized and only editable by admins.

### Extending
- **New Modules**: Register in `Pathway_Bridge_Suite::load_modules()`.
- **Custom Actions**: Implement as a static method in a class and register as a Job.
