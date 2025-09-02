<?php
/**
 * Social Media Sessions List Plugin
 * 
 * This file contains the core functionality for displaying and formatting
 * WordCamp sessions in a format suitable for social media teams.
 * 
 * @package Social_Sessions_List
 * @since 0.1
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Render sessions output in a format suitable for social media teams
 * 
 * @param array $sessions Array of session data including date, speakers, title and content
 * @return string HTML textarea containing formatted session data
 */
function social_render_output($sessions, $output_type = 'google_sheets') {
    $output = '';

    $weekdays = array(
        'Monday' => __('Monday', 'social-sessions-list'),
        'Tuesday' => __('Tuesday', 'social-sessions-list'),
        'Wednesday' => __('Wednesday', 'social-sessions-list'),
        'Thursday' => __('Thursday', 'social-sessions-list'),
        'Friday' => __('Friday', 'social-sessions-list'),
        'Saturday' => __('Saturday', 'social-sessions-list'),
        'Sunday' => __('Sunday', 'social-sessions-list')
    );

    // Get WordPress timezone
    $timezone_string = get_option('timezone_string');
    $timezone = new DateTimeZone($timezone_string ?: 'UTC');

    $output_rows = array();

    foreach($sessions as $session) {
        // Create DateTime object with timezone
        $datetime = new DateTime('@' . $session['timestamp']);
        $datetime->setTimezone($timezone);

        $output_rows[] = array(
            'date' => $datetime->format('d/m/Y'),
            'time' => $datetime->format('H:i'),
            'speakers' => $session['speakers'],
            'title' => $session['title'],
            'track' => $session['track'],
            'content' => $session['content'],
        );
    }
    // sort output_rows by date, time and track
    usort($output_rows, function($a, $b) {
        $date_cmp = strcmp($a['date'], $b['date']);
        if ($date_cmp !== 0) {
            return $date_cmp;
        }
        $time_cmp = strcmp($a['time'], $b['time']);
        if ($time_cmp !== 0) {
            return $time_cmp;
        }
        return strcmp($a['track'], $b['track']);
    });

    unset($output_row); // We clean the reference

    if ($output_type == 'ChatGPT') {
        
        $output = <<<EOT
A partir de un archivo CSV con las columnas: fecha, hora, track, ponente, título, descripción

Procesa únicamente el contenido del CSV. No consultes fuentes externas ni añadas información que no esté explícitamente en el archivo. No completes, interpretes ni inventes nada. Si un campo está vacío, deja el contenido correspondiente vacío o genera solo con lo disponible.

Genera un texto por cada entrada usando un estilo cercano y dinámico, como si estuvieras anunciando las charlas en redes sociales. Usa frases como: '¡A continuación tenemos a [ponente] que nos va a enseñar...!' o 'No te pierdas a [ponente] hablando de...'. Mantén el tono entusiasta pero natural, destacando lo interesante de cada charla con frases cortas y directas. Sigue usando solo la información proporcionada en el CSV.

Genera un texto por cada entrada, sin omitir ninguna. Devuelve la salida en formato TSV con las columnas:

fecha_hora <TAB> track <TAB> ponente <TAB> texto

Instrucciones:
- Une fecha y hora como "fecha_hora" en formato YYYY-MM-DD HH:MM
- Escribe el texto en español
- La columna texto debe tener un máximo de 280 caracteres, como si fuera para una publicación en Twitter. Procura que sea los más largo posible dentro de ese límite.
- Menciona al ponente y destaca el beneficio o enfoque de la charla, usando solo la descripción proporcionada
- No uses hashtags ni emojis
- Usa tabuladores reales (\t) para separar las columnas. No uses espacios
- Escapa los tabuladores dentro del contenido (reemplázalos por espacios)
- Devuelve solo un bloque de código con formato \`\`\`tsv, sin ningún texto adicional fuera del bloque
- Si inventas, asumes o rellenas cualquier contenido, la respuesta no es válida

Aquí tienes los datos de entrada en CSV:

EOT;   

        $output .= "date,time,track,speaker,title,content\n"; 
        foreach($output_rows as $output_row) {
            $output .= '"' . str_replace('"', '""', $output_row['date']) . '",';
            $output .= '"' . str_replace('"', '""', $output_row['time']) . '",';
            $output .= '"' . str_replace('"', '""', $output_row['track']) . '",';
            $output .= '"' . str_replace('"', '""', $output_row['speakers']) . '",';
            $output .= '"' . str_replace('"', '""', $output_row['title']) . '",';
            $output .= '"' . str_replace(['"', "\r\n", "\n", "\r"], ['""', ' ', ' ', ' '], $output_row['content']) . '"' . "\n";
        }
        $output = str_replace('"', '&quot;', $output);
        $output = '<textarea rows="20" cols="100" style="width: 100%; height: 300px;" onclick="this.select();">' . $output . '</textarea>';
    } else {
        $output = '<table>';
        $output .= '<tr><td>Date</td><td>Speakers</td><td>Title</td><td>Track</td><td>Content</td></tr>';
        foreach($output_rows as $output_row) {
            $output .= '<tr>';
            $output .= '<td>' . $output_row['date'] . '</td>';
            $output .= '<td>' . $output_row['speakers'] . '</td>';
            $output .= '<td>' . $output_row['title'] . '</td>';
            $output .= '<td>' . $output_row['track'] . '</td>';
            $output .= '<td>' . $output_row['content'] . '</td>';
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
function social_sessions_func($atts) {

    // Get WordCamp URL from form
    $wordcamp_url = sanitize_wordcamp_url('wordcamp_url');

    // Start output buffer
    ob_start();

    // Get output type from form
    $output_type = isset($_POST['output_type']) ? sanitize_text_field($_POST['output_type']) : 'ChatGPT';

    // Display input form
    ?>
    <div class="social-form-container">
        <p class="wptvsl-description">Enter a WordCamp website URL to get a list of sessions suitable for social media teams<br>
        <p class="wptvsl-example">Examples:<br>
            https://zaragoza.wordcamp.org/2025/<br>
            https://events.wordpress.org/lleida/2025/disseny/</p>
        <form method="post" action="" id="social-sessions-form" style="display: flex; flex-direction: row; gap: 10px;">
            <input type="text" 
                   size="50" 
                   id="wordcamp_url" 
                   name="wordcamp_url" 
                   value="<?php echo esc_url($wordcamp_url); ?>"
                   placeholder="https://wordcamp.org/YYYY/"
                   style="flex: 2;">
            <select name="output_type" id="output_type" style="flex: 1;">
                <option value="table" <?php selected($output_type, 'table'); ?>>HTML Table</option>
                <option value="ChatGPT" <?php selected($output_type, 'ChatGPT'); ?>>ChatGPT (prompt + CSV)</option>
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
        echo '<div class="wptvsl-error">You have to provide a valid WordCamp URL</div>';
        return ob_get_clean();
    }

    
    // Get speakers
    $wordcamp_speakers = wordcamp_get_speakers($wordcamp_url);
    if (!$wordcamp_speakers) {
        echo '<div class="wptvsl-error">Cannot retrieve speakers list</div>';
        return ob_get_clean();
    }

    // Get tracks
    $wordcamp_tracks = wordcamp_get_tracks($wordcamp_url);
    if (!$wordcamp_tracks) {
        echo '<div class="wptvsl-error">Cannot retrieve tracks list</div>';
        return ob_get_clean();
    }

    // Get sessions
    $wordcamp_sessions = wordcamp_get_sessions($wordcamp_url, $wordcamp_speakers, $wordcamp_tracks);
    if (!$wordcamp_sessions) {
        echo '<div class="wptvsl-error">Cannot retrieve sessions list</div>';
        return ob_get_clean();
    }

    // Render output
    echo social_render_output($wordcamp_sessions, $output_type);
    
    return ob_get_clean();
}

// Register shortcode
add_action('init', function() {
    add_shortcode('social_sessions', 'social_sessions_func');
}); 