<?php
/**
 * Regras do portão — PURAS. Nenhuma função do WordPress aqui dentro.
 *
 * Entrada: nome do arquivo, tamanho em bytes, configuração e contexto (é
 * administrador? há usuário logado?). Saída: aceita ou recusa, com a mensagem
 * que o redator vai ler. Tudo o que decide está neste arquivo, e é isto que os
 * testes em tests/ cobrem sem precisar de WordPress.
 *
 * Isenções, por decisão do conselho de 13/09/2026:
 *   - `manage_options` passa por tudo (o administrador continua livre);
 *   - execução sem usuário logado passa por tudo (cron, CLI, crawler que sobe
 *     capas PNG de madrugada). Bloquear o crawler derrubaria a publicação
 *     automática sem ninguém ver.
 */

defined( 'ABSPATH' ) || exit;

final class Ary_Block_Regras {

	public const OPCAO          = 'ary_block_config';
	public const OPCAO_REGISTRO = 'ary_block_registro';
	public const MAX_REGISTROS  = 50;

	public const GRUPOS = [
		'imagens'     => 'Imagens',
		'video'       => 'Vídeo',
		'audio'       => 'Áudio',
		'documentos'  => 'Documentos',
		'compactados' => 'Compactados',
		'codigo'      => 'Código e executáveis',
	];

	/**
	 * Apelidos de extensão: cada um herda a regra do formato canônico.
	 *
	 * POR QUE ISTO EXISTE. O WordPress aceita muito mais extensão do que se
	 * imagina, e várias são apelidos da mesma coisa: `wp_get_mime_types()` traz
	 * chaves compostas como `jpg|jpeg|jpe`, `mov|qt`, `mp3|m4a|m4b` e
	 * `gz|gzip`. Sem este mapa, um vídeo renomeado para `.ogv` ou `.mpg` — ou
	 * uma imagem renomeada para `.jpe` — não constava da tabela, e a decisão
	 * caía no caminho "formato fora da tabela", que aceita sem limite nenhum.
	 * Era possível subir vídeo de tamanho ilimitado justamente no portal que o
	 * plugin existe para proteger.
	 *
	 * A regra aqui é simples: o apelido vale o que vale o seu canônico. Se o
	 * administrador liberar MP4, `.ogv` e `.mpg` liberam junto, com o mesmo
	 * limite — que é o comportamento que quem configura a tela espera.
	 *
	 * @return array<string, string> apelido => extensão canônica na tabela.
	 */
	public const APELIDOS = [
		// Imagens.
		'jpe'     => 'jpg',
		'ico'     => 'gif',
		'heif'    => 'heic',
		'heics'   => 'heic',
		'heifs'   => 'heic',
		'xcf'     => 'psd',
		// Vídeo — tudo vai para o YouTube, igual ao MP4.
		'qt'      => 'mov',
		'mpeg'    => 'mp4',
		'mpg'     => 'mp4',
		'mpe'     => 'mp4',
		'mpv'     => 'mp4',
		'ogv'     => 'mp4',
		'ogm'     => 'mp4',
		'divx'    => 'mp4',
		'flv'     => 'mp4',
		'dv'      => 'mp4',
		'vob'     => 'mp4',
		'rm'      => 'mp4',
		'asf'     => 'wmv',
		'asx'     => 'wmv',
		'wmx'     => 'wmv',
		'wm'      => 'wmv',
		'3gpp'    => '3gp',
		'3g2'     => '3gp',
		'3gp2'    => '3gp',
		// Áudio.
		'm4b'     => 'm4a',
		'oga'     => 'ogg',
		'x-wav'   => 'wav',
		'mka'     => 'wav',
		'ra'      => 'wav',
		'ram'     => 'wav',
		'wma'     => 'wav',
		'wax'     => 'wav',
		'mid'     => 'wav',
		'midi'    => 'wav',
		'aif'     => 'wav',
		'aiff'    => 'wav',
		'ac3'     => 'wav',
		'm3a'     => 'wav',
		'mp1'     => 'wav',
		'mp2'     => 'wav',
		// Documentos: herdam o limite do parente de mesmo formato.
		'docm'    => 'docx',
		'dotx'    => 'docx',
		'dotm'    => 'docx',
		'pages'   => 'docx',
		'wri'     => 'doc',
		'wp'      => 'doc',
		'wpd'     => 'doc',
		'mdb'     => 'doc',
		'mpp'     => 'doc',
		'onetoc'  => 'doc',
		'onetoc2' => 'doc',
		'onetmp'  => 'doc',
		'onepkg'  => 'doc',
		'xlsm'    => 'xlsx',
		'xlsb'    => 'xlsx',
		'xltx'    => 'xlsx',
		'xltm'    => 'xlsx',
		'xlam'    => 'xlsx',
		'xla'     => 'xls',
		'xlt'     => 'xls',
		'xlw'     => 'xls',
		'numbers' => 'xlsx',
		'pptm'    => 'pptx',
		'ppsx'    => 'pptx',
		'ppsm'    => 'pptx',
		'potx'    => 'pptx',
		'potm'    => 'pptx',
		'ppam'    => 'pptx',
		'sldx'    => 'pptx',
		'sldm'    => 'pptx',
		'pot'     => 'ppt',
		'pps'     => 'ppt',
		'key'     => 'pptx',
		'odp'     => 'odt',
		'ods'     => 'odt',
		'odg'     => 'odt',
		'odc'     => 'odt',
		'odb'     => 'odt',
		'odf'     => 'odt',
		'oxps'    => 'pdf',
		'xps'     => 'pdf',
		'asc'     => 'txt',
		'srt'     => 'txt',
		'vtt'     => 'txt',
		'tsv'     => 'csv',
		'ics'     => 'txt',
		'rtx'     => 'txt',
		'dfxp'    => 'txt',
		'c'       => 'txt',
		'cc'      => 'txt',
		'h'       => 'txt',
		// Compactados.
		'gzip'    => 'gz',
		'tgz'     => 'gz',
		'bz2'     => 'gz',
		'tar'     => 'zip',
		'cab'     => 'zip',
		'dmg'     => 'zip',
		'sit'     => 'zip',
		'sea'     => 'zip',
		'sqx'     => 'zip',
		// Código.
		'htm'     => 'html',
		'phtml'   => 'php',
	];

	/** Limite padrão de imagem, em KB (regra do dono). */
	public const IMAGEM_KB = 290;

	/**
	 * Tabela de formatos: extensão => grupo, bloquear, limite em KB (0 = sem limite).
	 *
	 * Padrões do dono: PNG, MP4 e WAV bloqueados; imagens 290 KB; PDF e DOCX
	 * 10 MB; MP3 1 MB. Sugestões (bloqueadas por padrão): os outros vídeos
	 * (YouTube), os outros áudios, imagens de captura/edição (BMP, TIFF, HEIC,
	 * PSD), SVG (risco de script) e compactados.
	 *
	 * @return array<string, array{grupo:string, bloquear:bool, limite_kb:int}>
	 */
	public static function formatos_padrao(): array {
		$f = static fn( string $grupo, bool $bloquear, int $kb ): array => [
			'grupo'     => $grupo,
			'bloquear'  => $bloquear,
			'limite_kb' => $kb,
		];
		return [
			// Imagens.
			'jpg'  => $f( 'imagens', false, self::IMAGEM_KB ),
			'jpeg' => $f( 'imagens', false, self::IMAGEM_KB ),
			'webp' => $f( 'imagens', false, self::IMAGEM_KB ),
			'avif' => $f( 'imagens', false, self::IMAGEM_KB ),
			'gif'  => $f( 'imagens', false, self::IMAGEM_KB ),
			'png'  => $f( 'imagens', true, self::IMAGEM_KB ),
			'bmp'  => $f( 'imagens', true, 0 ),
			'tif'  => $f( 'imagens', true, 0 ),
			'tiff' => $f( 'imagens', true, 0 ),
			'heic' => $f( 'imagens', true, 0 ),
			'psd'  => $f( 'imagens', true, 0 ),
			'svg'  => $f( 'imagens', true, 0 ),
			// Vídeo: tudo vai para o YouTube.
			'mp4'  => $f( 'video', true, 0 ),
			'mov'  => $f( 'video', true, 0 ),
			'avi'  => $f( 'video', true, 0 ),
			'mkv'  => $f( 'video', true, 0 ),
			'wmv'  => $f( 'video', true, 0 ),
			'webm' => $f( 'video', true, 0 ),
			'm4v'  => $f( 'video', true, 0 ),
			'3gp'  => $f( 'video', true, 0 ),
			// Áudio.
			'mp3'  => $f( 'audio', false, 1024 ),
			'wav'  => $f( 'audio', true, 0 ),
			'flac' => $f( 'audio', true, 0 ),
			'ogg'  => $f( 'audio', true, 0 ),
			'aac'  => $f( 'audio', true, 0 ),
			'm4a'  => $f( 'audio', true, 0 ),
			// Documentos.
			'pdf'  => $f( 'documentos', false, 10240 ),
			'docx' => $f( 'documentos', false, 10240 ),
			'doc'  => $f( 'documentos', false, 10240 ),
			'xlsx' => $f( 'documentos', false, 10240 ),
			'xls'  => $f( 'documentos', false, 10240 ),
			'pptx' => $f( 'documentos', false, 10240 ),
			'ppt'  => $f( 'documentos', false, 10240 ),
			'odt'  => $f( 'documentos', false, 10240 ),
			'txt'  => $f( 'documentos', false, 1024 ),
			'csv'  => $f( 'documentos', false, 1024 ),
			'rtf'  => $f( 'documentos', false, 1024 ),
			// Compactados.
			'zip'  => $f( 'compactados', true, 0 ),
			'rar'  => $f( 'compactados', true, 0 ),
			'7z'   => $f( 'compactados', true, 0 ),
			'gz'   => $f( 'compactados', true, 0 ),
			// Código e executáveis: o WordPress aceita estes formatos, e um
			// HTML gravado na biblioteca vira página no próprio domínio.
			'html' => $f( 'codigo', true, 0 ),
			'js'   => $f( 'codigo', true, 0 ),
			'css'  => $f( 'codigo', true, 0 ),
			'php'  => $f( 'codigo', true, 0 ),
			'exe'  => $f( 'codigo', true, 0 ),
			'swf'  => $f( 'codigo', true, 0 ),
			'class' => $f( 'codigo', true, 0 ),
		];
	}

	/**
	 * @return array{formatos:array, largura_max_px:int, modo_aviso:bool, registrar:bool, mensagem_video:string, versao:int}
	 */
	public static function config_padrao(): array {
		return [
			'formatos'       => self::formatos_padrao(),
			'largura_max_px' => 1600,
			'modo_aviso'     => false,
			'registrar'      => true,
			'mensagem_video' => 'Vídeos não são enviados para o portal. Publique o vídeo primeiro no canal do YouTube do Direto Notícias e depois cole a URL do vídeo dentro da notícia: o player aparece sozinho.',
			'versao'         => 1,
		];
	}

	/**
	 * Une o que está gravado com os padrões (formato novo entra sem apagar
	 * ajustes antigos) e força tipos.
	 *
	 * @param mixed $gravado O que veio da opção.
	 */
	public static function mesclar( $gravado ): array {
		$padrao = self::config_padrao();
		if ( ! is_array( $gravado ) ) {
			return $padrao;
		}
		$config = $padrao;
		foreach ( [ 'largura_max_px' ] as $k ) {
			if ( isset( $gravado[ $k ] ) ) {
				$config[ $k ] = max( 320, min( 8000, (int) $gravado[ $k ] ) );
			}
		}
		foreach ( [ 'modo_aviso', 'registrar' ] as $k ) {
			if ( array_key_exists( $k, $gravado ) ) {
				$config[ $k ] = (bool) $gravado[ $k ];
			}
		}
		if ( isset( $gravado['mensagem_video'] ) && is_string( $gravado['mensagem_video'] ) && '' !== trim( $gravado['mensagem_video'] ) ) {
			$config['mensagem_video'] = self::texto_limpo( $gravado['mensagem_video'] );
		}
		if ( isset( $gravado['formatos'] ) && is_array( $gravado['formatos'] ) ) {
			foreach ( $gravado['formatos'] as $ext => $regra ) {
				$ext = self::canonica( self::extensao( 'x.' . (string) $ext ) );
				if ( '' === $ext || ! is_array( $regra ) ) {
					continue;
				}
				$grupo = isset( $config['formatos'][ $ext ]['grupo'] ) ? $config['formatos'][ $ext ]['grupo'] : ( isset( $regra['grupo'] ) && isset( self::GRUPOS[ $regra['grupo'] ] ) ? $regra['grupo'] : '' );
				if ( '' === $grupo ) {
					continue; // extensão desconhecida sem grupo válido: não entra.
				}
				$config['formatos'][ $ext ] = [
					'grupo'     => $grupo,
					'bloquear'  => ! empty( $regra['bloquear'] ),
					'limite_kb' => max( 0, min( 1048576, (int) ( $regra['limite_kb'] ?? 0 ) ) ),
				];
			}
		}
		// SVG nunca é liberado: é XML com script, e a biblioteca não o sanitiza.
		$config['formatos']['svg']['bloquear'] = true;
		return $config;
	}

	/**
	 * Converte o POST da tela (checkboxes e números como texto) em configuração.
	 *
	 * @param array $bruto Campos do formulário.
	 * @param array $atual Configuração vigente (mantém o que a tela não manda).
	 */
	public static function sanitizar_formulario( array $bruto, array $atual ): array {
		$novo = $atual;
		$novo['largura_max_px'] = (int) ( $bruto['largura_max_px'] ?? $atual['largura_max_px'] );
		$novo['modo_aviso']     = ! empty( $bruto['modo_aviso'] );
		$novo['registrar']      = ! empty( $bruto['registrar'] );
		if ( isset( $bruto['mensagem_video'] ) ) {
			$novo['mensagem_video'] = (string) $bruto['mensagem_video'];
		}
		$formatos = [];
		foreach ( $atual['formatos'] as $ext => $regra ) {
			if ( ! isset( $bruto['formatos'][ $ext ] ) || ! is_array( $bruto['formatos'][ $ext ] ) ) {
				$formatos[ $ext ] = $regra; // a tela não mandou esta linha: fica como está.
				continue;
			}
			$linha            = $bruto['formatos'][ $ext ]; // linha presente: checkbox ausente = desmarcado.
			$formatos[ $ext ] = [
				'grupo'     => $regra['grupo'],
				'bloquear'  => ! empty( $linha['bloquear'] ),
				'limite_kb' => array_key_exists( 'limite_kb', $linha ) ? (int) $linha['limite_kb'] : (int) $regra['limite_kb'],
			];
		}
		$novo['formatos'] = $formatos;
		return self::mesclar( $novo );
	}

	/** Extensão em minúsculas, sem ponto; '' se não houver. */
	public static function extensao( string $nome ): string {
		$nome = strtolower( trim( $nome ) );
		$p    = strrpos( $nome, '.' );
		if ( false === $p || $p === strlen( $nome ) - 1 ) {
			return '';
		}
		$ext = substr( $nome, $p + 1 );
		return preg_match( '/^[a-z0-9]{1,8}$/', $ext ) ? $ext : '';
	}

	/**
	 * A decisão.
	 *
	 * @param string $nome     Nome do arquivo (com extensão).
	 * @param int    $tamanho  Bytes.
	 * @param array  $config   Configuração já mesclada.
	 * @param array  $contexto ['admin' => bool, 'usuario' => int].
	 * @return array{ok:bool, motivo:string, mensagem:string, ext:string, grupo:string, aviso:bool}
	 */
	public static function avaliar( string $nome, int $tamanho, array $config, array $contexto ): array {
		// O redator lê a extensão que ELE enviou; a regra é a do formato
		// canônico (ver APELIDOS). Quem manda `capa.jpe` recebe uma mensagem
		// que fala de JPE, e não de JPG.
		$escrita = self::extensao( $nome );
		$ext     = self::canonica( $escrita );
		$grupo   = $config['formatos'][ $ext ]['grupo'] ?? '';
		$base    = [
			'ok'       => true,
			'motivo'   => '',
			'mensagem' => '',
			'ext'      => $ext,
			'grupo'    => $grupo,
			'aviso'    => false,
		];

		if ( ! empty( $contexto['admin'] ) ) {
			return array_merge( $base, [ 'motivo' => 'isento-admin' ] );
		}
		if ( empty( $contexto['usuario'] ) ) {
			return array_merge( $base, [ 'motivo' => 'isento-sistema' ] );
		}
		if ( '' === $ext || ! isset( $config['formatos'][ $ext ] ) ) {
			return array_merge( $base, [ 'motivo' => 'formato-fora-da-tabela' ] );
		}

		$regra    = $config['formatos'][ $ext ];
		$recusa   = null;
		$rotulo   = strtoupper( $escrita );

		if ( ! empty( $regra['bloquear'] ) ) {
			$recusa = [
				'motivo'   => 'formato-bloqueado',
				'mensagem' => self::mensagem_bloqueio( $rotulo, $grupo, $config ),
			];
		} elseif ( (int) $regra['limite_kb'] > 0 && $tamanho > (int) $regra['limite_kb'] * 1024 ) {
			$recusa = [
				'motivo'   => 'acima-do-limite',
				'mensagem' => self::mensagem_limite( $rotulo, $grupo, $tamanho, (int) $regra['limite_kb'], $config ),
			];
		}

		if ( null === $recusa ) {
			return array_merge( $base, [ 'motivo' => 'aceito' ] );
		}
		if ( ! empty( $config['modo_aviso'] ) ) {
			return array_merge( $base, $recusa, [ 'ok' => true, 'aviso' => true ] );
		}
		return array_merge( $base, $recusa, [ 'ok' => false ] );
	}

	/** A extensão que manda na decisão: ela mesma, ou o canônico do apelido. */
	public static function canonica( string $ext ): string {
		return self::APELIDOS[ $ext ] ?? $ext;
	}

	/**
	 * Extensões totalmente bloqueadas (para tirar da lista de tipos
	 * permitidos). Inclui os apelidos: sem eles, bloquear `mov` deixava `qt`
	 * de pé na chave `mov|qt` do núcleo, e o vídeo entrava por ali.
	 */
	public static function extensoes_bloqueadas( array $config ): array {
		$saida = [];
		foreach ( $config['formatos'] as $ext => $regra ) {
			if ( ! empty( $regra['bloquear'] ) ) {
				$saida[] = (string) $ext;
			}
		}
		$bloqueadas = array_flip( $saida );
		foreach ( self::APELIDOS as $apelido => $canonica ) {
			if ( isset( $bloqueadas[ $canonica ] ) ) {
				$saida[] = (string) $apelido;
			}
		}
		return array_values( array_unique( $saida ) );
	}

	/** Extensões aceitas de um grupo, com o limite, para citar na mensagem. */
	public static function aceitos_do_grupo( string $grupo, array $config ): string {
		$partes = [];
		foreach ( $config['formatos'] as $ext => $regra ) {
			if ( $regra['grupo'] !== $grupo || ! empty( $regra['bloquear'] ) ) {
				continue;
			}
			$partes[] = strtoupper( (string) $ext ) . ( (int) $regra['limite_kb'] > 0 ? ' (até ' . self::humano_kb( (int) $regra['limite_kb'] ) . ')' : '' );
		}
		return implode( ', ', $partes );
	}

	public static function mensagem_bloqueio( string $rotulo, string $grupo, array $config ): string {
		if ( 'video' === $grupo ) {
			return $config['mensagem_video'];
		}
		$aceitos = self::aceitos_do_grupo( $grupo, $config );
		$nome    = self::GRUPOS[ $grupo ] ?? 'Arquivos';
		$msg     = sprintf( 'Arquivos %s não são aceitos na biblioteca do portal.', $rotulo );
		if ( 'imagens' === $grupo ) {
			$msg .= ' Converta a imagem para JPG ou WEBP com até ' . self::humano_kb( self::limite_imagem( $config ) ) . ' — ou cole a imagem direto no texto da notícia (Ctrl+V), que o portal converte sozinho.';
		} elseif ( '' !== $aceitos ) {
			$msg .= ' ' . $nome . ' aceitos: ' . $aceitos . '.';
		}
		return $msg;
	}

	public static function mensagem_limite( string $rotulo, string $grupo, int $tamanho, int $limite_kb, array $config ): string {
		$msg = sprintf(
			'O arquivo %s tem %s; o limite para %s é %s.',
			$rotulo,
			self::humano_bytes( $tamanho ),
			$rotulo,
			self::humano_kb( $limite_kb )
		);
		if ( 'imagens' === $grupo ) {
			$msg .= ' Reduza a imagem (largura máxima ' . (int) $config['largura_max_px'] . ' px, JPG ou WEBP) — ou cole a imagem direto no texto da notícia (Ctrl+V), que o portal converte sozinho. Imagem pesada não abre na prévia do WhatsApp e das redes sociais.';
		}
		return $msg;
	}

	/** Menor limite entre os formatos de imagem aceitos (o que vale para a colagem). */
	public static function limite_imagem( array $config ): int {
		$menor = 0;
		foreach ( $config['formatos'] as $regra ) {
			if ( 'imagens' !== $regra['grupo'] || ! empty( $regra['bloquear'] ) || (int) $regra['limite_kb'] <= 0 ) {
				continue;
			}
			$menor = 0 === $menor ? (int) $regra['limite_kb'] : min( $menor, (int) $regra['limite_kb'] );
		}
		return $menor > 0 ? $menor : self::IMAGEM_KB;
	}

	public static function humano_kb( int $kb ): string {
		if ( $kb >= 1024 && 0 === $kb % 1024 ) {
			return ( $kb / 1024 ) . ' MB';
		}
		if ( $kb >= 1024 ) {
			return number_format( $kb / 1024, 1, ',', '.' ) . ' MB';
		}
		return $kb . ' KB';
	}

	public static function humano_bytes( int $bytes ): string {
		if ( $bytes >= 1048576 ) {
			return number_format( $bytes / 1048576, 1, ',', '.' ) . ' MB';
		}
		return (int) ceil( $bytes / 1024 ) . ' KB';
	}

	/**
	 * A URL aponta para o próprio site? (ignora www. e maiúsculas). Imagem do
	 * próprio portal colada no editor é nativa: não vira anexo novo.
	 */
	public static function mesmo_site( string $url, string $home ): bool {
		$a = parse_url( trim( $url ), PHP_URL_HOST );
		$b = parse_url( trim( $home ), PHP_URL_HOST );
		if ( ! is_string( $a ) || ! is_string( $b ) || '' === $a || '' === $b ) {
			return false;
		}
		$limpar = static fn( string $h ): string => strtolower( preg_replace( '/^www\./i', '', $h ) ?? $h );
		return $limpar( $a ) === $limpar( $b );
	}

	/** Texto sem tags nem quebras estranhas, para mensagens. */
	public static function texto_limpo( string $texto ): string {
		$texto = strip_tags( $texto );
		$texto = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texto ) ?? '';
		return trim( preg_replace( '/\s+/', ' ', $texto ) ?? '' );
	}
}
