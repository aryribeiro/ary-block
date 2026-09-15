/**
 * Ary Block — colar imagem no editor clássico (Visual e Texto).
 *
 * Intercepta o Ctrl+V que traz uma imagem (bitmap da área de transferência ou
 * <img> copiado da web), converte o bitmap para JPG dentro do limite no próprio
 * navegador, envia por AJAX e insere no texto o HTML devolvido pelo servidor —
 * já apontando para a biblioteca de mídia. Colagem de texto puro não é tocada.
 */
(function ($) {
	'use strict';

	var cfg = window.aryBlockColar || {};
	if (!cfg.ajaxurl) {
		return;
	}
	var contador = 0;

	// Imagem que já é do portal (copiada de dentro do próprio texto ou da
	// biblioteca) é nativa: não se grava de novo. Só o que vem de fora entra.
	function ehDoProprioSite(url) {
		try {
			var u = new URL(url, location.href);
			var meu = location.hostname.replace(/^www\./, '');
			var dele = u.hostname.replace(/^www\./, '');
			if (meu === dele) { return true; }
			if (cfg.uploads_url && url.indexOf(cfg.uploads_url) === 0) { return true; }
		} catch (e) { /* URL inválida: não é do site */ }
		return false;
	}

	function lerClipboard(cd) {
		if (!cd) {
			return null;
		}
		var i, item;
		// Primeiro o HTML: se ele traz <img> do próprio site, a colagem é nativa,
		// mesmo que o navegador também tenha posto um bitmap no clipboard.
		var htmlPrevio = cd.getData ? cd.getData('text/html') : '';
		if (htmlPrevio) {
			var mp = htmlPrevio.match(/<img[^>]+src=["']([^"']+)["']/i);
			if (mp && /^https?:\/\//i.test(mp[1]) && ehDoProprioSite(mp[1])) {
				return { tipo: 'nativo' };
			}
		}
		if (cd.items) {
			for (i = 0; i < cd.items.length; i++) {
				item = cd.items[i];
				if (item.kind === 'file' && /^image\//.test(item.type)) {
					return { tipo: 'arquivo', arquivo: item.getAsFile() };
				}
			}
		}
		if (cd.files && cd.files.length && /^image\//.test(cd.files[0].type)) {
			return { tipo: 'arquivo', arquivo: cd.files[0] };
		}
		var html = cd.getData ? cd.getData('text/html') : '';
		if (html) {
			var m = html.match(/<img[^>]+src=["']([^"']+)["']/i);
			if (m && /^https?:\/\//i.test(m[1])) {
				return { tipo: 'url', url: m[1] };
			}
			if (m && /^data:image\//i.test(m[1])) {
				return { tipo: 'dataurl', url: m[1] };
			}
		}
		var texto = cd.getData ? cd.getData('text/plain') : '';
		if (texto && /^https?:\/\/\S+\.(jpe?g|png|webp|gif)(\?\S*)?$/i.test(texto.trim())) {
			return { tipo: 'url', url: texto.trim() };
		}
		return null;
	}

	function carregarImagem(fonte) {
		return new Promise(function (resolve, reject) {
			var img = new Image();
			img.onload = function () { resolve(img); };
			img.onerror = function () { reject(new Error('imagem ilegível')); };
			img.src = fonte;
		});
	}

	function canvasParaBlob(canvas, qualidade) {
		return new Promise(function (resolve) {
			canvas.toBlob(function (b) { resolve(b); }, 'image/jpeg', qualidade);
		});
	}

	// Converte para JPG e reduz até caber no limite (largura e qualidade).
	async function converter(arquivoOuDataUrl) {
		var fonte = typeof arquivoOuDataUrl === 'string' ? arquivoOuDataUrl : URL.createObjectURL(arquivoOuDataUrl);
		var img = await carregarImagem(fonte);
		var limite = (parseInt(cfg.limite_kb, 10) || 290) * 1024;
		var largura = Math.min(img.naturalWidth || img.width, parseInt(cfg.largura_max, 10) || 1600);
		var blob = null;
		for (var rodada = 0; rodada < 6; rodada++) {
			var escala = largura / (img.naturalWidth || img.width);
			var canvas = document.createElement('canvas');
			canvas.width = Math.max(1, Math.round((img.naturalWidth || img.width) * escala));
			canvas.height = Math.max(1, Math.round((img.naturalHeight || img.height) * escala));
			var ctx = canvas.getContext('2d');
			ctx.fillStyle = '#ffffff'; // PNG transparente vira fundo branco, não preto.
			ctx.fillRect(0, 0, canvas.width, canvas.height);
			ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
			var qualidades = [0.86, 0.76, 0.66, 0.56];
			for (var q = 0; q < qualidades.length; q++) {
				blob = await canvasParaBlob(canvas, qualidades[q]);
				if (blob && blob.size <= limite) {
					if (typeof arquivoOuDataUrl !== 'string') { URL.revokeObjectURL(fonte); }
					return blob;
				}
			}
			largura = Math.floor(largura * 0.8);
			if (largura < 480) { break; }
		}
		if (typeof arquivoOuDataUrl !== 'string') { URL.revokeObjectURL(fonte); }
		return blob; // o servidor tenta de novo e recusa se não couber
	}

	async function enviar(alvo) {
		var fd = new FormData();
		fd.append('action', cfg.acao);
		fd.append('nonce', cfg.nonce);
		// post-new.php não tem ?post=; o ID do rascunho automático está no campo oculto.
		var postId = parseInt(cfg.post_id, 10) || parseInt($('#post_ID').val(), 10) || 0;
		fd.append('post_id', postId);
		if (alvo.tipo === 'url') {
			fd.append('url', alvo.url);
		} else {
			var blob = await converter(alvo.tipo === 'dataurl' ? alvo.url : alvo.arquivo);
			if (!blob) { throw new Error('conversão falhou'); }
			fd.append('imagem', blob, 'colado.jpg');
		}
		var resp = await fetch(cfg.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' });
		var json = await resp.json();
		if (!json || !json.success) {
			throw new Error((json && json.data && json.data.mensagem) || cfg.textos.falhou);
		}
		return json.data;
	}

	function idMarcador() {
		contador++;
		return 'ary-block-colando-' + Date.now() + '-' + contador;
	}

	// ---- Editor Visual (TinyMCE) -------------------------------------------
	$(document).on('tinymce-editor-init', function (e, editor) {
		editor.on('paste', function (ev) {
			var alvo = lerClipboard(ev.clipboardData || window.clipboardData);
			if (!alvo || alvo.tipo === 'nativo') {
				return; // texto, ou imagem que já é do portal: deixa o TinyMCE cuidar
			}
			ev.preventDefault();
			ev.stopImmediatePropagation();
			var id = idMarcador();
			editor.insertContent('<p id="' + id + '" class="ary-block-enviando">&#9203; ' + cfg.textos.enviando + '</p>');
			enviar(alvo).then(function (d) {
				var el = editor.dom.get(id);
				if (el) {
					editor.dom.setOuterHTML(el, '<p>' + d.html + '</p>');
				} else {
					editor.insertContent('<p>' + d.html + '</p>');
				}
				editor.undoManager.add();
			}).catch(function (err) {
				var el = editor.dom.get(id);
				if (el) { editor.dom.remove(el); }
				editor.notificationManager.open({ text: 'Ary Block: ' + err.message, type: 'error', timeout: 8000 });
			});
		}, true); // antes do próprio plugin de colagem do TinyMCE
	});

	// ---- Editor Texto (textarea) --------------------------------------------
	$(document).on('paste', 'textarea#content', function (ev) {
		var cd = ev.originalEvent && ev.originalEvent.clipboardData;
		var alvo = lerClipboard(cd);
		if (!alvo || alvo.tipo === 'nativo') {
			return;
		}
		ev.preventDefault();
		var ta = this;
		var marca = '[' + cfg.textos.enviando + ']';
		var ini = ta.selectionStart, fim = ta.selectionEnd;
		ta.value = ta.value.slice(0, ini) + marca + ta.value.slice(fim);
		enviar(alvo).then(function (d) {
			ta.value = ta.value.replace(marca, d.html);
			$(ta).trigger('change');
		}).catch(function (err) {
			ta.value = ta.value.replace(marca, '');
			window.alert('Ary Block: ' + err.message);
		});
	});
})(jQuery);
