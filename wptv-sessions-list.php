<?php
/*
 * Plugin Name:       WPTV sessions lists
 * Description:       Handle the basics with this plugin.
 * Version:           0.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Nilo Velez
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wptv-sessions-list
 * Domain Path:       /languages
 */

require_once plugin_dir_path(__FILE__) . 'inc/wordcamp.php';
require_once plugin_dir_path(__FILE__) . 'inc/wptv.php';
require_once plugin_dir_path(__FILE__) . 'inc/photos.php';
/*
per_page=100
page=1
_fields=author,id,excerpt,title,link
*/

/*
https://zaragoza.wordcamp.org/2025/wp-json/wp/v2/

https://zaragoza.wordcamp.org/2025/

wp-json/wp/v2/sessions?status=publish&_fields=title,content,meta._wcpt_session_time,meta._wcpt_speaker_id,meta._wcpt_session_type&per_page=100

wp-json/wp/v2/speakers?status=publish&_fields=id,title&per_page=100
*/

$wordcamp_url = '';
$wordcamp_speakers = array();
$wordcamp_tracks = array();
$wordcamp_sessions = array();