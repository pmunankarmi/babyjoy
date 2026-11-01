"""Copy source into an existing Local site; never modify its database or plugins."""
from pathlib import Path
import argparse,shutil
p=argparse.ArgumentParser();p.add_argument('wordpress',type=Path);args=p.parse_args()
root=Path(__file__).resolve().parent.parent
content=args.wordpress/'wp-content'
if not (args.wordpress/'wp-config.php').exists():raise SystemExit('Expected an existing WordPress installation.')
for source,dest in [(root/'wordpress-theme/babyjoy',content/'themes/babyjoy'),(root/'wordpress-plugin/babyjoy-core',content/'plugins/babyjoy-core')]:
 if dest.is_symlink():dest.unlink()
 shutil.copytree(source,dest,dirs_exist_ok=True)
 print('Synced',dest)

# Media lives outside the theme so replacing/updating the theme cannot remove it.
media_source=root/'wordpress-content/uploads/babyjoy'
media_dest=content/'uploads/babyjoy'
shutil.copytree(media_source,media_dest,dirs_exist_ok=True)
for name in ['img','uploads','fonts']:
 old=content/'themes/babyjoy/assets'/name
 if old.exists():
  for file in old.rglob('*'):
   if file.is_file():
    target=media_dest/name/file.relative_to(old)
    if not target.exists() or file.read_bytes()!=target.read_bytes():
     raise SystemExit(f'Media verification failed: {file}')
  shutil.rmtree(old)
print('Synced media outside theme:',media_dest)
