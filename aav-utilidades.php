<?php
/**
 * Plugin Name: AAV Utilidades
 * Plugin URI: https://allaboutvalencia.es
 * Description: Sistema completo de utilidades para All About Valencia
 * Version: 3.0
 * Author: Digitis
 * Text Domain: aav-utilidades
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
define('AAV_VERSION', '3.0');
define('AAV_PATH', plugin_dir_path(__FILE__));
define('AAV_URL', plugin_dir_url(__FILE__));

// IDs de Gravity Forms
define('AAV_EXPERTS_FORM_ID', 1);
define('AAV_GLOSSARY_FORM_ID', 2);
define('AAV_DOCUMENTS_FORM_ID', 3);
define('AAV_TOOLS_FORM_ID', 4);
define('AAV_CONTACT_FORM_ID', 5);

/**
 * Clase principal del plugin
 */
class AAV_Utilidades {
    
    private static $instance = null;
    private $used_ids = [];
    private $footnotes = [];
    private $footnote_counter = 0;
    
    /**
     * Singleton
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('the_content', [$this, 'process_footnotes'], 3);
        add_filter('the_content', [$this, 'add_heading_ids'], 4);
        add_filter('the_content', [$this, 'wrap_content'], 5);
        
        // AJAX handlers
        add_action('wp_ajax_aav_get_glossary_term', [$this, 'ajax_get_glossary_term']);
        add_action('wp_ajax_nopriv_aav_get_glossary_term', [$this, 'ajax_get_glossary_term']);
        add_action('wp_ajax_aav_load_contact_form', [$this, 'ajax_load_contact_form']);
        add_action('wp_ajax_nopriv_aav_load_contact_form', [$this, 'ajax_load_contact_form']);
        
        // Admin metaboxes
        add_action('add_meta_boxes', [$this, 'add_admin_metaboxes']);
        add_action('save_post', [$this, 'save_expert_metabox']);
    }
    
    /**
     * Inicialización
     */
    public function init() {
        // Cargar textdomain
        load_plugin_textdomain('aav-utilidades', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Registrar shortcodes
        $this->register_shortcodes();
    }
    
    /**
     * Registrar todos los shortcodes
     */
    private function register_shortcodes() {
        // Shortcodes de contenido
        add_shortcode('glosario', [$this, 'shortcode_glosario']);
        add_shortcode('arrow_link', [$this, 'shortcode_arrow_link']);
        add_shortcode('destacado', [$this, 'shortcode_destacado']);
        add_shortcode('caja_info', [$this, 'shortcode_caja_info']);
        add_shortcode('pasos', [$this, 'shortcode_pasos']);
        add_shortcode('documentos', [$this, 'shortcode_documentos']);
        add_shortcode('destacado_footer', [$this, 'shortcode_destacado_footer']);
    }
    
    /**
     * Cargar CSS y JS
     */
    public function enqueue_assets() {
        // Solo cargar en singles de posts
        if (!is_singular('post')) {
            return;
        }
        
        // CSS principal
        wp_enqueue_style('aav-styles', AAV_URL . 'aav.css', ['flatsome-main', 'flatsome-style'], AAV_VERSION);
        
        // JavaScript principal
        wp_enqueue_script('aav-scripts', AAV_URL . 'aav.js', ['jquery'], AAV_VERSION, true);
        
        // Localización para AJAX
        wp_localize_script('aav-scripts', 'aav_ajax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aav_nonce'),
            'contact_form_id' => AAV_CONTACT_FORM_ID
        ]);
        
        // Añadir clases al body
        add_filter('body_class', function($classes) {
            if (is_singular('post')) {
                $classes[] = 'aav-enabled';
                $classes[] = 'aav-single-post';
            }
            return $classes;
        });
    }
    
    /**
     * Añadir metaboxes en admin
     */
    public function add_admin_metaboxes() {
        add_meta_box(
            'aav_experts_metabox',
            'Expertos Verificadores',
            [$this, 'render_experts_metabox'],
            'post',
            'side',
            'default'
        );
    }
    
    /**
     * Renderizar metabox de expertos
     */
    public function render_experts_metabox($post) {
        // Nonce para seguridad
        wp_nonce_field('aav_experts_metabox', 'aav_experts_nonce');
        
        // Obtener expertos seleccionados
        $selected_experts = get_post_meta($post->ID, '_aav_verified_experts', true);
        if (!is_array($selected_experts)) {
            $selected_experts = [];
        }
        
        // Obtener todos los expertos disponibles
        if (!class_exists('GFAPI')) {
            echo '<p>Gravity Forms no está disponible.</p>';
            return;
        }
        
        $experts = GFAPI::get_entries(AAV_EXPERTS_FORM_ID, ['status' => 'active']);
        
        if (is_wp_error($experts)) {
            echo '<p>Error al obtener expertos: ' . $experts->get_error_message() . '</p>';
            return;
        }
        
        if (empty($experts)) {
            echo '<p>No hay expertos disponibles.</p>';
            return;
        }
        
        echo '<div class="aav-experts-checklist">';
        echo '<style>
            .aav-experts-checklist { max-height: 300px; overflow-y: auto; }
            .aav-expert-item { margin-bottom: 12px; }
            .aav-expert-item label { display: block; cursor: pointer; }
            .aav-expert-item label:hover { background: #f0f0f0; padding: 2px 4px; margin: -2px -4px; border-radius: 3px; }
            .aav-expert-item input { margin-right: 8px; }
            .aav-expert-name { font-weight: 600; }
            .aav-expert-title { font-size: 12px; color: #666; display: block; margin-left: 22px; }
        </style>';
        
        foreach ($experts as $expert) {
            $expert_id = $expert['id'];
            $name = rgar($expert, '1'); // Nombre y apellidos
            $title = rgar($expert, '2'); // Título profesional
            $specialty = rgar($expert, '11'); // Especialidad principal
            $email = rgar($expert, '5'); // Email de contacto
            
            // Si no hay nombre, extraer del email (parte antes del @)
            if (empty($name) && !empty($email)) {
                $email_parts = explode('@', $email);
                $name_from_email = $email_parts[0];
                // Convertir puntos en espacios y capitalizar
                $name = ucwords(str_replace('.', ' ', $name_from_email));
            }
            
            // Si aún no hay nombre, usar "Sin nombre"
            if (empty($name)) {
                $name = 'Sin nombre';
            }
            
            $checked = in_array($expert_id, $selected_experts) ? 'checked' : '';
            
            echo '<div class="aav-expert-item">';
            echo '<label>';
            echo '<input type="checkbox" name="aav_experts[]" value="' . esc_attr($expert_id) . '" ' . $checked . '>';
            echo '<span class="aav-expert-name">' . esc_html($name) . '</span>';
            // Mostrar título y especialidad si existen
            $info = array_filter([$title, $specialty]);
            if (!empty($info)) {
                echo '<span class="aav-expert-title">' . esc_html(implode(' - ', $info)) . '</span>';
            }
            echo '</label>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Guardar metabox de expertos
     */
    public function save_expert_metabox($post_id) {
        // Verificar nonce
        if (!isset($_POST['aav_experts_nonce']) || !wp_verify_nonce($_POST['aav_experts_nonce'], 'aav_experts_metabox')) {
            return;
        }
        
        // Verificar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Guardar expertos seleccionados
        $experts = isset($_POST['aav_experts']) ? array_map('intval', $_POST['aav_experts']) : [];
        update_post_meta($post_id, '_aav_verified_experts', $experts);
    }
    
    /**
     * Procesar footnotes antes de envolver el contenido
     */
    public function process_footnotes($content) {
        if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        // Reset footnotes para cada post
        $this->footnotes = [];
        $this->footnote_counter = 0;
        
        // Buscar y reemplazar <sup>número</sup>
        $content = preg_replace_callback('/<sup>(\d+)<\/sup>/', function($matches) {
            $this->footnote_counter++;
            $number = $matches[1];
            
            // Buscar la referencia correspondiente al final del contenido
            // Por ahora solo marcamos para procesamiento posterior
            return sprintf(
                '<sup class="aav-footnote-ref"><a href="#footnote-%d" id="ref-%d">[%d]</a></sup>',
                $number,
                $number,
                $number
            );
        }, $content);
        
        return $content;
    }
    
    /**
     * Envolver el contenido con nuestro layout
     */
    public function wrap_content($content) {
        // Solo en singles de posts
        if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        // Evitar doble procesamiento
        if (strpos($content, 'aav-layout') !== false) {
            return $content;
        }
        
        // Generar el sidebar con TOC
        $sidebar = $this->get_sidebar_content($content);
        
        // Obtener meta información
        $meta_info = $this->get_meta_info();
        
        // Envolver en nuestro layout
        $output = '<div class="aav-layout">';
        
        if ($sidebar) {
            $output .= '<aside class="aav-sidebar" id="aav-sidebar">' . $sidebar . '</aside>';
        }
        
        $output .= '<div class="aav-content">';
        $output .= '<h1 class="aav-post-title">' . get_the_title() . '</h1>';
        $output .= $meta_info;
        $output .= '<div class="aav-post-content">';
        
        // Imagen destacada
        if (has_post_thumbnail()) {
            $output .= '<div class="aav-featured-image">';
            $output .= get_the_post_thumbnail(get_the_ID(), 'large');
            $output .= '</div>';
        } else {
            $output .= '<div class="aav-featured-image-placeholder">Imagen destacada</div>';
        }
        
        // Contenido
        $output .= $content;
        
        // Bloques finales
        $output .= $this->get_footer_blocks();
        
        $output .= '</div>'; // .aav-post-content
        $output .= '</div>'; // .aav-content
        $output .= '</div>'; // .aav-layout
        
        return $output;
    }
    
    /**
     * Obtener meta información mejorada - VERSIÓN CON EXPERTOS DENTRO
     */
    private function get_meta_info() {
        $output = '<div class="aav-post-meta">';
        
        // Primera fila: fecha, autor y toggle de expertos
        $output .= '<div class="aav-meta-row">';
        
        // Configurar locale para fechas en español
        $meses = [
            'Jan' => 'ene', 'Feb' => 'feb', 'Mar' => 'mar', 'Apr' => 'abr',
            'May' => 'may', 'Jun' => 'jun', 'Jul' => 'jul', 'Aug' => 'ago',
            'Sep' => 'sep', 'Oct' => 'oct', 'Nov' => 'nov', 'Dec' => 'dic'
        ];
        
        $fecha_en = get_the_modified_date('j M Y');
        $fecha_es = str_replace(array_keys($meses), array_values($meses), $fecha_en);
        
        // Fecha
        $output .= '<span class="aav-meta-date">';
        $output .= '<svg class="aav-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">';
        $output .= '<path stroke="none" d="M0 0h24v24H0z" fill="none"></path>';
        $output .= '<rect x="4" y="5" width="16" height="16" rx="2"></rect>';
        $output .= '<line x1="16" y1="3" x2="16" y2="7"></line>';
        $output .= '<line x1="8" y1="3" x2="8" y2="7"></line>';
        $output .= '<line x1="4" y1="11" x2="20" y2="11"></line>';
        $output .= '<line x1="11" y1="15" x2="12" y2="15"></line>';
        $output .= '<line x1="12" y1="15" x2="12" y2="18"></line>';
        $output .= '</svg>';
        $output .= 'Actualizado el ' . $fecha_es;
        $output .= '</span>';
        
        // Autor
        $output .= '<span class="aav-meta-author">';
        $output .= '<svg class="aav-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">';
        $output .= '<path stroke="none" d="M0 0h24v24H0z" fill="none"></path>';
        $output .= '<circle cx="12" cy="7" r="4"></circle>';
        $output .= '<path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"></path>';
        $output .= '</svg>';
        $output .= 'Por <a href="' . get_author_posts_url(get_the_author_meta('ID')) . '">' . get_the_author() . '</a>';
        $output .= '</span>';
        
        // Expertos verificadores
        $experts = $this->get_post_experts(get_the_ID());
        if (!empty($experts)) {
            $expert_count = count($experts);
            $output .= '<span class="aav-meta-experts">';
            $output .= '<a href="#" class="aav-experts-toggle" aria-expanded="false">';
            $output .= '<svg class="aav-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">';
            $output .= '<path stroke="none" d="M0 0h24v24H0z" fill="none"></path>';
            $output .= '<polyline points="9 11 12 14 20 6"></polyline>';
            $output .= '<path d="M20 12v6a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h9"></path>';
            $output .= '</svg>';
            $output .= 'Verificado por ' . $expert_count . ' experto' . ($expert_count > 1 ? 's' : '');
            $output .= '<svg class="aav-chevron-small" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
            $output .= '</a>';
            $output .= '</span>';
        }
        
        $output .= '</div>'; // Cerrar .aav-meta-row
        
        // Lista de expertos DENTRO del meta
        if (!empty($experts)) {
            $output .= '<div class="aav-experts-list">';
            $output .= '<div class="aav-experts-card">';
            foreach ($experts as $index => $expert) {
                $output .= $this->render_expert_card($expert, $index === count($experts) - 1);
            }
            $output .= '</div>';
            $output .= '</div>';
        }
        
        $output .= '</div>'; // Cerrar .aav-post-meta
        
        return $output;
    }
    
    /**
     * Obtener expertos que verificaron este post
     */
    private function get_post_experts($post_id) {
        if (!class_exists('GFAPI')) {
            return [];
        }
        
        // Obtener IDs de expertos desde post meta
        $expert_ids = get_post_meta($post_id, '_aav_verified_experts', true);
        if (!is_array($expert_ids) || empty($expert_ids)) {
            return [];
        }
        
        $experts = [];
        foreach ($expert_ids as $expert_id) {
            $entry = GFAPI::get_entry($expert_id);
            if (!is_wp_error($entry) && $entry['status'] === 'active') {
                $experts[] = $entry;
            }
        }
        
        return $experts;
    }
    
    /**
     * Renderizar tarjeta de experto - VERSIÓN COMPACTA
     */
    private function render_expert_card($expert, $is_last = false) {
        $name = rgar($expert, '1'); // Nombre y apellidos
        $title = rgar($expert, '2'); // Título profesional
        $company = rgar($expert, '3'); // Empresa
        $bio = rgar($expert, '4'); // Biografía profesional
        $email = rgar($expert, '5'); // Email de contacto
        $specialty = rgar($expert, '11'); // Especialidad principal
        $photo_url = rgar($expert, '14'); // Foto de perfil (si existe este campo)
        $verified_date = rgar($expert, 'date_created');
        
        $output = '<div class="aav-expert-item' . ($is_last ? ' last' : '') . '" data-expert-id="' . esc_attr($expert['id']) . '">';
        
        // Foto o placeholder
        if ($photo_url) {
            $output .= '<img src="' . esc_url($photo_url) . '" alt="' . esc_attr($name) . '" class="aav-expert-photo">';
        } else {
            // Placeholder con logo del sitio
            $output .= '<div class="aav-expert-photo-placeholder">';
            $output .= '<img src="https://aav.digitis.net/wp-content/uploads/2025/04/orange.svg" alt="Logo" class="aav-expert-logo">';
            $output .= '</div>';
        }
        
        // Info
        $output .= '<div class="aav-expert-info">';
        $output .= '<div class="aav-expert-name">Verificado por ' . esc_html($name) . ' el ' . date_i18n('j \d\e F, Y', strtotime($verified_date)) . '</div>';
        
        // Biografía profesional combinada
        $bio_parts = array_filter([$name]);
        if ($company && $title) {
            $bio_parts[] = 'fundó ' . $company;
        } elseif ($company) {
            $bio_parts[] = 'trabaja en ' . $company;
        }
        
        if ($specialty) {
            $bio_parts[] = 'especializado en ' . $specialty;
        }
        
        if (!empty($bio_parts)) {
            $output .= '<div class="aav-expert-bio">' . esc_html(implode('. ', $bio_parts)) . '.</div>';
        } elseif ($bio) {
            $output .= '<div class="aav-expert-bio">' . esc_html($bio) . '</div>';
        }
        
        // Enlace de contacto
        $output .= '<a href="#" class="aav-expert-contact" data-expert-name="' . esc_attr($name) . '" data-expert-id="' . esc_attr($expert['id']) . '" data-expert-email="' . esc_attr($email) . '">Contacta y resuelve tus dudas</a>';
        
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Obtener bloques del footer
     */
    private function get_footer_blocks() {
        $output = '';
        
        // Bloque de donación
        $output .= $this->get_donation_block();
        
        // Referencias (si hay footnotes)
        if ($this->footnote_counter > 0) {
            $output .= $this->get_references_block();
        }
        
        // Guías relacionadas
        $output .= $this->get_related_guides_block();
        
        return $output;
    }
    
    /**
     * Bloque de donación
     */
    private function get_donation_block() {
        $output = '<div class="aav-donation-block">';
        $output .= '<h3>¿Te ha ayudado esta guía?</h3>';
        $output .= '<p>All About Valencia es un proyecto independiente. Tu apoyo nos ayuda a mantener el sitio actualizado y crear más guías útiles.</p>';
        $output .= '<a href="' . esc_url(get_option('aav_donation_url', '/donar')) . '" class="aav-donation-button">Apoyar el proyecto</a>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Bloque de referencias
     */
    private function get_references_block() {
        $output = '<div class="aav-references-block">';
        $output .= '<h3 class="aav-references-toggle" aria-expanded="false">';
        $output .= 'Referencias';
        $output .= '<span class="aav-toggle-icon">˅</span>';
        $output .= '</h3>';
        $output .= '<div class="aav-references-content">';
        $output .= '<ol class="aav-references-list">';
        
        // Por ahora, referencias de ejemplo
        for ($i = 1; $i <= $this->footnote_counter; $i++) {
            $output .= sprintf(
                '<li id="footnote-%d">Referencia %d <a href="#ref-%d" class="aav-back-link">↩</a></li>',
                $i,
                $i,
                $i
            );
        }
        
        $output .= '</ol>';
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Bloque de guías relacionadas
     */
    private function get_related_guides_block() {
        $categories = get_the_category();
        if (empty($categories)) {
            return '';
        }
        
        $args = [
            'category__in' => wp_list_pluck($categories, 'term_id'),
            'post__not_in' => [get_the_ID()],
            'posts_per_page' => 4,
            'orderby' => 'rand'
        ];
        
        $related = new WP_Query($args);
        
        if (!$related->have_posts()) {
            return '';
        }
        
        $output = '<div class="aav-related-guides">';
        $output .= '<h3>Guías relacionadas</h3>';
        $output .= '<div class="aav-guides-grid">';
        
        while ($related->have_posts()) {
            $related->the_post();
            $output .= '<article class="aav-guide-card">';
            $output .= '<a href="' . get_permalink() . '">';
            $output .= '<h4>' . get_the_title() . '</h4>';
            $output .= '<p>' . wp_trim_words(get_the_excerpt(), 15) . '</p>';
            $output .= '</a>';
            $output .= '</article>';
        }
        
        wp_reset_postdata();
        
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Obtener contenido del sidebar según contexto
     */
    private function get_sidebar_content($content = '') {
        // Para singles de posts: TOC
        if (is_singular('post')) {
            return $this->generate_toc($content);
        }
        
        return '';
    }
    
    /**
     * Generar TOC real basado en el contenido
     */
    private function generate_toc($content) {
        // Configuración
        $max_h2 = get_option('aav_max_toc_items', 10);
        $max_h3 = get_option('aav_max_toc_subitems', 4);
        
        // Extraer headings del contenido
        $headings = $this->extract_headings($content, $max_h2, $max_h3);
        
        if (empty($headings)) {
            return '';
        }
        
        // Construir HTML del TOC
        $output = '<nav class="aav-toc" role="navigation" aria-label="Tabla de contenidos">';
        $output .= '<h3 class="aav-toc-title">' . esc_html(get_option('aav_toc_title', 'En esta página')) . '</h3>';
        $output .= '<div class="aav-toc-toggle-wrapper">';
        $output .= '<button class="aav-toc-toggle" aria-expanded="false" aria-controls="aav-toc-list">';
        $output .= '<span>' . esc_html(get_option('aav_toc_title', 'En esta página')) . '</span>';
        $output .= '</button>';
        $output .= '</div>';
        $output .= '<ol class="aav-toc-list" id="aav-toc-list">';
        
        foreach ($headings as $heading) {
            $has_children = !empty($heading['children']);
            $output .= '<li' . ($has_children ? ' class="has-children"' : '') . '>';
            $output .= '<a href="#' . esc_attr($heading['id']) . '">' . esc_html($heading['text']);
            
            // Añadir chevron SVG si tiene hijos
            if ($has_children) {
                $output .= '<svg class="aav-chevron" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';
            }
            
            $output .= '</a>';
            
            if ($has_children) {
                $output .= '<ol>';
                foreach ($heading['children'] as $child) {
                    $output .= '<li><a href="#' . esc_attr($child['id']) . '">' . esc_html($child['text']) . '</a></li>';
                }
                $output .= '</ol>';
            }
            
            $output .= '</li>';
        }
        
        $output .= '</ol>';
        $output .= '</nav>';
        
        // Añadir bloque de donación en el sidebar
        $output .= '<div class="aav-sidebar-donation">';
        $output .= '<span class="aav-heart">❤</span>';
        $output .= '<div class="aav-donation-text">';
        $output .= '<p>¿Te ha ayudado esta web? Considera <a href="' . esc_url(get_option('aav_donation_url', '/donar')) . '">donar 10 €</a> para apoyar mi trabajo.</p>';
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Extraer headings del contenido
     */
    private function extract_headings($content, $max_h2 = 10, $max_h3 = 4) {
        $headings = [];
        $h2_count = 0;
        
        // Buscar todos los H2 con sus IDs existentes
        if (preg_match_all('/<h2([^>]*)>(.*?)<\/h2>/i', $content, $h2_matches, PREG_SET_ORDER)) {
            foreach ($h2_matches as $h2_match) {
                if ($h2_count >= $max_h2) break;
                
                $h2_full = $h2_match[0];
                $h2_attrs = $h2_match[1];
                $h2_content = $h2_match[2];
                
                // Obtener texto limpio del H2
                $h2_text = strip_tags($h2_content);
                
                // Extraer ID existente o generar uno nuevo
                $h2_id = '';
                if (preg_match('/id=["\']([^"\']+)["\']/', $h2_attrs, $id_match)) {
                    $h2_id = $id_match[1];
                } else {
                    $h2_id = $this->generate_heading_id($h2_text);
                }
                
                $heading = [
                    'level' => 2,
                    'text' => $h2_text,
                    'id' => $h2_id,
                    'children' => []
                ];
                
                // Buscar H3s después de este H2 y antes del siguiente H2
                $start_pos = strpos($content, $h2_full);
                $next_h2_pos = false;
                
                // Encontrar la posición del siguiente H2
                if (isset($h2_matches[$h2_count + 1])) {
                    $next_h2_pos = strpos($content, $h2_matches[$h2_count + 1][0], $start_pos + strlen($h2_full));
                }
                
                // Extraer la sección entre H2s
                if ($next_h2_pos !== false) {
                    $section = substr($content, $start_pos, $next_h2_pos - $start_pos);
                } else {
                    $section = substr($content, $start_pos);
                }
                
                // Buscar H3s en esta sección
                if (preg_match_all('/<h3([^>]*)>(.*?)<\/h3>/i', $section, $h3_matches, PREG_SET_ORDER)) {
                    $h3_count = 0;
                    foreach ($h3_matches as $h3_match) {
                        if ($h3_count >= $max_h3) break;
                        
                        $h3_attrs = $h3_match[1];
                        $h3_content = $h3_match[2];
                        $h3_text = strip_tags($h3_content);
                        
                        // Extraer ID existente o generar uno nuevo
                        $h3_id = '';
                        if (preg_match('/id=["\']([^"\']+)["\']/', $h3_attrs, $id_match)) {
                            $h3_id = $id_match[1];
                        } else {
                            $h3_id = $this->generate_heading_id($h3_text);
                        }
                        
                        $heading['children'][] = [
                            'level' => 3,
                            'text' => $h3_text,
                            'id' => $h3_id
                        ];
                        
                        $h3_count++;
                    }
                }
                
                $headings[] = $heading;
                $h2_count++;
            }
        }
        
        return $headings;
    }
    
    /**
     * Generar ID único para un heading
     */
    private function generate_heading_id($text) {
        // Limpiar el texto
        $id = sanitize_title($text);
        
        // Asegurar que el ID sea único
        if (!isset($this->used_ids)) {
            $this->used_ids = [];
        }
        
        $base_id = $id;
        $counter = 1;
        
        while (in_array($id, $this->used_ids)) {
            $id = $base_id . '-' . $counter;
            $counter++;
        }
        
        $this->used_ids[] = $id;
        return $id;
    }
    
    /**
     * Añadir IDs a los headings en el contenido
     */
    public function add_heading_ids($content) {
        // Solo procesar en singles de posts
        if (!is_singular('post')) {
            return $content;
        }
        
        // Reset de IDs usados para cada post
        $this->used_ids = [];
        
        // Añadir IDs a H2s
        $content = preg_replace_callback('/<h2([^>]*)>(.*?)<\/h2>/i', function($matches) {
            $attrs = $matches[1];
            $text = strip_tags($matches[2]);
            
            // Si ya tiene ID, no tocar
            if (preg_match('/id=["\']([^"\']+)["\']/', $attrs)) {
                return $matches[0];
            }
            
            $id = $this->generate_heading_id($text);
            return '<h2' . $attrs . ' id="' . $id . '">' . $matches[2] . '</h2>';
        }, $content);
        
        // Añadir IDs a H3s
        $content = preg_replace_callback('/<h3([^>]*)>(.*?)<\/h3>/i', function($matches) {
            $attrs = $matches[1];
            $text = strip_tags($matches[2]);
            
            // Si ya tiene ID, no tocar
            if (preg_match('/id=["\']([^"\']+)["\']/', $attrs)) {
                return $matches[0];
            }
            
            $id = $this->generate_heading_id($text);
            return '<h3' . $attrs . ' id="' . $id . '">' . $matches[2] . '</h3>';
        }, $content);
        
        return $content;
    }
    
    /**
     * Shortcode [glosario]
     */
    public function shortcode_glosario($atts, $content = '') {
        $atts = shortcode_atts([
            'termino' => '',
            'id' => ''
        ], $atts, 'glosario');
        
        if (empty($atts['termino']) || empty($content)) {
            return $content;
        }
        
        $term_slug = sanitize_title($atts['termino']);
        $glossary_id = !empty($atts['id']) ? intval($atts['id']) : 0;
        
        return sprintf(
            '<span class="aav-glossary-term" data-term="%s" data-id="%d" tabindex="0">%s</span>',
            esc_attr($term_slug),
            $glossary_id,
            esc_html($content)
        );
    }
    
    /**
     * Shortcode [arrow_link]
     */
    public function shortcode_arrow_link($atts, $content = '') {
        $atts = shortcode_atts([
            'url' => '',
            'text' => '',
            'blank' => 'no'
        ], $atts, 'arrow_link');
        
        if (empty($atts['url'])) {
            return '';
        }
        
        $text = !empty($atts['text']) ? $atts['text'] : $content;
        $target = ($atts['blank'] === 'yes') ? ' target="_blank" rel="noopener"' : '';
        
        return sprintf(
            '<a href="%s" class="aav-arrow-link"%s>%s <span class="aav-arrow">→</span></a>',
            esc_url($atts['url']),
            $target,
            esc_html($text)
        );
    }
    
    /**
     * Shortcode [destacado]
     */
    public function shortcode_destacado($atts, $content = '') {
        $atts = shortcode_atts([
            'tipo' => 'normal'
        ], $atts, 'destacado');
        
        $class = 'aav-destacado';
        if ($atts['tipo'] === 'borde') {
            $class .= ' aav-destacado-borde';
        }
        
        return sprintf(
            '<div class="%s">%s</div>',
            esc_attr($class),
            do_shortcode($content)
        );
    }
    
    /**
     * Shortcode [caja_info]
     */
    public function shortcode_caja_info($atts, $content = '') {
        $atts = shortcode_atts([
            'tipo' => 'aviso'
        ], $atts, 'caja_info');
        
        $icons = [
            'aviso' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M12 9v2m0 4v.01"></path><path d="M5 19h14a2 2 0 0 0 1.84 -2.75l-7.1 -12.25a2 2 0 0 0 -3.5 0l-7.1 12.25a2 2 0 0 0 1.75 2.75"></path></svg>',
            'consejo' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M3 12h1m8 -9v1m8 8h1m-15.4 -6.4l.7 .7m12.1 -.7l-.7 .7"></path><path d="M9 16a5 5 0 1 1 6 0a3.5 3.5 0 0 0 -1 3a2 2 0 0 1 -4 0a3.5 3.5 0 0 0 -1 -3"></path><line x1="9.7" y1="17" x2="14.3" y2="17"></line></svg>',
            'importante' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><circle cx="12" cy="12" r="9"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
        ];
        
        $icon = isset($icons[$atts['tipo']]) ? $icons[$atts['tipo']] : $icons['aviso'];
        
        return sprintf(
            '<div class="aav-info-box aav-info-%s"><div class="aav-info-icon">%s</div><div class="aav-info-content">%s</div></div>',
            esc_attr($atts['tipo']),
            $icon,
            do_shortcode($content)
        );
    }
    
    /**
     * Shortcode [pasos]
     */
    public function shortcode_pasos($atts, $content = '') {
        // Convertir lista normal a lista con clase especial
        $content = do_shortcode($content);
        
        // Si ya es una lista ordenada, solo añadir clase
        if (strpos($content, '<ol') !== false) {
            $content = str_replace('<ol', '<ol class="aav-steps-list"', $content);
        } else {
            // Si no, envolver en OL
            $lines = array_filter(explode("\n", strip_tags($content, '<br>')));
            $content = '<ol class="aav-steps-list">';
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $content .= '<li>' . $line . '</li>';
                }
            }
            $content .= '</ol>';
        }
        
        return $content;
    }
    
    /**
     * Shortcode [documentos]
     */
    public function shortcode_documentos($atts, $content = '') {
        // Convertir lista normal a lista con checkboxes
        $content = do_shortcode($content);
        
        // Si ya es una lista, solo añadir clase
        if (strpos($content, '<ul') !== false) {
            $content = str_replace('<ul', '<ul class="aav-documents-list"', $content);
        } else {
            // Si no, crear lista
            $lines = array_filter(explode("\n", strip_tags($content, '<br>')));
            $content = '<ul class="aav-documents-list">';
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $checked = strpos($line, '✓') !== false || strpos($line, '✔') !== false;
                    $line = str_replace(['✓', '✔'], '', $line);
                    $content .= '<li class="' . ($checked ? 'checked' : '') . '">' . trim($line) . '</li>';
                }
            }
            $content .= '</ul>';
        }
        
        return $content;
    }
    
    /**
     * Shortcode [destacado_footer]
     */
    public function shortcode_destacado_footer($atts, $content = '') {
        return sprintf(
            '<div class="aav-destacado-footer">%s</div>',
            do_shortcode($content)
        );
    }
    
    /**
     * AJAX handler para obtener término del glosario
     */
    public function ajax_get_glossary_term() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'aav_nonce')) {
            wp_die('Acceso no autorizado');
        }
        
        $term_slug = sanitize_text_field($_POST['term']);
        $term_id = intval($_POST['id']);
        
        // Si tenemos ID, buscar por ID, si no, buscar por slug
        if ($term_id > 0) {
            $entry = GFAPI::get_entry($term_id);
        } else {
            // Buscar en Gravity Forms por slug
            $search_criteria = [
                'status' => 'active',
                'field_filters' => [
                    [
                        'key' => '1', // Campo del término
                        'value' => $term_slug,
                        'operator' => 'is'
                    ]
                ]
            ];
            
            $entries = GFAPI::get_entries(AAV_GLOSSARY_FORM_ID, $search_criteria, null, ['page_size' => 1]);
            $entry = !empty($entries) ? $entries[0] : null;
        }
        
        if (!$entry || is_wp_error($entry)) {
            wp_send_json_error('Término no encontrado');
        }
        
        // Obtener datos del término
        $data = [
            'term' => rgar($entry, '1'),
            'definition_short' => rgar($entry, '7'),
            'definition_full' => rgar($entry, '16'),
            'related_guides' => rgar($entry, '18')
        ];
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX handler para cargar formulario de contacto
     */
    public function ajax_load_contact_form() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'aav_nonce')) {
            wp_die('Acceso no autorizado');
        }
        
        $expert_id = intval($_POST['expert_id']);
        $expert_name = sanitize_text_field($_POST['expert_name']);
        $expert_email = sanitize_email($_POST['expert_email']);
        $current_page = sanitize_text_field($_POST['current_page']);
        
        // Renderizar el formulario de Gravity Forms
        if (class_exists('GFAPI')) {
            // Pre-poblar valores del formulario
            add_filter('gform_field_value', function($value, $field, $name) use ($expert_name, $expert_email, $current_page) {
                // Campos del formulario según el JSON proporcionado
                if ($name === 'expert_name') {
                    return $expert_name;
                }
                if ($name === 'expert_email') {
                    return $expert_email;
                }
                if ($name === 'pagina_solicitud' || $field->id == 6) {
                    return $current_page;
                }
                return $value;
            }, 10, 3);
            
            // Modificar el mensaje de envío para incluir el nombre del experto
            add_filter('gform_pre_submission_' . AAV_CONTACT_FORM_ID, function($form) use ($expert_name, $expert_email) {
                // Añadir el nombre del experto al asunto del email
                foreach ($form['notifications'] as &$notification) {
                    if ($notification['toType'] === 'email' && !empty($expert_email)) {
                        // Enviar copia al experto
                        $notification['to'] .= ',' . $expert_email;
                    }
                    $notification['subject'] = 'Consulta para ' . $expert_name . ' - ' . $notification['subject'];
                }
                return $form;
            });
            
            // Renderizar el formulario
            echo do_shortcode('[gravityform id="' . AAV_CONTACT_FORM_ID . '" title="false" description="false" ajax="true"]');
            
            // Añadir un campo oculto con información del experto via JavaScript
            ?>
            <script>
            jQuery(document).ready(function($) {
                // Añadir información del experto al formulario
                var expertInfo = '<input type="hidden" name="expert_info" value="<?php echo esc_attr($expert_name . ' (' . $expert_email . ')'); ?>">';
                $('#gform_<?php echo AAV_CONTACT_FORM_ID; ?>').append(expertInfo);
                
                // Pre-llenar el campo de página de solicitud
                $('#input_<?php echo AAV_CONTACT_FORM_ID; ?>_6').val('<?php echo esc_js($current_page); ?>');
                
                // Personalizar el mensaje del textarea
                var textarea = $('#input_<?php echo AAV_CONTACT_FORM_ID; ?>_5');
                if (textarea.length && textarea.val() === '') {
                    textarea.attr('placeholder', 'Escribe aquí tu consulta para <?php echo esc_js($expert_name); ?>');
                }
            });
            </script>
            <?php
        } else {
            echo '<p>Error: Gravity Forms no está instalado.</p>';
        }
        
        wp_die();
    }
}

// Inicializar plugin
add_action('plugins_loaded', function() {
    AAV_Utilidades::instance();
});

// Activación del plugin
register_activation_hook(__FILE__, function() {
    // Configuración inicial
    add_option('aav_toc_title', 'En esta página');
    add_option('aav_sidebar_width', '369px');
    add_option('aav_sidebar_padding', '0');
    add_option('aav_content_padding', '3rem');
    add_option('aav_max_toc_items', 10);
    add_option('aav_max_toc_subitems', 4);
    add_option('aav_donation_url', '/donar');
    
    // Flush rewrite rules
    flush_rewrite_rules();
});

// Desactivación del plugin
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});