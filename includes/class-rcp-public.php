<?php

defined( 'ABSPATH' ) || exit;

final class RCP_Public {
	public static function init(): void {
		add_shortcode( 'reino_class_presenca', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets(): void {
		wp_register_style( 'rcp-public', RCP_URL . 'assets/public.css', array(), RCP_VERSION );
	}

	public static function shortcode(): string {
		wp_enqueue_style( 'rcp-public' );
		if ( ! is_user_logged_in() ) {
			return '<div class="rcp-box"><h2>Confirme sua presença</h2><p>Você precisa entrar na sua conta para confirmar a presença.</p><p><a class="rcp-button" href="' . esc_url( wp_login_url( self::current_url() ) ) . '">Entrar</a></p></div>';
		}

		$message = '';
		$type    = '';
		$session_id = absint( $_REQUEST['session'] ?? 0 );
		$token      = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );

		if ( $session_id && $token ) {
			$result  = RCP_Attendance::confirm( $session_id, get_current_user_id(), 'qr', $token );
			$message = is_wp_error( $result ) ? $result->get_error_message() : 'Presença confirmada com sucesso!';
			$type    = is_wp_error( $result ) && 'duplicate' !== $result->get_error_code() ? 'error' : 'success';
		} elseif ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['rcp_confirm'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rcp_nonce'] ?? '' ) ), 'rcp_confirm_attendance' ) ) {
				$message = 'A página expirou. Atualize e tente novamente.';
				$type = 'error';
			} else {
				$session_id = absint( $_POST['session'] ?? 0 );
				$code       = preg_replace( '/\D+/', '', wp_unslash( $_POST['code'] ?? '' ) );
				$result     = RCP_Attendance::confirm( $session_id, get_current_user_id(), 'code', $code );
				$message    = is_wp_error( $result ) ? $result->get_error_message() : 'Presença confirmada com sucesso!';
				$type       = is_wp_error( $result ) && 'duplicate' !== $result->get_error_code() ? 'error' : 'success';
			}
		}

		$session = $session_id ? RCP_Attendance::get_session( $session_id ) : self::latest_open_session();
		ob_start();
		?>
		<div class="rcp-box">
			<h2>Confirmar presença</h2>
			<?php if ( $message ) : ?><div class="rcp-message rcp-<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $message ); ?></div><?php endif; ?>
			<?php if ( $session && 'open' === $session->status && ! RCP_Attendance::has_attended( (int) $session->id, get_current_user_id() ) ) : ?>
				<p class="rcp-session-title"><?php echo esc_html( $session->title ); ?></p>
				<p>Digite o código exibido pelo professor.</p>
				<form method="post" class="rcp-confirm-form">
					<?php wp_nonce_field( 'rcp_confirm_attendance', 'rcp_nonce' ); ?>
					<input type="hidden" name="session" value="<?php echo esc_attr( $session->id ); ?>">
					<label for="rcp-code">Código da aula</label>
					<input id="rcp-code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
					<button class="rcp-button" type="submit" name="rcp_confirm" value="1">Confirmar presença</button>
				</form>
			<?php elseif ( ! $message ) : ?>
				<p>Não existe uma chamada aberta neste momento.</p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function latest_open_session(): ?object {
		global $wpdb;
		return $wpdb->get_row( "SELECT * FROM " . RCP_Attendance::sessions_table() . " WHERE status = 'open' ORDER BY opened_at DESC LIMIT 1" );
	}

	private static function current_url(): string {
		$page_id = (int) get_option( 'rcp_attendance_page_id' );
		return $page_id ? get_permalink( $page_id ) : home_url( '/' );
	}
}
