<!doctype html>
<html lang="<?php echo esc_attr(bj_language()); ?>" dir="<?php echo bj_language()==='ar'?'rtl':'ltr'; ?>">
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(bj_language()==='ar'?'rtl':'ltr'); ?>><?php wp_body_open(); ?>
<?php
$header=bj_chrome('header');
libxml_use_internal_errors(true);$doc=new DOMDocument();$doc->loadHTML('<?xml encoding="UTF-8">'.$header,LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);$xpath=new DOMXPath($doc);
foreach($xpath->query('//a[contains(concat(" ",normalize-space(@class)," ")," bjx-hlogo ")]') as $a){while($a->firstChild)$a->removeChild($a->firstChild);$frag=$doc->createDocumentFragment();$logo=get_custom_logo();if($logo){$temp=new DOMDocument();$temp->loadHTML('<?xml encoding="UTF-8">'.$logo);$img=$temp->getElementsByTagName('img')->item(0);if($img)$a->appendChild($doc->importNode($img,true));}$a->setAttribute('href',bj_page_url('index'));}
foreach($xpath->query('//nav[contains(@class,"bjx-nav")]') as $nav){$frag=$doc->createDocumentFragment();ob_start();bj_nav();$html=ob_get_clean();$tmp=new DOMDocument();$tmp->loadHTML('<?xml encoding="UTF-8">'.$html);while($nav->firstChild)$nav->removeChild($nav->firstChild);if($tmp->getElementsByTagName('ul')->length)$nav->appendChild($doc->importNode($tmp->getElementsByTagName('ul')->item(0),true));}
foreach($xpath->query('//li[contains(@class,"lang-item")]/a') as $a){$lang=bj_language()==='ar'?'en':'ar';$url=is_singular('product')?bj_product_url(get_queried_object_id(),$lang):(function_exists('pll_get_post')&&pll_get_post(get_queried_object_id(),$lang)?get_permalink(pll_get_post(get_queried_object_id(),$lang)):bj_page_url('index',$lang));$a->setAttribute('href',$url);$a->setAttribute('lang',$lang);}
foreach($xpath->query('//ul[contains(@class,"mt-social")]') as $ul){ob_start();bj_socials();$socials=ob_get_clean();$tmp=new DOMDocument();$tmp->loadHTML('<?xml encoding="UTF-8"><div>'.$socials.'</div>');while($ul->firstChild)$ul->removeChild($ul->firstChild);foreach(iterator_to_array($tmp->getElementsByTagName('a')) as $a){$li=$doc->createElement('li');$li->appendChild($doc->importNode($a,true));$ul->appendChild($li);}}
echo preg_replace('/<\?xml[^>]*>/', '',$doc->saveHTML());
?>
<main id="main">
