from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
root=Path(__file__).resolve().parent.parent
(root/'build').mkdir(exist_ok=True)
for folder,name in [('wordpress-theme/babyjoy','babyjoy'),('wordpress-plugin/babyjoy-core','babyjoy-core')]:
 with ZipFile(root/'build'/f'{name}.zip','w',ZIP_DEFLATED) as z:
  for p in sorted((root/folder).rglob('*')):
   if p.is_file() and p.name!='.DS_Store':z.write(p,Path(name)/p.relative_to(root/folder))
 print(root/'build'/f'{name}.zip')
