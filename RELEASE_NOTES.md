# Release v2.1.0 — Rate Limiting, Email Log & Multi-Recipient

## How to Create This Release on GitHub

### Step 1: Create the ZIP
The plugin ZIP is already built: `my-rest-mailer-v2.1.0.zip`

To rebuild it manually:
```bash
mkdir -p /tmp/my-rest-mailer
cp my-rest-mailer.php README.md /tmp/my-rest-mailer/
cd /tmp && zip -r my-rest-mailer-v2.1.0.zip my-rest-mailer/
```

### Step 2: Create the Release on GitHub
1. Go to your repository → **Releases** → **Draft a new release**
2. Click **Choose a tag** → type `v2.1.0` → **Create new tag on publish**
3. Set **Release title**: `v2.1.0 — Rate Limiting, Email Log & Multi-Recipient`
4. Paste the release notes below into the **Description** field
5. Drag and drop `my-rest-mailer-v2.1.0.zip` into the **Assets** area
6. Click **Publish release**

---

## Release Notes (copy-paste below)

### New Features
- **Rate Limiting** — Configurable max emails per minute (transient-based), prevents abuse
- **Email Log** — Admin UI tab showing last 50 sent emails with date, recipient, subject, and status
- **Multiple Recipients** — `to` field now accepts comma-separated email addresses
- **WordPress Filters** — `mrm_email_headers` and `mrm_before_send_email` for extensibility

### Improvements
- Admin settings page with tabbed interface (Settings + Email Log)
- Clear log button with nonce-verified action
- Uninstall cleanup includes log option and rate transient

### Project Additions
- PHPUnit test suite (authentication, REST endpoint, sanitization, settings)
- Usage examples (curl + Python)
- Full README documentation

### Requirements
- WordPress 5.6+
- PHP 7.4+

### Installation

1. Download **`my-rest-mailer-v2.1.0.zip`** from the Assets section below
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**
3. Choose the downloaded ZIP file and click **Install Now**
4. Activate the plugin
5. Go to **Settings → REST Mailer** to configure:
   - Set an **API Key** (click "Generate Key" for a random 48-char key)
   - Configure **From Email**, **From Name**, **Reply-To**
   - Set the **Rate Limit** (default: 10 emails/minute)

### Quick Test

```bash
curl -X POST "https://your-site.com/wp-json/custom/v1/send-email" \
  -u "username:APPLICATION_PASSWORD" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{
    "to": "recipient@example.com",
    "subject": "Test Email",
    "message": "<h2>Hello!</h2><p>HTML email via REST API.</p>"
  }'
```

### Troubleshooting 400 Errors

| Error | Cause | Fix |
|-------|-------|-----|
| `rest_missing_callback_param` | Missing `to`, `subject`, or `message` | Include all 3 required fields in JSON body |
| `Invalid recipient email address` | Email format not valid | Check email format, use comma-separated for multiple |
| `Subject and message body must not be empty` | Empty fields | Ensure both contain non-whitespace text |
| Generic `rest_invalid_param` | Wrong data type | All fields must be strings, not arrays or objects |

**Tip:** Always include `Content-Type: application/json` header, otherwise WordPress won't parse the request body.
