<?php
/**
 * Class Google\Site_Kit\Plugin
 *
 * @package   Google\Site_Kit
 * @copyright 2019 Google LLC
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link      https://sitekit.withgoogle.com
 */

namespace Google\Site_Kit;

/**
 * Main class for the plugin.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * The plugin context object.
	 *
	 * @since 1.0.0
	 * @var Context
	 */
	private $context;

	/**
	 * Main instance of the plugin.
	 *
	 * @since 1.0.0
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Sets the plugin main file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $main_file Absolute path to the plugin main file.
	 */
	public function __construct( $main_file ) {
		$this->context = new Context( $main_file );
	}

	/**
	 * Retrieves the plugin context object.
	 *
	 * @since 1.0.0
	 *
	 * @return Context Plugin context.
	 */
	public function context() {
		return $this->context;
	}

	/**
	 * Registers the plugin with WordPress.
	 *
	 * @since 1.0.0
	 */
	public function register() {
		if ( $this->context->is_network_active() ) {
			add_action(
				'network_admin_notices',
				function() {
					?>
					<div class="notice notice-warning">
						<p>
							<?php
							echo wp_kses(
								__( 'The Site Kit by Google plugin is <strong>not yet compatible</strong> for use in a WordPress multisite network, but we&#8217;re actively working on that.', 'google-site-kit' ),
								array(
									'strong' => array(),
								)
							);
							?>
						</p>
						<p>
							<?php esc_html_e( 'Meanwhile, we recommend deactivating it in the network and re-activating it for an individual site.', 'google-site-kit' ); ?>
						</p>
					</div>
					<?php
				}
			);
			return;
		}

		$display_site_kit_meta = function() {
			printf( '<meta name="generator" content="Site Kit by Google %s" />', esc_attr( GOOGLESITEKIT_VERSION ) );
		};
		add_action( 'wp_head', $display_site_kit_meta );
		add_action( 'login_head', $display_site_kit_meta );

		$options    = new Core\Storage\Options( $this->context );
		$transients = new Core\Storage\Transients( $this->context );
		$assets     = new Core\Assets\Assets( $this->context );
		$assets->register();

		// Initiate the plugin on 'init' for relying on current user being set.
		add_action(
			'init',
			function() use ( $options, $transients, $assets ) {
				$user_options = new Core\Storage\User_Options( $this->context, get_current_user_id() );

				$authentication = new Core\Authentication\Authentication( $this->context, $options, $user_options, $transients );
				$authentication->register();

				$modules = new Core\Modules\Modules( $this->context, $options, $user_options, $authentication );
				$modules->register();

				$permissions = new Core\Permissions\Permissions( $this->context, $authentication );
				$permissions->register();

				$tracking = new Core\Util\Tracking( $this->context, $authentication );
				$tracking->register();

				$rest_routes = new Core\REST_API\REST_Routes( $this->context, $authentication, $modules );
				$rest_routes->register();

				( new Core\Admin_Bar\Admin_Bar( $this->context, $assets ) )->register();
				( new Core\Admin\Screens( $this->context, $assets ) )->register();
				( new Core\Admin\Notices() )->register();
				( new Core\Admin\Dashboard( $this->context, $assets ) )->register();

				// If a login is happening (runs after 'init'), update current user in dependency chain.
				add_action(
					'wp_login',
					function( $username, $user ) use ( $user_options ) {
						$user_options->switch_user( $user->ID );
					},
					-999,
					2
				);

				/**
				 * Fires when Site Kit has fully initialized.
				 *
				 * @since 1.0.0
				 */
				do_action( 'googlesitekit_init' );
			},
			-999
		);

		$reset = new Core\Util\Reset( $this->context, $options );

		( new Core\Util\Activation( $this->context, $options, $assets ) )->register();
		( new Core\Util\Beta_Migration( $this->context ) )->register();
		( new Core\Util\Uninstallation( $reset ) )->register();

		if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
			add_filter(
				'debug_bar_panels',
				function( $panels ) {
					$panels[] = new Core\Util\Debug_Bar();
					return $panels;
				}
			);
		}
	}

	/**
	 * Retrieves the main instance of the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return Plugin Plugin main instance.
	 */
	public static function instance() {
		return static::$instance;
	}

	/**
	 * Loads the plugin main instance and initializes it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $main_file Absolute path to the plugin main file.
	 * @return bool True if the plugin main instance could be loaded, false otherwise.
	 */
	public static function load( $main_file ) {
		if ( null !== static::$instance ) {
			return false;
		}

		static::$instance = new static( $main_file );
		static::$instance->register();

		return true;
	}
}
