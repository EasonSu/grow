<?php
/**
 * The Compatibility Checker parent.
 *
 * @package Automattic/WooCommerce/Grow/Tools
 */

namespace Automattic\WooCommerce\Grow\Tools\CompatChecker\v0_0_1\Checks;

use WC_Admin_Notices;

defined( 'ABSPATH' ) || exit;

/**
 * The CompatCheck class.
 */
abstract class CompatCheck {

	/**
	 * Array of admin notices.
	 *
	 * @var array
	 */
	protected $notices = [];

	/**
	 * Array of CompatCheck instances.
	 *
	 * @var array
	 */
	private static $instances = [];

	/**
	 * The plugin data.
	 *
	 * @var array
	 */
	protected $plugin_data = [];

	/**
	 * Run checks
	 *
	 * @return bool
	 */
	abstract protected function run_checks();

	/**
	 * Get the instance of the CompatCheck object.
	 *
	 * @param string $plugin_basename The basename of the plugin.
	 *
	 * @return CompatCheck
	 */
	public static function instance( $plugin_basename ) {
		$class = get_called_class();
		if ( ! isset( self::$instances[ $class ][ $plugin_basename ] ) ) {
			self::$instances[ $class ][ $plugin_basename ] = new $class();
		}

		return self::$instances[ $class ][ $plugin_basename ];
	}

	/**
	 * Adds an admin notice to be displayed.
	 *
	 * @param string $slug      The slug for the notice.
	 * @param string $css_class The CSS class for the notice.
	 * @param string $message   The notice message.
	 */
	protected function add_admin_notice( $slug, $css_class, $message ) {
		$screen = get_current_screen();
		$hidden = [ 'update', 'update-network', 'update-core', 'update-core-network', 'upgrade', 'upgrade-network', 'network' ];
		$show   = isset( $screen->id ) && ! in_array( $screen->id, $hidden, true );
		$slug   = isset( $this->plugin_data['File'] ) ? plugin_basename( $this->plugin_data['File'] ) . '-' . $slug : $slug;

		/**
		 * The Compat Check filter to show an admin notice.
		 *
		 * @since 0.0.1
		 *
		 * @param bool   $show Whether to show the admin notice.
		 * @param string $slug The slug for the notice.
		 */
		if ( ! apply_filters( 'wc_grow_compat_check_show_admin_notice', $show, $slug ) ) {
			return;
		}

		// If the notice is a warning and WooCommerce admin notice system is available. Then use it.
		if ( str_contains( $css_class, 'warning' ) && class_exists( WC_Admin_Notices::class ) ) {
			// Do not display the notice if it was dismissed.
			if ( ! get_user_meta( get_current_user_id(), 'dismissed_' . $slug . '_notice', true ) ) {
				WC_Admin_Notices::add_custom_notice( $slug, $message );
			}
		} else {
			$this->notices[ $slug ] = [
				'class'   => $css_class,
				'message' => $message,
			];
		}
	}

	/**
	 * Compares major version.
	 *
	 * @param string $left     First version number.
	 * @param string $right    Second version number.
	 * @param string $operator An optional operator. The possible operators are: <, lt, <=, le, >, gt, >=, ge, ==, =, eq, !=, <>, ne respectively.
	 *
	 * @return int|bool
	 */
	protected function compare_major_version( $left, $right, $operator = null ) {
		$pattern = '/^(\d+\.\d+).*/';
		$replace = '$1.0';

		$left  = preg_replace( $pattern, $replace, $left );
		$right = preg_replace( $pattern, $replace, $right );

		return version_compare( $left, $right, $operator );
	}

	/**
	 * Display admin notices generated by the checker.
	 */
	public function display_admin_notices() {
		$allowed_tags = [
			'a'      => [
				'class'  => [],
				'href'   => [],
				'target' => [],
			],
			'strong' => [],
		];

		foreach ( $this->notices as $key => $notice ) {
			$class   = $notice['class'];
			$message = $notice['message'];
			printf(
				'<div class="notice notice-%1$s"><p>%2$s</p></div>',
				esc_attr( $class ),
				wp_kses( $message, $allowed_tags )
			);
		}
	}

	/**
	 * Sets the plugin data.
	 *
	 * @param array $plugin_data The plugin data.
	 */
	protected function set_plugin_data( $plugin_data ) {
		$defaults          = [
			'Name'        => '',
			'Version'     => '',
			'RequiresWP'  => '',
			'RequiresPHP' => '',
			'RequiresWC'  => '',
			'TestedWP'    => '',
			'TestedWC'    => '',
		];
		$this->plugin_data = wp_parse_args( $plugin_data, $defaults );
	}

	/**
	 * Determines if the plugin is WooCommerce compatible.
	 *
	 * @param array $plugin_data The plugin data.
	 *
	 * @return bool
	 */
	public function is_compatible( $plugin_data ) {
		$this->set_plugin_data( $plugin_data );
		add_action( 'admin_notices', [ $this, 'display_admin_notices' ], 20 );
		return $this->run_checks();
	}
}
