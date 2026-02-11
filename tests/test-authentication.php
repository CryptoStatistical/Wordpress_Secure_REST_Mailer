<?php
/**
 * Tests for the dual authentication layer (WordPress credentials + API key).
 *
 * Covers unauthenticated access, insufficient capabilities,
 * API key requirement toggle, missing / wrong / valid API keys,
 * and the edge case where the toggle is on but no key is configured.
 *
 * @package My_REST_Mailer
 * @since   2.1.0
 */

/**
 * Class Test_Authentication
 *
 * @coversDefaultClass \mrm_check_permissions
 * @since 2.1.0
 */
class Test_Authentication extends WP_UnitTestCase {

	/**
	 * REST API server instance.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * Stored API key used during tests.
	 *
	 * @var string
	 */
	private string $test_api_key = 'test-secret-api-key-32chars-long!';

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

		// Reset all users and options to a clean state.
		wp_set_current_user( 0 );
		delete_option( MRM_OPTION_NAME );
		delete_transient( MRM_RATE_TRANSIENT );

		// Intercept wp_mail so no real mail is sent during auth tests.
		add_filter( 'pre_wp_mail', '__return_true', 10, 2 );
	}

	/**
	 * Tear down test fixtures.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function tear_down(): void {

		remove_filter( 'pre_wp_mail', '__return_true', 10 );
		delete_option( MRM_OPTION_NAME );
		delete_option( MRM_LOG_OPTION );
		delete_transient( MRM_RATE_TRANSIENT );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/* -----------------------------------------------------------------
	 * Helpers
	 * -------------------------------------------------------------- */

	/**
	 * Create a standard POST request to the send-email endpoint.
	 *
	 * @since 2.1.0
	 * @param array  $body    JSON body parameters.
	 * @param string $api_key Optional API key to include in the X-API-Key header.
	 * @return WP_REST_Request
	 */
	private function create_request( array $body = array(), string $api_key = '' ): WP_REST_Request {

		$defaults = array(
			'to'      => 'test@example.com',
			'subject' => 'Auth Test',
			'message' => '<p>Testing authentication.</p>',
		);

		$request = new WP_REST_Request( 'POST', '/custom/v1/send-email' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array_merge( $defaults, $body ) ) );

		if ( '' !== $api_key ) {
			$request->set_header( 'X-API-Key', $api_key );
		}

		return $request;
	}

	/**
	 * Configure the plugin options for API key tests.
	 *
	 * @since 2.1.0
	 * @param bool   $require Whether to require the API key.
	 * @param string $key     The stored API key value.
	 * @return void
	 */
	private function set_api_key_options( bool $require, string $key = '' ): void {

		update_option( MRM_OPTION_NAME, array_merge( mrm_get_defaults(), array(
			'require_api_key' => $require ? '1' : '0',
			'api_key'         => $key,
		) ) );
	}

	/* -----------------------------------------------------------------
	 * Tests
	 * -------------------------------------------------------------- */

	/**
	 * A request with no authentication at all must be rejected.
	 *
	 * WordPress returns 401 when no user credentials are provided
	 * because current_user_can('edit_posts') fails for user 0.
	 * However, the permission_callback returns a WP_Error with status 403.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_check_permissions
	 * @return void
	 */
	public function test_unauthenticated_request_rejected(): void {

		wp_set_current_user( 0 );
		$this->set_api_key_options( false );

		$request  = $this->create_request();
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status(), 'Unauthenticated requests must be rejected with 403.' );
	}

	/**
	 * A request authenticated with wrong / nonexistent credentials is rejected.
	 *
	 * Simulated by ensuring no user is logged in (user ID 0).
	 *
	 * @since 2.1.0
	 * @covers ::mrm_check_permissions
	 * @return void
	 */
	public function test_wrong_credentials_rejected(): void {

		wp_set_current_user( 0 );
		$this->set_api_key_options( false );

		$request  = $this->create_request();
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status(), 'Wrong credentials must be rejected.' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertSame( 'rest_forbidden', $data['code'] );
	}

	/**
	 * A valid user WITHOUT edit_posts capability (Subscriber) must be rejected with 403.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_check_permissions
	 * @return void
	 */
	public function test_valid_credentials_without_capability_rejected(): void {

		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );
		$this->set_api_key_options( false );

		$request  = $this->create_request();
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status(), 'Subscriber without edit_posts must be rejected with 403.' );
	}

	/**
	 * A valid user WITH edit_posts capability (Editor) passes the permission callback.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_check_permissions
	 * @return void
	 */
	public function test_valid_credentials_with_capability_accepted(): void {

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		$this->set_api_key_options( false );

		$request  = $this->create_request();
		$response = $this->server->dispatch( $request );

		// 200 means both permission check and handler succeeded.
		$this->assertSame( 200, $response->get_status(), 'Editor with edit_posts must pass the permission check.' );
	}

	/**
	 * When API key is required but the request omits X-API-Key, return 401.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_check_permissions
	 * @return void
	 */
	public function test_missing_api_key_when_required(): void {

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		$this->set_api_key_options( true, $this->test_api_key );

		// Request WITHOUT the X-API-Key header.
		$request  = $this->create_request();
		$response = $this->server->dispatch( $request );

		$this->assertSame( 401, $response->get_status(), 'Missing API key must return 401.' );

		$data = $response->get_data();
		$this->assertSame( 'rest_missing_api_key', $data['code'] );
	}

	/**
	 * When API key is required and the wrong key is sent, return 403.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_check_permissions
	 * @return void
	 */
	public function test_wrong_api_key_rejected(): void {

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		$this->set_api_key_options( true, $this->test_api_key );

		$request  = $this->create_request( array(), 'wrong-key-value' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status(), 'Wrong API key must return 403.' );

		$data = $response->get_data();
		$this->assertSame( 'rest_invalid_api_key', $data['code'] );
	}

	/**
	 * When API key is required and the correct key is sent, the request succeeds.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_check_permissions
	 * @return void
	 */
	public function test_valid_api_key_accepted(): void {

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		$this->set_api_key_options( true, $this->test_api_key );

		$request  = $this->create_request( array(), $this->test_api_key );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Valid API key must allow the request through.' );
	}

	/**
	 * When API key requirement is disabled, the X-API-Key header is not needed.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_check_permissions
	 * @return void
	 */
	public function test_api_key_not_required_when_disabled(): void {

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		$this->set_api_key_options( false, $this->test_api_key );

		// No X-API-Key header sent.
		$request  = $this->create_request();
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Request without API key must succeed when requirement is disabled.' );
	}

	/**
	 * When API key requirement is enabled but no key has been configured
	 * (admin forgot), the endpoint must return 500 to prevent unchecked access.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_check_permissions
	 * @return void
	 */
	public function test_api_key_enabled_but_not_configured(): void {

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		// API key required but the stored key is empty.
		$this->set_api_key_options( true, '' );

		$request  = $this->create_request( array(), 'some-key-from-caller' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 500, $response->get_status(), 'Enabled but unconfigured API key must return 500.' );

		$data = $response->get_data();
		$this->assertSame( 'rest_api_key_not_configured', $data['code'] );
	}
}
