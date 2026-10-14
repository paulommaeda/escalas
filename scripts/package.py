from pathlib import Path
import re,zipfile
root=Path(__file__).resolve().parents[1]
header=(root/'ccshalom-escalas.php').read_text()
version=re.search(r'Version: (\d+\.\d+\.\d+)',header).group(1)
assert f"define('CCS_VERSION', '{version}');" in header
assert f'Stable tag: {version}' in (root/'readme.txt').read_text()
out=root/'dist';out.mkdir(exist_ok=True)
with zipfile.ZipFile(out/'ccshalom-escalas.zip','w',zipfile.ZIP_DEFLATED) as z:
 for p in sorted(root.rglob('*')):
  if not p.is_file():continue
  rel=p.relative_to(root)
  if rel.parts[0] in {'.git','.github','tests','scripts','dist'} or rel.name in {'.gitignore','.gitattributes'}:continue
  z.write(p,'ccshalom-escalas/'+rel.as_posix())
with zipfile.ZipFile(out/'ccshalom-escalas.zip') as z:
 assert z.testzip() is None
 assert 'ccshalom-escalas/includes/updater.php' in z.namelist()
 assert 'ccshalom-escalas/assets/fonts/montserrat-latin.woff2' in z.namelist()
print(version)
