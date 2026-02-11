<?php
/**
 * Plugin Name: My REST Mailer
 * Plugin URI:  https://github.com/CryptoStatistical/Wordpress_SECURE_REST_MAIL_SENDER
 * Description: Secure REST API endpoint (POST /wp-json/custom/v1/send-email) to send HTML emails. Features dual authentication (WordPress Application Passwords + custom API Key), configurable sender, reply-to, rate limiting, email log, and a full admin settings page.
 * Version:     2.1.0
 * Author:      CryptoStatistical
 * Author URI:  https://github.com/CryptoStatistical
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

define( 'MRM_VERSION', '2.1.0' );
define( 'MRM_OPTION_GROUP', 'mrm_settings_group' );
define( 'MRM_OPTION_NAME', 'mrm_options' );
define( 'MRM_LOG_OPTION', 'mrm_email_log' );
define( 'MRM_RATE_TRANSIENT', 'mrm_rate_count' );
define( 'MRM_LOG_MAX_ENTRIES', 50 );

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

	// ── Section: Rate Limiting ───────────────────────────────────────
	add_settings_section(
		'mrm_section_rate',
		__( 'Rate Limiting', 'my-rest-mailer' ),
		static function (): void {
			echo '<p>' . esc_html__(
				'Limit the number of emails that can be sent per minute to prevent abuse.',
				'my-rest-mailer'
			) . '</p>';
		},
		'my-rest-mailer'
	);

	add_settings_field(
		'mrm_rate_limit',
		__( 'Max Emails per Minute', 'my-rest-mailer' ),
		'mrm_render_field_rate_limit',
		'my-rest-mailer',
		'mrm_section_rate'
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
		'rate_limit'      => 10,
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
	$clean['rate_limit']      = absint( $input['rate_limit'] ?? 10 );

	if ( $clean['rate_limit'] < 1 ) {
		$clean['rate_limit'] = 1;
	}

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
	echo '<input type="text" name="' . esc_attr( MRM_OPTION_NAME ) . '[api_key]" value="' . $value . '" class="regular-text" autocomplete="off" />';
	echo '<p class="description">' . esc_html__( 'A secret key that external callers must send in the X-API-Key header. Generate a long random string (e.g. 32+ chars).', 'my-rest-mailer' ) . '</p>';
	echo '<button type="button" class="button button-secondary" id="mrm-generate-key">' . esc_html__( 'Generate Key', 'my-rest-mailer' ) . '</button>';
	echo '<script>
		document.getElementById("mrm-generate-key").addEventListener("click", function(){
			var a = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
			var k = "";
			var arr = new Uint32Array(48);
			window.crypto.getRandomValues(arr);
			for(var i = 0; i < 48; i++) k += a[arr[i] % a.length];
			document.querySelector("input[name=\'' . esc_js( MRM_OPTION_NAME ) . '[api_key]\']").value = k;
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
	echo '<input type="checkbox" name="' . esc_attr( MRM_OPTION_NAME ) . '[require_api_key]" value="1" ' . $checked . ' />';
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
	echo '<input type="email" name="' . esc_attr( MRM_OPTION_NAME ) . '[from_email]" value="' . $value . '" class="regular-text" />';
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
	echo '<input type="text" name="' . esc_attr( MRM_OPTION_NAME ) . '[from_name]" value="' . $value . '" class="regular-text" />';
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
	echo '<input type="email" name="' . esc_attr( MRM_OPTION_NAME ) . '[reply_to]" value="' . $value . '" class="regular-text" />';
	echo '<p class="description">' . esc_html__( 'Default Reply-To address. Can be overridden per-request via the "reply_to" JSON field.', 'my-rest-mailer' ) . '</p>';
}

/**
 * Render the Rate Limit field.
 *
 * @since 2.1.0
 * @return void
 */
function mrm_render_field_rate_limit(): void {

	$value = absint( mrm_get_option( 'rate_limit' ) );
	echo '<input type="number" name="' . esc_attr( MRM_OPTION_NAME ) . '[rate_limit]" value="' . esc_attr( $value ) . '" class="small-text" min="1" step="1" />';
	echo '<p class="description">' . esc_html__( 'Maximum number of emails allowed per minute. Set higher for batch sending. Default: 10.', 'my-rest-mailer' ) . '</p>';
}

/**
 * Render the full settings page with tabs (Settings + Log).
 *
 * @since 2.0.0
 * @return void
 */
function mrm_render_settings_page(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Show save confirmation notice.
	if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		add_settings_error(
			'mrm_messages',
			'mrm_updated',
			__( 'Settings saved.', 'my-rest-mailer' ),
			'updated'
		);
	}

	// Handle log clear action.
	if ( isset( $_POST['mrm_clear_log'] ) && check_admin_referer( 'mrm_clear_log_action', 'mrm_clear_log_nonce' ) ) {
		delete_option( MRM_LOG_OPTION );
		add_settings_error(
			'mrm_messages',
			'mrm_log_cleared',
			__( 'Email log cleared.', 'my-rest-mailer' ),
			'updated'
		);
	}

	$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php settings_errors( 'mrm_messages' ); ?>

		<h2 class="nav-tab-wrapper">
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=my-rest-mailer&tab=settings' ) ); ?>"
			   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Settings', 'my-rest-mailer' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=my-rest-mailer&tab=log' ) ); ?>"
			   class="nav-tab <?php echo 'log' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Email Log', 'my-rest-mailer' ); ?>
			</a>
		</h2>

		<?php if ( 'log' === $active_tab ) : ?>
			<?php mrm_render_log_tab(); ?>
		<?php else : ?>
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
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render the Email Log tab content.
 *
 * @since 2.1.0
 * @return void
 */
function mrm_render_log_tab(): void {

	$log = get_option( MRM_LOG_OPTION, array() );

	?>
	<form method="post">
		<?php wp_nonce_field( 'mrm_clear_log_action', 'mrm_clear_log_nonce' ); ?>
		<p>
			<?php
			printf(
				/* translators: %d: number of log entries */
				esc_html__( 'Showing the last %d email(s) sent via REST Mailer.', 'my-rest-mailer' ),
				count( $log )
			);
			?>
			<input type="submit" name="mrm_clear_log" class="button button-secondary" value="<?php esc_attr_e( 'Clear Log', 'my-rest-mailer' ); ?>" />
		</p>
	</form>

	<?php if ( empty( $log ) ) : ?>
		<p><em><?php esc_html_e( 'No emails logged yet.', 'my-rest-mailer' ); ?></em></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'my-rest-mailer' ); ?></th>
					<th><?php esc_html_e( 'To', 'my-rest-mailer' ); ?></th>
					<th><?php esc_html_e( 'Subject', 'my-rest-mailer' ); ?></th>
					<th><?php esc_html_e( 'Result', 'my-rest-mailer' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_reverse( $log ) as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( $entry['date'] ?? '' ); ?></td>
						<td><?php echo esc_html( $entry['to'] ?? '' ); ?></td>
						<td><?php echo esc_html( $entry['subject'] ?? '' ); ?></td>
						<td>
							<?php if ( ! empty( $entry['success'] ) ) : ?>
								<span style="color:green;">&#10003; <?php esc_html_e( 'Sent', 'my-rest-mailer' ); ?></span>
							<?php else : ?>
								<span style="color:red;">&#10007; <?php esc_html_e( 'Failed', 'my-rest-mailer' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif;
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

		if ( $stored_key !== $provided_key ) {
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
 * The "to" field accepts a single email string or a comma-separated list of emails.
 *
 * @since 2.0.0
 * @return array<string, array>
 */
function mrm_get_endpoint_args(): array {

	return array(
		'to'          => array(
			'required'          => true,
			'type'              => 'string',
			'description'       => __( 'Recipient email address. Accepts a single email or comma-separated list.', 'my-rest-mailer' ),
			'sanitize_callback' => 'sanitize_text_field',
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

/**
 * Parse the "to" field into an array of validated email addresses.
 *
 * Accepts a single email string or a comma-separated list.
 *
 * @since 2.1.0
 * @param string $to_raw Raw "to" value from the request.
 * @return string[] Array of valid sanitized email addresses.
 */
function mrm_parse_recipients( string $to_raw ): array {

	$parts      = explode( ',', $to_raw );
	$recipients = array();

	foreach ( $parts as $part ) {
		$email = sanitize_email( trim( $part ) );
		if ( is_email( $email ) ) {
			$recipients[] = $email;
		}
	}

	return array_unique( $recipients );
}

/* =========================================================================
 * 4. RATE LIMITING
 * ======================================================================= */

/**
 * Check whether the current request exceeds the rate limit.
 *
 * Uses a WordPress transient with a 60-second TTL.
 *
 * @since 2.1.0
 * @return bool|WP_Error True if allowed, WP_Error if rate-limited.
 */
function mrm_check_rate_limit() {

	$limit = absint( mrm_get_option( 'rate_limit' ) );

	if ( $limit < 1 ) {
		$limit = 10;
	}

	$count = (int) get_transient( MRM_RATE_TRANSIENT );

	if ( $count >= $limit ) {
		return new WP_Error(
			'rest_rate_limit_exceeded',
			sprintf(
				/* translators: %d: rate limit per minute */
				__( 'Rate limit exceeded. Maximum %d emails per minute allowed.', 'my-rest-mailer' ),
				$limit
			),
			array( 'status' => 429 )
		);
	}

	return true;
}

/**
 * Increment the rate limit counter.
 *
 * @since 2.1.0
 * @return void
 */
function mrm_increment_rate_counter(): void {

	$count = (int) get_transient( MRM_RATE_TRANSIENT );

	if ( 0 === $count ) {
		set_transient( MRM_RATE_TRANSIENT, 1, 60 );
	} else {
		set_transient( MRM_RATE_TRANSIENT, $count + 1, 60 );
	}
}

/* =========================================================================
 * 5. EMAIL LOG
 * ======================================================================= */

/**
 * Add an entry to the email log (stored as a WordPress option).
 *
 * Keeps at most MRM_LOG_MAX_ENTRIES entries.
 *
 * @since 2.1.0
 * @param string $to      Recipient(s).
 * @param string $subject Email subject.
 * @param bool   $success Whether wp_mail() returned true.
 * @return void
 */
function mrm_log_email( string $to, string $subject, bool $success ): void {

	$log = get_option( MRM_LOG_OPTION, array() );

	$log[] = array(
		'date'    => current_time( 'Y-m-d H:i:s' ),
		'to'      => $to,
		'subject' => $subject,
		'success' => $success,
	);

	// Keep only the last N entries.
	if ( count( $log ) > MRM_LOG_MAX_ENTRIES ) {
		$log = array_slice( $log, -MRM_LOG_MAX_ENTRIES );
	}

	update_option( MRM_LOG_OPTION, $log, false );
}

/* =========================================================================
 * 6. EMAIL HANDLER
 * ======================================================================= */

/**
 * Main callback — build headers, send the email, return JSON response.
 *
 * Supports the following filters:
 * - `mrm_before_send_email` — modify or block the email payload before sending.
 * - `mrm_email_headers`     — modify the email headers array before sending.
 *
 * @since 2.0.0
 * @param WP_REST_Request $request Validated & sanitised request.
 * @return WP_REST_Response|WP_Error
 */
function mrm_handle_send_email( WP_REST_Request $request ) {

	// ── Rate limiting ───────────────────────────────────────────────
	$rate_check = mrm_check_rate_limit();
	if ( is_wp_error( $rate_check ) ) {
		return $rate_check;
	}

	// ── Required fields ──────────────────────────────────────────────
	$to_raw  = $request->get_param( 'to' );
	$subject = $request->get_param( 'subject' );
	$message = $request->get_param( 'message' );

	// Parse recipients (supports comma-separated list).
	$recipients = mrm_parse_recipients( $to_raw );

	if ( empty( $recipients ) ) {
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

	/**
	 * Filter the email headers before sending.
	 *
	 * @since 2.1.0
	 * @param string[] $headers    Array of email header strings.
	 * @param string[] $recipients Array of recipient email addresses.
	 * @param string   $subject    Email subject.
	 * @param string   $message    Email body (HTML).
	 */
	$headers = apply_filters( 'mrm_email_headers', $headers, $recipients, $subject, $message );

	/**
	 * Filter or block the email before sending.
	 *
	 * Return a WP_Error to prevent the email from being sent.
	 * Return the (optionally modified) payload array to continue.
	 *
	 * @since 2.1.0
	 * @param array $payload {
	 *     Email payload.
	 *
	 *     @type string[] $to      Recipient email addresses.
	 *     @type string   $subject Email subject.
	 *     @type string   $message Email body (HTML).
	 *     @type string[] $headers Email headers.
	 * }
	 */
	$payload = apply_filters(
		'mrm_before_send_email',
		array(
			'to'      => $recipients,
			'subject' => $subject,
			'message' => $message,
			'headers' => $headers,
		)
	);

	if ( is_wp_error( $payload ) ) {
		return new WP_REST_Response(
			array(
				'status'  => 'error',
				'message' => $payload->get_error_message(),
			),
			400
		);
	}

	// Apply any modifications from the filter.
	$recipients = $payload['to'];
	$subject    = $payload['subject'];
	$message    = $payload['message'];
	$headers    = $payload['headers'];

	$to_string = implode( ', ', $recipients );

	// ── Send ─────────────────────────────────────────────────────────
	$sent = wp_mail( $recipients, $subject, $message, $headers );

	// Increment rate counter after sending attempt.
	mrm_increment_rate_counter();

	// Log the email.
	mrm_log_email( $to_string, $subject, $sent );

	if ( $sent ) {
		return new WP_REST_Response(
			array(
				'status'  => 'success',
				'message' => sprintf(
					/* translators: %s: comma-separated list of recipient emails */
					__( 'Email sent successfully to %s.', 'my-rest-mailer' ),
					$to_string
				),
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
 * 7. HOUSEKEEPING
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
	delete_option( MRM_LOG_OPTION );
	delete_transient( MRM_RATE_TRANSIENT );
}
register_uninstall_hook( __FILE__, 'mrm_uninstall' );
