<?php
/**
 * Colar imagem no editor.
 *
 * O navegador entrega a imagem da área de transferência como PNG, mesmo que a
 * origem fosse JPG. O script (assets/colar.js) converte para JPG no próprio
 * navegador e envia por AJAX; aqui o servidor NÃO confia no cliente: reconverte
 * com o editor de imagem do WordPress, limita a largura e reduz a qualidade
 * até caber no limite de imagem do portão. Só então grava o anexo no post e
 * devolve o HTML da imagem para o editor inserir.
 *
 * Também aceita URL de imagem (quem copia no Google Imagens às vezes copia o
 * endereço, não o bitmap): o servidor baixa, normaliza e grava — acaba o
 * hotlink "de não se sabe onde".
 */

defined( 'ABSPATH' ) || exit;

final class Ary_Block_Colar {

	public const ACAO  = 'ary_block_colar';
	public const NONCE = 'ary_block_colar';

	/** Tamanho máximo que aceitamos receber antes de normalizar (bytes). */
	private const ENTRADA_MAX = 25 * 1024 * 1024;

	/** Teto de resolução na entrada, em pixels (50 MP). Ver a checagem no ajax(). */
	private const PIXELS_MAX = 50000000;

	public static function iniciar(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'scripts' ] );
		add_action( 'wp_ajax_' . self::ACAO, [ self::class, 'ajax' ] );
	}

	public static function scripts( string $tela ): void {
		if ( ! in_array( $tela, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		// `upload_files`, não `edit_posts`: quem grava na biblioteca de mídia é
		// quem tem a capacidade de gravar na biblioteca de mídia. Colaborador
		// tem `edit_posts` e não tem `upload_files` — enfileirar o script para
		// ele seria dar de presente uma permissão que a instalação lhe nega.
		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$config = Ary_Block_Config::ler();
		wp_enqueue_script( 'ary-block-colar', ARY_BLOCK_URL . 'assets/colar.js', [ 'jquery' ], ARY_BLOCK_VERSAO, true );
		wp_localize_script(
			'ary-block-colar',
			'aryBlockColar',
			[
				'ajaxurl'     => admin_url( 'admin-ajax.php' ),
				'acao'        => self::ACAO,
				'nonce'       => wp_create_nonce( self::NONCE ),
				'post_id'     => (int) ( $_GET['post'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'uploads_url' => trailingslashit( wp_get_upload_dir()['baseurl'] ),
				'limite_kb'   => Ary_Block_Regras::limite_imagem( $config ),
				'largura_max' => (int) $config['largura_max_px'],
				'textos'      => [
					'enviando' => 'Enviando imagem para a biblioteca…',
					'falhou'   => 'Não foi possível gravar a imagem colada.',
				],
			]
		);
	}

	public static function ajax(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		// `media_handle_sideload()` não confere capacidade nenhuma: ela confia
		// em quem a chamou. Esta linha é a única guarda entre a rota e a
		// biblioteca de mídia, e por isso pede a capacidade certa.
		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'mensagem' => 'Sem permissão para enviar imagens.' ], 403 );
		}
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'mensagem' => 'Sem permissão para editar esta notícia.' ], 403 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$origem = '';
		$tmp    = '';
		if ( ! empty( $_FILES['imagem'] ) && is_array( $_FILES['imagem'] ) && UPLOAD_ERR_OK === (int) ( $_FILES['imagem']['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			$tmp = (string) $_FILES['imagem']['tmp_name'];
			// O sideload move com `rename()`, e não com `move_uploaded_file()`,
			// que faria esta conferência sozinho. Aqui ela é explícita.
			if ( ! is_uploaded_file( $tmp ) ) {
				wp_send_json_error( [ 'mensagem' => 'Arquivo enviado inválido.' ], 400 );
			}
			$origem = 'colagem';
		} elseif ( ! empty( $_POST['url'] ) ) {
			$url = esc_url_raw( wp_unslash( (string) $_POST['url'] ) );
			if ( ! preg_match( '#^https?://#i', $url ) ) {
				wp_send_json_error( [ 'mensagem' => 'Endereço de imagem inválido.' ], 400 );
			}
			// Imagem do próprio portal: é nativa, não se grava de novo (o script já
			// evita chegar aqui; esta é a segunda camada).
			if ( Ary_Block_Regras::mesmo_site( $url, home_url() ) ) {
				$id_existente = attachment_url_to_postid( preg_replace( '/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', $url ) );
				if ( $id_existente > 0 ) {
					$src_e = wp_get_attachment_image_src( $id_existente, 'full' );
					$html  = sprintf( '<img src="%s" alt="" width="%d" height="%d" class="alignnone size-full wp-image-%d" />', esc_url( $src_e ? $src_e[0] : $url ), $src_e ? (int) $src_e[1] : 0, $src_e ? (int) $src_e[2] : 0, $id_existente );
					wp_send_json_success( [ 'id' => $id_existente, 'url' => $src_e ? $src_e[0] : $url, 'html' => $html, 'origem' => 'nativo' ] );
				}
				wp_send_json_success( [ 'id' => 0, 'url' => $url, 'html' => '<img src="' . esc_url( $url ) . '" alt="" />', 'origem' => 'nativo' ] );
			}
			// `download_url()` transmite direto para o disco, sem teto: um
			// endereço que devolve gigabytes enche o /tmp da hospedagem
			// compartilhada antes de a checagem de 25 MB abaixo ser alcançada.
			// O HEAD não fecha o caso (o servidor do outro lado pode mentir no
			// Content-Length), mas tira o caminho trivial da mesa.
			$cabeca = wp_safe_remote_head( $url, [ 'timeout' => 10, 'redirection' => 3 ] );
			if ( ! is_wp_error( $cabeca ) ) {
				$anunciado = (int) wp_remote_retrieve_header( $cabeca, 'content-length' );
				if ( $anunciado > self::ENTRADA_MAX ) {
					wp_send_json_error( [ 'mensagem' => 'Imagem grande demais para ser processada (máximo 25 MB).' ], 413 );
				}
			}
			$baixado = download_url( $url, 20 );
			if ( is_wp_error( $baixado ) ) {
				wp_send_json_error( [ 'mensagem' => 'Não foi possível baixar a imagem do endereço colado.' ], 400 );
			}
			$tmp    = (string) $baixado;
			$origem = 'url';
		} else {
			wp_send_json_error( [ 'mensagem' => 'Nada para colar.' ], 400 );
		}

		if ( ! is_readable( $tmp ) || filesize( $tmp ) > self::ENTRADA_MAX ) {
			self::apagar( $tmp, $origem );
			wp_send_json_error( [ 'mensagem' => 'Imagem grande demais para ser processada (máximo 25 MB).' ], 413 );
		}
		$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $info ) || ! in_array( $info['mime'] ?? '', [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ], true ) ) {
			self::apagar( $tmp, $origem );
			wp_send_json_error( [ 'mensagem' => 'O conteúdo colado não é uma imagem JPG, PNG, WEBP ou GIF.' ], 415 );
		}
		// Bomba de descompressão: peso em disco não diz nada sobre o custo de
		// abrir. Um PNG de poucos MB pode virar 20000x20000 px, e o GD aloca
		// largura x altura x 4 bytes — mais de 1 GB numa requisição só. Numa
		// hospedagem compartilhada isso derruba o site inteiro.
		if ( (int) $info[0] * (int) $info[1] > self::PIXELS_MAX ) {
			self::apagar( $tmp, $origem );
			wp_send_json_error( [ 'mensagem' => 'Imagem com resolução alta demais para ser processada (máximo 50 megapixels).' ], 413 );
		}

		$config  = Ary_Block_Config::ler();
		$limite  = Ary_Block_Regras::limite_imagem( $config );
		$largura = (int) $config['largura_max_px'];
		$normal  = self::normalizar( $tmp, $largura, $limite );
		if ( is_wp_error( $normal ) ) {
			self::apagar( $tmp, $origem );
			wp_send_json_error( [ 'mensagem' => $normal->get_error_message() ], 422 );
		}

		$nome    = 'colado-' . current_time( 'Ymd-His' ) . '-' . wp_generate_password( 4, false, false ) . '.jpg';
		$arquivo = [
			'name'     => $nome,
			'type'     => 'image/jpeg',
			'tmp_name' => $normal['caminho'],
			'error'    => 0,
			'size'     => (int) filesize( $normal['caminho'] ),
		];
		$id = media_handle_sideload( $arquivo, $post_id > 0 ? $post_id : 0, null, [ 'post_title' => 'Imagem colada em ' . current_time( 'd/m/Y H:i' ) ] );
		if ( $normal['caminho'] !== $tmp ) {
			self::apagar( $tmp, $origem );
		}
		if ( is_wp_error( $id ) ) {
			// O sideload passa pelo portão; se ele recusou, a mensagem dele é a certa.
			if ( file_exists( $normal['caminho'] ) ) {
				wp_delete_file( $normal['caminho'] );
			}
			wp_send_json_error( [ 'mensagem' => $id->get_error_message() ], 422 );
		}

		$src  = wp_get_attachment_image_src( (int) $id, 'full' );
		$w    = $src ? (int) $src[1] : (int) $normal['largura'];
		$h    = $src ? (int) $src[2] : (int) $normal['altura'];
		$url  = $src ? $src[0] : wp_get_attachment_url( (int) $id );
		$html = sprintf(
			'<img src="%s" alt="" width="%d" height="%d" class="alignnone size-full wp-image-%d" />',
			esc_url( $url ),
			$w,
			$h,
			(int) $id
		);
		wp_send_json_success(
			[
				'id'      => (int) $id,
				'url'     => $url,
				'largura' => $w,
				'altura'  => $h,
				'kb'      => (int) ceil( $arquivo['size'] / 1024 ),
				'html'    => $html,
				'origem'  => $origem,
			]
		);
	}

	/**
	 * Reconverte para JPG, limita a largura e reduz a qualidade até caber.
	 *
	 * Sem editor de imagem no servidor (sem GD nem Imagick), aceita o arquivo
	 * como veio só se já for JPG/WEBP dentro do limite; senão recusa com
	 * mensagem — o portão faria o mesmo.
	 *
	 * @return array{caminho:string, largura:int, altura:int}|WP_Error
	 */
	public static function normalizar( string $caminho, int $largura_max, int $limite_kb ) {
		$limite_bytes = $limite_kb * 1024;
		$editor       = wp_get_image_editor( $caminho );
		if ( is_wp_error( $editor ) ) {
			$info = @getimagesize( $caminho ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$mime = is_array( $info ) ? ( $info['mime'] ?? '' ) : '';
			if ( in_array( $mime, [ 'image/jpeg', 'image/webp' ], true ) && filesize( $caminho ) <= $limite_bytes && (int) $info[0] <= $largura_max ) {
				return [ 'caminho' => $caminho, 'largura' => (int) $info[0], 'altura' => (int) $info[1] ];
			}
			return new WP_Error( 'ary_block_sem_editor', 'O servidor não conseguiu converter a imagem. Reduza-a para JPG com até ' . Ary_Block_Regras::humano_kb( $limite_kb ) . ' e envie pela biblioteca.' );
		}

		$tamanho = $editor->get_size();
		$largura = min( (int) $tamanho['width'], $largura_max );
		$saida   = wp_tempnam( 'ary-block-colado.jpg' );

		// Tenta qualidade decrescente; se ainda não couber, reduz a largura.
		//
		// O editor é aberto UMA VEZ POR RODADA, fora do laço de qualidade. A
		// versão anterior o reabria a cada tentativa: até 24 decodificações do
		// arquivo original — que pode ter 25 MB — numa única requisição HTTP.
		// Como só a qualidade muda dentro da rodada, a mesma instância já
		// redimensionada serve para as quatro tentativas.
		for ( $rodada = 0; $rodada < 6; $rodada++ ) {
			$e = wp_get_image_editor( $caminho );
			if ( is_wp_error( $e ) ) {
				self::limpar( $saida );
				return $e;
			}
			$e->resize( $largura, null, false );
			foreach ( [ 85, 75, 65, 55 ] as $q ) {
				$e->set_quality( $q );
				$r = $e->save( $saida, 'image/jpeg' );
				if ( is_wp_error( $r ) ) {
					self::limpar( $saida );
					return $r;
				}
				$gravado = (string) $r['path'];
				if ( $gravado !== $saida && file_exists( $gravado ) ) {
					// O editor pode trocar a extensão; padroniza o caminho.
					if ( file_exists( $saida ) ) {
						wp_delete_file( $saida );
					}
					rename( $gravado, $saida );
				}
				if ( filesize( $saida ) <= $limite_bytes ) {
					return [ 'caminho' => $saida, 'largura' => (int) $r['width'], 'altura' => (int) $r['height'] ];
				}
			}
			$largura = (int) floor( $largura * 0.8 );
			if ( $largura < 480 ) {
				break;
			}
		}
		self::limpar( $saida );
		return new WP_Error( 'ary_block_nao_coube', 'A imagem não coube em ' . Ary_Block_Regras::humano_kb( $limite_kb ) . ' mesmo reduzida. Use uma imagem mais simples.' );
	}

	/** Apaga o temporário da normalização, se existir. */
	private static function limpar( string $caminho ): void {
		if ( '' !== $caminho && file_exists( $caminho ) ) {
			wp_delete_file( $caminho );
		}
	}

	private static function apagar( string $tmp, string $origem ): void {
		if ( 'url' === $origem && '' !== $tmp && file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}
	}
}
