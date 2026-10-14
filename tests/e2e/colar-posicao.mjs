// Prova do Ctrl+V REAL (teclado) no editor Visual: a imagem tem de cair onde o cursor está.
// Uso: node colar-posicao.mjs <png> [base=http://127.0.0.1:9400]
import { chromium } from 'playwright';
import { readFileSync } from 'fs';

const png = readFileSync(process.argv[2]).toString('base64');
const base = process.argv[3] || 'http://127.0.0.1:9400';
const browser = await chromium.launch();
const ctx = await browser.newContext();
await ctx.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: base });
const page = await ctx.newPage();
const erros = [];
page.on('pageerror', (e) => erros.push(e.message));

await page.goto(base + '/wp-login.php');
await page.fill('#user_login', 'editora');
await page.fill('#user_pass', 'Senha-Editora-123');
await page.click('#wp-submit');
await page.waitForLoadState('networkidle');

async function caso(nome, posicionar) {
	await page.goto(base + '/wp-admin/post-new.php');
	await page.waitForFunction(() => window.tinymce && tinymce.get('content') && tinymce.get('content').initialized, null, { timeout: 60000 });
	await page.evaluate(() => {
		const ed = tinymce.get('content');
		ed.setContent('<p>PRIMEIRO paragrafo.</p><p>SEGUNDO paragrafo.</p><p>TERCEIRO paragrafo.</p>');
	});
	await page.evaluate(async (b64) => {
		const bin = Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
		await navigator.clipboard.write([new ClipboardItem({ 'image/png': new Blob([bin], { type: 'image/png' }) })]);
	}, png);
	await page.frameLocator('#content_ifr').locator('body').click();
	await page.evaluate(posicionar);
	await page.keyboard.press('Control+V');
	await page.waitForFunction(() => /wp-image-\d+/.test(tinymce.get('content').getContent()), null, { timeout: 60000 }).catch(() => {});
	const html = await page.evaluate(() => tinymce.get('content').getContent());
	const blocos = (html.match(/<p[^>]*>[\s\S]*?<\/p>/g) || []).map((p) => (/wp-image-\d+/.test(p) ? 'IMAGEM' : p.replace(/<[^>]+>/g, '').trim().split(' ')[0]));
	const pastebin = await page.evaluate(() => !!tinymce.get('content').dom.get('mcepastebin'));
	console.log(nome.padEnd(22), '→', blocos.join(' | '), pastebin ? '| SOBROU #mcepastebin' : '');
}

await caso('cursor no TOPO', () => { const ed = tinymce.get('content'); ed.focus(); ed.selection.setCursorLocation(ed.getBody().firstChild, 0); });
await caso('cursor no MEIO', () => { const ed = tinymce.get('content'); ed.focus(); const p = ed.getBody().childNodes[1]; ed.selection.select(p, true); ed.selection.collapse(false); });
await caso('cursor MEIO DA FRASE', () => { const ed = tinymce.get('content'); ed.focus(); const t = ed.getBody().childNodes[1].firstChild; const r = ed.dom.createRng(); r.setStart(t, 7); r.collapse(true); ed.selection.setRng(r); });
// texto puro: o TinyMCE cuida sozinho; tem de cair no topo e nao deixar a area escondida
await page.goto(base + '/wp-admin/post-new.php');
await page.waitForFunction(() => window.tinymce && tinymce.get('content') && tinymce.get('content').initialized, null, { timeout: 60000 });
await page.evaluate(() => tinymce.get('content').setContent('<p>PRIMEIRO paragrafo.</p><p>SEGUNDO paragrafo.</p>'));
await page.evaluate(() => navigator.clipboard.writeText('TEXTOCOLADO '));
await page.frameLocator('#content_ifr').locator('body').click();
await page.evaluate(() => { const ed = tinymce.get('content'); ed.focus(); ed.selection.setCursorLocation(ed.getBody().firstChild.firstChild, 0); });
await page.keyboard.press('Control+V');
await page.waitForTimeout(1500);
console.log('texto puro no TOPO    →', (await page.evaluate(() => tinymce.get('content').getContent().replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim())),
	(await page.evaluate(() => !!tinymce.get('content').dom.get('mcepastebin'))) ? '| SOBROU #mcepastebin' : '');
console.log('erros de página:', erros.length ? erros.join(' / ') : 'nenhum');
await browser.close();
