<?php
/**
 * UmaTen Restaurant Search Handler Class
 *
 * @package UmaTen_Restaurant_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Search handler class
 */
class Umaten_Restaurant_Search_Handler {

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
        add_action('pre_get_posts', array($this, 'modify_search_query'));
        add_filter('posts_where', array($this, 'custom_search_where'), 10, 2);
        add_filter('posts_join', array($this, 'custom_search_join'), 10, 2);
        add_filter('posts_groupby', array($this, 'custom_search_groupby'), 10, 2);
        // v1.1.1: KUSANAGIキャッシュ対策のためのヘッダー送信
        add_action('template_redirect', array($this, 'send_cache_headers'), 5);
    }

    /**
     * Modify main query for search
     */
    public function modify_search_query($query) {
        // 管理画面やメインクエリでない場合は処理しない
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // 検索パラメータを取得
        $umaten_search = isset($_GET['umaten_search']) ? $_GET['umaten_search'] : '';

        // リライトルールから来たクエリ変数を取得
        $region = get_query_var('umaten_region', '');
        $area = get_query_var('umaten_area', '');
        $genre = get_query_var('umaten_genre', '');

        // UmaTen検索フラグまたはリライトルールからのクエリ変数がない場合は処理しない
        // v1.1.0: 直接URL（/hokkaido/hakodate/ramenなど）からのアクセスにも対応
        if (empty($umaten_search) && empty($region) && empty($area) && empty($genre)) {
            return;
        }

        // v1.1.1: KUSANAGIキャッシュ対策 - 検索クエリであることを明示的に設定
        // モバイルデバイスでキャッシュクリア後にホームページが表示される問題を修正
        $query->is_home = false;
        $query->is_archive = true;
        $query->is_search = false; // タクソノミー検索なのでfalse

        // ===== DDoS攻撃対策 =====

        // 1. Nonce検証（CSRF対策）
        // v1.1.0: 直接URLアクセス（リライトルール経由）の場合はNonceチェックをスキップ
        if (!empty($umaten_search)) {
//             if (!isset($_GET['umaten_search_nonce']) || !wp_verify_nonce($_GET['umaten_search_nonce'], 'umaten_search_action')) {
//                 // Nonceが無効な場合は処理を中止
//                 wp_die('セキュリティチェックに失敗しました。ページを再読み込みして再試行してください。', 'セキュリティエラー', array('response' => 403));
//                 return;
//             }
        }

        // 2. レート制限チェック
        if (!$this->check_rate_limit()) {
            wp_die('検索リクエストが多すぎます。しばらくしてから再試行してください。', 'リクエスト制限', array('response' => 429));
            return;
        }

        // 3. パラメータのバリデーションとサニタイゼーション（強化）
        $keyword = isset($_GET['umaten_keyword']) ? $this->sanitize_search_keyword($_GET['umaten_keyword']) : '';
        $category_id = isset($_GET['umaten_category']) ? absint($_GET['umaten_category']) : 0;
        $tag_id = isset($_GET['umaten_tag']) ? absint($_GET['umaten_tag']) : 0;

        // v1.1.0: リライトルールから来た値を優先（すでに取得済み）
        $region = $this->sanitize_taxonomy_slug($region);
        $area = $this->sanitize_taxonomy_slug($area);
        $genre = $this->sanitize_taxonomy_slug($genre);

        // フラグを設定(カスタム検索クエリであることを示す)
        $query->set('umaten_custom_search', true);

        // 税クエリ配列を初期化
        $tax_query = array('relation' => 'AND');

        // カテゴリフィルター
        if (!empty($category_id)) {
            $tax_query[] = array(
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $category_id,
                'include_children' => true
            );
        }

        // タグフィルター
        if (!empty($tag_id)) {
            $tax_query[] = array(
                'taxonomy' => 'post_tag',
                'field' => 'term_id',
                'terms' => $tag_id
            );
        }

        // 地域タクソノミー検索（既存のリライトルール対応）
        // v1.1.0: タクソノミータームの存在チェックを改善し、ターム検証を強化
        // v1.1.1: is_archive/is_homeの設定は関数の最初で一度だけ行うように変更
        if (!empty($region)) {
            $region_term = get_term_by('slug', $region, 'region');
            if ($region_term && !is_wp_error($region_term)) {
                $tax_query[] = array(
                    'taxonomy' => 'region',
                    'field' => 'slug',
                    'terms' => $region
                );
            }
        }

        if (!empty($area)) {
            $area_term = get_term_by('slug', $area, 'area');
            if ($area_term && !is_wp_error($area_term)) {
                $tax_query[] = array(
                    'taxonomy' => 'area',
                    'field' => 'slug',
                    'terms' => $area
                );
            }
        }

        if (!empty($genre)) {
            $genre_term = get_term_by('slug', $genre, 'genre');
            if ($genre_term && !is_wp_error($genre_term)) {
                $tax_query[] = array(
                    'taxonomy' => 'genre',
                    'field' => 'slug',
                    'terms' => $genre
                );
            }
        }

        // 税クエリを適用
        if (count($tax_query) > 1) {
            $query->set('tax_query', $tax_query);
        }

        // キーワード検索
        if (!empty($keyword)) {
            $query->set('s', $keyword);
        }
    }

    /**
     * Apply search scope
     */
    private function apply_search_scope($query, $scope, $region, $area, $genre) {
        // スコープごとの処理
        if ($scope === 'all') {
            // すべて検索 - 特別な制限なし
            return;
        }

        // カテゴリ指定
        if (strpos($scope, 'category_') === 0) {
            $category_id = str_replace('category_', '', $scope);
            $query->set('cat', $category_id);
            return;
        }

        // タグ指定
        if (strpos($scope, 'tag_') === 0) {
            $tag_id = str_replace('tag_', '', $scope);
            $query->set('tag_id', $tag_id);
            return;
        }

        // 地域階層での検索
        if ($scope === 'region' && !empty($region)) {
            $query->set('umaten_scope_region', $region);
        } elseif ($scope === 'area' && !empty($region) && !empty($area)) {
            $query->set('umaten_scope_region', $region);
            $query->set('umaten_scope_area', $area);
        } elseif ($scope === 'genre' && !empty($region) && !empty($area) && !empty($genre)) {
            $query->set('umaten_scope_region', $region);
            $query->set('umaten_scope_area', $area);
            $query->set('umaten_scope_genre', $genre);
        }
    }

    /**
     * Apply hierarchical search (region > area > genre)
     */
    private function apply_hierarchical_search($query, $region, $area, $genre) {
        $tax_query = array('relation' => 'AND');

        // 都道府県タクソノミー
        if (!empty($region)) {
            $region_term = get_term_by('slug', $region, 'region');
            if ($region_term) {
                $tax_query[] = array(
                    'taxonomy' => 'region',
                    'field' => 'slug',
                    'terms' => $region,
                );
            }
        }

        // エリアタクソノミー
        if (!empty($area)) {
            $area_term = get_term_by('slug', $area, 'area');
            if ($area_term) {
                $tax_query[] = array(
                    'taxonomy' => 'area',
                    'field' => 'slug',
                    'terms' => $area,
                );
            }
        }

        // ジャンルタクソノミー
        if (!empty($genre)) {
            $genre_term = get_term_by('slug', $genre, 'genre');
            if ($genre_term) {
                $tax_query[] = array(
                    'taxonomy' => 'genre',
                    'field' => 'slug',
                    'terms' => $genre,
                );
            }
        }

        if (count($tax_query) > 1) {
            $query->set('tax_query', $tax_query);
        }
    }

    /**
     * Custom WHERE clause for advanced search
     */
    public function custom_search_where($where, $query) {
        global $wpdb;

        if (!$query->get('umaten_custom_search')) {
            return $where;
        }

        $keyword = $query->get('s', '');

        if (!empty($keyword)) {
            // タイトル、本文、抜粋、カスタムフィールドで検索
            $search_term = $wpdb->esc_like($keyword);
            $search_term = '%' . $search_term . '%';

            $custom_where = " AND (
                ({$wpdb->posts}.post_title LIKE %s)
                OR ({$wpdb->posts}.post_content LIKE %s)
                OR ({$wpdb->posts}.post_excerpt LIKE %s)
                OR (umaten_pm.meta_value LIKE %s)
            )";

            $where = $wpdb->prepare($custom_where, $search_term, $search_term, $search_term, $search_term);
        }

        // スコープ範囲での WHERE条件追加
        $scope_region = $query->get('umaten_scope_region', '');
        $scope_area = $query->get('umaten_scope_area', '');
        $scope_genre = $query->get('umaten_scope_genre', '');

        if (!empty($scope_region)) {
            // タクソノミー検索はtax_queryで処理されるため、ここでは追加処理は不要
            // カスタムフィールドでの地域情報がある場合はここで追加可能
        }

        return $where;
    }

    /**
     * Custom JOIN clause for advanced search
     */
    public function custom_search_join($join, $query) {
        global $wpdb;

        if (!$query->get('umaten_custom_search')) {
            return $join;
        }

        // カスタムフィールド検索のためのJOIN
        $join .= " LEFT JOIN {$wpdb->postmeta} AS umaten_pm ON ({$wpdb->posts}.ID = umaten_pm.post_id)";

        return $join;
    }

    /**
     * Custom GROUP BY to avoid duplicates
     */
    public function custom_search_groupby($groupby, $query) {
        global $wpdb;

        if (!$query->get('umaten_custom_search')) {
            return $groupby;
        }

        // 重複を防ぐためにGROUP BY を追加
        $groupby = "{$wpdb->posts}.ID";

        return $groupby;
    }

    /**
     * Get search results count by scope
     */
    public function get_results_count($args = array()) {
        $defaults = array(
            'keyword' => '',
            'scope' => 'all',
            'region' => '',
            'area' => '',
            'genre' => '',
            'post_type' => 'post',
        );

        $args = wp_parse_args($args, $defaults);

        $query_args = array(
            'post_type' => $args['post_type'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        );

        if (!empty($args['keyword'])) {
            $query_args['s'] = $args['keyword'];
        }

        // スコープの適用
        if ($args['scope'] !== 'all') {
            if (strpos($args['scope'], 'category_') === 0) {
                $category_id = str_replace('category_', '', $args['scope']);
                $query_args['cat'] = $category_id;
            } elseif (strpos($args['scope'], 'tag_') === 0) {
                $tag_id = str_replace('tag_', '', $args['scope']);
                $query_args['tag_id'] = $tag_id;
            }
        }

        // タクソノミー検索
        if (!empty($args['region']) || !empty($args['area']) || !empty($args['genre'])) {
            $tax_query = array('relation' => 'AND');

            if (!empty($args['region'])) {
                $tax_query[] = array(
                    'taxonomy' => 'region',
                    'field' => 'slug',
                    'terms' => $args['region'],
                );
            }

            if (!empty($args['area'])) {
                $tax_query[] = array(
                    'taxonomy' => 'area',
                    'field' => 'slug',
                    'terms' => $args['area'],
                );
            }

            if (!empty($args['genre'])) {
                $tax_query[] = array(
                    'taxonomy' => 'genre',
                    'field' => 'slug',
                    'terms' => $args['genre'],
                );
            }

            if (count($tax_query) > 1) {
                $query_args['tax_query'] = $tax_query;
            }
        }

        $query = new WP_Query($query_args);
        return $query->post_count;
    }

    /**
     * レート制限チェック（DDoS攻撃対策）
     * IPアドレスごとに5分間に最大50リクエストまで許可
     * 通常のユーザーの連続検索を妨げず、悪意のある大量リクエストのみをブロック
     */
    private function check_rate_limit() {
        $ip = $this->get_client_ip();
        $transient_key = 'umaten_rate_limit_' . md5($ip);

        // 設定値（カスタマイズ可能）
        $max_requests = 50; // 最大リクエスト数
        $time_window = 5 * MINUTE_IN_SECONDS; // 5分間

        $request_count = get_transient($transient_key);

        // 初回リクエストまたはキャッシュ期限切れ
        if (false === $request_count) {
            set_transient($transient_key, 1, $time_window);
            return true;
        }

        // リクエスト制限を超えている場合
        if ($request_count >= $max_requests) {
            return false;
        }

        // リクエストカウントを増加
        set_transient($transient_key, $request_count + 1, $time_window);
        return true;
    }

    /**
     * クライアントIPアドレスを取得（プロキシ対応）
     */
    private function get_client_ip() {
        $ip = '';

        // プロキシやロードバランサー経由の場合を考慮
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // カンマ区切りの場合は最初のIPを使用
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // IPアドレスのバリデーション
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Send cache headers for KUSANAGI
     * v1.1.1: モバイルデバイスでのキャッシュ問題を防ぐため、
     * 検索クエリページでは適切なVaryヘッダーを送信
     */
    public function send_cache_headers() {
        // 検索パラメータを取得
        $umaten_search = isset($_GET['umaten_search']) ? $_GET['umaten_search'] : '';
        $region = get_query_var('umaten_region', '');
        $area = get_query_var('umaten_area', '');
        $genre = get_query_var('umaten_genre', '');

        // UmaTen検索クエリまたはタクソノミーURLの場合
        if (!empty($umaten_search) || !empty($region) || !empty($area) || !empty($genre)) {
            // KUSANAGIのキャッシュにUser-Agentでの区別を指示
            if (!headers_sent()) {
                header('Vary: User-Agent, Accept-Encoding');
                // 検索結果ページは短時間キャッシュ（5分）
                header('Cache-Control: public, max-age=300');
            }
        }
    }

    /**
     * 検索キーワードのサニタイズ（強化版）
     */
    private function sanitize_search_keyword($keyword) {
        // 基本的なサニタイズ
        $keyword = sanitize_text_field($keyword);

        // 長すぎるキーワードを制限（200文字まで）
        $keyword = mb_substr($keyword, 0, 200);

        // SQL特殊文字のエスケープ
        $keyword = esc_sql($keyword);

        // 危険なパターンを除去
        $dangerous_patterns = array(
            '/\b(union|select|insert|update|delete|drop|create|alter|exec|execute)\b/i',
            '/<script\b[^>]*>.*?<\/script>/is', // XSS対策
            '/javascript:/i',
            '/on\w+\s*=/i' // イベントハンドラ
        );

        foreach ($dangerous_patterns as $pattern) {
            $keyword = preg_replace($pattern, '', $keyword);
        }

        return trim($keyword);
    }

    /**
     * タクソノミーslugのサニタイズ
     */
    private function sanitize_taxonomy_slug($slug) {
        // 英数字、ハイフン、アンダースコアのみ許可
        $slug = sanitize_title($slug);

        // 長さを50文字に制限
        $slug = substr($slug, 0, 50);

        return $slug;
    }
}

// Initialize the search handler
Umaten_Restaurant_Search_Handler::get_instance();
