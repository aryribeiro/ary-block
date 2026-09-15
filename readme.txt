=== Ary Block ===
Contributors: aryribeiro
Tags: upload, media, images, paste, editor
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.2
License: GPL-2.0-or-later

Portão de upload com mensagens amigáveis e colagem de imagem direto no editor, com gravação automática na biblioteca.

== Description ==

Dois em um, para portais com colunistas voluntários:

1. Portão de upload. Para quem está abaixo de administrador, recusa na biblioteca de mídia os formatos bloqueados (vídeo, PNG, WAV, SVG, compactados...) e os arquivos acima do limite do seu formato (imagens 290 KB, PDF e DOCX 10 MB, MP3 1 MB por padrão), com mensagem que diz o número e, para vídeo, orienta a publicar no YouTube e colar a URL. Administradores e publicações automáticas nunca são bloqueados.

2. Colar imagem. Ctrl+V de uma imagem no editor clássico (Visual ou Texto) converte para JPG leve, grava na biblioteca anexada ao post e insere no texto. Aceita bitmap da área de transferência e imagem copiada da web.

Tela de configuração só para administradores: formatos, limites, largura máxima ao colar, modo "só avisar" e registro dos últimos bloqueios.

== Changelog ==

= 1.0.2 =
* Segurança: a rota de colagem passa a exigir a capacidade `upload_files`, e não apenas `edit_posts`. Quem grava na biblioteca de mídia precisa ter permissão de gravar na biblioteca de mídia.
* Segurança: apelidos de extensão que o WordPress aceita (`.jpe`, `.ogv`, `.mpg`, `.qt`, `.m4b`, `.oga`, `.gzip`, `.3gpp` e outros) herdam a regra do formato canônico. Antes eles ficavam fora da tabela e passavam sem limite nenhum — dava para subir vídeo de tamanho ilimitado renomeando o arquivo.
* Segurança: novo grupo "Código e executáveis" (HTML, JS, CSS, PHP, EXE, SWF), bloqueado por padrão.
* Segurança: teto de 50 megapixels na entrada da colagem, contra bomba de descompressão, e conferência do tamanho anunciado antes de baixar imagem por endereço.
* Desempenho: a normalização abre o editor de imagem uma vez por rodada, e não a cada tentativa de qualidade — de até 24 decodificações do arquivo original para no máximo 6.
* AVIF passa a ser aceito como imagem, com o mesmo limite dos demais formatos.
* Correção: o temporário da normalização é apagado também quando o editor de imagem falha no meio do caminho.

= 1.0.1 =
* Imagem do próprio portal colada no editor é nativa: não gera anexo novo (pedido do dono, 13/09).

= 1.0.0 =
* Primeira versão (13/09/2026).
