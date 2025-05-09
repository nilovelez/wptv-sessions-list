<?php
/**
 * WordCamp API functions
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

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

/**
 * Get speakers from WordCamp API
 *
 * @param string $base_url The base URL of the WordCamp site
 * @return array|false Array of speakers or false on error
 */
function wordcamp_get_speakers($base_url) {
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

/**
 * Get tracks from WordCamp API
 *
 * @param string $base_url The base URL of the WordCamp site
 * @return array|false Array of tracks or false on error
 */
function wordcamp_get_tracks($base_url) {
    $endpoint = $base_url . 'wp-json/wp/v2/session_track?per_page=100';

    $content = fetch_rest_api_content($endpoint);
    $tracks = array();

    if (is_wp_error($content)) {
        // Handle the error
        error_log($content->get_error_message());
        return false;
    } else {
        // Work with the returned content
        foreach ($content as $term) {
            $tracks[$term->id] = $term->slug;
        }
        return $tracks;
    }
}

/**
 * Clean content from HTML and format it for plain text
 *
 * @param string $content The content to clean
 * @return string Cleaned content
 */
function wordcamp_clean_session_content($content) {
    $content = str_replace("<br>", "\n", $content);
    $content = str_replace("</p>\n\n\n\n<p>", "\n\n", $content);
    $content = strip_tags($content);
    return $content;
}

/**
 * Get sessions from WordCamp API
 *
 * @param string $base_url The base URL of the WordCamp site
 * @return array|false Array of sessions or false on error
 */
function wordcamp_get_sessions($base_url, $wordcamp_speakers, $wordcamp_tracks) {

    $endpoint = $base_url . 'wp-json/wp/v2/sessions?status=publish&_fields=title,content,meta._wcpt_session_time,content,session_track,meta._wcpt_speaker_id,meta._wcpt_session_type&per_page=100';

    $content = fetch_rest_api_content($endpoint);
    $sessions = array();

    if (is_wp_error($content)) {
        // Handle the error
        error_log($content->get_error_message());
        return false;
    } else {
        // Work with the returned content
        foreach ($content as $post) {
            if ('custom' == $post->meta->_wcpt_session_type) {
                continue;
            }

            $session = array();

            $session['date'] = date('d/m/Y', $post->meta->_wcpt_session_time);
            $session['timestamp'] = intval($post->meta->_wcpt_session_time);
            $session['title'] = $post->title->rendered;
            $session['content'] = wordcamp_clean_session_content($post->content->rendered);
            $session_speakers = array();

            foreach ($post->meta->_wcpt_speaker_id as $speaker_id) {
                if (array_key_exists($speaker_id, $wordcamp_speakers)) {
                    $session_speakers[] = $wordcamp_speakers[$speaker_id];
                }
            }
            $session['speakers'] = implode(', ', $session_speakers);

            $session['track'] = '';
            // We only keep the first track
            if (array_key_exists($post->session_track[0], $wordcamp_tracks)) {
                $session['track'] = $wordcamp_tracks[$post->session_track[0]];
            }
            $sessions[] = $session;
        }

        usort($sessions, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });

        return $sessions;
    }
}

/**
 * Sanitize and validate WordCamp URL
 *
 * @param string $wordcamp_url The URL to sanitize
 * @return string|false Sanitized URL or false on error
 */
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