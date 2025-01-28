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

/*
per_page=100
page=1
_fields=author,id,excerpt,title,link
*/

/*
https://zaragoza.wordcamp.org/2025/wp-json/wp/v2/

https://zaragoza.wordcamp.org/2025/

wp-json/wp/v2/sessions?status=publish&_fields=title,meta._wcpt_session_time,meta._wcpt_speaker_id?per_page=100

wp-json/wp/v2/speakers?status=publish&_fields=id,title?per_page=100
*/

function get_sessions( $base_url ) {
	$endpoint = $base_url . 'wp-json/wp/v2/sessions?status=publish&_fields=title,meta._wcpt_session_time,meta._wcpt_speaker_id?per_page=100';

	$content = fetch_rest_api_content($endpoint);
	$sessions = array();

	if (is_wp_error($content)) {
	    // Handle the error
	    error_log($content->get_error_message());
	    return false;
	} else {
	    // Work with the returned content
	    foreach ($content as $post) {
	        $sessions[] = $post->title->rendered;
	    }
	    return $sessions;
	}
}

$base_url = 'https://zaragoza.wordcamp.org/2025/';

if ( $sessions = get_sessions( $base_url ) ) {
	var_dump( $sessions );
}
