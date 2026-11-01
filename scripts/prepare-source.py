"""Convert the supplied reference into import data, never executable instructions."""
from pathlib import Path
from bs4 import BeautifulSoup, NavigableString, Comment
import json,re,shutil,zipfile
ROOT=Path(__file__).resolve().parent.parent
src=ROOT/'BabyJoy9.57'
if not src.exists():
 with zipfile.ZipFile('/Users/puskar/Downloads/BabyJoy9.57.zip') as z:z.extractall(src)
theme=ROOT/'wordpress-theme/babyjoy'
shutil.copytree(src/'assets',theme/'assets',dirs_exist_ok=True)
data={'pages':[],'products':[],'chrome':{},'retailers':[],'socials':[]}
def soup(lang,name):return BeautifulSoup((src/('en' if lang=='en' else '')/name).read_text(),'html.parser')
def asset(s):return re.sub(r'^(?:\.\./|\./)*assets/','',s)
def body(s):
 h=s.header;f=s.footer
 return ''.join(str(x) for x in h.next_siblings if x is not f) .split('<footer')[0]
# Stop exactly at the footer, and discard source scripts.
def main(s):
 out=[]
 for x in s.header.next_siblings:
  if x is s.footer:break
  if not isinstance(x,Comment):out.append(str(x))
 return BeautifulSoup(''.join(out),'html.parser')
def tokenize(s):
 fields=[]
 for comment in list(s.find_all(string=lambda t:isinstance(t,Comment))):comment.extract()
 for tag in list(s.select('script')):tag.decompose()
 for el in s.find_all(True):
  for key in list(el.attrs):
   if key.startswith('on'):del el[key]
  for key in ['src','poster','href','data-img']:
   if el.has_attr(key) and re.match(r'^(?:\.\./|\./)*assets/',el[key]):el[key]='{{asset:'+asset(el[key])+'}}'
  if el.has_attr('srcset'):del el['srcset']
 for node in list(s.find_all(string=True)):
  if node.parent.name in ['style','script'] or not str(node).strip() or str(node).lstrip().startswith('[if'):continue
  value=str(node).strip()
  if value.startswith('<!--'):continue
  i=len(fields);fields.append({'label':value[:90],'value':value});node.replace_with('{{text:'+str(i)+'}}')
 for el in s.find_all(True):
  for key in ['alt','aria-label','placeholder','title']+(['href'] if el.get('href','').startswith(('https://','http://','tel:')) else []):
   if el.get(key):
    i=len(fields);fields.append({'label':key+': '+el[key][:70],'value':el[key]});el[key]='{{text:'+str(i)+'}}'
 return {'markup':str(s),'fields':fields}
for lang in ['en','ar']:
 home=soup(lang,'index.html')
 header=BeautifulSoup(str(home.header),'html.parser')
 nav=header.select_one('.bjx-nav')
 data['chrome'][lang]={'nav':[{'label':a.text,'url':a['href']} for a in nav.select('a')], 'header':tokenize(header),'footer':tokenize(BeautifulSoup(str(home.footer),'html.parser'))}
 for p in ['index','about','contact','guide','terms','privacy','aggregators','products']:
  s=soup(lang,p+'.html');m=main(s)
  for social in m.select('ul.mt-social'):social.replace_with(NavigableString('[babyjoy_socials]'))
  for a in m.select('a.s8-pack[href]'):
   slug=a['href'].replace('product-','').replace('.html','')
   a.replace_with(NavigableString('[babyjoy_pack slug="'+slug+'"]'))
  if p=='contact':
   email_link=m.select_one('a[href^="mailto:"]')
   if email_link:email_link.parent.replace_with(NavigableString('[babyjoy_contact_details]'))
   for a in m.select('a.mt-btn'):a['data-contact-open']=''
  if p=='products':m.select_one('.bjx-fam').replace_with(NavigableString('[babyjoy_products]'))
  if p=='aggregators':
   for el in m.select('.bjx-ret,.shop-tiles'):el.replace_with(NavigableString('[babyjoy_retailers]'))
  if p=='index':
   for el in m.select('.bjx-ret,.shop-tiles'):el.replace_with(NavigableString('[babyjoy_retailers]'))
   sg=m.select_one('.bjx-sg')
   if sg:sg['data-dynamic']='1'
  title=s.title.text.split('|')[0].strip()
  h=m.find(['h1','h2'])
  if h:title=h.get_text(' ',strip=True)
  d=tokenize(m);d.update({'slug':p,'language':lang,'title':title});data['pages'].append(d)
for p in sorted((src/'en').glob('product-*.html')):
 slug=p.stem.removeprefix('product-');s=soup('en',p.name);ar=soup('ar',p.name)
 product={'slug':slug,'title':s.h1.get_text(' ',strip=True),'title_ar':ar.h1.get_text(' ',strip=True),'image':asset((s.select_one('#pp-img') or s.select_one('.bjx-pp .pk img') or s.select_one('.pk img'))['src']),'sizes':[],'benefits':[],'benefits_ar':[],'attributes':[]}
 for b in s.select('.bjx-sizes button'):
  w=BeautifulSoup(b.get('data-w',''),'html.parser').get_text(' ',strip=True)
  nums=re.findall(r'\d+(?:\.\d+)?',w)
  product['sizes'].append({'size':b['data-s'],'weight':w,'min':float(nums[0]) if nums else None,'max':float(nums[1]) if len(nums)>1 else None,'image':asset(b['data-img']),'packs':b.get('data-pk','')})
 product['benefits']=[x.text for x in s.select('.bjx-ben li')];product['benefits_ar']=[x.text for x in ar.select('.bjx-ben li')]
 for enrow,arrow in zip(s.select('.bjx-spec tr'),ar.select('.bjx-spec tr')):
  if not enrow.select_one('[id]'):
   product['attributes'].append({'name':enrow.th.text,'value':enrow.td.text,'name_ar':arrow.th.text,'value_ar':arrow.td.text})
 product['collection']='olive' if 'olive' in slug else 's8' if 'super-big' in slug else 'comp' if slug in ['compressed-tape','pants'] else 'wipes'
 product['type']='pants' if 'pants' in slug else 'tape' if 'tape' in slug else 'wipes'
 data['products'].append(product)
home=soup('en','index.html')
for a in home.select('#shop .shop-tiles a'):
 data['retailers'].append({'name':a.get('title',a.img.get('alt','')),'url':a['href'],'logo':asset(a.img['src']),'enabled':True})
for a in home.select('header .mt-social a'):data['socials'].append({'name':a.img.get('alt',''),'url':a['href'],'icon':asset(a.img['src'])})
# Keep decorative layout CSS, but remove stale source URLs and static global UI.
styles='\n'.join(x.text for x in home.select('head style'))
styles=re.sub(r'(?:\.\./)?assets/', '../',styles)
(theme/'assets/css/imported.css').write_text(styles)
(ROOT/'build/import-data.json').write_text(json.dumps(data,ensure_ascii=False,indent=2))
print('Prepared',len(data['pages']),'pages;',len(data['products']),'products;',len(data['retailers']),'retailers')
