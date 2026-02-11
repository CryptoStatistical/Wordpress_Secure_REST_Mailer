# Wordpress Secure REST Mailer

![Version](https://img.shields.io/badge/version-2.1.0-blue.svg)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-8892BF.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.6%2B-21759B.svg)

A secure WordPress plugin that exposes a REST API endpoint for sending HTML emails with dual-layer authentication, configurable rate limiting, an email log, and full admin settings UI.

---

## Table of Contents

- [Description](#description)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Authentication Setup](#authentication-setup)
- [API Reference](#api-reference)
- [Usage Examples](#usage-examples)
- [Security](#security)
- [Hooks and Filters](#hooks-and-filters)
- [Troubleshooting](#troubleshooting)
- [WP-CLI](#wp-cli)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [License](#license)

---

## Description

**My REST Mailer** turns any WordPress installation into a reliable email-sending gateway by registering a single REST API endpoint:

```
POST /wp-json/custom/v1/send-email
```

External systems authenticate with WordPress Application Passwords (Basic Auth) and an optional custom API Key, then submit a JSON payload containing the recipient, subject, and HTML body. WordPress handles the actual delivery through `wp_mail()`, so it works seamlessly with any SMTP plugin you already have configured.

### Why use it?

WordPress is already running on most web hosts, often with a fully configured SMTP pipeline. Instead of provisioning a separate mail relay, you can leverage that existing infrastructure from anywhere -- a NAS, a Raspberry Pi, a CI runner, or a monitoring daemon -- all through a simple authenticated HTTP call.

### Use cases

- **NAS automation** -- Send disk health reports, backup completion notices, or storage alerts from Synology, QNAP, or TrueNAS scripts.
- **Server monitoring** -- Forward uptime checks, resource threshold warnings, or cron job summaries to your inbox.
- **CI/CD notifications** -- Post build results, deployment confirmations, or test failure alerts from GitHub Actions, Jenkins, or GitLab CI pipelines.
- **External application integration** -- Any application or script that can make an HTTP POST request can send richly formatted HTML email through your WordPress site without needing its own mail configuration.
- **IoT and home automation** -- Trigger email alerts from Home Assistant, Node-RED, or custom microcontroller firmware.

---

## Features

### Core

- Single REST API endpoint (`POST /wp-json/custom/v1/send-email`) for sending HTML emails.
- Full HTML email support via `wp_mail()` with `Content-Type: text/html; charset=UTF-8`.
- Configurable **From** address, **From** display name, and **Reply-To** header -- settable as defaults in the admin and overridable per request.
- Multiple recipients via comma-separated email addresses in the `to` field.
- Clean JSON responses with appropriate HTTP status codes for every outcome.

### Authentication (dual-layer)

- **Layer 1** -- WordPress native authentication through Application Passwords (Basic Auth). Requires the `edit_posts` capability.
- **Layer 2** -- Custom API Key verified via the `X-API-Key` header using timing-safe `hash_equals()`. Can be enabled or disabled from the settings page.

### Admin Settings Page (v2.0.0+)

- Dedicated settings page under **Settings > REST Mailer** with a tabbed interface.
- One-click API Key generator using `crypto.getRandomValues()` (48-character key).
- Toggle to enable or disable API Key requirement.
- Configurable default email fields: From Email, From Name, Reply-To.
- Quick reference panel showing the endpoint URL and a ready-to-use cURL example.
- "Settings" quick-access link on the Plugins list page.

### New in v2.1.0

- **Rate limiting** -- Configurable maximum number of emails per minute (default: 10), backed by a WordPress transient with a 60-second TTL. Returns HTTP 429 when exceeded.
- **Email log** -- Stores the last 50 emails (date, recipients, subject, success/failure) in a dedicated "Email Log" tab. Includes a one-click "Clear Log" button.
- **Multiple recipients** -- The `to` field now accepts a comma-separated list of email addresses, each individually validated and deduplicated.
- **Developer filters** -- Two new filters (`mrm_email_headers` and `mrm_before_send_email`) allow other plugins or custom code to modify or block outgoing emails.
- **Clean uninstall** -- All options, log data, and transients are removed when the plugin is deleted through the WordPress admin.

---

## Requirements

| Requirement | Minimum Version |
|---|---|
| WordPress | 5.6 or later |
| PHP | 7.4 or later |
| Authentication | WordPress Application Passwords (built into WP 5.6+) |

### Additional notes

- **HTTPS is strongly recommended.** Application Passwords transmit credentials via Basic Auth (Base64-encoded). Without TLS, credentials are sent in cleartext. See the [Authentication Setup](#authentication-setup) section for notes on HTTP/LAN environments.
- **An SMTP plugin is recommended** for reliable delivery. WordPress uses PHP `mail()` by default, which many hosts block or limit. Popular options include [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/), [FluentSMTP](https://wordpress.org/plugins/fluent-smtp/), or [Post SMTP](https://wordpress.org/plugins/post-smtp-mailer-email-log/).

---

## Installation

### Method 1: Upload via WordPress Admin

1. Download the latest release ZIP from the [GitHub releases page](https://github.com/CryptoStatistical/Wordpress_SECURE_REST_MAIL_SENDER/releases).
2. In your WordPress admin, navigate to **Plugins > Add New > Upload Plugin**.
3. Select the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

### Method 2: FTP / SFTP

1. Download or clone the repository:
   ```bash
   git clone https://github.com/CryptoStatistical/Wordpress_SECURE_REST_MAIL_SENDER.git
   ```
2. Upload the `my-rest-mailer.php` file to your server at:
   ```
   /wp-content/plugins/my-rest-mailer/my-rest-mailer.php
   ```
3. Log in to the WordPress admin and navigate to **Plugins**.
4. Locate **My REST Mailer** in the list and click **Activate**.

### Method 3: WP-CLI

```bash
# Clone into the plugins directory
cd /path/to/wordpress/wp-content/plugins
git clone https://github.com/CryptoStatistical/Wordpress_SECURE_REST_MAIL_SENDER.git my-rest-mailer

# Activate the plugin
wp plugin activate my-rest-mailer
```

---

## Configuration

After activation, navigate to **Settings > REST Mailer** in the WordPress admin sidebar.

The settings page is organized into three sections:

### Authentication

| Field | Description |
|---|---|
| **API Key** | A secret string that callers must include in the `X-API-Key` header. Click **Generate Key** to create a cryptographically random 48-character key. |
| **Require API Key** | When checked, every request must include a valid `X-API-Key` header in addition to Basic Auth. Enabled by default. |

### Email Defaults

| Field | Description |
|---|---|
| **From Email** | Default sender address. Can be overridden per-request with the `from` JSON field. |
| **From Name** | Default sender display name. Can be overridden per-request with the `sender_name` JSON field. |
| **Reply-To Email** | Default Reply-To address. Can be overridden per-request with the `reply_to` JSON field. |

### Rate Limiting

| Field | Description |
|---|---|
| **Max Emails per Minute** | Maximum number of emails that can be sent within a rolling 60-second window. Default: 10. Minimum: 1. |

### Screenshot

![Settings Page](assets/settings-screenshot.png)

### Email Log Tab

Switch to the **Email Log** tab to view the last 50 emails sent through the plugin. Each entry shows the date, recipient(s), subject, and whether the send succeeded or failed. Use the **Clear Log** button to purge all entries.

---

## Authentication Setup

My REST Mailer uses two independent authentication layers. Both must pass before the endpoint processes a request.

### Layer 1: WordPress Application Passwords (required)

Application Passwords are built into WordPress 5.6 and later. They let you create per-app credentials without exposing your main admin password.

1. Log in to your WordPress admin.
2. Navigate to **Users > Profile** (or the profile of the user you want to authenticate as).
3. Scroll down to the **Application Passwords** section.
4. Enter a name for the new password (e.g., `NAS Mailer` or `CI Pipeline`).
5. Click **Add New Application Password**.
6. **Copy the generated password immediately.** It is displayed only once. The password will look like groups of four characters separated by spaces (e.g., `abcd efgh ijkl mnop qrst uvwx`).
7. When making API calls, use the password **without** the spaces.

The credentials are sent via HTTP Basic Auth:

```
Authorization: Basic base64(username:application_password)
```

With cURL, the `-u` flag handles encoding automatically:

```bash
curl -u "username:abcdefghijklmnopqrstuvwx" ...
```

**Important:** The WordPress user must have the `edit_posts` capability (Editor or Administrator role by default).

### Layer 2: Custom API Key (optional but enabled by default)

1. Navigate to **Settings > REST Mailer**.
2. Click **Generate Key** or enter your own secret string (32+ characters recommended).
3. Save the settings.
4. Include the key in every request via the `X-API-Key` header:
   ```
   X-API-Key: your_generated_key_here
   ```

### Note about HTTP and LAN environments

Application Passwords require HTTPS by default. WordPress filters block Application Password authentication over plain HTTP to protect credentials in transit.

If you are running WordPress on a **local network (LAN)** or in a development environment without TLS, you can allow Application Passwords over HTTP by adding the following to your theme's `functions.php` or a custom plugin:

```php
add_filter( 'wp_is_application_passwords_available_for_user', '__return_true' );
```

Additionally, if the REST API is not accessible, you may need to add:

```php
add_filter( 'application_password_is_api_request', '__return_true' );
```

**Warning:** Only use these filters on trusted private networks. Never enable plain-HTTP Application Passwords on a public-facing site.

---

## API Reference

### Endpoint

```
POST /wp-json/custom/v1/send-email
```

### Request Headers

| Header | Required | Description |
|---|---|---|
| `Authorization` | Yes | Basic Auth credentials (`Basic base64(user:app_password)`). |
| `Content-Type` | Yes | Must be `application/json`. |
| `X-API-Key` | Conditional | Required when "Require API Key" is enabled in settings. |

### Request Body (JSON)

| Field | Type | Required | Description |
|---|---|---|---|
| `to` | `string` | Yes | Recipient email address. Supports a single address or a comma-separated list (e.g., `"a@example.com, b@example.com"`). Each address is individually validated. |
| `subject` | `string` | Yes | Email subject line. Must be a non-empty string. |
| `message` | `string` | Yes | Email body. Supports safe HTML (sanitized with `wp_kses_post`). Must be non-empty. |
| `from` | `string` | No | Sender email address. Overrides the admin-configured default. Falls back to WordPress default if not set. |
| `sender_name` | `string` | No | Sender display name (e.g., `"My NAS"`). Overrides the admin-configured default. |
| `reply_to` | `string` | No | Reply-To email address. Overrides the admin-configured default. |

### Response Schema

All responses return a JSON object with `status` and `message` fields.

#### 200 OK -- Email sent successfully

```json
{
  "status": "success",
  "message": "Email sent successfully to recipient@example.com."
}
```

#### 400 Bad Request -- Invalid input

```json
{
  "status": "error",
  "message": "Invalid recipient email address."
}
```

```json
{
  "status": "error",
  "message": "Subject and message body must not be empty."
}
```

#### 401 Unauthorized -- Missing API Key

```json
{
  "code": "rest_missing_api_key",
  "message": "Missing X-API-Key header.",
  "data": {
    "status": 401
  }
}
```

#### 403 Forbidden -- Authentication failed

WordPress credentials invalid or insufficient capability:

```json
{
  "code": "rest_forbidden",
  "message": "Authentication failed. Valid WordPress credentials with edit_posts capability are required.",
  "data": {
    "status": 403
  }
}
```

Invalid API Key:

```json
{
  "code": "rest_invalid_api_key",
  "message": "Invalid API Key.",
  "data": {
    "status": 403
  }
}
```

#### 429 Too Many Requests -- Rate limit exceeded

```json
{
  "code": "rest_rate_limit_exceeded",
  "message": "Rate limit exceeded. Maximum 10 emails per minute allowed.",
  "data": {
    "status": 429
  }
}
```

#### 500 Internal Server Error

API Key required but not configured:

```json
{
  "code": "rest_api_key_not_configured",
  "message": "API Key authentication is enabled but no key has been configured. Please set one in Settings > REST Mailer.",
  "data": {
    "status": 500
  }
}
```

`wp_mail()` failure:

```json
{
  "status": "error",
  "message": "Failed to send email. Check your WordPress mail configuration (SMTP plugin, server settings, etc.)."
}
```

### Error Codes Reference

| Error Code | HTTP Status | Description |
|---|---|---|
| `rest_forbidden` | 403 | WordPress authentication failed or user lacks `edit_posts` capability. |
| `rest_missing_api_key` | 401 | The `X-API-Key` header was not provided and API Key enforcement is enabled. |
| `rest_invalid_api_key` | 403 | The provided API Key does not match the configured key. |
| `rest_api_key_not_configured` | 500 | API Key enforcement is enabled but no key has been set in the admin settings. |
| `rest_rate_limit_exceeded` | 429 | The per-minute email rate limit has been exceeded. Wait and retry. |

---

## Usage Examples

### cURL

**Basic request:**

```bash
curl -X POST "https://example.com/wp-json/custom/v1/send-email" \
  -u "admin:abcdefghijklmnopqrstuvwx" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{
    "to": "recipient@example.com",
    "subject": "Test Email",
    "message": "<p>Hello from My REST Mailer!</p>"
  }'
```

**Full request with all optional fields:**

```bash
curl -X POST "https://example.com/wp-json/custom/v1/send-email" \
  -u "admin:abcdefghijklmnopqrstuvwx" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{
    "to": "recipient@example.com",
    "subject": "NAS Backup Report",
    "message": "<h2>Backup Complete</h2><p>All volumes synced successfully at 03:00 UTC.</p>",
    "from": "nas@example.com",
    "sender_name": "Synology NAS",
    "reply_to": "admin@example.com"
  }'
```

**Debug mode (verbose output):**

```bash
curl -v -X POST "https://example.com/wp-json/custom/v1/send-email" \
  -u "admin:abcdefghijklmnopqrstuvwx" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{
    "to": "recipient@example.com",
    "subject": "Debug Test",
    "message": "<p>Checking headers and response codes.</p>"
  }'
```

**Multiple recipients:**

```bash
curl -X POST "https://example.com/wp-json/custom/v1/send-email" \
  -u "admin:abcdefghijklmnopqrstuvwx" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{
    "to": "alice@example.com, bob@example.com, carol@example.com",
    "subject": "Team Notification",
    "message": "<p>Deployment to production completed.</p>"
  }'
```

### Python (requests)

```python
import requests

response = requests.post(
    "https://example.com/wp-json/custom/v1/send-email",
    auth=("admin", "abcdefghijklmnopqrstuvwx"),
    headers={
        "Content-Type": "application/json",
        "X-API-Key": "YOUR_API_KEY",
    },
    json={
        "to": "recipient@example.com",
        "subject": "Alert from Python Script",
        "message": "<h3>Disk Usage Warning</h3><p>Volume 1 is at 92% capacity.</p>",
        "from": "monitor@example.com",
        "sender_name": "Server Monitor",
    },
)

print(response.status_code)
print(response.json())
```

### PHP (wp_remote_post)

```php
$response = wp_remote_post(
    'https://example.com/wp-json/custom/v1/send-email',
    array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode( 'admin:abcdefghijklmnopqrstuvwx' ),
            'Content-Type'  => 'application/json',
            'X-API-Key'     => 'YOUR_API_KEY',
        ),
        'body' => wp_json_encode( array(
            'to'      => 'recipient@example.com',
            'subject' => 'Notification from Another Plugin',
            'message' => '<p>This email was sent from a remote WordPress site.</p>',
        ) ),
    )
);

if ( is_wp_error( $response ) ) {
    error_log( 'REST Mailer error: ' . $response->get_error_message() );
} else {
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    error_log( 'REST Mailer response: ' . print_r( $body, true ) );
}
```

### Node.js (fetch)

```javascript
const credentials = Buffer.from("admin:abcdefghijklmnopqrstuvwx").toString("base64");

const response = await fetch("https://example.com/wp-json/custom/v1/send-email", {
  method: "POST",
  headers: {
    "Authorization": `Basic ${credentials}`,
    "Content-Type": "application/json",
    "X-API-Key": "YOUR_API_KEY",
  },
  body: JSON.stringify({
    to: "recipient@example.com",
    subject: "Build Complete",
    message: "<p>CI pipeline <strong>#1234</strong> passed all checks.</p>",
    sender_name: "GitHub Actions",
  }),
});

const data = await response.json();
console.log(response.status, data);
```

---

## Security

My REST Mailer implements multiple layers of defense to prevent unauthorized use and abuse.

### 1. WordPress Capability Check

The permission callback requires the authenticated user to have the `edit_posts` capability. Anonymous users and subscribers are rejected with a `403 Forbidden` response before any further processing occurs.

### 2. Custom API Key with Timing-Safe Comparison

When enabled, the plugin compares the `X-API-Key` header against the stored key using `hash_equals()`. This is a constant-time comparison function that prevents timing attacks, which could otherwise allow an attacker to deduce the key one character at a time by measuring response latency.

### 3. Input Sanitization

Every request parameter is sanitized before use:

- `to`, `subject`, `sender_name` -- processed with `sanitize_text_field()`.
- `from`, `reply_to` -- processed with `sanitize_email()`.
- `message` -- processed with `wp_kses_post()`, which strips dangerous HTML (scripts, iframes, event handlers) while preserving safe formatting tags (`<p>`, `<h1>`-`<h6>`, `<strong>`, `<em>`, `<a>`, `<ul>`, `<li>`, `<table>`, etc.).

### 4. Rate Limiting

A configurable per-minute rate limit (default: 10 emails/minute) prevents abuse and runaway scripts. The counter uses a WordPress transient with a 60-second TTL. Once the limit is reached, subsequent requests receive a `429 Too Many Requests` response until the window resets.

### 5. Clean Uninstall

When the plugin is deleted through the WordPress admin, all stored options (`mrm_options`, `mrm_email_log`) and transients (`mrm_rate_count`) are removed, leaving no residual data in the database.

---

## Hooks and Filters

My REST Mailer provides two filters for developers who need to customize or extend its behavior.

### `mrm_email_headers`

Modify the array of email header strings before the email is sent.

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$headers` | `string[]` | Array of email header strings (e.g., `'Content-Type: text/html; charset=UTF-8'`). |
| `$recipients` | `string[]` | Array of validated recipient email addresses. |
| `$subject` | `string` | The email subject line. |
| `$message` | `string` | The email body (HTML). |

**Example -- Add a CC header:**

```php
add_filter( 'mrm_email_headers', function ( array $headers, array $recipients, string $subject, string $message ): array {
    $headers[] = 'Cc: manager@example.com';
    return $headers;
}, 10, 4 );
```

**Example -- Add a custom tracking header:**

```php
add_filter( 'mrm_email_headers', function ( array $headers ): array {
    $headers[] = 'X-Mailer-Source: my-rest-mailer';
    return $headers;
}, 10, 4 );
```

### `mrm_before_send_email`

Inspect, modify, or block the entire email payload before it is passed to `wp_mail()`. Return the payload array to continue sending, or return a `WP_Error` to abort.

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$payload` | `array` | Associative array with keys: `to` (string[]), `subject` (string), `message` (string), `headers` (string[]). |

**Example -- Block emails to a specific domain:**

```php
add_filter( 'mrm_before_send_email', function ( $payload ) {
    foreach ( $payload['to'] as $email ) {
        if ( str_ends_with( $email, '@blocked-domain.com' ) ) {
            return new WP_Error(
                'blocked_domain',
                'Emails to blocked-domain.com are not permitted.'
            );
        }
    }
    return $payload;
} );
```

**Example -- Append a footer to every email:**

```php
add_filter( 'mrm_before_send_email', function ( array $payload ): array {
    $payload['message'] .= '<hr><p style="font-size:12px;color:#888;">Sent via My REST Mailer</p>';
    return $payload;
} );
```

---

## Troubleshooting

| Problem | Possible Cause | Solution |
|---|---|---|
| **401 Unauthorized** (`rest_missing_api_key`) | `X-API-Key` header not included in the request. | Add the `-H "X-API-Key: YOUR_KEY"` header. If you do not need API Key enforcement, disable "Require API Key" in Settings. |
| **403 Forbidden** (`rest_forbidden`) | WordPress credentials are invalid, or the user lacks the `edit_posts` capability. | Verify the username and Application Password. Ensure the user has the Editor or Administrator role. |
| **403 Forbidden** (`rest_invalid_api_key`) | The API Key in the request does not match the one stored in Settings. | Copy the exact key from **Settings > REST Mailer** and ensure there are no extra spaces or line breaks. |
| **429 Too Many Requests** | You have exceeded the configured rate limit. | Wait 60 seconds for the window to reset, or increase "Max Emails per Minute" in Settings. |
| **500 Internal Server Error** (`rest_api_key_not_configured`) | API Key enforcement is on but no key has been saved. | Go to **Settings > REST Mailer**, generate or enter an API Key, and save. |
| **500 Internal Server Error** (wp_mail failure) | The underlying mail transport failed. | Install and configure an SMTP plugin. Check PHP `mail()` availability. Review the mail server logs. |
| **Email received without HTML formatting** | The Content-Type header was overridden or stripped. | Ensure no other plugin is hooking into `wp_mail` and forcing plain text. Check your SMTP plugin settings for content type options. |
| **REST API returns 404** | Pretty permalinks are not enabled, or the rewrite rules are stale. | Go to **Settings > Permalinks** and click **Save Changes** (even without making changes) to flush rewrite rules. |
| **Cannot authenticate over HTTP** | WordPress blocks Application Passwords on non-HTTPS connections by default. | Add `add_filter( 'wp_is_application_passwords_available_for_user', '__return_true' );` to your theme or a custom plugin. Only do this on trusted private networks. |
| **.htaccess stripping Authorization header** | Some Apache configurations strip the `Authorization` header before PHP receives it. | Add the following to your `.htaccess` file above the WordPress block: `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]` or `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`. |
| **CDN or reverse proxy stripping custom headers** | Cloudflare, Nginx, or other proxies may strip non-standard headers like `X-API-Key`. | In Cloudflare, ensure no Transform Rule is removing custom headers. In Nginx, add `proxy_pass_request_headers on;` and explicitly pass the header with `proxy_set_header X-API-Key $http_x_api_key;`. |

---

## WP-CLI

You can quickly test the endpoint from the command line using `wp eval` combined with `wp_remote_post`:

```bash
wp eval '
$r = wp_remote_post( home_url("/wp-json/custom/v1/send-email"), array(
    "headers" => array(
        "Authorization" => "Basic " . base64_encode("admin:abcdefghijklmnopqrstuvwx"),
        "Content-Type"  => "application/json",
        "X-API-Key"     => "YOUR_API_KEY",
    ),
    "body" => wp_json_encode(array(
        "to"      => "test@example.com",
        "subject" => "WP-CLI Test",
        "message" => "<p>Sent from WP-CLI.</p>",
    )),
));
echo wp_remote_retrieve_response_code($r) . "\n";
echo wp_remote_retrieve_body($r) . "\n";
'
```

Alternatively, use cURL directly from a terminal session on the server:

```bash
curl -s -X POST "https://$(wp option get siteurl | sed 's|https\?://||')/wp-json/custom/v1/send-email" \
  -u "admin:abcdefghijklmnopqrstuvwx" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{"to":"test@example.com","subject":"CLI Test","message":"<p>Hello</p>"}' | python3 -m json.tool
```

---

## Changelog

For a detailed version history, see [CHANGELOG.md](CHANGELOG.md).

### 2.1.0

- Added configurable rate limiting (emails per minute) with HTTP 429 response.
- Added email log (last 50 entries) with an admin tab and clear button.
- Added support for multiple recipients via comma-separated `to` field.
- Added `mrm_email_headers` and `mrm_before_send_email` developer filters.
- Added clean uninstall hook that removes all plugin data from the database.

### 2.0.0

- Introduced the admin settings page with tabbed UI.
- Added dual-layer authentication (Application Passwords + API Key).
- Added configurable defaults for From Email, From Name, and Reply-To.
- Added one-click API Key generator.
- Added "Settings" quick link on the Plugins page.

### 1.0.0

- Initial release with basic REST API email sending endpoint.

---

## Contributing

Contributions are welcome. To get started:

1. Fork the repository at [https://github.com/CryptoStatistical/Wordpress_SECURE_REST_MAIL_SENDER](https://github.com/CryptoStatistical/Wordpress_SECURE_REST_MAIL_SENDER).
2. Create a feature branch from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. Make your changes, following the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).
4. Test your changes against a local WordPress installation.
5. Commit with clear, descriptive messages.
6. Open a pull request describing what you changed and why.

### Guidelines

- Keep pull requests focused on a single change.
- Add inline PHPDoc comments for all new functions.
- Sanitize and validate all user input.
- Do not introduce dependencies outside of WordPress core.
- If adding a new feature, update the README accordingly.

---

## License

This project is licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

```
Copyright (C) CryptoStatistical

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```
