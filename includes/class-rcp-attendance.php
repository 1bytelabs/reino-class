<?php

defined( 'ABSPATH' ) || exit;

final class RCP_Attendance {
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'close_expired_sessions' ) );
	}

	public static function sessions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'rcp_sessions';
	}

	public static function records_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'rcp_attendance';
	}

	public static function now(): string {
		return current_time( 'mysql' );
	}

	public static function format_local( string $format, string $mysql ): string {
		if ( '' === $mysql ) {
			return '';
		}
		$date = new DateTimeImmutable( $mysql, wp_timezone() );
		return wp_date( $format, $date->getTimestamp(), wp_timezone() );
	}

	public static function create_session( array $data ): int|WP_Error {
		global $wpdb;

		$title = sanitize_text_field( $data['title'] ?? '' );
		$date  = sanitize_text_field( $data['session_date'] ?? '' );
		if ( '' === $title || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new WP_Error( 'invalid_session', 'Informe um título e uma data válidos.' );
		}

		$now = self::now();
		$ok  = $wpdb->insert(
			self::sessions_table(),
			array(
				'course_id'   => absint( $data['course_id'] ?? 0 ),
				'title'       => $title,
				'session_date'=> $date,
				'status'      => 'scheduled',
				'created_by'  => get_current_user_id(),
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : new WP_Error( 'db_error', 'Não foi possível criar a aula.' );
	}

	public static function open_session( int $session_id, int $minutes = 20 ): array|WP_Error {
		global $wpdb;
		$session = self::get_session( $session_id );
		if ( ! $session ) {
			return new WP_Error( 'not_found', 'Aula não encontrada.' );
		}

		$minutes = max( 5, min( 180, $minutes ) );
		$code    = (string) random_int( 100000, 999999 );
		$token   = bin2hex( random_bytes( 24 ) );
		$opened  = current_datetime();
		$closes  = $opened->modify( "+{$minutes} minutes" );

		$ok = $wpdb->update(
			self::sessions_table(),
			array(
				'status'     => 'open',
				'opened_at'  => $opened->format( 'Y-m-d H:i:s' ),
				'closes_at'  => $closes->format( 'Y-m-d H:i:s' ),
				'code_hash'  => wp_hash_password( $code ),
				'token_hash' => hash( 'sha256', $token ),
				'updated_at' => self::now(),
			),
			array( 'id' => $session_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'db_error', 'Não foi possível abrir a chamada.' );
		}

		return array(
			'code'   => $code,
			'token'  => $token,
			'closes' => $closes->format( 'Y-m-d H:i:s' ),
		);
	}

	public static function close_session( int $session_id ): bool {
		global $wpdb;
		return false !== $wpdb->update(
			self::sessions_table(),
			array( 'status' => 'closed', 'updated_at' => self::now() ),
			array( 'id' => $session_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public static function get_session( int $session_id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::sessions_table() . ' WHERE id = %d', $session_id ) );
	}

	public static function get_sessions( int $limit = 100 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::sessions_table() . ' ORDER BY session_date DESC, id DESC LIMIT %d', $limit ) );
	}

	public static function get_records( int $session_id ): array {
		global $wpdb;
		$sql = 'SELECT a.*, u.display_name, u.user_email FROM ' . self::records_table() . ' a INNER JOIN ' . $wpdb->users . ' u ON u.ID = a.user_id WHERE a.session_id = %d ORDER BY u.display_name ASC';
		return $wpdb->get_results( $wpdb->prepare( $sql, $session_id ) );
	}

	public static function has_attended( int $session_id, int $user_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::records_table() . ' WHERE session_id = %d AND user_id = %d', $session_id, $user_id ) );
	}

	public static function confirm( int $session_id, int $user_id, string $method, string $secret ): true|WP_Error {
		global $wpdb;
		$session = self::get_session( $session_id );
		if ( ! $session || 'open' !== $session->status ) {
			return new WP_Error( 'closed', 'Esta chamada não está aberta.' );
		}

		$now = current_datetime();
		if ( empty( $session->closes_at ) || $now > new DateTimeImmutable( $session->closes_at, wp_timezone() ) ) {
			self::close_session( $session_id );
			return new WP_Error( 'expired', 'O período de confirmação terminou.' );
		}

		$valid = 'qr' === $method
			? hash_equals( (string) $session->token_hash, hash( 'sha256', $secret ) )
			: wp_check_password( $secret, (string) $session->code_hash );

		if ( ! $valid ) {
			return new WP_Error( 'invalid_code', 'Código inválido.' );
		}

		if ( self::has_attended( $session_id, $user_id ) ) {
			return new WP_Error( 'duplicate', 'Sua presença nesta aula já foi confirmada.' );
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		$ok = $wpdb->insert(
			self::records_table(),
			array(
				'session_id'     => $session_id,
				'user_id'        => $user_id,
				'confirmed_at'   => self::now(),
				'method'         => 'qr' === $method ? 'qr' : 'code',
				'ip_hash'        => $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : null,
				'user_agent_hash'=> $ua ? hash_hmac( 'sha256', $ua, wp_salt( 'auth' ) ) : null,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return $ok ? true : new WP_Error( 'db_error', 'Não foi possível registrar sua presença.' );
	}

	public static function close_expired_sessions(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::sessions_table() . " SET status = 'closed', updated_at = %s WHERE status = 'open' AND closes_at < %s", self::now(), self::now() ) );
	}
}
