<?php
/**
 * Plugin Name: Ary Block
 * Plugin URI:  https://diretonoticias.com.br
 * Description: Dois em um. (1) Portão de upload: recusa na biblioteca de mídia, com mensagem amigável, os formatos e tamanhos que o administrador definir — administradores e publicações automáticas nunca são bloqueados. (2) Colar imagem: Ctrl+V de uma imagem no editor da notícia grava a imagem na biblioteca, já convertida em JPG leve, e a insere no texto.
 * Version:     1.0.2
 * Author:      Ary Ribeiro
 * Author URI:  https://linkedin.com/in/aryribeiro
 * License:     GPL-2.0-or-later
 * Requires PHP: 8.1
 * Text Domain: ary-block
 * Update URI:  https://github.com/aryribeiro/ary-block
 *
 * POR QUE ESTE PLUGIN EXISTE (13/09/2026)
 *
 * Colunistas e articulistas voluntários subiam para a hospedagem PNG de 5 MB,
 * vídeos MP4 e PDFs pesados. Capa pesada demais não abre na prévia do WhatsApp
 * nem das redes sociais quando o link da notícia é compartilhado; vídeo dentro
 * do WordPress custa espaço e banda que o portal não tem. A regra do dono:
 * imagem até 290 KB, vídeo vai para o YouTube e entra na notícia como URL.
 *
 * E o Ctrl+V de imagem no editor clássico simplesmente não ia para a biblioteca:
 * a imagem colada ficava embutida ou apontando para o site de origem, e depois
 * ninguém sabia de onde ela vinha. Este plugin intercepta a colagem, converte
 * para JPG dentro do limite e grava o anexo no post.
 *
 * ARQUITETURA
 *
 *   includes/class-regras.php  regras puras (sem WordPress): aceita ou recusa,
 *                              e a mensagem. É o que os testes cobrem.
 *   includes/class-config.php  leitura/gravação da opção com os padrões.
 *   includes/class-portao.php  ganchos de upload (prefilter, sideload, mimes).
 *   includes/class-colar.php   colagem: script no editor + AJAX + normalização.
 *   includes/class-admin.php   tela do administrador (API de configurações).
 *
 * Contrato completo em CONTRATO.md. Testes em tests/ (PHPUnit, WordPress dublado).
 */

defined( 'ABSPATH' ) || exit;

// Mexeu em assets/, sobe a versão: o navegador só busca o script novo se o
// endereço mudar (lição do Painel ADS).
define( 'ARY_BLOCK_VERSAO', '1.0.2' );
define( 'ARY_BLOCK_ARQUIVO', __FILE__ );
define( 'ARY_BLOCK_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARY_BLOCK_URL', plugin_dir_url( __FILE__ ) );

require_once ARY_BLOCK_DIR . 'includes/class-regras.php';
require_once ARY_BLOCK_DIR . 'includes/class-config.php';
require_once ARY_BLOCK_DIR . 'includes/class-portao.php';
require_once ARY_BLOCK_DIR . 'includes/class-colar.php';
require_once ARY_BLOCK_DIR . 'includes/class-admin.php';

add_action(
	'plugins_loaded',
	static function (): void {
		Ary_Block_Portao::iniciar();
		Ary_Block_Colar::iniciar();
		if ( is_admin() ) {
			Ary_Block_Admin::iniciar();
		}
	}
);

register_activation_hook(
	__FILE__,
	static function (): void {
		if ( false === get_option( Ary_Block_Regras::OPCAO ) ) {
			add_option( Ary_Block_Regras::OPCAO, Ary_Block_Regras::config_padrao(), '', false );
		}
		if ( false === get_option( Ary_Block_Regras::OPCAO_REGISTRO ) ) {
			add_option( Ary_Block_Regras::OPCAO_REGISTRO, [], '', false );
		}
	}
);
