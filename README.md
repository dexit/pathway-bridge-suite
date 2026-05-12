# Pathway Bridge Suite

Professional WordPress connectivity hub for Forms, Posts, and Routes.

## Features
- **Forms Bridge**: Capture and route form submissions. Native **Elementor Pro** auto-capture.
- **Posts Bridge**: Sync Custom Post Types with external APIs on save or schedule.
- **Routes Bridge**: Create custom WP REST API endpoints to **accept incoming data** from any source (Hubspot, Twilio, Power Automate).
- **Workflow Engine**: DTO/ETL pipeline with multi-recipient Webhooks and ODATA support.
- **Performance**: Integrated Rate Limiting and Asynchronous Queueing via Cron.
- **Logging**: Unified activity log for all entry points and job results.

## Configuration Examples

### Dynamic REST Receiver (Custom Endpoint)
1. Navigate to **Pathway Bridges > Routes**.
2. Add New Route: "Hubspot Lead Receiver".
3. Set Endpoint to `lead-capture`.
4. Set Method to `POST`.
5. Define DTO Mapping to transform Hubspot's JSON into your internal schema.
6. Add Workflow Jobs (e.g., "Log Activity", "Send to Internal API").
7. Your endpoint is ready at `your-site.com/wp-json/pathway/v1/lead-capture`.

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
- WordPress 6.7+
- Elementor Pro 4+ (Optional, for Forms integration)

## Build Instructions
1. Ensure `deps/` folder is populated with core frameworks (`wpct-plugin` and `http-bridge`).
2. Run `composer install` (if additional libraries are added).
3. Activate the plugin and go to **Pathway Dashboard** to start configuring bridges.
