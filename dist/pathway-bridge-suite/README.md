# Pathway Bridge Suite

Professional WordPress connectivity hub for Forms, Posts, and Routes with Enterprise Workflow Engine.

## Features
- **Forms Bridge**: Capture and route form submissions. Native **Elementor Pro** auto-capture and file upload support.
- **Posts Bridge**: Sync Custom Post Types with external APIs. Supports **VIP Workflow** status transitions.
- **Routes Bridge**: Create custom WP REST API endpoints to **accept incoming data** from any source (Hubspot, Twilio, Power Automate).
- **Workflow Engine**: DTO/ETL pipeline with **multi-recipient Webhooks**, **ODATA** support, and native **WP Mail** integration.
- **Performance**: Integrated Rate Limiting and Asynchronous Queueing via Cron.
- **Logging**: Unified activity log for all entry points and job results.

## How to Build/Compile for Production

To generate a production-ready ZIP file of the plugin, follow these steps:

1.  **Environment**: Ensure you have `composer`, `npm`, and `zip` installed on your system.
2.  **Dependencies**: The build script will automatically handle PHP and JS dependencies.
3.  **Run Build**:
    ```bash
    npm run prod
    ```
    *This runs `bin/build.sh` which performs cleanup, installs dependencies, builds React assets, and packages everything.*
4.  **Result**: Your production-ready plugin will be located at `dist/pathway-bridge-suite.zip`.

## Configuration Examples

### Elementor Form with File Upload
Configure a Mail Job in your workflow:
```json
{
  "to": "admin@example.com",
  "subject": "New Submission from {form_name}",
  "attachments": ["upload_field_id"]
}
```

### Microsoft Dynamics (ODATA)
Use the `HTTP_Job` with the following ODATA config in your workflow:
```json
{
  "odata": {
    "filter": "emailaddress1 eq '{email}'",
    "select": "firstname,lastname"
  }
}
```

## Technical Requirements
- PHP 8.0+
- WordPress 6.9+ (Fully compatible)
- Elementor Pro 4+ (Optional, for Forms integration)
