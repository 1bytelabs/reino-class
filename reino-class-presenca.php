<?php
/**
 * Plugin Name: Reino Class - Presença
 * Description: Controle de presença presencial para cursos do Tutor LMS.
 * Version: 0.1.0
 * Author: 1Byte Labs
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: reino-class-presenca
 */

defined( 'ABSPATH' ) || exit;

define( 'RCP_VERSION', '0.1.0' );
define( 'RCP_FILE', __FILE__ );
define( 'RCP_PATH', plugin_dir_path( __FILE__ ) );
define( 'RCP_URL', plugin_dir_url( __FILE__ ) );

require_once RCP_PATH . 'includes/class-rcp-install.php';
require_once RCP_PATH . 'includes/class-rcp-attendance.php';
require_once RCP_PATH . 'includes/class-rcp-admin.php';
require_once RCP_PATH . 'includes/class-rcp-public.php';

register_activation_hook( __FILE__, array( 'RCP_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RCP_Install', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		RCP_Attendance::init();
		RCP_Admin::init();
		RCP_Public::init();
	}
);
