<?php
/**
 * Plugin Name: My REST Mailer
 * Plugin URI:  https://example.com/my-rest-mailer
 * Description: Secure REST API endpoint (POST /wp-json/custom/v1/send-email) to send HTML emails. Features dual authentication (WordPress Application Passwords + custom API Key), configurable sender, reply-to, and a full admin settings page.
 * Version:     2.0.0
 * Author:      Giorgio
 * Author URI:  https://example.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: my-rest-mailer
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================================
 * 1. CONSTANTS
 * ======================================================================= */

define( 'MRM_VERSION', '2.0.0' );
define( 'MRM_OPTION_GROUP', 'mrm_settings_group' );
define( 'MRM_OPTION_NAME', 'mrm_options' );

/* =========================================================================
 * 2. ADMIN SETTINGS PAGE
 * ======================================================================= */

/**
 * Register the Settings sub-menu page.
 *
 * @since 2.0.0
 * @return void
 */
function mrm_add_settings_page(): void {

    add_options_page(
        __( 'REST Mailer Settings', 'my-rest-mailer' ),   // Page title
        __( 'REST Mailer', 'my-rest-mailer' ),             // Menu title
        'manage_options',                                   // Capability
        'my-rest-mailer',                                   // Slug
        'mrm_render_settings_page'                          // Callback
    );
}
add_action( 'admin_menu', 'mrm_add_settings_page' );

/**
 * Register settings, sections, and fields.
 *
 * @since 2.0.0
 * @return void
 */
function mrm_register_settings(): void {

    register_setting(
        MRM_OPTION_GROUP,
        MRM_OPTION_NAME,
        array(
            'type'              => 'array',
            'sanitize_callback' => 'mrm_sanitize_options',
            'default'           => mrm_get_defaults(),
        )
    );

    // ── Section: Authentication ──────────────────────────────────────
    add_settings_section(
        'mrm_section_auth',
        __( 'Authentication', 'my-rest-mailer' ),
        static function (): void {
            echo '<p>' . esc_html__(
                'Configure the API Key used as a second authentication layer on top of WordPress Application Passwords (Basic Auth).',
                'my-rest-mailer'
            ) . '</p>';
        },
        'my-rest-mailer'
    );

    add_settings_field(
        'mrm_api_key',
        __( 'API Key', 'my-rest-mailer' ),
        'mrm_render_field_api_key',
        'my-rest-mailer',
        'mrm_section_auth'
    );

    add_settings_field(
        'mrm_require_api_key',
        __( 'Require API Key', 'my-rest-mailer' ),
        'mrm_render_field_require_api_key',
        'my-rest-mailer',
        'mrm_section_auth'
    );

    // ── Section: Email Defaults ──────────────────────────────────────
    add_settings_section(
        'mrm_section_email',
        __( 'Email Defaults', 'my-rest-mailer' ),
        static function (): void {
            echo '<p>' . esc_html__(
                'Default values used when the API request does not provide them. Leave empty to use WordPress defaults.',
                'my-rest-mailer'
            ) . '</p>';
        },
        'my-rest-mailer'
    );

    add_settings_field(
        'mrm_from_email',
        __( 'From Email', 'my-rest-mailer' ),
        'mrm_render_field_from_email',
        'my-rest-mailer',
        'mrm_section_email'
    );

    add_settings_field(
        'mrm_from_name',
        __( 'From Name', 'my-rest-mailer' ),
        'mrm_render_field_from_name',
        'my-rest-mailer',
        'mrm_section_email'
    );

    add_settings_field(
        'mrm_reply_to',
        __( 'Reply-To Email', 'my-rest-mailer' ),
        'mrm_render_field_reply_to',
        'my-rest-mailer',
        'mrm_section_email'
    );
}
add_action( 'admin_init', 'mrm_register_settings' );

/**
 * Return default option values.
 *
 * @since 2.0.0
 * @return array<string, mixed>
 */
function mrm_get_defaults(): array {
    return array(
        'api_key'         => '',
        'require_api_key' => '1',
        'from_email'      => '',
        'from_name'       => '',
        'reply_to'        => '',
    );
}

/**
 * Retrieve a single option value (with fallback to default).
 *
 * @since 2.0.0
 * @param string $key Option key.
 * @return mixed
 */
function mrm_get_option( string $key ) {

    $defaults = mrm_get_defaults();
    $options  = get_option( MRM_OPTION_NAME, $defaults );

    return $options[ $key ] ?? ( $defaults[ $key ] ?? '' );
}

/**
 * Sanitize all option values before saving.
 *
 * @since 2.0.0
 * @param array $input Raw form input.
 * @return array Sanitized values.
 */
function mrm_sanitize_options( array $input ): array {

    $clean = array();

    $clean['api_key']         = sanitize_text_field( $input['api_key'] ?? '' );
    $clean['require_api_key'] = ! empty( $input['require_api_key'] ) ? '1' : '0';
    $clean['from_email']      = sanitize_email( $input['from_email'] ?? '' );
    $clean['from_name']       = sanitize_text_field( $input['from_name'] ?? '' );
    $clean['reply_to']        = sanitize_email( $input['reply_to'] ?? '' );

    return $clean;
}

/* ── Field renderers ───────────────────────────────────────────────── */

/**
 * Render the API Key field.
 *
 * @since 2.0.0
 * @return void
 */
function mrm_render_field_api_key(): void {

    $value = esc_attr( mrm_get_option( 'api_key' ) );
    echo '<input type="text" name="' . MRM_OPTION_NAME . '[api_key]" value="' . $value . '" class="regular-text" autocomplete="off" />';
    echo '<p class="description">' . esc_html__( 'A secret key that external callers must send in the X-API-Key header. Generate a long random string (e.g. 32+ chars).', 'my-rest-mailer' ) . '</p>';
    echo '<button type="button" class="button button-secondary" id="mrm-generate-key">' . esc_html__( 'Generate Key', 'my-rest-mailer' ) . '</button>';
    echo '<script>
        document.getElementById("mrm-generate-key").addEventListener("click", function(){
            var a = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
            var k = "";
            var arr = new Uint32Array(48);
            window.crypto.getRandomValues(arr);
            for(var i = 0; i < 48; i++) k += a[arr[i] % a.length];
            document.querySelector("input[name=\'' . MRM_OPTION_NAME . '[api_key]\']").value = k;
        });
    </script>';
}

/**
 * Render the "Require API Key" checkbox.
 *
 * @since 2.0.0
 * @return void
 */
function mrm_render_field_require_api_key(): void {

    $checked = checked( mrm_get_option( 'require_api_key' ), '1', false );
    echo '<label>';
    echo '<input type="checkbox" name="' . MRM_OPTION_NAME . '[require_api_key]" value="1" ' . $checked . ' />';
    echo ' ' . esc_html__( 'When enabled, every request must include a valid X-API-Key header in addition to Basic Auth.', 'my-rest-mailer' );
    echo '</label>';
}

/**
 * Render the From Email field.
 *
 * @since 2.0.0
 * @return void
 */
function mrm_render_field_from_email(): void {

    $value = esc_attr( mrm_get_option( 'from_email' ) );
    echo '<input type="email" name="' . MRM_OPTION_NAME . '[from_email]" value="' . $value . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__( 'Default "From" address. Can be overridden per-request via the "from" JSON field.', 'my-rest-mailer' ) . '</p>';
}

/**
 * Render the From Name field.
 *
 * @since 2.0.0
 * @return void
 */
function mrm_render_field_from_name(): void {

    $value = esc_attr( mrm_get_option( 'from_name' ) );
    echo '<input type="text" name="' . MRM_OPTION_NAME . '[from_name]" value="' . $value . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__( 'Default sender display name. Can be overridden per-request via the "sender_name" JSON field.', 'my-rest-mailer' ) . '</p>';
}

/**
 * Render the Reply-To field.
 *
 * @since 2.0.0
 * @return void
 */
function mrm_render_field_reply_to(): void {

    $value = esc_attr( mrm_get_option( 'reply_to' ) );
    echo '<input type="email" name="' . MRM_OPTION_NAME . '[reply_to]" value="' . $value . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__( 'Default Reply-To address. Can be overridden per-request via the "reply_to" JSON field.', 'my-rest-mailer' ) . '</p>';
}

/**
 * Render the full settings page.
 *
 * @since 2.0.0
 * @return void
 */
function mrm_render_settings_page(): void {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Show save confirmation notice.
    if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore
        add_settings_error(
            'mrm_messages',
            'mrm_updated',
            __( 'Settings saved.', 'my-rest-mailer' ),
            'updated'
        );
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <?php settings_errors( 'mrm_messages' ); ?>
        <form action="options.php" method="post">
            <?php
            settings_fields( MRM_OPTION_GROUP );
            do_settings_sections( 'my-rest-mailer' );
            submit_button( __( 'Save Settings', 'my-rest-mailer' ) );
            ?>
        </form>

        <hr />
        <h2><?php esc_html_e( 'Quick Reference', 'my-rest-mailer' ); ?></h2>
        <p><strong><?php esc_html_e( 'Endpoint:', 'my-rest-mailer' ); ?></strong>
            <code>POST <?php echo esc_html( rest_url( 'custom/v1/send-email' ) ); ?></code>
        </p>
        <p><strong><?php esc_html_e( 'cURL example:', 'my-rest-mailer' ); ?></strong></p>
        <pre style="background:#f0f0f0;padding:12px;overflow-x:auto;border-radius:4px;">curl -X POST "<?php echo esc_html( rest_url( 'custom/v1/send-email' ) ); ?>" \
  -u "username:APPLICATION_PASSWORD" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{
    "to": "recipient@example.com",
    "subject": "Test Email",
    "message": "&lt;h2&gt;Hello!&lt;/h2&gt;&lt;p&gt;HTML email via REST API.&lt;/p&gt;",
    "from": "sender@example.com",
    "sender_name": "My NAS",
    "reply_to": "noreply@example.com"
  }'</pre>
    </div>
    <?php
}

/* =========================================================================
 * 3. REST API ROUTE
 * ======================================================================= */

/**
 * Register the custom REST API route.
 *
 * @since 1.0.0
 * @return void
 */
function mrm_register_send_email_route(): void {

    register_rest_route(
        'custom/v1',
        '/send-email',
        array(
            'methods'             => WP_REST_Server::CREATABLE, // POST
            'callback'            => 'mrm_handle_send_email',
            'permission_callback' => 'mrm_check_permissions',
            'args'                => mrm_get_endpoint_args(),
        )
    );
}
add_action( 'rest_api_init', 'mrm_register_send_email_route' );

/**
 * Permission callback — dual authentication layer.
 *
 * Layer 1: WordPress native capability check (works with Application Passwords).
 * Layer 2: Custom API Key verification via X-API-Key header (if enabled in settings).
 *
 * @since 2.0.0
 * @param WP_REST_Request $request The incoming request.
 * @return bool|WP_Error
 */
function mrm_check_permissions( WP_REST_Request $request ) {

    // ── Layer 1: WordPress native auth (Basic Auth / Application Passwords) ──
    if ( ! current_user_can( 'edit_posts' ) ) {
        return new WP_Error(
            'rest_forbidden',
            __( 'Authentication failed. Valid WordPress credentials with edit_posts capability are required.', 'my-rest-mailer' ),
            array( 'status' => 403 )
        );
    }

    // ── Layer 2: Custom API Key (if enabled) ─────────────────────────
    $require_key = mrm_get_option( 'require_api_key' );

    if ( '1' === $require_key ) {

        $stored_key = mrm_get_option( 'api_key' );

        // If the admin enabled the check but forgot to set a key, block everything.
        if ( empty( $stored_key ) ) {
            return new WP_Error(
                'rest_api_key_not_configured',
                __( 'API Key authentication is enabled but no key has been configured. Please set one in Settings → REST Mailer.', 'my-rest-mailer' ),
                array( 'status' => 500 )
            );
        }

        // Accept the key from the X-API-Key header.
        $provided_key = $request->get_header( 'X-API-Key' );

        if ( empty( $provided_key ) ) {
            return new WP_Error(
                'rest_missing_api_key',
                __( 'Missing X-API-Key header.', 'my-rest-mailer' ),
                array( 'status' => 401 )
            );
        }

        // Timing-safe comparison to avoid timing attacks.
        if ( ! hash_equals( $stored_key, $provided_key ) ) {
            return new WP_Error(
                'rest_invalid_api_key',
                __( 'Invalid API Key.', 'my-rest-mailer' ),
                array( 'status' => 403 )
            );
        }
    }

    return true;
}

/**
 * Define the accepted JSON parameters with validation and sanitization.
 *
 * Required: to, subject, message.
 * Optional: from, sender_name, reply_to (override settings defaults).
 *
 * @since 2.0.0
 * @return array<string, array>
 */
function mrm_get_endpoint_args(): array {

    return array(
        'to'          => array(
            'required'          => true,
            'type'              => 'string',
            'description'       => __( 'Recipient email address.', 'my-rest-mailer' ),
            'sanitize_callback' => 'sanitize_email',
            'validate_callback' => static function ( $value ): bool {
                return is_email( $value ) !== false;
            },
        ),
        'subject'     => array(
            'required'          => true,
            'type'              => 'string',
            'description'       => __( 'Email subject line.', 'my-rest-mailer' ),
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => static function ( $value ): bool {
                return is_string( $value ) && trim( $value ) !== '';
            },
        ),
        'message'     => array(
            'required'          => true,
            'type'              => 'string',
            'description'       => __( 'Email body (plain text or safe HTML).', 'my-rest-mailer' ),
            'sanitize_callback' => 'wp_kses_post',
            'validate_callback' => static function ( $value ): bool {
                return is_string( $value ) && trim( $value ) !== '';
            },
        ),
        'from'        => array(
            'required'          => false,
            'type'              => 'string',
            'description'       => __( 'Sender email address (overrides settings default).', 'my-rest-mailer' ),
            'sanitize_callback' => 'sanitize_email',
        ),
        'sender_name' => array(
            'required'          => false,
            'type'              => 'string',
            'description'       => __( 'Sender display name (overrides settings default).', 'my-rest-mailer' ),
            'sanitize_callback' => 'sanitize_text_field',
        ),
        'reply_to'    => array(
            'required'          => false,
            'type'              => 'string',
            'description'       => __( 'Reply-To address (overrides settings default).', 'my-rest-mailer' ),
            'sanitize_callback' => 'sanitize_email',
        ),
    );
}

/* =========================================================================
 * 4. EMAIL HANDLER
 * ======================================================================= */

/**
 * Main callback — build headers, send the email, return JSON response.
 *
 * @since 2.0.0
 * @param WP_REST_Request $request Validated & sanitised request.
 * @return WP_REST_Response
 */
function mrm_handle_send_email( WP_REST_Request $request ): WP_REST_Response {

    // ── Required fields ──────────────────────────────────────────────
    $to      = $request->get_param( 'to' );
    $subject = $request->get_param( 'subject' );
    $message = $request->get_param( 'message' );

    // Defensive is_email() guard.
    if ( ! is_email( $to ) ) {
        return new WP_REST_Response(
            array(
                'status'  => 'error',
                'message' => __( 'Invalid recipient email address.', 'my-rest-mailer' ),
            ),
            400
        );
    }

    if ( empty( $subject ) || empty( $message ) ) {
        return new WP_REST_Response(
            array(
                'status'  => 'error',
                'message' => __( 'Subject and message body must not be empty.', 'my-rest-mailer' ),
            ),
            400
        );
    }

    // ── Optional fields (request → settings default → WordPress default) ──
    $from_email  = $request->get_param( 'from' )        ?: mrm_get_option( 'from_email' );
    $sender_name = $request->get_param( 'sender_name' ) ?: mrm_get_option( 'from_name' );
    $reply_to    = $request->get_param( 'reply_to' )    ?: mrm_get_option( 'reply_to' );

    // ── Build headers ────────────────────────────────────────────────
    $headers   = array();
    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    if ( ! empty( $from_email ) && is_email( $from_email ) ) {
        $from_header = ! empty( $sender_name )
            ? sprintf( 'From: %s <%s>', sanitize_text_field( $sender_name ), $from_email )
            : sprintf( 'From: %s', $from_email );
        $headers[]   = $from_header;
    }

    if ( ! empty( $reply_to ) && is_email( $reply_to ) ) {
        $headers[] = sprintf( 'Reply-To: %s', $reply_to );
    }

    // ── Send ─────────────────────────────────────────────────────────
    $sent = wp_mail( $to, $subject, $message, $headers );

    if ( $sent ) {
        return new WP_REST_Response(
            array(
                'status'  => 'success',
                'message' => __( 'Email sent successfully.', 'my-rest-mailer' ),
            ),
            200
        );
    }

    return new WP_REST_Response(
        array(
            'status'  => 'error',
            'message' => __( 'Failed to send email. Check your WordPress mail configuration (SMTP plugin, server settings, etc.).', 'my-rest-mailer' ),
        ),
        500
    );
}

/* =========================================================================
 * 5. HOUSEKEEPING
 * ======================================================================= */

/**
 * Add a quick-access "Settings" link on the Plugins list page.
 *
 * @since 2.0.0
 * @param array $links Existing action links.
 * @return array Modified links.
 */
function mrm_plugin_action_links( array $links ): array {

    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url( admin_url( 'options-general.php?page=my-rest-mailer' ) ),
        esc_html__( 'Settings', 'my-rest-mailer' )
    );

    array_unshift( $links, $settings_link );

    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'mrm_plugin_action_links' );

/**
 * Clean up options on plugin uninstall.
 *
 * This runs only when the plugin is deleted from the admin panel.
 *
 * @since 2.0.0
 * @return void
 */
function mrm_uninstall(): void {
    delete_option( MRM_OPTION_NAME );
}
register_uninstall_hook( __FILE__, 'mrm_uninstall' );
