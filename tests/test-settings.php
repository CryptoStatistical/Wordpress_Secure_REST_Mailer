<?php
/**
 * Tests for the plugin settings page, option defaults, and option sanitization.
 *
 * Covers default values, sanitize callback behaviour, admin page access
 * control, and the mrm_get_option() fallback mechanism.
 *
 * @package My_REST_Mailer
 * @since   2.1.0
 */

/**
 * Class Test_Settings
 *
 * @since 2.1.0
 */
class Test_Settings extends WP_UnitTestCase {

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

		delete_option( MRM_OPTION_NAME );
		wp_set_current_user( 0 );
	}

	/**
	 * Tear down test fixtures.
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function tear_down(): void {

		delete_option( MRM_OPTION_NAME );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/* -----------------------------------------------------------------
	 * Tests
	 * -------------------------------------------------------------- */

	/**
	 * mrm_get_defaults() must return all expected keys with sensible defaults.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_get_defaults
	 * @return void
	 */
	public function test_default_options_exist(): void {

		$defaults = mrm_get_defaults();

		$this->assertIsArray( $defaults );

		// Every expected key must be present.
		$expected_keys = array(
			'api_key',
			'require_api_key',
			'from_email',
			'from_name',
			'reply_to',
			'rate_limit',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $defaults, "Default options must include the '{$key}' key." );
		}

		// Verify specific default values.
		$this->assertSame( '', $defaults['api_key'], 'Default api_key must be empty.' );
		$this->assertSame( '1', $defaults['require_api_key'], 'Default require_api_key must be "1" (enabled).' );
		$this->assertSame( '', $defaults['from_email'], 'Default from_email must be empty.' );
		$this->assertSame( '', $defaults['from_name'], 'Default from_name must be empty.' );
		$this->assertSame( '', $defaults['reply_to'], 'Default reply_to must be empty.' );
		$this->assertSame( 10, $defaults['rate_limit'], 'Default rate_limit must be 10.' );
	}

	/**
	 * mrm_sanitize_options() must clean dirty input values.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_sanitize_options
	 * @return void
	 */
	public function test_options_sanitization(): void {

		$dirty_input = array(
			'api_key'         => '  my-key<script>alert(1)</script>  ',
			'require_api_key' => '1',
			'from_email'      => '  admin<>@example.com  ',
			'from_name'       => '<b>Admin</b> Name',
			'reply_to'        => 'reply@example.com',
			'rate_limit'      => '-5',
		);

		$clean = mrm_sanitize_options( $dirty_input );

		// api_key: sanitize_text_field strips tags and extra whitespace.
		$this->assertStringNotContainsString( '<script>', $clean['api_key'] );
		$this->assertStringNotContainsString( '  ', $clean['api_key'] );

		// require_api_key: non-empty → '1'.
		$this->assertSame( '1', $clean['require_api_key'] );

		// from_email: sanitize_email removes angle brackets and spaces.
		$this->assertStringNotContainsString( '<', $clean['from_email'] );
		$this->assertStringNotContainsString( '>', $clean['from_email'] );

		// from_name: sanitize_text_field strips HTML.
		$this->assertStringNotContainsString( '<b>', $clean['from_name'] );
		$this->assertStringContainsString( 'Admin', $clean['from_name'] );

		// reply_to: valid email passes through.
		$this->assertSame( 'reply@example.com', $clean['reply_to'] );

		// rate_limit: absint(-5) = 5, but minimum is 1 (clamped).
		// Actually absint('-5') returns 5, and 5 >= 1, so it stays 5.
		$this->assertGreaterThanOrEqual( 1, $clean['rate_limit'] );
		$this->assertIsInt( $clean['rate_limit'] );

		// Test rate_limit = 0 triggers the minimum clamp.
		$zero_input = array( 'rate_limit' => '0' );
		$zero_clean = mrm_sanitize_options( $zero_input );
		$this->assertSame( 1, $zero_clean['rate_limit'], 'rate_limit of 0 must be clamped to 1.' );
	}

	/**
	 * The settings page must be accessible to an admin (manage_options capability).
	 *
	 * The render function checks current_user_can('manage_options') and outputs
	 * the page when the capability is present.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_render_settings_page
	 * @return void
	 */
	public function test_settings_page_accessible_by_admin(): void {

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->assertTrue(
			current_user_can( 'manage_options' ),
			'Admin must have manage_options capability.'
		);

		// Capture the output of the render function.
		ob_start();
		mrm_render_settings_page();
		$output = ob_get_clean();

		// The function outputs the settings page markup for admins.
		$this->assertNotEmpty( $output, 'Settings page must produce output for admin users.' );
		$this->assertStringContainsString( 'REST Mailer', $output );
	}

	/**
	 * The settings page must produce no output for non-admin users.
	 *
	 * mrm_render_settings_page() returns early when current_user_can('manage_options') is false.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_render_settings_page
	 * @return void
	 */
	public function test_settings_page_forbidden_for_non_admin(): void {

		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse(
			current_user_can( 'manage_options' ),
			'Subscriber must not have manage_options capability.'
		);

		ob_start();
		mrm_render_settings_page();
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'Settings page must produce no output for non-admin users.' );
	}

	/**
	 * mrm_get_option() returns the default value when the option row is missing
	 * from the database entirely.
	 *
	 * @since 2.1.0
	 * @covers ::mrm_get_option
	 * @return void
	 */
	public function test_get_option_returns_default_when_missing(): void {

		// Ensure the option does not exist in the database.
		delete_option( MRM_OPTION_NAME );

		$defaults = mrm_get_defaults();

		// Every key should return its default.
		foreach ( $defaults as $key => $expected ) {
			$actual = mrm_get_option( $key );
			$this->assertSame(
				$expected,
				$actual,
				"mrm_get_option('{$key}') must return the default when no option is stored."
			);
		}

		// A completely unknown key should return empty string.
		$this->assertSame(
			'',
			mrm_get_option( 'nonexistent_key' ),
			'mrm_get_option() must return empty string for unknown keys.'
		);
	}
}
