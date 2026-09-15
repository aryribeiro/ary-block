<?php
/**
 * Desinstalação: apaga as duas opções do plugin. Os anexos criados pela colagem
 * são conteúdo do portal e ficam.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ary_block_config' );
delete_option( 'ary_block_registro' );
