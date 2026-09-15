<?php
/**
 * O portão: ganchos de upload do WordPress.
 *
 * `wp_handle_upload_prefilter` recebe todo upload humano (biblioteca, modal do
 * editor, REST de mídia) antes de o arquivo ser movido; devolver a chave
 * `error` com texto faz o WordPress mostrar exatamente esse texto ao redator.
 * `wp_handle_sideload_prefilter` cobre os sideloads (inclusive a colagem deste
 * plugin). `upload_mimes`, só para quem não é administrador, tira da lista de
 * tipos permitidos os formatos totalmente bloqueados — segunda camada, e a que
 * segura SVG.
 */

defined( 'ABSPATH' ) || exit;

final class Ary_Block_Portao {

	public static function iniciar(): void {
		add_filter( 'wp_handle_upload_prefilter', [ self::class, 'filtrar' ], 5 );
		add_filter( 'wp_handle_sideload_prefilter', [ self::class, 'filtrar' ], 5 );
		add_filter( 'upload_mimes', [ self::class, 'mimes' ], 99 );
	}

	/**
	 * @param array $arquivo Entrada de $_FILES (name, type, tmp_name, error, size).
	 */
	public static function filtrar( $arquivo ) {
		if ( ! is_array( $arquivo ) || ! empty( $arquivo['error'] ) ) {
			return $arquivo;
		}
		$nome    = (string) ( $arquivo['name'] ?? '' );
		$tamanho = (int) ( $arquivo['size'] ?? 0 );
		if ( $tamanho <= 0 && ! empty( $arquivo['tmp_name'] ) && is_readable( $arquivo['tmp_name'] ) ) {
			$tamanho = (int) filesize( $arquivo['tmp_name'] );
		}
		$config = Ary_Block_Config::ler();
		$r      = Ary_Block_Regras::avaliar( $nome, $tamanho, $config, Ary_Block_Config::contexto() );

		if ( $r['ok'] ) {
			if ( $r['aviso'] ) {
				Ary_Block_Config::registrar( [ 'arquivo' => $nome, 'tamanho' => $tamanho, 'motivo' => $r['motivo'], 'aviso' => true ] );
			}
			return $arquivo;
		}

		Ary_Block_Config::registrar( [ 'arquivo' => $nome, 'tamanho' => $tamanho, 'motivo' => $r['motivo'] ] );
		$arquivo['error'] = $r['mensagem'];
		return $arquivo;
	}

	/**
	 * @param array $mimes 'jpg|jpeg|jpe' => 'image/jpeg', ...
	 */
	public static function mimes( $mimes ) {
		if ( ! is_array( $mimes ) ) {
			return $mimes;
		}
		$ctx = Ary_Block_Config::contexto();
		if ( $ctx['admin'] || 0 === $ctx['usuario'] ) {
			return $mimes;
		}
		$bloqueadas = array_flip( Ary_Block_Regras::extensoes_bloqueadas( Ary_Block_Config::ler() ) );
		if ( empty( $bloqueadas ) ) {
			return $mimes;
		}
		$saida = [];
		foreach ( $mimes as $chave => $mime ) {
			$partes = array_values( array_filter( explode( '|', (string) $chave ), static fn( $e ) => ! isset( $bloqueadas[ strtolower( $e ) ] ) ) );
			if ( empty( $partes ) ) {
				continue;
			}
			$saida[ implode( '|', $partes ) ] = $mime;
		}
		return $saida;
	}
}
