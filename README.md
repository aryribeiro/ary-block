# Ary Block

**Portão de upload e colagem de imagem para o WordPress com editor clássico.**

Dois problemas antigos, resolvidos num plugin só:

1. **Colar imagem no editor não salva nada na biblioteca.** Você dá `Ctrl+V` numa notícia, a imagem aparece, você publica — e ninguém sabe de onde ela veio. Ou ficou embutida no texto, ou está apontando para o site de origem, que pode apagá-la amanhã. O editor de blocos resolveu isso; o editor clássico, que boa parte dos portais de notícia ainda usa, não.
2. **Qualquer pessoa sobe qualquer arquivo.** Um PNG de 11 MB, um MP4 de meia hora, um PDF de 30 MB. Em hospedagem compartilhada isso custa espaço e banda. E capa pesada demais **não abre na prévia do WhatsApp e das redes sociais** quando o link da notícia é compartilhado — o leitor recebe um retângulo cinza.

Feito para o [Direto Notícias](https://diretonoticias.com.br), portal de Guarapari (ES), onde colunistas e articulistas voluntários publicam todo dia.

---

## O que ele faz

### 1. Portão de upload

Para quem está **abaixo de administrador**, recusa na biblioteca de mídia os formatos bloqueados e os arquivos acima do limite, com mensagem que diz o número:

> O arquivo JPG tem 1,1 MB; o limite para JPG é 290 KB. Reduza a imagem (largura máxima 1600 px, JPG ou WEBP) — ou cole a imagem direto no texto da notícia (Ctrl+V), que o portal converte sozinho. Imagem pesada não abre na prévia do WhatsApp e das redes sociais.

Para vídeo, a mensagem ensina o caminho certo em vez de só barrar:

> Vídeos não são enviados para o portal. Publique o vídeo primeiro no canal do YouTube e depois cole a URL do vídeo dentro da notícia: o player aparece sozinho.

**Nunca são bloqueados:** administradores e qualquer publicação automática (cron, WP-CLI, REST autenticada, robô de publicação). Isso não é detalhe: um portão que só pergunta "é administrador?" derruba a publicação agendada às três da manhã e ninguém descobre até o dia seguinte.

### 2. Colar imagem

`Ctrl+V` de uma imagem no editor clássico, na aba Visual ou na aba Texto:

- o navegador converte para JPEG e reduz até caber no limite configurado;
- o servidor **reconverte por conta própria**, sem confiar no cliente, limita a largura e reduz a qualidade em passos até caber;
- grava na biblioteca de mídia, **anexada à notícia** e com o autor correto;
- insere a tag `<img>` já apontando para o arquivo da biblioteca.

Aceita bitmap da área de transferência e imagem copiada da web (endereço). **Imagem que já é do próprio site é tratada como nativa e não gera cópia** — copiar e colar um trecho do próprio texto não polui a biblioteca.

### 3. Tela de configuração

Só para administradores. Cada formato tem dois controles: bloquear e limite em KB. Mais a largura máxima ao colar, um modo "só avisar" e o registro dos últimos bloqueios, com quem tentou, qual arquivo e por quê.

---

## Decisões de projeto que valem ser explicadas

Este plugin nasceu de medição, não de preferência. As decisões abaixo foram tomadas contra a intuição, e é por isso que estão documentadas.

**O clipboard sempre entrega PNG.** Mesmo que você copie um JPG, o navegador coloca um bitmap PNG na área de transferência. Como o portão bloqueia PNG, a função de colar morreria pela função de bloquear na primeira tentativa. Por isso a conversão para JPEG acontece **antes** de o arquivo tocar o portão, e a normalização vale para todo mundo, administrador incluído: o objetivo é peso, não hierarquia.

**A conversão é feita duas vezes, de propósito.** No navegador, porque é grátis e não gasta CPU do servidor. No servidor, porque o cliente pode mentir. Se o servidor não tiver editor de imagem disponível, o arquivo só passa se já estiver dentro do limite.

**Execução sem usuário logado é isenta.** Um robô de publicação por cron não tem usuário; tratá-lo como visitante anônimo e bloqueá-lo quebra a publicação automática de madrugada, em silêncio. A regra é: `manage_options` passa, ausência de usuário passa, o resto é regulado.

**SVG nunca é liberado**, mesmo que o administrador marque. SVG é XML que pode conter script, e a biblioteca de mídia não o sanitiza.

**Uma lista de formatos é sempre menor do que se imagina.** Esta é a lição mais cara do projeto, e só apareceu numa revisão de segurança. O WordPress aceita 98 chaves de formato, e boa parte delas são apelidos da mesma coisa: `jpg|jpeg|jpe`, `mov|qt`, `mp3|m4a|m4b`, `gz|gzip`. A tabela original listava `mp4` e `mov`, mas não `.ogv`, `.mpg`, `.flv` nem `.qt` — e extensão fora da tabela era aceita **sem limite nenhum**. Bastava renomear o arquivo para subir vídeo de tamanho ilimitado no portal que o plugin existe para proteger.

A correção não foi listar mais extensões, que voltaria a furar quando o WordPress aceitasse a próxima. Foi um mapa de apelidos: cada extensão herda a regra do seu formato canônico. Liberar MP4 libera `.ogv` e `.mpg` junto, com o mesmo limite — que é o que quem configura a tela espera. O teste que prova isso roda o filtro contra a tabela real do núcleo e exige que nenhuma das 70 extensões sobreviventes caia no caminho sem limite.

**A camada de reforço era a porta lateral.** Para blindar, o plugin também retira os formatos bloqueados da lista de tipos permitidos do WordPress. Só que as chaves são compostas: tirar `m4a` de `mp3|m4a|m4b` deixava `mp3|m4b` de pé, e o `.m4b` entrava sem limite. A saída óbvia — descartar a chave inteira — derrubaria junto o MP3, que é aceito. Por isso a correção ficou na cobertura da tabela, e não na remontagem da chave.

**Colar imagem exige `upload_files`, não `edit_posts`.** São capacidades diferentes, e o papel Colaborador tem a segunda sem ter a primeira — exatamente o papel que um portal daria a um colunista voluntário. Como `media_handle_sideload()` não confere capacidade nenhuma, essa linha é a única guarda entre a rota e a biblioteca de mídia. Pedir a capacidade errada seria dar de presente uma permissão que a instalação nega por design.

**O limite de imagem é o menor entre os formatos aceitos**, e é ele que a colagem persegue. Assim não existe caminho pelo qual a colagem produza um arquivo que o portão recusaria.

**A decisão de layout fica com o editor.** A imagem colada não vira imagem de destaque automaticamente: isso é escolha editorial, não técnica.

---

## Padrões usados

| Onde | Por quê |
|---|---|
| `wp_handle_upload_prefilter` e `wp_handle_sideload_prefilter` | todo upload humano passa por eles; devolver a chave `error` faz o WordPress mostrar o texto ao redator, no uploader e no modal de mídia |
| `upload_mimes`, só para não-administrador | segunda camada: o formato totalmente bloqueado some da lista de tipos permitidos |
| `wp_get_image_editor` | usa GD ou Imagick, o que houver, sem dependência externa |
| `media_handle_sideload` | cria o anexo pelo caminho oficial, com metadados e tamanhos gerados |
| Classe de regras **pura**, sem WordPress dentro | é o que torna o comportamento testável sem subir um site |

## Testes

```bash
php phpunit.phar -c tests/phpunit.xml
```

95 testes, 246 asserções. Cobrem os limites por formato, os apelidos de extensão, as isenções, as mensagens com número, a sanitização do formulário (incluindo o caso em que o checkbox desmarcado precisa desligar a regra) e o reconhecimento do próprio domínio, com e sem `www`.

A verificação de ponta a ponta foi feita em [WordPress Playground](https://wordpress.github.io/wordpress-playground/) dirigido por Playwright: recusas com a mensagem certa para um usuário Editor, aceitação para administrador, colagem nas duas abas do editor, e uma imagem de 11 MB e 2.400 px virando um JPEG de 286 KB anexado ao post.

## Instalação

1. Baixe o `.zip` da [última versão](../../releases) ou clone este repositório na pasta `wp-content/plugins/`.
2. Ative o plugin.
3. Abra **Ary Block** no menu do painel e ajuste formatos e limites.

Requer PHP 8.1 ou superior e WordPress 6.0 ou superior. Testado no WordPress 7.0.

## Licença

GPL-2.0-or-later. Veja [LICENSE](LICENSE).

---

## English summary

**Ary Block** is a WordPress plugin for sites still on the Classic Editor. It does two things:

- **Upload gate.** Blocks formats and file sizes you choose, for everyone below administrator, with friendly messages that state the actual limit. Administrators and automated publishing (cron, WP-CLI, REST) are never blocked.
- **Paste images.** `Ctrl+V` an image into the editor and it is converted to JPEG, resized to fit your limit, saved to the Media Library attached to the post, and inserted — something the Classic Editor never did. Images already hosted on your own site are treated as native and never duplicated.

Configuration screen is administrator-only. Built and measured for a working news site; see the design notes above.
