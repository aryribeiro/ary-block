<?php
/**
 * Leitura e gravação da configuração e do registro de bloqueios.
 *
 * Única porta entre o WordPress (get_option/update_option) e as regras puras.
 */

defined( 'ABSPATH' ) || exit;

final class Ary_Block_Config {

	/** @var array|null Cache por requisição. */
	private static ?array $cache = null;

	public static function ler(): array {
		if ( null === self::$cache ) {
			self::$cache = Ary_Block_Regras::mesclar( get_option( Ary_Block_Regras::OPCAO, [] ) );
		}
		return self::$cache;
	}

	public static function gravar( array $config ): void {
		self::$cache = Ary_Block_Regras::mesclar( $config );
		update_option( Ary_Block_Regras::OPCAO, self::$cache, false );
	}

	public static function restaurar_padroes(): void {
		self::gravar( Ary_Block_Regras::config_padrao() );
	}

	/**
	 * Anota um bloqueio (ou aviso) no registro circular.
	 *
	 * @param array $dados ['arquivo','tamanho','motivo','mensagem','aviso','origem'].
	 */
	public static function registrar( array $dados ): void {
		$config = self::ler();
		if ( empty( $config['registrar'] ) ) {
			return;
		}
		$usuario = wp_get_current_user();
		$linha   = [
			'quando'  => current_time( 'mysql' ),
			'usuario' => $usuario && $usuario->exists() ? $usuario->user_login : '(sem usuário)',
			'papel'   => $usuario && $usuario->exists() && ! empty( $usuario->roles ) ? implode( ',', $usuario->roles ) : '',
			'arquivo' => Ary_Block_Regras::texto_limpo( (string) ( $dados['arquivo'] ?? '' ) ),
			'tamanho' => (int) ( $dados['tamanho'] ?? 0 ),
			'motivo'  => (string) ( $dados['motivo'] ?? '' ),
			'aviso'   => ! empty( $dados['aviso'] ),
			'origem'  => (string) ( $dados['origem'] ?? 'upload' ),
		];
		$registro = get_option( Ary_Block_Regras::OPCAO_REGISTRO, [] );
		if ( ! is_array( $registro ) ) {
			$registro = [];
		}
		array_unshift( $registro, $linha );
		$registro = array_slice( $registro, 0, Ary_Block_Regras::MAX_REGISTROS );
		update_option( Ary_Block_Regras::OPCAO_REGISTRO, $registro, false );
	}

	public static function registro(): array {
		$r = get_option( Ary_Block_Regras::OPCAO_REGISTRO, [] );
		return is_array( $r ) ? $r : [];
	}

	public static function limpar_registro(): void {
		update_option( Ary_Block_Regras::OPCAO_REGISTRO, [], false );
	}

	/** Contexto do usuário atual, no formato que as regras esperam. */
	public static function contexto(): array {
		$uid = (int) get_current_user_id();
		return [
			'usuario' => $uid,
			'admin'   => $uid > 0 && current_user_can( 'manage_options' ),
		];
	}
}
