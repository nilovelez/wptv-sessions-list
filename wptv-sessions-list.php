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
	    	$session['timestamp'] = intval($post->meta->_wcpt_session_time);
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
	    usort($sessions, function($a, $b) {
	        return $a['timestamp'] - $b['timestamp'];
	    });
	    return $sessions;
	}
}

function render_output($sessions) {
    $output = '';
    $row = 8;
    
    foreach($sessions as $session) {
        // Escape content to avoid issues with special characters
        $content = str_replace(["\n", "\r", "\t"], [' ', ' ', ' '], $session['content']);
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        
        // Build line with tabs as separators
        $line = "\tPending\t\t\t" . $session['date'] . "\t\t\t" . 
                $session['speakers'] . "\t" . $session['title'] . "\t" .
                '= IF( ISBLANK(H'.$row.'), "", CONCAT(CONCAT(H'.$row.',": "), I'.$row.') )' . "\t" .
                $content . "\n";
        
        $output .= $line;
        $row++;
    }
    
    return '<textarea rows="20" cols="100" style="width: 100%; height: 300px;" onclick="this.select();">' . $output . '</textarea>';
}

function sanitize_wordcamp_url($wordcamp_url) {
    $wordcamp_url = filter_input(INPUT_POST, $wordcamp_url, FILTER_SANITIZE_URL);
    
    if (empty($wordcamp_url)) {
        return false;
    }

    // Add https:// if missing
    if (strpos($wordcamp_url, 'https://') !== 0) {
        $wordcamp_url = 'https://' . $wordcamp_url;
    }

    // Extract base URL according to format
    if (preg_match('/^(https:\/\/[^\/]+\.wordcamp\.org\/\d{4})/', $wordcamp_url, $matches)) {
        // wordcamp.org format - only up to year
        $wordcamp_url = $matches[1];
    } elseif (preg_match('/^(https:\/\/events\.wordpress\.org\/[^\/]+\/\d{4}\/[^\/]+)/', $wordcamp_url, $matches)) {
        // events.wordpress.org format - up to event slug
        $wordcamp_url = $matches[1];
    }

    // Add trailing slash if missing
    if (substr($wordcamp_url, -1) !== '/') {
        $wordcamp_url .= '/';
    }

    return $wordcamp_url;
}

function wptv_sessions_func($atts) {
    global $wptv_base_url, $wptv_speakers, $wptv_sessions;

    // Get WordCamp URL from form
    $wordcamp_url = sanitize_wordcamp_url('wordcamp_url');

    // Display input form
    ?>
	<style>
		#wptv-sessions-form {
			display: flex;
			flex-direction: row;
			gap: 10px;
		}
		#wptv-sessions-form input[type="text"] {
			flex: 2;
		}
		#wptv-sessions-form input[type="submit"] {
			flex: 1;
		}
	</style>
    <div class="wptv-form-container">
        <p>Please enter a WordCamp website URL<br>
        <p>Examples:<br>
			https://zaragoza.wordcamp.org/2025/<br>
			https://events.wordpress.org/lleida/2025/disseny/</p>
        <form method="post" action="" id="wptv-sessions-form">
            <input type="text" 
                   size="50" 
                   id="wordcamp_url" 
                   name="wordcamp_url" 
                   value="<?php echo esc_url($wordcamp_url); ?>"
                   placeholder="https://wordcamp.org/YYYY/">
            <input type="submit" value="Get Sessions">
        </form>
    </div>
    <?php

    // Start output buffer
    ob_start();

    // Validations
    if (!isset($_POST['wordcamp_url'])) {
        return ob_get_clean();
    }

    if (empty($wordcamp_url)) {
        return 'You have to provide a valid WordCamp URL';
    }

    // Set base URL
    $wptv_base_url = $wordcamp_url;

    // Get speakers
    $wptv_speakers = get_speakers($wptv_base_url);
    if (!$wptv_speakers) {
        return 'Cannot retrieve speakers list';
    }

    // Get sessions
    $wptv_sessions = get_sessions($wptv_base_url);
    if (!$wptv_sessions) {
        return 'Cannot retrieve sessions list';
    }

    // Render output
    echo render_output($wptv_sessions);
    
    return ob_get_clean();
}

$wptv_base_url = '';
$wptv_speakers = array();
$wptv_sessions = array();

add_action( 'init', function(){

	
	add_shortcode( 'wptv_sessions', 'wptv_sessions_func' );
});



