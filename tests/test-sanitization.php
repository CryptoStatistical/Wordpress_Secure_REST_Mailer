<?php
/**
 * Tests for input sanitization throughout the plugin.
 *
 * Covers email address sanitization, subject line tag stripping,
 * safe HTML allowlisting in message bodies, dangerous HTML removal,
 * and XSS prevention in the subject field.
 *
 * @package My_REST_Mailer
 * @since   2.1.0
 */

/**
 * Class Test_Sanitization
 *
 * @since 2.1.0
 */
class Test_Sanitization extends WP_UnitTestCase {

	/**
	 * REST API server instance.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	private int $editor_id;

	/**
	 * Last mail payload captured by the pre_wp_mail filter.
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

		global $wp_rest_server;
		$this->server = rest_get_server();

		$this->editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $this->editor_id );

		// Disable API key requirement.
		update_option( MRM_OPTION_NAME, array_merge( mrm_get_defaults(), array(
			'require_api_key' => '0',
		) ) );

		delete_transient( MRM_RATE_TRANSIENT );
		$this->last_mail = null;

		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	/**
	 * Tear down test fixtures.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function tear_down(): void {

		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		delete_option( MRM_OPTION_NAME );
		delete_option( MRM_LOG_OPTION );
		delete_transient( MRM_RATE_TRANSIENT );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Capture the mail payload and short-circuit wp_mail.
	 *
	 * @since 2.1.0
	 * @param null|bool $result Short-circuit result.
	 * @param array     $atts   Mail attributes.
	 * @return bool
	 */
	public function capture_mail( $result, array $atts ): bool {

		$this->last_mail = $atts;
		return true;
	}

	/* -----------------------------------------------------------------
	 * Helpers
	 * -------------------------------------------------------------- */

	/**
	 * Build a POST request for the send-email endpoint.
	 *
	 * @since 2.1.0
	 * @param array $body JSON body parameters.
	 * @return WP_REST_Request
	 */
	private function create_request( array $body ): WP_REST_Request {

		$request = new WP_REST_Request( 'POST', '/custom/v1/send-email' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );

		return $request;
	}

	/* -----------------------------------------------------------------
	 * Tests
	 * -------------------------------------------------------------- */

	/**
	 * Dirty characters in the "to" email address must be cleaned so that
	 * only a valid email remains after sanitize_email + is_email.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_parse_recipients
	 * @return void
	 */
	public function test_email_sanitization(): void {

		$body = array(
			'to'      => '  <user>@example.com  ',
			'subject' => 'Email Sanitization Test',
			'message' => '<p>Body</p>',
		);

		$request  = $this->create_request( $body );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		// sanitize_email strips angle brackets and whitespace.
		$delivered_to = $this->last_mail['to'];
		$this->assertIsArray( $delivered_to );

		foreach ( $delivered_to as $email ) {
			$this->assertStringNotContainsString( '<', $email );
			$this->assertStringNotContainsString( '>', $email );
			$this->assertSame( trim( $email ), $email, 'Email must have no leading/trailing whitespace.' );
			$this->assertNotFalse( is_email( $email ), 'Delivered email must be a valid address.' );
		}
	}

	/**
	 * HTML tags inside the subject line must be stripped by sanitize_text_field.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_get_endpoint_args
	 * @return void
	 */
	public function test_subject_sanitization(): void {

		$body = array(
			'to'      => 'test@example.com',
			'subject' => '<b>Bold Subject</b> with <em>emphasis</em>',
			'message' => '<p>Body</p>',
		);

		$request  = $this->create_request( $body );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		// sanitize_text_field strips all HTML.
		$delivered_subject = $this->last_mail['subject'];
		$this->assertStringNotContainsString( '<b>', $delivered_subject );
		$this->assertStringNotContainsString( '<em>', $delivered_subject );
		$this->assertStringContainsString( 'Bold Subject', $delivered_subject );
		$this->assertStringContainsString( 'emphasis', $delivered_subject );
	}

	/**
	 * Safe HTML elements allowed by wp_kses_post must survive in the message body.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_handle_send_email
	 * @return void
	 */
	public function test_message_allows_safe_html(): void {

		$safe_html = '<h1>Heading</h1>'
			. '<p>A paragraph with <strong>bold</strong> text.</p>'
			. '<table><thead><tr><th>Header</th></tr></thead><tbody><tr><td>Cell</td></tr></tbody></table>'
			. '<a href="https://example.com" title="Example">Link</a>';

		$body = array(
			'to'      => 'test@example.com',
			'subject' => 'Safe HTML Test',
			'message' => $safe_html,
		);

		$request  = $this->create_request( $body );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$delivered_message = $this->last_mail['message'];

		$this->assertStringContainsString( '<h1>', $delivered_message );
		$this->assertStringContainsString( '<p>', $delivered_message );
		$this->assertStringContainsString( '<strong>', $delivered_message );
		$this->assertStringContainsString( '<table>', $delivered_message );
		$this->assertStringContainsString( '<a href=', $delivered_message );
	}

	/**
	 * Dangerous HTML elements (script, iframe, onerror) must be removed by wp_kses_post.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_handle_send_email
	 * @return void
	 */
	public function test_message_strips_dangerous_html(): void {

		$dangerous_html = '<p>Hello</p>'
			. '<script>document.cookie</script>'
			. '<iframe src="https://evil.com"></iframe>'
			. '<img src="x" onerror="alert(1)" />'
			. '<p>Safe paragraph.</p>';

		$body = array(
			'to'      => 'test@example.com',
			'subject' => 'Dangerous HTML Test',
			'message' => $dangerous_html,
		);

		$request  = $this->create_request( $body );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$delivered_message = $this->last_mail['message'];

		$this->assertStringNotContainsString( '<script>', $delivered_message );
		$this->assertStringNotContainsString( '<iframe', $delivered_message );
		$this->assertStringNotContainsString( 'onerror', $delivered_message );

		// Safe content survives.
		$this->assertStringContainsString( 'Safe paragraph.', $delivered_message );
	}

	/**
	 * XSS payloads in the subject field must be neutralized by sanitize_text_field.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_get_endpoint_args
	 * @return void
	 */
	public function test_xss_in_subject_prevented(): void {

		$xss_subjects = array(
			'<script>alert("xss")</script>Normal Subject'         => 'alert("xss")Normal Subject',
			'<img src=x onerror=alert(1)>Subject'                 => 'Subject',
			'"><svg/onload=alert(1)>'                              => '">',
			"Subject with\x00null bytes"                           => 'Subject withnull bytes',
		);

		foreach ( $xss_subjects as $malicious => $expected_contains ) {

			$body = array(
				'to'      => 'test@example.com',
				'subject' => $malicious,
				'message' => '<p>Body</p>',
			);

			$request  = $this->create_request( $body );
			$response = $this->server->dispatch( $request );

			// Some subjects may be fully stripped to empty, causing a 400.
			if ( 200 === $response->get_status() ) {
				$this->assertStringNotContainsString( '<script>', $this->last_mail['subject'] );
				$this->assertStringNotContainsString( 'onerror', $this->last_mail['subject'] );
				$this->assertStringNotContainsString( 'onload', $this->last_mail['subject'] );
				$this->assertStringNotContainsString( "\x00", $this->last_mail['subject'] );
			} else {
				// 400 is also acceptable if sanitisation leaves the subject empty.
				$this->assertSame( 400, $response->get_status() );
			}

			// Reset rate limiter between iterations.
			delete_transient( MRM_RATE_TRANSIENT );
		}
	}
}
