<?php
/**
 * WPTV Sessions List Plugin
 * 
 * This file contains the core functionality for displaying and formatting
 * WordCamp sessions in a format suitable for Google Sheets.
 * 
 * @package WPTV_Sessions_List
 * @since 0.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Render sessions output in a format suitable for Google Sheets
 * 
 * @param array $sessions Array of session data including date, speakers, title and content
 * @return string HTML textarea containing formatted session data
 */
function wptv_render_output($sessions, $output_type = 'google_sheets') {
    $output = '';
    $row = 8;
    // Get WordPress timezone
    $timezone_string = get_option('timezone_string');
    $timezone = new DateTimeZone($timezone_string ?: 'UTC');

    $output_rows = array();
    foreach($sessions as $session) {
        // Create DateTime object with timezone
        $datetime = new DateTime('@' . $session['timestamp']);
        $datetime->setTimezone($timezone);

        $folder = $datetime->format('Hi') . '_' . $session['track'] . '_';
        $name = !empty($session['speakers']) ? explode(', ', $session['speakers'])[0] : $session['title'];
        $folder .= preg_replace('/[:\/\\\*?"<>|]/', '', explode(' ', trim($name))[0]);

        $output_rows[] = array(
            'date' => $datetime->format('d/m/Y'),
            'speakers' => $session['speakers'],
            'title' => $session['title'],
            'track' => $session['track'],
            'content' => $session['content'],
        );
    }
    // sort output_rows by track alphabetically
    usort($output_rows, function($a, $b) {
        return strcmp($a['track'], $b['track']);
    });

    if ($output_type == 'google_sheets') {
        foreach($output_rows as $output_row) {
            // Escape content to avoid issues with special characters
            $content = str_replace(["\n", "\r", "\t"], [' ', ' ', ' '], $output_row['content']);
            $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
            
            // Build line with tabs as separators
            $line = "\tPending\t\t\t" . $output_row['date'] . "\t\t\t" . 
                    $output_row['speakers'] . "\t" . $output_row['title'] . "\t" .
                    '= IF( ISBLANK(H'.$row.'); &quot;&quot;; CONCAT(CONCAT(H'.$row.'; &quot;: &quot;); I'.$row.') )' . "\t" .
                    $content . "\n";
            
            $output .= $line;
            $row++;
        }
        return '<textarea rows="20" cols="100" style="width: 100%; height: 300px;" onclick="this.select();">' . $output . '</textarea>';
    } else {
        $output = '<table>';
        $output .= '<tr>';
        $output .= '<th>Date</th>';
        $output .= '<th>Track</th>';
        $output .= '<th>Speakers</th>';
        $output .= '<th>Title</th>';
        $output .= '<th width="30%">Content</th>';
        $output .= '</tr>';
        foreach($output_rows as $output_row) {
            $output .= '<tr>';
            $output .= '<td>' . $output_row['date'] . '</td>';
            $output .= '<td>' . $output_row['track'] . '</td>';
            $output .= '<td>' . $output_row['speakers'] . '</td>';
            $output .= '<td>' . $output_row['title'] . '</td>';
            $output .= '<td>' . $output_row['content'] . '</td>';
        }
        $output .= '</table>';
        return $output;
    }
}

/**
 * Main shortcode function to display the WordCamp sessions form and results
 * 
 * @param array $atts Shortcode attributes (not used)
 * @return string HTML output of the form and results
 */
function wptv_sessions_func($atts) {
    // Get WordCamp URL from form
    $wordcamp_url = sanitize_wordcamp_url('wordcamp_url');

    // Start output buffer
    ob_start();

    // Get output type from form
    $output_type = isset($_POST['output_type']) ? sanitize_text_field($_POST['output_type']) : 'google_sheets';


    // Display input form
    ?>
    <div class="wptv-form-container" style="margin-bottom: 20px;">
        <p>Enter a WordCamp website URL to get a list of sessions suitable for the WPTV Google Sheet<br>
        <p>Examples:<br>
            https://zaragoza.wordcamp.org/2025/<br>
            https://events.wordpress.org/lleida/2025/disseny/</p>
        <form method="post" action="" id="wptv-sessions-form" style="display: flex; flex-direction: row; gap: 10px;">
            <input type="text" 
                   size="50" 
                   id="wordcamp_url" 
                   name="wordcamp_url" 
                   value="<?php echo esc_url($wordcamp_url); ?>"
                   placeholder="https://wordcamp.org/YYYY/"
                   style="flex: 3;">
            <select name="output_type" id="output_type" style="flex: 1;">
                <option value="table" <?php selected($output_type, 'table'); ?>>HTML Table</option>
                <option value="google_sheets" <?php selected($output_type, 'google_sheets'); ?>>Google Sheet</option>
            </select>
            <input type="submit" value="Get Sessions" style="flex: 1;">
        </form>
    </div>
    <?php

    // Validations
    if (!isset($_POST['wordcamp_url'])) {
        return ob_get_clean();
    }

    if (empty($wordcamp_url)) {
        return 'You have to provide a valid WordCamp URL';
    }
    

    // Get speakers
    $wordcamp_speakers = wordcamp_get_speakers($wordcamp_url);
    if (!$wordcamp_speakers) {
        return 'Cannot retrieve speakers list';
    }

    // Get tracks
    $wordcamp_tracks = wordcamp_get_tracks($wordcamp_url);
    if (!$wordcamp_tracks) {
        return 'Cannot retrieve tracks list';
    }

    // Get sessions
    $wordcamp_sessions = wordcamp_get_sessions($wordcamp_url, $wordcamp_speakers, $wordcamp_tracks);
    if (!$wordcamp_sessions) {
        return 'Cannot retrieve sessions list';
    }

  

    // Render output
    echo wptv_render_output($wordcamp_sessions, $output_type);
    
    return ob_get_clean();
}

// Register shortcode
add_action('init', function() {
    add_shortcode('wptv_sessions', 'wptv_sessions_func');
});