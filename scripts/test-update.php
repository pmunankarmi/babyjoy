<?php
if(!defined('WP_CLI'))exit;
$before=wp_get_theme('babyjoy')->get('Version');$pages=wp_count_posts('page')->publish;$logo=get_theme_mod('custom_logo');
delete_transient('bj_github_release');delete_site_transient('update_themes');wp_update_themes();$updates=get_site_transient('update_themes');$update=$updates->response['babyjoy']??null;
if(!$update)WP_CLI::error('No native theme update detected for sandbox version '.$before);
WP_CLI::log('Native update detected: '.$before.' -> '.$update['new_version']);
require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';$upgrader=new Theme_Upgrader(new WP_CLI\UpgraderSkin());$result=$upgrader->upgrade('babyjoy');
if(is_wp_error($result)||!$result)WP_CLI::error('Theme upgrade failed.');
wp_clean_themes_cache();if(wp_get_theme('babyjoy')->get('Version')!==$update['new_version'])WP_CLI::error('Installed version mismatch.');
if(wp_count_posts('page')->publish!==$pages||get_theme_mod('custom_logo')!==$logo)WP_CLI::error('Database content changed during update.');
WP_CLI::success('GitHub update installed through WordPress; pages and logo preserved.');
