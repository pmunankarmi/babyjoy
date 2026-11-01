<?php
if(!defined('ABSPATH'))exit;
add_filter('update_themes_github.com',function($update,$theme_data,$stylesheet){
 if($stylesheet!=='babyjoy')return $update;
 $release=get_transient('bj_github_release');
 if(false===$release){$response=wp_remote_get('https://api.github.com/repos/pmunankarmi/babyjoy/releases/latest',['timeout'=>8,'headers'=>['Accept'=>'application/vnd.github+json']]);if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200){set_transient('bj_github_release',['unavailable'=>true],15*MINUTE_IN_SECONDS);return false;}$release=json_decode(wp_remote_retrieve_body($response),true);set_transient('bj_github_release',$release,HOUR_IN_SECONDS);}
 if(!empty($release['unavailable'])||!empty($release['prerelease'])||!empty($release['draft']))return false;
 $version=ltrim($release['tag_name']??'','v');if(!preg_match('/^\d+\.\d+\.\d+(?:[.-][\w.-]+)?$/',$version)||!version_compare($version,$theme_data['Version'],'>'))return false;
 foreach($release['assets']??[] as $asset)if(($asset['name']??'')==='babyjoy.zip'&&str_starts_with($asset['browser_download_url']??'','https://github.com/pmunankarmi/babyjoy/releases/download/'))return ['theme'=>'babyjoy','version'=>$version,'url'=>'https://github.com/pmunankarmi/babyjoy','package'=>$asset['browser_download_url'],'requires'=>'6.6','requires_php'=>'8.1'];
 return false;
},10,3);
