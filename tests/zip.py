"""Gera ary-block/ary-block.zip com os arquivos publicáveis (mesma lista do deploy).

O PHP local não tem ZipArchive; por isso Python. Uso: python ary-block/tests/zip.py
"""
import os
import re
import zipfile

RAIZ = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
# .git entrou na lista quando a pasta virou repositório: sem ele aqui, o
# os.walk desceria no histórico inteiro e o empacotaria junto.
PULAR = {
    "tests",
    "checkpoints",
    "CONTRATO.md",
    "TESTES.md",
    ".phpunit.cache",
    ".git",
    ".github",
    ".gitignore",
    ".gitattributes",
}
with open(os.path.join(RAIZ, "ary-block.php"), encoding="utf-8") as f:
    versao = re.search(r"^\s*\*\s*Version:\s*(\S+)", f.read(), re.M).group(1)
saida = os.path.join(RAIZ, "ary-block.zip")
if os.path.exists(saida):
    os.remove(saida)
n = 0
with zipfile.ZipFile(saida, "w", zipfile.ZIP_DEFLATED) as z:
    for pasta, dirs, arquivos in os.walk(RAIZ):
        dirs[:] = [d for d in dirs if d not in PULAR]
        for nome in sorted(arquivos):
            if nome in PULAR or nome.endswith(".zip"):
                continue
            caminho = os.path.join(pasta, nome)
            rel = os.path.relpath(caminho, RAIZ).replace(os.sep, "/")
            z.write(caminho, "ary-block/" + rel)
            n += 1
print(f"ary-block.zip: versao {versao}, {n} arquivos, {os.path.getsize(saida)} bytes")
