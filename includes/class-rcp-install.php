<?php

defined( 'ABSPATH' ) || exit;

final class RCP_Install {
	public static function activate(): void {
		self::create_tables();
		self::add_capabilities();
		self::create_attendance_page();
		update_option( 'rcp_db_version', RCP_VERSION );
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	private static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$sessions = $wpdb->prefix . 'rcp_sessions';
		$records  = $wpdb->prefix . 'rcp_attendance';

		$sql_sessions = "CREATE TABLE {$sessions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(190) NOT NULL,
			session_date date NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'scheduled',
			opened_at datetime NULL,
			closes_at datetime NULL,
			code_hash varchar(255) NULL,
			token_hash varchar(64) NULL,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY course_id (course_id),
			KEY session_date (session_date),
			KEY status (status)
		) {$charset};";

		$sql_records = "CREATE TABLE {$records} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			confirmed_at datetime NOT NULL,
			method varchar(20) NOT NULL,
			ip_hash varchar(64) NULL,
			user_agent_hash varchar(64) NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_user (session_id,user_id),
			KEY user_id (user_id),
			KEY confirmed_at (confirmed_at)
		) {$charset};";

		dbDelta( $sql_sessions );
		dbDelta( $sql_records );
	}

	private static function add_capabilities(): void {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( 'rcp_manage_attendance' );
			$administrator->add_cap( 'rcp_view_attendance_reports' );
		}

		$instructor = get_role( 'tutor_instructor' );
		if ( $instructor ) {
			$instructor->add_cap( 'rcp_manage_attendance' );
		}
	}

	private static function create_attendance_page(): void {
		$page_id = (int) get_option( 'rcp_attendance_page_id' );
		if ( $page_id && get_post( $page_id ) ) {
			return;
		}

		$existing = get_page_by_path( 'confirmar-presenca' );
		if ( $existing ) {
			update_option( 'rcp_attendance_page_id', (int) $existing->ID );
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'     => 'Confirmar presença',
				'post_name'      => 'confirmar-presenca',
				'post_content'   => '[reino_class_presenca]',
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'comment_status' => 'closed',
			),
			true
		);

		if ( ! is_wp_error( $page_id ) ) {
			update_option( 'rcp_attendance_page_id', (int) $page_id );
		}
	}
}
