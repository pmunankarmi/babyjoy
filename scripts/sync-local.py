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
