<?php
/**
 * Tests for the REST API endpoint: POST /wp-json/custom/v1/send-email.
 *
 * Covers route registration, required / optional field validation,
 * HTML sanitization, response format, multiple recipients, and rate limiting.
 *
 * @package My_REST_Mailer
 * @since   2.1.0
 */

/**
 * Class Test_REST_Endpoint
 *
 * @coversDefaultClass \mrm_handle_send_email
 * @since 2.1.0
 */
class Test_REST_Endpoint extends WP_UnitTestCase {

	/**
	 * REST API server instance.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * Editor user ID used to authenticate most requests.
	 *
	 * @var int
	 */
	private int $editor_id;

	/**
	 * Tracks whether wp_mail was intercepted during a test.
	 *
	 * @var bool
	 */
	private bool $mail_sent = false;

	/**
	 * Stores the last mail payload captured by the pre_wp_mail filter.
	 *
	 * @var array|null
	 */
	private ?array $last_mail = null;

	/* -----------------------------------------------------------------
	 * Set-up / Tear-down
	 * -------------------------------------------------------------- */

	/**
	 * Set up test fixtures.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function set_up(): void {

		parent::set_up();

		// Initialise the REST server and register routes.
		global $wp_rest_server;
		$this->server = rest_get_server();

		// Create an editor user (has edit_posts capability).
		$this->editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );

		// Authenticate as editor for every request by default.
		wp_set_current_user( $this->editor_id );

		// Disable API-key requirement so endpoint tests focus on the handler.
		update_option( MRM_OPTION_NAME, array_merge( mrm_get_defaults(), array(
			'require_api_key' => '0',
		) ) );

		// Reset rate-limit transient.
		delete_transient( MRM_RATE_TRANSIENT );

		// Reset mail tracking.
		$this->mail_sent = false;
		$this->last_mail = null;

		// Intercept wp_mail so no real mail is ever sent.
		add_filter( 'pre_wp_mail', array( $this, 'intercept_wp_mail' ), 10, 2 );
	}

	/**
	 * Tear down test fixtures.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function tear_down(): void {

		remove_filter( 'pre_wp_mail', array( $this, 'intercept_wp_mail' ), 10 );
		delete_option( MRM_OPTION_NAME );
		delete_option( MRM_LOG_OPTION );
		delete_transient( MRM_RATE_TRANSIENT );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Filter callback that intercepts wp_mail and records the payload.
	 *
	 * Returning a non-null value from pre_wp_mail short-circuits wp_mail().
	 *
	 * @since 2.1.0
	 * @param null|bool $result Null to continue, anything else to short-circuit.
	 * @param array     $atts   Mail attributes (to, subject, message, headers, attachments).
	 * @return bool Always returns true (mail "sent" successfully).
	 */
	public function intercept_wp_mail( $result, array $atts ): bool {

		$this->mail_sent = true;
		$this->last_mail = $atts;

		return true;
	}

	/* -----------------------------------------------------------------
	 * Helpers
	 * -------------------------------------------------------------- */

	/**
	 * Build a WP_REST_Request for the send-email endpoint.
	 *
	 * @since 2.1.0
	 * @param array $body JSON body parameters.
	 * @return WP_REST_Request
	 */
	private function create_request( array $body = array() ): WP_REST_Request {

		$request = new WP_REST_Request( 'POST', '/custom/v1/send-email' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );

		return $request;
	}

	/**
	 * Return a valid default request body.
	 *
	 * @since 2.1.0
	 * @return array
	 */
	private function valid_body(): array {

		return array(
			'to'      => 'recipient@example.com',
			'subject' => 'Test Subject',
			'message' => '<p>Hello, World!</p>',
		);
	}

	/* -----------------------------------------------------------------
	 * Tests
	 * -------------------------------------------------------------- */

	/**
	 * The send-email route must be registered on rest_api_init.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_register_send_email_route
	 * @return void
	 */
	public function test_endpoint_is_registered(): void {

		$routes = $this->server->get_routes();

		$this->assertArrayHasKey(
			'/custom/v1/send-email',
			$routes,
			'The /custom/v1/send-email route must be registered.'
		);
	}

	/**
	 * A fully valid POST request returns 200 and confirms mail was sent.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_handle_send_email
	 * @return void
	 */
	public function test_successful_email_send(): void {

		$request  = $this->create_request( $this->valid_body() );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'success', $data['status'] );
		$this->assertTrue( $this->mail_sent, 'wp_mail should have been called.' );
	}

	/**
	 * Omitting "to" must return a 400 error.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_handle_send_email
	 * @return void
	 */
	public function test_missing_required_field_to(): void {

		$body = $this->valid_body();
		unset( $body['to'] );

		$request  = $this->create_request( $body );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Omitting "subject" must return a 400 error.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_handle_send_email
	 * @return void
	 */
	public function test_missing_required_field_subject(): void {

		$body = $this->valid_body();
		unset( $body['subject'] );

		$request  = $this->create_request( $body );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Omitting "message" must return a 400 error.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_handle_send_email
	 * @return void
	 */
	public function test_missing_required_field_message(): void {

		$body = $this->valid_body();
		unset( $body['message'] );

		$request  = $this->create_request( $body );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * An obviously invalid email address in "to" must return 400.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_handle_send_email
	 * @covers ::mrm_parse_recipients
	 * @return void
	 */
	public function test_invalid_email_address(): void {

		$body       = $this->valid_body();
		$body['to'] = 'not-an-email';

		$request  = $this->create_request( $body );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'error', $data['status'] );
	}

	/**
	 * A subject that consists only of HTML tags should be stripped to empty
	 * and be rejected by the validate_callback with a 400.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_get_endpoint_args
	 * @return void
	 */
	public function test_empty_subject_after_sanitization(): void {

		$body            = $this->valid_body();
		$body['subject'] = '<b></b><script>alert(1)</script>';

		$request  = $this->create_request( $body );
		$response = $this->server->dispatch( $request );

		// sanitize_text_field strips all tags, leaving an empty string.
		// The validate_callback rejects empty strings, returning 400.
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Safe HTML tags (h1, p, strong, a, table) must survive wp_kses_post.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_handle_send_email
	 * @return void
	 */
	public function test_html_content_preserved(): void {

		$safe_html = '<h1>Title</h1><p>Paragraph with <strong>bold</strong> and <a href="https://example.com">link</a>.</p><table><tr><td>Cell</td></tr></table>';

		$body            = $this->valid_body();
		$body['message'] = $safe_html;

		$request  = $this->create_request( $body );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $this->mail_sent );

		// Verify the mail body still contains the safe tags.
		$this->assertStringContainsString( '<h1>', $this->last_mail['message'] );
		$this->assertStringContainsString( '<strong>', $this->last_mail['message'] );
		$this->assertStringContainsString( '<a href=', $this->last_mail['message'] );
		$this->assertStringContainsString( '<table>', $this->last_mail['message'] );
	}

	/**
	 * Dangerous HTML (<script>, onclick) must be stripped by wp_kses_post.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_handle_send_email
	 * @return void
	 */
	public function test_dangerous_html_stripped(): void {

		$dirty_html = '<p onclick="alert(1)">Hello</p><script>alert("xss")</script><p>Safe</p>';

		$body            = $this->valid_body();
		$body['message'] = $dirty_html;

		$request  = $this->create_request( $body );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $this->mail_sent );

		// Dangerous elements must not be present in the delivered body.
		$this->assertStringNotContainsString( '<script>', $this->last_mail['message'] );
		$this->assertStringNotContainsString( 'onclick', $this->last_mail['message'] );

		// Safe content survives.
		$this->assertStringContainsString( 'Safe', $this->last_mail['message'] );
	}

	/**
	 * Optional fields (from, sender_name, reply_to) are accepted without error.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_handle_send_email
	 * @return void
	 */
	public function test_optional_fields_accepted(): void {

		$body                = $this->valid_body();
		$body['from']        = 'sender@example.com';
		$body['sender_name'] = 'Test Sender';
		$body['reply_to']    = 'reply@example.com';

		$request  = $this->create_request( $body );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $this->mail_sent );

		// Verify headers contain the custom From and Reply-To values.
		$headers_string = implode( "\n", $this->last_mail['headers'] );
		$this->assertStringContainsString( 'From: Test Sender <sender@example.com>', $headers_string );
		$this->assertStringContainsString( 'Reply-To: reply@example.com', $headers_string );
	}

	/**
	 * Every response body must contain 'status' and 'message' keys.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_handle_send_email
	 * @return void
	 */
	public function test_response_format(): void {

		// Successful request.
		$request  = $this->create_request( $this->valid_body() );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'status', $data, 'Response must include a "status" key.' );
		$this->assertArrayHasKey( 'message', $data, 'Response must include a "message" key.' );

		// Error request (invalid recipient).
		$bad_body       = $this->valid_body();
		$bad_body['to'] = 'invalid';

		$request  = $this->create_request( $bad_body );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'status', $data, 'Error response must include a "status" key.' );
		$this->assertArrayHasKey( 'message', $data, 'Error response must include a "message" key.' );
	}

	/**
	 * Comma-separated email addresses in "to" are split and all receive mail.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_handle_send_email
	 * @covers ::mrm_parse_recipients
	 * @return void
	 */
	public function test_multiple_recipients(): void {

		$body       = $this->valid_body();
		$body['to'] = 'alice@example.com, bob@example.com, carol@example.com';

		$request  = $this->create_request( $body );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $this->mail_sent );

		// wp_mail receives all three addresses.
		$this->assertIsArray( $this->last_mail['to'] );
		$this->assertCount( 3, $this->last_mail['to'] );
		$this->assertContains( 'alice@example.com', $this->last_mail['to'] );
		$this->assertContains( 'bob@example.com', $this->last_mail['to'] );
		$this->assertContains( 'carol@example.com', $this->last_mail['to'] );

		// Success message references the recipients.
		$this->assertStringContainsString( 'alice@example.com', $data['message'] );
	}

	/**
	 * Exceeding the configured rate limit returns 429 Too Many Requests.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_check_rate_limit
	 * @covers ::mrm_increment_rate_counter
	 * @return void
	 */
	public function test_rate_limit_exceeded(): void {

		// Set rate limit to 2 emails per minute.
		update_option( MRM_OPTION_NAME, array_merge( mrm_get_defaults(), array(
			'require_api_key' => '0',
			'rate_limit'      => 2,
		) ) );

		// Send two emails (both should succeed).
		for ( $i = 0; $i < 2; $i++ ) {
			$request  = $this->create_request( $this->valid_body() );
			$response = $this->server->dispatch( $request );
			$this->assertSame( 200, $response->get_status(), "Email #{$i} should succeed." );
		}

		// Third email should be rate-limited.
		$request  = $this->create_request( $this->valid_body() );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 429, $response->get_status(), 'Third email must be rejected with 429.' );
	}
}
