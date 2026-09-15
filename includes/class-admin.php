<?php
/**
 * Tela do administrador. Só `manage_options` vê o menu, abre a tela, salva,
 * restaura padrões ou limpa o registro. A opção não é exposta na REST.
 */

defined( 'ABSPATH' ) || exit;

final class Ary_Block_Admin {

	public const CAPACIDADE = 'manage_options';
	public const PAGINA     = 'ary-block';
	public const NONCE      = 'ary_block_admin';

	public static function iniciar(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ] );
		add_action( 'admin_post_ary_block_salvar', [ self::class, 'salvar' ] );
		add_action( 'admin_post_ary_block_restaurar', [ self::class, 'restaurar' ] );
		add_action( 'admin_post_ary_block_limpar_registro', [ self::class, 'limpar_registro' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'estilo' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( ARY_BLOCK_ARQUIVO ), [ self::class, 'link_configuracoes' ] );
	}

	public static function menu(): void {
		add_menu_page(
			'Ary Block',
			'Ary Block',
			self::CAPACIDADE,
			self::PAGINA,
			[ self::class, 'tela' ],
			'dashicons-shield',
			81
		);
	}

	public static function link_configuracoes( array $links ): array {
		if ( current_user_can( self::CAPACIDADE ) ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGINA ) ) . '">Configurações</a>' );
		}
		return $links;
	}

	public static function estilo( string $tela ): void {
		if ( 'toplevel_page_' . self::PAGINA !== $tela ) {
			return;
		}
		wp_enqueue_style( 'ary-block-admin', ARY_BLOCK_URL . 'assets/admin.css', [], ARY_BLOCK_VERSAO );
	}

	private static function exigir_admin(): void {
		if ( ! current_user_can( self::CAPACIDADE ) ) {
			wp_die( 'Acesso restrito a administradores.', 'Ary Block', [ 'response' => 403 ] );
		}
	}

	private static function voltar( string $aviso ): void {
		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGINA, 'aviso' => $aviso ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function salvar(): void {
		self::exigir_admin();
		check_admin_referer( self::NONCE );
		$bruto = isset( $_POST['ary'] ) && is_array( $_POST['ary'] ) ? wp_unslash( $_POST['ary'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		Ary_Block_Config::gravar( Ary_Block_Regras::sanitizar_formulario( $bruto, Ary_Block_Config::ler() ) );
		self::voltar( 'salvo' );
	}

	public static function restaurar(): void {
		self::exigir_admin();
		check_admin_referer( self::NONCE );
		Ary_Block_Config::restaurar_padroes();
		self::voltar( 'restaurado' );
	}

	public static function limpar_registro(): void {
		self::exigir_admin();
		check_admin_referer( self::NONCE );
		Ary_Block_Config::limpar_registro();
		self::voltar( 'limpo' );
	}

	public static function tela(): void {
		self::exigir_admin();
		$config   = Ary_Block_Config::ler();
		$registro = Ary_Block_Config::registro();
		$aviso    = isset( $_GET['aviso'] ) ? sanitize_key( (string) $_GET['aviso'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$avisos   = [
			'salvo'      => 'Configuração salva.',
			'restaurado' => 'Padrões restaurados.',
			'limpo'      => 'Registro limpo.',
		];
		$bloqueados = count( Ary_Block_Regras::extensoes_bloqueadas( $config ) );
		$por_grupo  = [];
		foreach ( $config['formatos'] as $ext => $regra ) {
			$por_grupo[ $regra['grupo'] ][ $ext ] = $regra;
		}
		?>
		<div class="wrap ary-block">
			<h1><span class="dashicons dashicons-shield"></span> Ary Block</h1>
			<p class="ary-block__sub">Portão de upload e colagem de imagem. Visível e configurável apenas por administradores.</p>

			<?php if ( '' !== $aviso && isset( $avisos[ $aviso ] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $avisos[ $aviso ] ); ?></p></div>
			<?php endif; ?>

			<div class="ary-block__resumo">
				<div class="ary-block__cartao"><strong><?php echo (int) $bloqueados; ?></strong><span>formatos bloqueados</span></div>
				<div class="ary-block__cartao"><strong><?php echo esc_html( Ary_Block_Regras::humano_kb( Ary_Block_Regras::limite_imagem( $config ) ) ); ?></strong><span>limite de imagem</span></div>
				<div class="ary-block__cartao"><strong><?php echo (int) $config['largura_max_px']; ?> px</strong><span>largura máxima ao colar</span></div>
				<div class="ary-block__cartao <?php echo $config['modo_aviso'] ? 'is-aviso' : 'is-ativo'; ?>"><strong><?php echo $config['modo_aviso'] ? 'Só avisa' : 'Bloqueando'; ?></strong><span>modo do portão</span></div>
			</div>

			<div class="notice notice-info inline ary-block__isentos"><p><strong>Quem nunca é bloqueado:</strong> administradores e publicações automáticas (crawler, agendamentos, DN CLI). O portão vale para editores, autores, colunistas e colaboradores.</p></div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ary_block_salvar">
				<?php wp_nonce_field( self::NONCE ); ?>

				<h2>Formatos e limites</h2>
				<p class="description">Marque <em>Bloquear</em> para recusar o formato por completo. O limite vale para os formatos aceitos; <code>0</code> = sem limite. Valores em KB (1 MB = 1024 KB).</p>

				<?php foreach ( Ary_Block_Regras::GRUPOS as $grupo => $rotulo ) : ?>
					<?php if ( empty( $por_grupo[ $grupo ] ) ) { continue; } ?>
					<table class="widefat striped ary-block__tabela">
						<thead><tr><th class="col-formato"><?php echo esc_html( $rotulo ); ?></th><th class="col-bloquear">Bloquear</th><th class="col-limite">Limite (KB)</th><th class="col-nota">Equivale a</th></tr></thead>
						<tbody>
						<?php foreach ( $por_grupo[ $grupo ] as $ext => $regra ) : ?>
							<tr class="<?php echo $regra['bloquear'] ? 'is-bloqueado' : ''; ?>">
								<td class="col-formato"><code>.<?php echo esc_html( $ext ); ?></code><?php if ( 'svg' === $ext ) : ?> <span class="ary-block__fixo" title="SVG pode conter script; nunca é liberado.">sempre bloqueado</span><?php endif; ?></td>
								<td class="col-bloquear"><input type="checkbox" name="ary[formatos][<?php echo esc_attr( $ext ); ?>][bloquear]" value="1" <?php checked( $regra['bloquear'] ); ?> <?php disabled( 'svg' === $ext ); ?>></td>
								<td class="col-limite"><input type="number" min="0" step="1" name="ary[formatos][<?php echo esc_attr( $ext ); ?>][limite_kb]" value="<?php echo (int) $regra['limite_kb']; ?>" class="small-text"></td>
								<td class="col-nota"><?php echo (int) $regra['limite_kb'] > 0 ? esc_html( Ary_Block_Regras::humano_kb( (int) $regra['limite_kb'] ) ) : '<span class="description">sem limite</span>'; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endforeach; ?>

				<h2>Colagem de imagem e mensagens</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ary-largura">Largura máxima ao colar</label></th>
						<td><input type="number" id="ary-largura" name="ary[largura_max_px]" min="320" max="8000" step="10" value="<?php echo (int) $config['largura_max_px']; ?>" class="small-text"> px
							<p class="description">A imagem colada com Ctrl+V é convertida em JPG, limitada a esta largura e reduzida até caber no limite de imagem. Isso vale para todos, inclusive administradores: o objetivo é peso, não hierarquia.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="ary-msg-video">Mensagem para vídeo</label></th>
						<td><textarea id="ary-msg-video" name="ary[mensagem_video]" rows="3" class="large-text"><?php echo esc_textarea( $config['mensagem_video'] ); ?></textarea>
							<p class="description">O que o redator lê ao tentar enviar um vídeo.</p></td>
					</tr>
					<tr>
						<th scope="row">Modo do portão</th>
						<td><label><input type="checkbox" name="ary[modo_aviso]" value="1" <?php checked( $config['modo_aviso'] ); ?>> Só avisar: registra o que seria bloqueado, mas deixa passar</label>
							<p class="description">Útil na primeira semana, para ver o que os colunistas tentam enviar antes de bloquear de verdade.</p></td>
					</tr>
					<tr>
						<th scope="row">Registro</th>
						<td><label><input type="checkbox" name="ary[registrar]" value="1" <?php checked( $config['registrar'] ); ?>> Guardar os últimos <?php echo (int) Ary_Block_Regras::MAX_REGISTROS; ?> bloqueios (quem, arquivo, tamanho, motivo)</label></td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary button-large">Salvar configuração</button>
				</p>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ary-block__inline" onsubmit="return confirm('Restaurar os padrões do plugin? Os ajustes atuais serão perdidos.');">
				<input type="hidden" name="action" value="ary_block_restaurar">
				<?php wp_nonce_field( self::NONCE ); ?>
				<button type="submit" class="button">Restaurar padrões</button>
			</form>

			<h2>Últimos bloqueios</h2>
			<?php if ( empty( $registro ) ) : ?>
				<p class="description">Nenhum bloqueio registrado ainda.</p>
			<?php else : ?>
				<table class="widefat striped ary-block__registro">
					<thead><tr><th>Quando</th><th>Usuário</th><th>Arquivo</th><th>Tamanho</th><th>Motivo</th></tr></thead>
					<tbody>
					<?php foreach ( $registro as $l ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $l['quando'] ); ?></td>
							<td><?php echo esc_html( (string) $l['usuario'] ); ?><?php if ( ! empty( $l['papel'] ) ) : ?> <span class="description">(<?php echo esc_html( (string) $l['papel'] ); ?>)</span><?php endif; ?></td>
							<td><code><?php echo esc_html( (string) $l['arquivo'] ); ?></code></td>
							<td><?php echo esc_html( Ary_Block_Regras::humano_bytes( (int) $l['tamanho'] ) ); ?></td>
							<td><?php echo esc_html( self::motivo_legivel( (string) $l['motivo'] ) ); ?><?php if ( ! empty( $l['aviso'] ) ) : ?> <span class="ary-block__fixo">só aviso</span><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ary-block__inline">
					<input type="hidden" name="action" value="ary_block_limpar_registro">
					<?php wp_nonce_field( self::NONCE ); ?>
					<button type="submit" class="button">Limpar registro</button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function motivo_legivel( string $motivo ): string {
		return [
			'formato-bloqueado' => 'formato bloqueado',
			'acima-do-limite'   => 'acima do limite',
		][ $motivo ] ?? $motivo;
	}
}
