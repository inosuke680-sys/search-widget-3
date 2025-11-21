<?php
/**
 * Plugin Name: UmaTen Restaurant Search Widget
 * Plugin URI: https://umaten.jp
 * Description: 飲食店レビューサイト用の高度な検索ウィジェット。現在のカテゴリとタグを自動取得し、範囲指定検索とフリーワード検索が可能です。
 * Version: 1.1.2
 * Author: UmaTen
 * Author URI: https://umaten.jp
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: umaten-restaurant-search
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('UMATEN_SEARCH_VERSION', '1.1.2');
define('UMATEN_SEARCH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UMATEN_SEARCH_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class Umaten_Restaurant_Search_Widget_Plugin {

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        require_once UMATEN_SEARCH_PLUGIN_DIR . 'includes/class-widget.php';
        require_once UMATEN_SEARCH_PLUGIN_DIR . 'includes/class-search-handler.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'register_taxonomies'), 0);
        add_action('widgets_init', array($this, 'register_widgets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        // v1.1.0: 404エラー処理の改善
        add_action('template_redirect', array($this, 'handle_taxonomy_urls'), 1);
    }

    /**
     * Register custom taxonomies automatically
     * プラグインを入れるだけで動作するようにタクソノミーを自動登録
     */
    public function register_taxonomies() {
        // 都道府県タクソノミー (Region)
        register_taxonomy('region', array('post'), array(
            'labels' => array(
                'name' => '都道府県',
                'singular_name' => '都道府県',
                'menu_name' => '都道府県',
                'all_items' => 'すべての都道府県',
                'edit_item' => '都道府県を編集',
                'view_item' => '都道府県を表示',
                'update_item' => '都道府県を更新',
                'add_new_item' => '新しい都道府県を追加',
                'new_item_name' => '新しい都道府県名',
                'search_items' => '都道府県を検索',
                'popular_items' => '人気の都道府県',
                'not_found' => '都道府県が見つかりません',
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'rewrite' => array(
                'slug' => 'region',
                'with_front' => false,
                'hierarchical' => true,
            ),
            'public' => true,
            'show_tagcloud' => false,
        ));

        // エリアタクソノミー (Area)
        register_taxonomy('area', array('post'), array(
            'labels' => array(
                'name' => 'エリア',
                'singular_name' => 'エリア',
                'menu_name' => 'エリア',
                'all_items' => 'すべてのエリア',
                'edit_item' => 'エリアを編集',
                'view_item' => 'エリアを表示',
                'update_item' => 'エリアを更新',
                'add_new_item' => '新しいエリアを追加',
                'new_item_name' => '新しいエリア名',
                'search_items' => 'エリアを検索',
                'popular_items' => '人気のエリア',
                'not_found' => 'エリアが見つかりません',
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'rewrite' => array(
                'slug' => 'area',
                'with_front' => false,
                'hierarchical' => true,
            ),
            'public' => true,
            'show_tagcloud' => false,
        ));

        // ジャンルタクソノミー (Genre)
        register_taxonomy('genre', array('post'), array(
            'labels' => array(
                'name' => 'ジャンル',
                'singular_name' => 'ジャンル',
                'menu_name' => 'ジャンル',
                'all_items' => 'すべてのジャンル',
                'edit_item' => 'ジャンルを編集',
                'view_item' => 'ジャンルを表示',
                'update_item' => 'ジャンルを更新',
                'add_new_item' => '新しいジャンルを追加',
                'new_item_name' => '新しいジャンル名',
                'search_items' => 'ジャンルを検索',
                'popular_items' => '人気のジャンル',
                'not_found' => 'ジャンルが見つかりません',
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'rewrite' => array(
                'slug' => 'genre',
                'with_front' => false,
                'hierarchical' => true,
            ),
            'public' => true,
            'show_tagcloud' => true,
        ));
    }

    /**
     * Register widgets
     */
    public function register_widgets() {
        register_widget('Umaten_Restaurant_Search_Widget');
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'umaten-search-widget',
            UMATEN_SEARCH_PLUGIN_URL . 'assets/css/style.css',
            array(),
            UMATEN_SEARCH_VERSION
        );

        wp_enqueue_script(
            'umaten-search-widget',
            UMATEN_SEARCH_PLUGIN_URL . 'assets/js/search.js',
            array('jquery'),
            UMATEN_SEARCH_VERSION,
            true
        );

        // Localize script for AJAX
        wp_localize_script('umaten-search-widget', 'umatenSearch', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('umaten_search_nonce')
        ));
    }

    /**
     * Add custom rewrite rules for hierarchical category/tag URLs
     * Example: /hokkaido/susukino/washoku
     * v1.1.2: WordPress コアURLやプラグインURLとの競合を防ぐため、
     * 除外パターンを追加し、優先度を調整
     */
    public function add_rewrite_rules() {
        // WordPress予約語とコアURLのパターンを除外
        // wp-, sitemap, feed, trackback, xmlrpc, robots.txt などを除外
        $exclusions = 'wp-admin|wp-content|wp-includes|wp-json|feed|trackback|xmlrpc|sitemap|sitemap\.xml|wp-sitemap\.xml|robots\.txt|favicon\.ico';

        // カスタムリライトルールを追加
        // 3階層: 都道府県/エリア/ジャンル
        add_rewrite_rule(
            '^(?!' . $exclusions . ')([^/]+)/([^/]+)/([^/]+)/?$',
            'index.php?umaten_region=$matches[1]&umaten_area=$matches[2]&umaten_genre=$matches[3]',
            'bottom'
        );

        // 2階層: 都道府県/エリア
        add_rewrite_rule(
            '^(?!' . $exclusions . ')([^/]+)/([^/]+)/?$',
            'index.php?umaten_region=$matches[1]&umaten_area=$matches[2]',
            'bottom'
        );

        // 1階層: 都道府県のみ
        add_rewrite_rule(
            '^(?!' . $exclusions . ')([^/]+)/?$',
            'index.php?umaten_region=$matches[1]',
            'bottom'
        );
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'umaten_region';
        $vars[] = 'umaten_area';
        $vars[] = 'umaten_genre';
        $vars[] = 'umaten_keyword';
        $vars[] = 'umaten_search_scope';
        return $vars;
    }

    /**
     * Handle taxonomy URLs and prevent redirect to home
     * v1.1.0: リライトルールで設定されたクエリ変数を適切に処理
     */
    public function handle_taxonomy_urls() {
        $region = get_query_var('umaten_region', '');
        $area = get_query_var('umaten_area', '');
        $genre = get_query_var('umaten_genre', '');

        // いずれかのクエリ変数が設定されている場合
        if (!empty($region) || !empty($area) || !empty($genre)) {
            // タクソノミータームの存在チェック
            $valid_query = false;

            if (!empty($region)) {
                $term = get_term_by('slug', $region, 'region');
                if ($term && !is_wp_error($term)) {
                    $valid_query = true;
                }
            }

            if (!empty($area)) {
                $term = get_term_by('slug', $area, 'area');
                if ($term && !is_wp_error($term)) {
                    $valid_query = true;
                }
            }

            if (!empty($genre)) {
                $term = get_term_by('slug', $genre, 'genre');
                if ($term && !is_wp_error($term)) {
                    $valid_query = true;
                }
            }

            // 有効なタクソノミーが1つも存在しない場合は404
            if (!$valid_query) {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                get_template_part(404);
                exit();
            }
        }
    }
}

/**
 * Initialize the plugin
 */
function umaten_restaurant_search_widget_init() {
    return Umaten_Restaurant_Search_Widget_Plugin::get_instance();
}

// Start the plugin
umaten_restaurant_search_widget_init();

/**
 * Activation hook
 */
register_activation_hook(__FILE__, 'umaten_search_widget_activate');
function umaten_search_widget_activate() {
    // Flush rewrite rules on activation
    flush_rewrite_rules();
}

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, 'umaten_search_widget_deactivate');
function umaten_search_widget_deactivate() {
    // Flush rewrite rules on deactivation
    flush_rewrite_rules();
}
