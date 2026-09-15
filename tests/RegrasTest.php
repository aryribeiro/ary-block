<?php
/**
 * Regras do portão: aceita, recusa, isenta — com as entradas que o portal recebe.
 */

declare( strict_types=1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RegrasTest extends TestCase {

	private const EDITOR = [ 'admin' => false, 'usuario' => 7 ];
	private const ADMIN  = [ 'admin' => true, 'usuario' => 1 ];
	private const CRON   = [ 'admin' => false, 'usuario' => 0 ];

	private function config(): array {
		return Ary_Block_Regras::config_padrao();
	}

	// ---------------------------------------------------------------- padrões do dono

	public static function padroesDoDono(): array {
		return [
			'PNG bloqueado'            => [ 'capa.png', 50 * 1024, false, 'formato-bloqueado' ],
			'MP4 bloqueado'            => [ 'video.MP4', 5 * 1024 * 1024, false, 'formato-bloqueado' ],
			'WAV bloqueado'            => [ 'som.wav', 10 * 1024, false, 'formato-bloqueado' ],
			'JPG 289 KB passa'         => [ 'foto.jpg', 289 * 1024, true, 'aceito' ],
			'JPG 290 KB passa (igual)' => [ 'foto.jpg', 290 * 1024, true, 'aceito' ],
			'JPG 291 KB recusa'        => [ 'foto.jpeg', 291 * 1024, false, 'acima-do-limite' ],
			'WEBP 400 KB recusa'       => [ 'foto.webp', 400 * 1024, false, 'acima-do-limite' ],
			'PDF 9 MB passa'           => [ 'edital.pdf', 9 * 1024 * 1024, true, 'aceito' ],
			'PDF 11 MB recusa'         => [ 'edital.pdf', 11 * 1024 * 1024, false, 'acima-do-limite' ],
			'DOCX 11 MB recusa'        => [ 'texto.docx', 11 * 1024 * 1024, false, 'acima-do-limite' ],
			'MP3 900 KB passa'         => [ 'audio.mp3', 900 * 1024, true, 'aceito' ],
			'MP3 1,5 MB recusa'        => [ 'audio.mp3', 1536 * 1024, false, 'acima-do-limite' ],
			'SVG bloqueado'            => [ 'icone.svg', 2 * 1024, false, 'formato-bloqueado' ],
			'ZIP bloqueado'            => [ 'pacote.zip', 2 * 1024, false, 'formato-bloqueado' ],
			'MOV bloqueado (sugestão)' => [ 'clipe.mov', 2 * 1024, false, 'formato-bloqueado' ],
			'formato desconhecido passa (WP decide)' => [ 'arquivo.xyz', 2 * 1024, true, 'formato-fora-da-tabela' ],
			'sem extensão passa (WP decide)'         => [ 'semext', 2 * 1024, true, 'formato-fora-da-tabela' ],
		];
	}

	#[DataProvider( 'padroesDoDono' )]
	public function testPadroesParaEditor( string $nome, int $tamanho, bool $ok, string $motivo ): void {
		$r = Ary_Block_Regras::avaliar( $nome, $tamanho, $this->config(), self::EDITOR );
		self::assertSame( $ok, $r['ok'], $r['mensagem'] );
		self::assertSame( $motivo, $r['motivo'] );
		if ( ! $ok ) {
			self::assertNotSame( '', $r['mensagem'] );
		}
	}

	// ---------------------------------------------------------------- isenções

	#[DataProvider( 'padroesDoDono' )]
	public function testAdminPassaPorTudo( string $nome, int $tamanho ): void {
		$r = Ary_Block_Regras::avaliar( $nome, $tamanho, $this->config(), self::ADMIN );
		self::assertTrue( $r['ok'] );
		self::assertSame( 'isento-admin', $r['motivo'] );
	}

	#[DataProvider( 'padroesDoDono' )]
	public function testSemUsuarioPassaPorTudo( string $nome, int $tamanho ): void {
		$r = Ary_Block_Regras::avaliar( $nome, $tamanho, $this->config(), self::CRON );
		self::assertTrue( $r['ok'] );
		self::assertSame( 'isento-sistema', $r['motivo'] );
	}

	public function testCrawlerSobeCapaPngDeMadrugada(): void {
		$r = Ary_Block_Regras::avaliar( 'capa-sacar-o-fgts.png', 900 * 1024, $this->config(), self::CRON );
		self::assertTrue( $r['ok'] );
	}

	// ---------------------------------------------------------------- mensagens

	public function testMensagemDeVideoMandaParaOYoutube(): void {
		$r = Ary_Block_Regras::avaliar( 'entrevista.mp4', 1, $this->config(), self::EDITOR );
		self::assertStringContainsString( 'YouTube', $r['mensagem'] );
		self::assertStringContainsString( 'URL', $r['mensagem'] );
	}

	public function testMensagemDeLimiteTrazOsNumeros(): void {
		$r = Ary_Block_Regras::avaliar( 'foto.jpg', 1200 * 1024, $this->config(), self::EDITOR );
		self::assertStringContainsString( '1,2 MB', $r['mensagem'] );
		self::assertStringContainsString( '290 KB', $r['mensagem'] );
		self::assertStringContainsString( '1600 px', $r['mensagem'] );
		self::assertStringContainsString( 'WhatsApp', $r['mensagem'] );
	}

	public function testMensagemDePngEnsinaAColar(): void {
		$r = Ary_Block_Regras::avaliar( 'print.png', 10, $this->config(), self::EDITOR );
		self::assertStringContainsString( 'PNG', $r['mensagem'] );
		self::assertStringContainsString( 'Ctrl+V', $r['mensagem'] );
		self::assertStringContainsString( '290 KB', $r['mensagem'] );
	}

	public function testMensagemDeDocumentoListaAceitos(): void {
		$r = Ary_Block_Regras::avaliar( 'arquivo.rar', 10, $this->config(), self::EDITOR );
		self::assertStringContainsString( 'RAR', $r['mensagem'] );
		self::assertStringContainsString( 'não são aceitos', $r['mensagem'] );
	}

	public function testHumano(): void {
		self::assertSame( '290 KB', Ary_Block_Regras::humano_kb( 290 ) );
		self::assertSame( '1 MB', Ary_Block_Regras::humano_kb( 1024 ) );
		self::assertSame( '10 MB', Ary_Block_Regras::humano_kb( 10240 ) );
		self::assertSame( '1,5 MB', Ary_Block_Regras::humano_kb( 1536 ) );
		self::assertSame( '12,0 MB', Ary_Block_Regras::humano_bytes( 12 * 1024 * 1024 ) );
		self::assertSame( '3 KB', Ary_Block_Regras::humano_bytes( 2500 ) );
	}

	// ---------------------------------------------------------------- modo aviso

	public function testModoAvisoDeixaPassarMasMarca(): void {
		$c               = $this->config();
		$c['modo_aviso'] = true;
		$r               = Ary_Block_Regras::avaliar( 'video.mp4', 10, $c, self::EDITOR );
		self::assertTrue( $r['ok'] );
		self::assertTrue( $r['aviso'] );
		self::assertSame( 'formato-bloqueado', $r['motivo'] );
	}

	// ---------------------------------------------------------------- apelidos de extensão

	/**
	 * O buraco que a versão 1.0.2 fechou.
	 *
	 * O WordPress aceita mais extensão do que a tabela listava, e várias são
	 * apelidos da mesma coisa (`wp_get_mime_types()` traz `jpg|jpeg|jpe`,
	 * `mov|qt`, `mp3|m4a|m4b`, `gz|gzip`). Toda extensão fora da tabela caía
	 * em "formato-fora-da-tabela", que aceita SEM LIMITE. Bastava renomear
	 * `filme.mp4` para `filme.ogv` para subir vídeo de tamanho ilimitado no
	 * portal que este plugin existe para proteger.
	 */
	public static function apelidos(): array {
		return [
			// Vídeo: todo apelido segue o MP4, que vai para o YouTube.
			'OGV segue o vídeo'  => [ 'filme.ogv', 80 * 1024 * 1024, false, 'formato-bloqueado' ],
			'MPG segue o vídeo'  => [ 'filme.mpg', 80 * 1024 * 1024, false, 'formato-bloqueado' ],
			'MPEG segue o vídeo' => [ 'filme.mpeg', 80 * 1024 * 1024, false, 'formato-bloqueado' ],
			'FLV segue o vídeo'  => [ 'filme.flv', 80 * 1024 * 1024, false, 'formato-bloqueado' ],
			'DIVX segue o vídeo' => [ 'filme.divx', 80 * 1024 * 1024, false, 'formato-bloqueado' ],
			'QT segue o MOV'     => [ 'filme.qt', 80 * 1024 * 1024, false, 'formato-bloqueado' ],
			'3GPP segue o 3GP'   => [ 'filme.3gpp', 80 * 1024 * 1024, false, 'formato-bloqueado' ],
			'ASX segue o WMV'    => [ 'filme.asx', 80 * 1024 * 1024, false, 'formato-bloqueado' ],
			// Imagem: o apelido herda o limite, não a ausência dele.
			'JPE tem o limite do JPG' => [ 'capa.jpe', 5 * 1024 * 1024, false, 'acima-do-limite' ],
			'JPE dentro do limite passa' => [ 'capa.jpe', 200 * 1024, true, 'aceito' ],
			'AVIF é imagem aceita'    => [ 'capa.avif', 200 * 1024, true, 'aceito' ],
			'AVIF pesado recusa'      => [ 'capa.avif', 5 * 1024 * 1024, false, 'acima-do-limite' ],
			'HEIF segue o HEIC'       => [ 'capa.heif', 200 * 1024, false, 'formato-bloqueado' ],
			// Áudio.
			'M4B segue o M4A' => [ 'livro.m4b', 60 * 1024 * 1024, false, 'formato-bloqueado' ],
			'OGA segue o OGG' => [ 'som.oga', 60 * 1024 * 1024, false, 'formato-bloqueado' ],
			'WMA segue o WAV' => [ 'som.wma', 60 * 1024 * 1024, false, 'formato-bloqueado' ],
			// Compactados.
			'GZIP segue o GZ'  => [ 'pacote.gzip', 60 * 1024 * 1024, false, 'formato-bloqueado' ],
			'TAR segue o ZIP'  => [ 'pacote.tar', 60 * 1024 * 1024, false, 'formato-bloqueado' ],
			// Código: HTML na biblioteca vira página no próprio domínio.
			'HTML bloqueado' => [ 'pagina.html', 2 * 1024, false, 'formato-bloqueado' ],
			'HTM segue o HTML' => [ 'pagina.htm', 2 * 1024, false, 'formato-bloqueado' ],
			'JS bloqueado'   => [ 'script.js', 2 * 1024, false, 'formato-bloqueado' ],
			'PHP bloqueado'  => [ 'shell.php', 2 * 1024, false, 'formato-bloqueado' ],
			'EXE bloqueado'  => [ 'programa.exe', 2 * 1024, false, 'formato-bloqueado' ],
			// Documento: herda o limite do parente, em vez de não ter nenhum.
			'DOCM segue o DOCX' => [ 'texto.docm', 11 * 1024 * 1024, false, 'acima-do-limite' ],
			'XPS segue o PDF'   => [ 'edital.xps', 11 * 1024 * 1024, false, 'acima-do-limite' ],
			'ODS segue o ODT'   => [ 'planilha.ods', 11 * 1024 * 1024, false, 'acima-do-limite' ],
		];
	}

	#[DataProvider( 'apelidos' )]
	public function testApelidoHerdaARegraDoCanonico( string $nome, int $tamanho, bool $ok, string $motivo ): void {
		$r = Ary_Block_Regras::avaliar( $nome, $tamanho, $this->config(), self::EDITOR );
		self::assertSame( $ok, $r['ok'], $r['mensagem'] );
		self::assertSame( $motivo, $r['motivo'] );
	}

	public function testMp3ContinuaAceitoEmboraM4aSejaBloqueado(): void {
		// A chave do núcleo é `mp3|m4a|m4b`. Descartar a chave inteira porque
		// `m4a` está bloqueada derrubaria o MP3, que é aceito. Por isso a
		// correção está na cobertura da tabela, e não na remontagem da chave.
		$r = Ary_Block_Regras::avaliar( 'audio.mp3', 900 * 1024, $this->config(), self::EDITOR );
		self::assertTrue( $r['ok'] );
		self::assertSame( 'aceito', $r['motivo'] );
	}

	public function testBloqueadasIncluemOsApelidos(): void {
		$b = Ary_Block_Regras::extensoes_bloqueadas( $this->config() );
		foreach ( [ 'qt', 'ogv', 'mpg', 'm4b', 'oga', 'gzip', '3gpp', 'tar', 'html', 'htm', 'heif' ] as $ext ) {
			self::assertContains( $ext, $b, "o apelido {$ext} precisa sair da lista de tipos permitidos" );
		}
		// Apelido de formato ACEITO não pode entrar na lista de bloqueio.
		foreach ( [ 'jpe', 'docm', 'xps' ] as $ext ) {
			self::assertNotContains( $ext, $b, "{$ext} é apelido de formato aceito" );
		}
	}

	public function testMensagemFalaDaExtensaoQueORedatorEnviou(): void {
		$r = Ary_Block_Regras::avaliar( 'capa.jpe', 5 * 1024 * 1024, $this->config(), self::EDITOR );
		self::assertStringContainsString( 'JPE', $r['mensagem'], 'quem mandou .jpe precisa ler JPE' );
	}

	public function testLiberarOCanonicoLiberaOApelido(): void {
		$c = Ary_Block_Regras::mesclar( [ 'formatos' => [ 'mp4' => [ 'bloquear' => '', 'limite_kb' => 2048 ] ] ] );
		$r = Ary_Block_Regras::avaliar( 'filme.ogv', 1024 * 1024, $c, self::EDITOR );
		self::assertTrue( $r['ok'], 'apelido segue a configuração do canônico' );
		$r2 = Ary_Block_Regras::avaliar( 'filme.ogv', 5 * 1024 * 1024, $c, self::EDITOR );
		self::assertFalse( $r2['ok'], 'e segue também o limite dele' );
	}

	// ---------------------------------------------------------------- configuração

	public function testMesclarMantemPadroesEForcaTipos(): void {
		$c = Ary_Block_Regras::mesclar( [
			'largura_max_px' => '999999',
			'modo_aviso'     => '1',
			'formatos'       => [
				'PNG' => [ 'bloquear' => '', 'limite_kb' => '500' ],
				'svg' => [ 'bloquear' => '' ],
				'xyz' => [ 'bloquear' => '1' ],
			],
		] );
		self::assertSame( 8000, $c['largura_max_px'] );
		self::assertTrue( $c['modo_aviso'] );
		self::assertFalse( $c['formatos']['png']['bloquear'] );
		self::assertSame( 500, $c['formatos']['png']['limite_kb'] );
		self::assertTrue( $c['formatos']['svg']['bloquear'], 'SVG nunca é liberado' );
		self::assertArrayNotHasKey( 'xyz', $c['formatos'], 'extensão desconhecida sem grupo não entra' );
		self::assertSame( 'imagens', $c['formatos']['png']['grupo'] );
		self::assertArrayHasKey( 'mp4', $c['formatos'], 'formato não enviado continua com o padrão' );
	}

	public function testMesclarComLixoDevolvePadrao(): void {
		self::assertSame( Ary_Block_Regras::config_padrao(), Ary_Block_Regras::mesclar( 'lixo' ) );
		self::assertSame( Ary_Block_Regras::config_padrao(), Ary_Block_Regras::mesclar( false ) );
	}

	public function testFormularioCheckboxDesmarcadoDesbloqueia(): void {
		$atual = $this->config();
		$bruto = [
			'largura_max_px' => '1200',
			'mensagem_video' => '  Vídeo? <b>YouTube</b>  ',
			'formatos'       => [
				'png' => [ 'limite_kb' => '350' ],          // sem 'bloquear' = desmarcado
				'mp4' => [ 'bloquear' => '1', 'limite_kb' => '0' ],
				'pdf' => [ 'limite_kb' => '1024' ],
			],
		];
		$c = Ary_Block_Regras::sanitizar_formulario( $bruto, $atual );
		self::assertFalse( $c['formatos']['png']['bloquear'] );
		self::assertSame( 350, $c['formatos']['png']['limite_kb'] );
		self::assertTrue( $c['formatos']['mp4']['bloquear'] );
		self::assertSame( 1024, $c['formatos']['pdf']['limite_kb'] );
		self::assertFalse( $c['modo_aviso'], 'checkbox ausente = desligado' );
		self::assertFalse( $c['registrar'] );
		self::assertSame( 1200, $c['largura_max_px'] );
		self::assertSame( 'Vídeo? YouTube', $c['mensagem_video'] );
		// Formatos que a tela não mandou ficam como estavam.
		self::assertTrue( $c['formatos']['wav']['bloquear'] );
	}

	public function testLimiteDeImagemEOMenorDosAceitos(): void {
		$c = $this->config();
		self::assertSame( 290, Ary_Block_Regras::limite_imagem( $c ) );
		$c['formatos']['webp']['limite_kb'] = 200;
		self::assertSame( 200, Ary_Block_Regras::limite_imagem( $c ) );
	}

	public function testExtensoesBloqueadasIncluemPngMp4WavSvg(): void {
		$b = Ary_Block_Regras::extensoes_bloqueadas( $this->config() );
		foreach ( [ 'png', 'mp4', 'wav', 'svg', 'zip', 'mov' ] as $e ) {
			self::assertContains( $e, $b );
		}
		self::assertNotContains( 'jpg', $b );
		self::assertNotContains( 'pdf', $b );
	}

	public function testImagemDoProprioSiteENativa(): void {
		$home = 'https://diretonoticias.com.br';
		self::assertTrue( Ary_Block_Regras::mesmo_site( 'https://diretonoticias.com.br/wp-content/uploads/2026/09/capa.jpg', $home ) );
		self::assertTrue( Ary_Block_Regras::mesmo_site( 'HTTP://WWW.DIRETONOTICIAS.COM.BR/x.jpg', $home ) );
		self::assertTrue( Ary_Block_Regras::mesmo_site( 'https://diretonoticias.com.br/a.jpg', 'https://www.diretonoticias.com.br' ) );
		self::assertFalse( Ary_Block_Regras::mesmo_site( 'https://images.pexels.com/foto.jpg', $home ) );
		self::assertFalse( Ary_Block_Regras::mesmo_site( 'https://diretonoticias.com.br.evil.com/a.jpg', $home ) );
		self::assertFalse( Ary_Block_Regras::mesmo_site( 'sem-esquema', $home ) );
		self::assertFalse( Ary_Block_Regras::mesmo_site( '', $home ) );
	}

	public function testExtensao(): void {
		self::assertSame( 'jpg', Ary_Block_Regras::extensao( 'Foto.JPG' ) );
		self::assertSame( 'gz', Ary_Block_Regras::extensao( 'backup.tar.gz' ) );
		self::assertSame( '', Ary_Block_Regras::extensao( 'sem-ponto' ) );
		self::assertSame( '', Ary_Block_Regras::extensao( 'termina.' ) );
		self::assertSame( '', Ary_Block_Regras::extensao( 'x.<script>' ) );
	}
}
