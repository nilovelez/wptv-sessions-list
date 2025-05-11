<?php
/**
 * Photos Sessions List Plugin
 * 
 * This file contains the core functionality for displaying and formatting
 * WordCamp sessions in a format suitable for Google Sheets.
 * 
 * @package Photos_Sessions_List
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
function photos_render_output($sessions, $output_type = 'google_sheets') {
    $output = '';
    
    $weekdays = array(
        'Monday' => __('Monday', 'photos-sessions-list'),
        'Tuesday' => __('Tuesday', 'photos-sessions-list'),
        'Wednesday' => __('Wednesday', 'photos-sessions-list'),
        'Thursday' => __('Thursday', 'photos-sessions-list'),
        'Friday' => __('Friday', 'photos-sessions-list'),
        'Saturday' => __('Saturday', 'photos-sessions-list'),
        'Sunday' => __('Sunday', 'photos-sessions-list')
    );

    // Get WordPress timezone
    $timezone_string = get_option('timezone_string');
    $timezone = new DateTimeZone($timezone_string ?: 'UTC');

    $output_rows = array();
    $last_date = '';
    foreach($sessions as $session) {
        // Create DateTime object with timezone
        $datetime = new DateTime('@' . $session['timestamp']);
        $datetime->setTimezone($timezone);
        $event_name = $weekdays[$datetime->format('l')];


        if ($last_date != $session['date']) {
            $folder = $datetime->format('Ymd') . '__misc';
            $output_rows[] = array(
                'folder' => $folder,
                'event_name' => $event_name,
                'event_subject' => '',
                'speaker_name' => '',
                'event_description' => '',
                'mkdir' => 'mkdir ' . $event_name . ' && mkdir ' . $folder
            );
            $last_date = $session['date'];
        }

        $folder = $datetime->format('Ymd') . '_' . $session['track'] . '_' . $datetime->format('Hi');
        $output_rows[] = array(
            'folder' => $folder,
            'event_name' => $weekdays[$datetime->format('l')],
            'event_subject' => $session['title'],
            'speaker_name' => $session['speakers'],
            'event_description' => '',
            'mkdir' => 'mkdir ' . $event_name . '/' . $folder
        );
    }
    // sort output_rows by folder alphabetically
    usort($output_rows, function($a, $b) {
        return strcmp($a['folder'], $b['folder']);
    });

    // Add formulas after sorting
    $row = 4;
    foreach($output_rows as &$output_row) {
        if (empty($output_row['event_subject'])) {
            $row++;
            continue;
        }
        $output_row['event_description'] = '= IF( ISBLANK(D'.$row.'), C'.$row.', CONCAT(CONCAT(D'.$row.',": "), C'.$row.') )';
        $row++;
    }

    if ($output_type == 'google_sheets') {
        
        $output = "//$$\tEvents\t\t\t\n";
        $output .= "//==\t{foldernum}\t\t\t\n";
        $output .= "//##\tEventName\tEventSubject\tSpeakerName\tEventDescription\n"; 
        foreach($output_rows as $output_row) {
            $output .= $output_row['folder'] . "\t";
            $output .= $output_row['event_name'] . "\t";
            $output .= $output_row['event_subject'] . "\t";
            $output .= $output_row['speaker_name'] . "\t";
            $output .= $output_row['event_description'] . "\n";
            $output .= $output_row['mkdir'] . "\n";
        }
        $output = '<textarea rows="20" cols="100" style="width: 100%; height: 300px;" onclick="this.select();">' . $output . '</textarea>';
    } else {
        $output = '<table>';
        $output .= '<tr><td>//$$</td><td>Events</td><td></td><td></td></tr>';
        $output .= '<tr><td>//==</td><td>{foldernum}</td><td></td><td></td></tr>';
        $output .= '<tr><td>//##</td><td>EventName</td><td>EventSubject</td><td>SpeakerName</td></tr>';
        foreach($output_rows as $output_row) {
            $output .= '<tr>';

            $output .= '<td>' . $output_row['folder'] . '</td>';
            $output .= '<td>' . $output_row['event_name'] . '</td>';
            $output .= '<td>' . $output_row['event_subject'] . '</td>';
            $output .= '<td>' . $output_row['speaker_name'] . '</td>';
            $output .= '</tr>';
        }
        $output .= '</table>';
    }
    return $output;
}

/**
 * Main shortcode function to display the WordCamp sessions form and results
 * 
 * @param array $atts Shortcode attributes (not used)
 * @return string HTML output of the form and results
 */
function photos_sessions_func($atts) {

    // Get WordCamp URL from form
    $wordcamp_url = sanitize_wordcamp_url('wordcamp_url');

    // Start output buffer
    ob_start();

    // Get output type from form
    $output_type = isset($_POST['output_type']) ? sanitize_text_field($_POST['output_type']) : 'google_sheets';

    // Display input form
    ?>
    <div class="photos-form-container">
        <p>Enter a WordCamp website URL to get a list of sessions suitable for the Photo Mechanic replacements sheet<br>
        <p>Examples:<br>
            https://zaragoza.wordcamp.org/2025/<br>
            https://events.wordpress.org/lleida/2025/disseny/</p>
        <form method="post" action="" id="photos-sessions-form" style="display: flex; flex-direction: row; gap: 10px;">
            <input type="text" 
                   size="50" 
                   id="wordcamp_url" 
                   name="wordcamp_url" 
                   value="<?php echo esc_url($wordcamp_url); ?>"
                   placeholder="https://wordcamp.org/YYYY/"
                   style="flex: 2;">
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
    echo photos_render_output($wordcamp_sessions, $output_type);
    
    return ob_get_clean();
}

// Register shortcode
add_action('init', function() {
    add_shortcode('photos_sessions', 'photos_sessions_func');
}); 