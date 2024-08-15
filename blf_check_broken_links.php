<?php
/*
Plugin Name: Broken Link Fixer
Description: Busca y elimina enlaces rotos dentro de los posts.
Version: 1.0
Author: Misterdigital
*/

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}
// Definir la ruta completa al archivo de log dentro de la carpeta /logs del plugin
$log_dir = plugin_dir_path(__FILE__) . 'logs';

if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$log_file = $log_dir . '/error_log.txt';

// Crear el archivo de log si no existe
if (!file_exists($log_file)) {
    $handle = fopen($log_file, 'w') or die('No se pudo crear el archivo de log: ' . $log_file);
    fclose($handle);
}

define('BLF_LOG_FILE_PATH', $log_file);

// Intentar escribir un mensaje de prueba
error_log("Esto es un mensaje de prueba.", 3, BLF_LOG_FILE_PATH);

// Agregar la página de opciones del plugin
function blf_add_options_page() {
    add_options_page(
        'Broken Link Fixer',
        'Broken Link Fixer',
        'manage_options',
        'blf-options',
        'blf_render_options_page'
    );
}
add_action('admin_menu', 'blf_add_options_page');


// Editar la función para renderizar la página de opciones del plugin
function blf_render_options_page() {
    ?>
    <div class="wrap">
        <h1>Broken Link Fixer</h1>
        <h2>Opciones</h2>
        <label>
            <input type="checkbox" id="blf-internal-only" checked> Solo enlaces internos
        </label>
        <div id="blf-specific-post-status"></div>
        <h2>Limpieza masiva</h2>
        <button id="blf-start-cleaning" class="button button-primary">Iniciar Limpieza</button>
        <button id="blf-cancel-cleaning" class="button" style="display:none;">Cancelar Limpieza</button>
        <div id="blf-status"></div>

        <h2>Limpiar post específico</h2>
        <textarea id="blf-post-input" placeholder="URLs o IDs del post" cols="100" rows="100"></textarea>


        <button id="blf-clean-specific-post" class="button">Limpiar Post</button>
    </div>
    <?php
}

function blf_clean_specific_post() {
    
    check_ajax_referer('blf-nonce', 'nonce');
    $post_id = $_POST['post_id'];
    if (is_numeric($post_id)) {
        $post_id = intval($post_id);  // Tratar como int
    } else {
        $post_id = filter_var($post_id, FILTER_VALIDATE_URL) ? $post_id : null;  // Validar como URL
        if (is_null($post_id)) {
            wp_send_json_error('Invalid post_id');
        }
    }
    
    $internal_only = isset($_POST['internal_only']) && $_POST['internal_only'] === 'true';
    
    // Añadir log
    error_log("Intentando limpiar el post ID: " . $post_id, 3, BLF_LOG_FILE_PATH);

    $result = blf_check_broken_links($post_id, $internal_only);
    
    // Añadir más logs
    error_log("Resultado de la limpieza: " . print_r($result, true), 3, BLF_LOG_FILE_PATH);

    wp_send_json_success($result);
}

add_action('wp_ajax_blf_clean_specific_post', 'blf_clean_specific_post');

// AJAX handler para iniciar la limpieza
function blf_start_cleaning() {
    check_ajax_referer('blf-nonce', 'nonce');

    // Validar entrada
    if (!isset($_POST['post_id'])) {
        wp_send_json_error('post_id is required');
    }

    $post_id = $_POST['post_id'];

    if (is_numeric($post_id)) {
        $post_id = intval($post_id);  // Tratar como int
    } else {
        $post_id = filter_var($post_id, FILTER_VALIDATE_URL) ? $post_id : null;  // Validar como URL
        if (is_null($post_id)) {
            wp_send_json_error('Invalid post_id');
        }
    }

    // Validar internal_only input
    $internal_only = isset($_POST['internal_only']) && $_POST['internal_only'] === 'true';

    // Llamar a la función de limpieza y manejar errores posibles
    $result = blf_check_broken_links($post_id, $internal_only);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    wp_send_json_success($result);
}
add_action('wp_ajax_blf_start_cleaning', 'blf_start_cleaning');


function blf_check_broken_links($post_identifier = null, $internal_only = false) {
    
    // Verificar si $post_identifier es una URL o un post ID
    if ($post_identifier && !is_numeric($post_identifier)) {
        $post_id = blf_get_post_id_from_url($post_identifier);
        if (!$post_id) {
            error_log("No se encontró un post con la URL proporcionada: " . $post_identifier, 3, BLF_LOG_FILE_PATH);
            return null;
        }
    } else {
        $post_id = $post_identifier;
    }

    error_log("Iniciando blf_check_broken_links para post ID: " . $post_id);

    $args = array(
        'post_type' => 'post',
        'posts_per_page' => 1,
        'orderby' => 'ID',
        'order' => 'ASC',
    );

    if ($post_id) {
        $args['p'] = $post_id;
    }

    $posts = get_posts($args);

    error_log("Número de posts encontrados: " . count($posts), 3, BLF_LOG_FILE_PATH);

    $cleaned_count = 0;
    $link_count = 0;
    $broken_link_count = 0;
    $last_cleaned_url = '';
    $next_post_id = null;

    $internal_links_count = 0;  // Contador de enlaces internos
    $external_links_count = 0;  // Contador de enlaces externos

    foreach ($posts as $post) {
        $content = $post->post_content;
        error_log("Procesando post ID: " . $post->ID, 3, BLF_LOG_FILE_PATH);

        // Patrón para capturar enlaces en HTML
        $pattern = '/<a\s+href=["\']([^"\']+)["\']/i';
        if (preg_match_all($pattern, $content, $matches)) {
            $links = $matches[1]; // Array con todos los href encontrados
            $num_links = count($links);
            error_log("Número de enlaces encontrados en post ID " . $post->ID . ": " . $num_links, 3, BLF_LOG_FILE_PATH);
            $link_count += $num_links;
        } else {
            error_log("No se encontraron enlaces en el post ID " . $post->ID . ".", 3, BLF_LOG_FILE_PATH);
            continue;
        }

        $content_changed = false;
        foreach ($links as $href) {
            $href = trim($href); // Asegurar que no haya espacios en la URL
            error_log("Verificando enlace: " . $href);

            if (empty($href)) {
                error_log("Enlace vacío encontrado y omitido.", 3, BLF_LOG_FILE_PATH);
                continue;
            }

            if (!filter_var($href, FILTER_VALIDATE_URL)) {
                error_log("Enlace no válido detectado: " . $href, 3, BLF_LOG_FILE_PATH);
                continue;
            }

            if (blf_is_internal_link($href)) {
                $internal_links_count++;
            } else {
                $external_links_count++;
            }

            if ($internal_only && !blf_is_internal_link($href)) {
                error_log("Enlace externo omitido: " . $href, 3, BLF_LOG_FILE_PATH);
                continue;
            }

            if (blf_is_broken_link($href)) {
                $broken_link_count++;
                error_log("Enlace roto detectado: " . $href, 3, BLF_LOG_FILE_PATH);

                // Patrón para encontrar la etiqueta <a> completa
                $link_pattern = '/<a\s+href=["\']' . preg_quote($href, '/') . '["\'].*?>(.*?)<\/a>/i';
                if (preg_match($link_pattern, $content, $link_matches)) {
                    $full_anchor_tag = $link_matches[0];
                    $anchor_text = $link_matches[1];

                    $content = str_replace($full_anchor_tag, $anchor_text, $content);
                    $cleaned_count++;
                    $last_cleaned_url = $href;
                    $content_changed = true;

                    error_log("Enlace roto encontrado y limpiado: " . $href, 3, BLF_LOG_FILE_PATH);
                }
            } else {
                error_log("El enlace no está roto: " . $href, 3, BLF_LOG_FILE_PATH);
            }
        }

        if ($content_changed) {
            wp_update_post(array(
                'ID' => $post->ID,
                'post_content' => $content,
            ));
            error_log("Contenido actualizado para post ID: " . $post->ID, 3, BLF_LOG_FILE_PATH);
        }

        if (!$post_id) {
            $next_post_id = $post->ID + 1;
        }
    }

    $result = array(
        'post_id' => $post_id ? $post_id : (isset($post) ? $post->ID : null),
        'link_count' => $link_count,
        'broken_link_count' => $broken_link_count,
        'cleaned_count' => $cleaned_count,
        'last_cleaned_url' => $last_cleaned_url,
        'next_post_id' => $next_post_id,
        'internal_links_count' => $internal_links_count, // Agregado al resultado
        'external_links_count' => $external_links_count, // Agregado al resultado
    );

    return $result;
}

function blf_get_post_id_from_url($url) {
    
    // Parsear la URL
    $parsed_url = parse_url($url);
    if (!isset($parsed_url['path'])) {
        return null;
    }

    // Obtener el slug del path de la URL
    $path = trim($parsed_url['path'], '/');
    $slug = basename($path);

    error_log("URL: $url", 3, BLF_LOG_FILE_PATH);
    error_log("Parsed URL: " . print_r($parsed_url, true), 3, BLF_LOG_FILE_PATH);
    error_log("Path: $path", 3, BLF_LOG_FILE_PATH);
    error_log("Slug: $slug", 3, BLF_LOG_FILE_PATH);

    // Buscar el post por el slug
    $post = get_page_by_path($slug, OBJECT, 'post');

    if (!$post) {
        error_log("No post found with slug: $slug", 3, BLF_LOG_FILE_PATH);
    } else {
        error_log("Found post ID: " . $post->ID, 3, BLF_LOG_FILE_PATH);
    }

    return $post ? $post->ID : null;
}


function blf_is_internal_link($url) {
    
    // Parseamos la URL principal del sitio
    $home_url = parse_url(home_url());
    $parsed_url = parse_url($url);

    // Asegurarse de que el parsed_url tenga un host
    if (!isset($parsed_url['host'])) {
        error_log("Internal link detected (relative URL): " . $url, 3, BLF_LOG_FILE_PATH);
        return true;
    }

    // Log para comparar valores
    error_log("Comparing home_url host: " . $home_url['host'] . " with URL host: " . $parsed_url['host'], 3, BLF_LOG_FILE_PATH);

    $home_host = strtolower($home_url['host']);
    $parsed_host = strtolower($parsed_url['host']);
    
    // Comparamos los hostnames para verificar si la URL es interna
    $is_internal = $home_host === $parsed_host;

    if ($is_internal) {
        error_log("Internal link confirmed: " . $url, 3, BLF_LOG_FILE_PATH);
    } else {
        error_log("External link detected: " . $url, 3, BLF_LOG_FILE_PATH);
    }

    return $is_internal;
}

function blf_is_broken_link($url) {
    if (empty($url)) {
        error_log("URL vacía proporcionada.", 3, BLF_LOG_FILE_PATH);
        return true; // Considerar vacío como roto
    }

    error_log("Verificando enlace: " . $url);

    // Incrementar el timeout a 10 segundos para dar más tiempo
    $response = wp_remote_head($url, array('timeout' => 10));
    if (is_wp_error($response)) {
        error_log("Error al verificar enlace: " . $response->get_error_message(), 3, BLF_LOG_FILE_PATH);
        return true;
    }

    $status = wp_remote_retrieve_response_code($response);
    error_log("Código de estado para " . $url . ": " . $status);

    // Consideramos roto si el código de estado es 404 o 410
    // Podrías agregar más códigos de estado si es necesario
    return in_array($status, array(404, 410));
}


// Enqueue scripts para la página de opciones
function blf_enqueue_scripts($hook) {
    // Verificar si estamos en el admin panel
    if (!is_admin()) {
        return;
    }
  
    if ($hook != 'settings_page_blf-options') {
        return;
    }

    wp_enqueue_script('blf-admin', plugin_dir_url(__FILE__) . 'js/blf-admin.js', array('jquery'), null, true);
    wp_localize_script('blf-admin', 'blf_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('blf-nonce')
    ));
}
add_action('admin_enqueue_scripts', 'blf_enqueue_scripts');

// Desactivar el evento al desactivar el plugin 
function blf_deactivate() {
    wp_clear_scheduled_hook('blf_daily_event');
}
register_deactivation_hook(__FILE__, 'blf_deactivate');