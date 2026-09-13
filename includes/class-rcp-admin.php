<?php

defined( 'ABSPATH' ) || exit;

final class RCP_Admin {
	private static ?array $opened = null;

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_post_rcp_export_csv', array( __CLASS__, 'export_csv' ) );
	}

	public static function menu(): void {
		add_menu_page( 'Reino Class', 'Reino Class', 'rcp_manage_attendance', 'reino-class', array( __CLASS__, 'render' ), 'dashicons-welcome-learn-more', 25 );
	}

	public static function assets( string $hook ): void {
		if ( 'toplevel_page_reino-class' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'rcp-admin', RCP_URL . 'assets/admin.css', array(), RCP_VERSION );
	}

	public static function handle_actions(): void {
		if ( ! current_user_can( 'rcp_manage_attendance' ) || empty( $_POST['rcp_action'] ) ) {
			return;
		}

		check_admin_referer( 'rcp_admin_action', 'rcp_nonce' );
		$action = sanitize_key( wp_unslash( $_POST['rcp_action'] ) );
		$id     = absint( $_POST['session_id'] ?? 0 );

		if ( 'create' === $action ) {
			$result = RCP_Attendance::create_session( wp_unslash( $_POST ) );
			self::notice_from_result( $result, 'Aula criada.' );
		} elseif ( 'open' === $action ) {
			$result = RCP_Attendance::open_session( $id, absint( $_POST['duration'] ?? 20 ) );
			if ( is_wp_error( $result ) ) {
				self::notice_from_result( $result, '' );
			} else {
				self::$opened = array_merge( $result, array( 'session_id' => $id ) );
			}
		} elseif ( 'close' === $action ) {
			RCP_Attendance::close_session( $id );
			add_settings_error( 'rcp', 'closed', 'Chamada encerrada.', 'success' );
		}
	}

	private static function notice_from_result( mixed $result, string $success ): void {
		if ( is_wp_error( $result ) ) {
			add_settings_error( 'rcp', $result->get_error_code(), $result->get_error_message(), 'error' );
		} else {
			add_settings_error( 'rcp', 'success', $success, 'success' );
		}
	}

	public static function render(): void {
		if ( ! current_user_can( 'rcp_manage_attendance' ) ) {
			wp_die( esc_html__( 'Acesso negado.', 'reino-class-presenca' ) );
		}

		$sessions = RCP_Attendance::get_sessions();
		$courses  = get_posts( array( 'post_type' => 'courses', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		?>
		<div class="wrap rcp-admin">
			<h1>Presenças do Reino Class</h1>
			<?php settings_errors( 'rcp' ); ?>
			<?php self::render_opened_card(); ?>

			<section class="rcp-card">
				<h2>Nova aula</h2>
				<form method="post">
					<?php wp_nonce_field( 'rcp_admin_action', 'rcp_nonce' ); ?>
					<input type="hidden" name="rcp_action" value="create">
					<div class="rcp-grid">
						<label>Título da aula<input required type="text" name="title" maxlength="190" placeholder="Ex.: Aula 1 - Introdução"></label>
						<label>Data<input required type="date" name="session_date" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"></label>
						<label>Curso<select name="course_id"><option value="0">Sem curso vinculado</option><?php foreach ( $courses as $course ) : ?><option value="<?php echo esc_attr( $course->ID ); ?>"><?php echo esc_html( $course->post_title ); ?></option><?php endforeach; ?></select></label>
					</div>
					<?php submit_button( 'Criar aula', 'primary', 'submit', false ); ?>
				</form>
			</section>

			<section class="rcp-card">
				<h2>Aulas</h2>
				<div class="rcp-table-wrap"><table class="widefat striped">
					<thead><tr><th>Data</th><th>Aula</th><th>Status</th><th>Presentes</th><th>Ações</th></tr></thead>
					<tbody>
					<?php if ( ! $sessions ) : ?><tr><td colspan="5">Nenhuma aula cadastrada.</td></tr><?php endif; ?>
					<?php foreach ( $sessions as $session ) : $records = RCP_Attendance::get_records( (int) $session->id ); ?>
						<tr>
							<td><?php echo esc_html( RCP_Attendance::format_local( 'd/m/Y', $session->session_date . ' 00:00:00' ) ); ?></td>
							<td><strong><?php echo esc_html( $session->title ); ?></strong></td>
							<td><span class="rcp-status rcp-status-<?php echo esc_attr( $session->status ); ?>"><?php echo esc_html( self::status_label( $session->status ) ); ?></span></td>
							<td><?php echo esc_html( count( $records ) ); ?></td>
							<td class="rcp-actions">
								<?php if ( 'open' !== $session->status ) : ?>
				<form method="post"><?php wp_nonce_field( 'rcp_admin_action', 'rcp_nonce' ); ?><input type="hidden" name="rcp_action" value="open"><input type="hidden" name="session_id" value="<?php echo esc_attr( $session->id ); ?>"><input aria-label="Duração em minutos" type="number" name="duration" value="20" min="5" max="180"><button class="button button-primary">Abrir chamada</button></form>
								<?php else : ?>
								<form method="post"><?php wp_nonce_field( 'rcp_admin_action', 'rcp_nonce' ); ?><input type="hidden" name="rcp_action" value="close"><input type="hidden" name="session_id" value="<?php echo esc_attr( $session->id ); ?>"><button class="button">Encerrar</button></form>
								<?php endif; ?>
								<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'reino-class', 'view' => $session->id ), admin_url( 'admin.php' ) ) ); ?>">Relatorio</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
			</section>
			<?php self::render_report(); ?>
		</div>
		<?php
	}

	private static function render_opened_card(): void {
		if ( ! self::$opened ) {
			return;
		}
		$page_id = (int) get_option( 'rcp_attendance_page_id' );
		$url     = add_query_arg( array( 'session' => self::$opened['session_id'], 'token' => self::$opened['token'] ), get_permalink( $page_id ) );
		$qr_url  = 'https://quickchart.io/qr?size=320&margin=2&text=' . rawurlencode( $url );
		?>
		<section class="rcp-card rcp-call-card">
			<div><h2>Chamada aberta</h2><p>Código para os alunos:</p><div class="rcp-code"><?php echo esc_html( self::$opened['code'] ); ?></div><p>Aberta até <?php echo esc_html( RCP_Attendance::format_local( 'H:i', self::$opened['closes'] ) ); ?>.</p><p><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">Abrir link de confirmação</a></p></div>
			<div><img class="rcp-qr" src="<?php echo esc_url( $qr_url ); ?>" width="320" height="320" alt="QR Code da chamada"><p class="description">Se o QR não carregar, os alunos podem usar o código.</p></div>
		</section>
		<?php
	}

	private static function render_report(): void {
		$id = absint( $_GET['view'] ?? 0 );
		if ( ! $id ) {
			return;
		}
		$session = RCP_Attendance::get_session( $id );
		if ( ! $session ) {
			return;
		}
		$records = RCP_Attendance::get_records( $id );
		?>
		<section class="rcp-card"><h2>Relatorio: <?php echo esc_html( $session->title ); ?></h2>
		<?php if ( current_user_can( 'rcp_view_attendance_reports' ) ) : ?><p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rcp_export_csv&session_id=' . $id ), 'rcp_export_' . $id ) ); ?>">Exportar CSV</a></p><?php endif; ?>
		<div class="rcp-table-wrap"><table class="widefat striped"><thead><tr><th>Aluno</th><th>E-mail</th><th>Confirmado em</th><th>Metodo</th></tr></thead><tbody>
		<?php if ( ! $records ) : ?><tr><td colspan="4">Nenhuma presenca confirmada.</td></tr><?php endif; ?>
		<?php foreach ( $records as $record ) : ?><tr><td><?php echo esc_html( $record->display_name ); ?></td><td><?php echo esc_html( $record->user_email ); ?></td><td><?php echo esc_html( RCP_Attendance::format_local( 'd/m/Y H:i', $record->confirmed_at ) ); ?></td><td><?php echo esc_html( 'qr' === $record->method ? 'QR Code' : 'Código' ); ?></td></tr><?php endforeach; ?>
		</tbody></table></div></section>
		<?php
	}

	public static function export_csv(): void {
		if ( ! current_user_can( 'rcp_view_attendance_reports' ) ) {
			wp_die( 'Acesso negado.' );
		}
		$id = absint( $_GET['session_id'] ?? 0 );
		check_admin_referer( 'rcp_export_' . $id );
		$session = RCP_Attendance::get_session( $id );
		if ( ! $session ) {
			wp_die( 'Aula não encontrada.' );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="presencas-' . sanitize_file_name( $session->session_date . '-' . $session->title ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'Aluno', 'E-mail', 'Data e hora', 'Método' ), ';' );
		foreach ( RCP_Attendance::get_records( $id ) as $record ) {
			fputcsv( $out, array( $record->display_name, $record->user_email, RCP_Attendance::format_local( 'd/m/Y H:i', $record->confirmed_at ), $record->method ), ';' );
		}
		fclose( $out );
		exit;
	}

	private static function status_label( string $status ): string {
		return array( 'scheduled' => 'Agendada', 'open' => 'Aberta', 'closed' => 'Encerrada' )[ $status ] ?? ucfirst( $status );
	}
}
