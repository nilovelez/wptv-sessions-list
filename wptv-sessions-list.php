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

wp-json/wp/v2/sessions?status=publish&_fields=title,content,meta._wcpt_session_time,meta._wcpt_speaker_id,meta._wcpt_session_type&per_page=100

wp-json/wp/v2/speakers?status=publish&_fields=id,title&per_page=100
*/

/**
 * Fetch content from another WordPress site's REST API endpoint.
 *
 * @param string $endpoint The full URL of the REST API endpoint.
 * @return mixed The decoded response as an object on success, or a WP_Error object on failure.
 */

function fetch_rest_api_content($endpoint) {
    // Perform the HTTP GET request
    $response = wp_remote_get($endpoint);

    // Check if the request resulted in an error
    if (is_wp_error($response)) {
        return $response; // Return the error object
    }

    // Check the HTTP status code
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        return new WP_Error('http_error', 'Failed to fetch content.', ['status_code' => $status_code]);
    }

    // Get the response body
    $body = wp_remote_retrieve_body($response);

    // Decode the JSON response
    $decoded = json_decode($body);

    // Check if decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('json_error', 'Failed to decode JSON.', ['json_error' => json_last_error_msg()]);
    }

    return $decoded;
}

function get_speakers( $base_url ) {

	$endpoint = $base_url . 'wp-json/wp/v2/speakers?status=publish&_fields=id,title&per_page=100';

	$content = fetch_rest_api_content($endpoint);
	$speakers = array();

	if (is_wp_error($content)) {
	    // Handle the error
	    error_log($content->get_error_message());
	    return false;
	} else {
	    // Work with the returned content
	    foreach ($content as $post) {
	        $speakers[$post->id] = $post->title->rendered;
	    }
	    return $speakers;
	}
}

function wptv_clean_content( $content ) {
	$content = str_replace("<br>", "\n", $content);
	$content = str_replace("</p>\n\n\n\n<p>", "\n\n", $content);
	$content = strip_tags( $content );
	return $content;
}

function get_sessions( $base_url ) {
	global $wptv_speakers;

	$endpoint = $base_url . 'wp-json/wp/v2/sessions?status=publish&_fields=title,content,meta._wcpt_session_time,meta._wcpt_speaker_id,meta._wcpt_session_type&per_page=100';

	$content = fetch_rest_api_content($endpoint);
	$sessions = array();

	if (is_wp_error($content)) {
	    // Handle the error
	    error_log($content->get_error_message());
	    return false;
	} else {
	    // Work with the returned content
	    foreach ($content as $post) {

	    	if ( 'custom' == $post->meta->_wcpt_session_type ) {
	    		continue;
	    	}

	    	$session = array();

	    	$session['date'] = date('d/m/Y', $post->meta->_wcpt_session_time);
	    	$session['title'] = $post->title->rendered;
	    	$session['content'] = wptv_clean_content($post->content->rendered);
	    	$session_speakers = array();

	    	
	    	foreach ($post->meta->_wcpt_speaker_id as $speaker_id) {
	    		if ( array_key_exists( $speaker_id, $wptv_speakers) ) {
	    			$session_speakers[] = $wptv_speakers[ $speaker_id ];
	    		}
	    	}
	    	$session['speakers'] = implode(', ', $session_speakers );
			
			//$session['speakers'] = $post->meta->_wcpt_speaker_id;
	    	$session['date'] = date('d/m/Y', $post->meta->_wcpt_session_time);

	        $sessions[] = $session;
	    }
	    return $sessions;
	}
}

function wptv_sessions_func( $atts ){
	global $wptv_base_url, $wptv_speakers, $wptv_sessions;

	$wptv_post_wordcamp_url = filter_input( INPUT_POST, 'wordcamp_url' );

	?>
	<p>Please enter a WorCamp website URL<br>
	for example: https://zaragoza.wordcamp.org/2025/</p>
	<form method="post" action="">
		<input type="text" size="50" id="wordcamp_url" name="wordcamp_url" value="<?php echo esc_url($wptv_post_wordcamp_url) ?>">
		<input type="submit">
	</form>
	<?php

	ob_start();


	if ( ! isset($_POST['wordcamp_url'] ) ) {
		return;
	}
	if ( ! $wptv_post_wordcamp_url ) {

		return 'You have to provide a valid WordCamp URL';
	}

	$wptv_base_url = $wptv_post_wordcamp_url;

	if ( ! $wptv_speakers = get_speakers( $wptv_base_url ) ) {
		return 'Can\'t retieve speakers list';
	}

	if ( ! $wptv_speakers = get_speakers( $wptv_base_url ) ) {
		return 'Can\'t retieve speakers list';
	}
	// var_dump( $wptv_speakers );

	if ( ! $wptv_sessions = get_sessions( $wptv_base_url ) ) {
		return 'Can\'t retieve sessions list';
	}

	
	
	echo '<div id="wptv_sessions_table"><table>';

	$row = 8;
	
	foreach( $wptv_sessions as $session ) {
		echo "<tr><td>&nbsp;</td><td>Pending</td><td></td><td></td><td>", $session['date'] . "</td><td></td><td></td><td>" . $session['speakers'] . "</td><td>" . $session['title'] . "</td><td>".'= IF( ISBLANK(H'.$row.'), "", CONCAT(CONCAT(H'.$row.',": "), I'.$row.') )'."</td><td>" . $session['content'] . "</td></tr>";
		$row++;
	}

	echo '</table></div>';


	
	return ob_get_clean();
}

$wptv_base_url = '';
$wptv_speakers = array();
$wptv_sessions = array();

add_action( 'init', function(){

	
	add_shortcode( 'wptv_sessions', 'wptv_sessions_func' );
});



