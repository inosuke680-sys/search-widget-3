<?php
/**
 * Taxonomy Registration Example for UmaTen Restaurant Search Widget
 *
 * このファイルは、プラグインで使用するカスタムタクソノミーの登録例です。
 * テーマのfunctions.phpまたは別のプラグインに追加してください。
 *
 * @package UmaTen_Restaurant_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register custom taxonomies for restaurant search
 */
function umaten_register_restaurant_taxonomies() {

    // 都道府県タクソノミー (Region)
    register_taxonomy('region', array('post', 'restaurant'), array(
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
    register_taxonomy('area', array('post', 'restaurant'), array(
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
    register_taxonomy('genre', array('post', 'restaurant'), array(
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

// タクソノミーを登録
add_action('init', 'umaten_register_restaurant_taxonomies', 0);

/**
 * Add meta box for hierarchical taxonomy selection
 * 階層的なタクソノミー選択のためのメタボックスを追加
 */
function umaten_add_taxonomy_meta_boxes() {
    // 都道府県メタボックス
    add_meta_box(
        'umaten_region_meta_box',
        '都道府県を選択',
        'umaten_region_meta_box_callback',
        'post',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'umaten_add_taxonomy_meta_boxes');

/**
 * 都道府県選択メタボックスのコールバック
 */
function umaten_region_meta_box_callback($post) {
    $terms = get_terms(array(
        'taxonomy' => 'region',
        'hide_empty' => false,
    ));

    $current_term = wp_get_post_terms($post->ID, 'region', array('fields' => 'ids'));
    $current_term_id = !empty($current_term) ? $current_term[0] : 0;

    echo '<select name="umaten_region" id="umaten-region-select" style="width: 100%;">';
    echo '<option value="">-- 選択してください --</option>';

    if (!empty($terms) && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $selected = selected($current_term_id, $term->term_id, false);
            echo '<option value="' . esc_attr($term->term_id) . '"' . $selected . '>';
            echo esc_html($term->name);
            echo '</option>';
        }
    }

    echo '</select>';
}

/**
 * Custom rewrite rules for hierarchical URLs
 * 階層的URLのためのカスタムリライトルール
 *
 * 例: /hokkaido/susukino/washoku → 北海道 > すすきの > 和食
 */
function umaten_custom_rewrite_rules() {
    // 3階層: region/area/genre
    add_rewrite_rule(
        '^([^/]+)/([^/]+)/([^/]+)/?$',
        'index.php?region=$matches[1]&area=$matches[2]&genre=$matches[3]',
        'top'
    );

    // 2階層: region/area
    add_rewrite_rule(
        '^([^/]+)/([^/]+)/?$',
        'index.php?region=$matches[1]&area=$matches[2]',
        'top'
    );
}
add_action('init', 'umaten_custom_rewrite_rules');

/**
 * Flush rewrite rules on theme activation
 * テーマ有効化時にリライトルールをフラッシュ
 */
function umaten_flush_rewrite_rules_on_activation() {
    umaten_register_restaurant_taxonomies();
    umaten_custom_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'umaten_flush_rewrite_rules_on_activation');

/**
 * Example: Pre-populate with Japanese prefectures
 * 例: 日本の都道府県を事前登録
 */
function umaten_prepopulate_regions() {
    // この関数は一度だけ実行してください
    // 既に都道府県が登録されている場合は実行しない
    $existing_terms = get_terms(array(
        'taxonomy' => 'region',
        'hide_empty' => false,
    ));

    if (!empty($existing_terms)) {
        return; // 既に登録済み
    }

    $prefectures = array(
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県',
        '岐阜県', '静岡県', '愛知県', '三重県',
        '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
        '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県',
        '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県',
        '沖縄県'
    );

    $prefecture_slugs = array(
        'hokkaido', 'aomori', 'iwate', 'miyagi', 'akita', 'yamagata', 'fukushima',
        'ibaraki', 'tochigi', 'gunma', 'saitama', 'chiba', 'tokyo', 'kanagawa',
        'niigata', 'toyama', 'ishikawa', 'fukui', 'yamanashi', 'nagano',
        'gifu', 'shizuoka', 'aichi', 'mie',
        'shiga', 'kyoto', 'osaka', 'hyogo', 'nara', 'wakayama',
        'tottori', 'shimane', 'okayama', 'hiroshima', 'yamaguchi',
        'tokushima', 'kagawa', 'ehime', 'kochi',
        'fukuoka', 'saga', 'nagasaki', 'kumamoto', 'oita', 'miyazaki', 'kagoshima',
        'okinawa'
    );

    foreach ($prefectures as $index => $prefecture) {
        wp_insert_term($prefecture, 'region', array(
            'slug' => $prefecture_slugs[$index],
        ));
    }
}

// 都道府県の事前登録（必要に応じてコメントアウトを外してください）
// add_action('init', 'umaten_prepopulate_regions', 999);

/**
 * Example: Add custom columns to admin list
 * 例: 管理画面のリストにカスタム列を追加
 */
function umaten_custom_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['region'] = '都道府県';
            $new_columns['area'] = 'エリア';
            $new_columns['genre'] = 'ジャンル';
        }
    }
    return $new_columns;
}
add_filter('manage_post_posts_columns', 'umaten_custom_columns');

/**
 * Display custom column content
 */
function umaten_custom_column_content($column, $post_id) {
    switch ($column) {
        case 'region':
            $terms = get_the_terms($post_id, 'region');
            if ($terms && !is_wp_error($terms)) {
                $term_names = array();
                foreach ($terms as $term) {
                    $term_names[] = $term->name;
                }
                echo esc_html(implode(', ', $term_names));
            }
            break;

        case 'area':
            $terms = get_the_terms($post_id, 'area');
            if ($terms && !is_wp_error($terms)) {
                $term_names = array();
                foreach ($terms as $term) {
                    $term_names[] = $term->name;
                }
                echo esc_html(implode(', ', $term_names));
            }
            break;

        case 'genre':
            $terms = get_the_terms($post_id, 'genre');
            if ($terms && !is_wp_error($terms)) {
                $term_names = array();
                foreach ($terms as $term) {
                    $term_names[] = $term->name;
                }
                echo esc_html(implode(', ', $term_names));
            }
            break;
    }
}
add_action('manage_post_posts_custom_column', 'umaten_custom_column_content', 10, 2);
