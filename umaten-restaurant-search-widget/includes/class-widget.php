<?php
/**
 * UmaTen Restaurant Search Widget Class
 *
 * @package UmaTen_Restaurant_Search
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget class for restaurant search
 */
class Umaten_Restaurant_Search_Widget extends WP_Widget {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'umaten_restaurant_search',
            '飲食店検索ウィジェット（エンタープライズ）',
            array(
                'description' => '高度な検索機能を持つ飲食店検索ウィジェット。カテゴリ階層表示、タググループ化、複数条件検索に対応'
            )
        );
    }

    /**
     * Front-end display of widget
     */
    public function widget($args, $instance) {
        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        // 現在のカテゴリとタグを取得
        $current_context = $this->get_current_context();

        // ウィジェット設定を取得
        $enable_category_filter = isset($instance['enable_category_filter']) ? $instance['enable_category_filter'] : 'yes';
        $enable_tag_filter = isset($instance['enable_tag_filter']) ? $instance['enable_tag_filter'] : 'yes';
        $enable_keyword = isset($instance['enable_keyword']) ? $instance['enable_keyword'] : 'yes';
        $enable_all_tags = isset($instance['enable_all_tags']) ? $instance['enable_all_tags'] : 'no';
        $selected_categories = isset($instance['selected_categories']) ? $instance['selected_categories'] : array();
        $tag_groups = isset($instance['tag_groups']) ? $instance['tag_groups'] : array();

        // 検索フォームを表示
        $this->display_search_form($current_context, array(
            'enable_category_filter' => $enable_category_filter,
            'enable_tag_filter' => $enable_tag_filter,
            'enable_keyword' => $enable_keyword,
            'enable_all_tags' => $enable_all_tags,
            'selected_categories' => $selected_categories,
            'tag_groups' => $tag_groups
        ), $instance);

        echo $args['after_widget'];
    }

    /**
     * Get current page context (categories, tags, custom taxonomies)
     */
    private function get_current_context() {
        $context = array(
            'region' => '',
            'area' => '',
            'genre' => '',
            'categories' => array(),
            'tags' => array(),
            'post_type' => get_post_type(),
            'current_url' => $_SERVER['REQUEST_URI'] ?? ''
        );

        // カスタムクエリ変数から取得（既存のリライトルール対応）
        $context['region'] = get_query_var('umaten_region', '');
        $context['area'] = get_query_var('umaten_area', '');
        $context['genre'] = get_query_var('umaten_genre', '');

        // カテゴリページの場合
        if (is_category()) {
            $category = get_queried_object();
            $context['categories'][] = $category;
            $context['current_category_id'] = $category->term_id;
        }

        // タグページの場合
        if (is_tag()) {
            $tag = get_queried_object();
            $context['tags'][] = $tag;
        }

        // 単一投稿ページの場合
        if (is_single()) {
            $post_id = get_the_ID();
            $categories = get_the_category($post_id);
            $tags = get_the_tags($post_id);

            if ($categories) {
                $context['categories'] = $categories;
            }

            if ($tags) {
                $context['tags'] = $tags;
            }
        }

        return $context;
    }

    /**
     * Get categories grouped by parent
     */
    private function get_categories_grouped($selected_cat_ids = array()) {
        $all_categories = get_categories(array(
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));

        // 選択されたカテゴリのみを対象にする
        if (!empty($selected_cat_ids)) {
            $all_categories = array_filter($all_categories, function($cat) use ($selected_cat_ids) {
                return in_array($cat->term_id, $selected_cat_ids);
            });
        }

        $grouped = array();
        $parent_categories = array();
        $child_categories = array();

        // 親と子を分類
        foreach ($all_categories as $category) {
            if ($category->parent == 0) {
                $parent_categories[$category->term_id] = $category;
            } else {
                if (!isset($child_categories[$category->parent])) {
                    $child_categories[$category->parent] = array();
                }
                $child_categories[$category->parent][] = $category;
            }
        }

        // 親カテゴリごとにグループ化
        foreach ($parent_categories as $parent_id => $parent_cat) {
            $grouped[] = array(
                'parent' => $parent_cat,
                'children' => isset($child_categories[$parent_id]) ? $child_categories[$parent_id] : array()
            );
        }

        // 親のない子カテゴリも追加
        foreach ($child_categories as $parent_id => $children) {
            if (!isset($parent_categories[$parent_id])) {
                foreach ($children as $child) {
                    $grouped[] = array(
                        'parent' => null,
                        'children' => array($child)
                    );
                }
            }
        }

        return $grouped;
    }

    /**
     * Get tags grouped by custom groups
     */
    private function get_tags_grouped($tag_groups = array(), $enable_all_tags = false) {
        $all_tags = get_tags(array(
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => $enable_all_tags ? 0 : 100, // 一括有効化時は全タグを取得
            'hide_empty' => false // 投稿に紐付いていないタグも表示
        ));

        if (empty($all_tags) || is_wp_error($all_tags)) {
            return array();
        }

        $grouped_tags = array();

        // 一括有効化が有効な場合
        if ($enable_all_tags) {
            $grouped_tags['すべてのタグ'] = $all_tags;
            return $grouped_tags;
        }

        // タググループが設定されている場合
        if (!empty($tag_groups)) {
            foreach ($tag_groups as $group_name => $tag_ids) {
                if (!empty($tag_ids) && is_array($tag_ids)) {
                    $group_tags = array_filter($all_tags, function($tag) use ($tag_ids) {
                        return in_array($tag->term_id, $tag_ids);
                    });
                    if (!empty($group_tags)) {
                        $grouped_tags[$group_name] = $group_tags;
                    }
                }
            }

            // グループに属さないタグ
            $grouped_tag_ids = array();
            foreach ($tag_groups as $tag_ids) {
                if (is_array($tag_ids)) {
                    $grouped_tag_ids = array_merge($grouped_tag_ids, $tag_ids);
                }
            }

            $ungrouped_tags = array_filter($all_tags, function($tag) use ($grouped_tag_ids) {
                return !in_array($tag->term_id, $grouped_tag_ids);
            });

            if (!empty($ungrouped_tags)) {
                $grouped_tags['その他'] = $ungrouped_tags;
            }
        } else {
            // デフォルトグループ
            $grouped_tags['人気のタグ'] = array_slice($all_tags, 0, 50);
        }

        return $grouped_tags;
    }

    /**
     * Display search form
     */
    private function display_search_form($context, $options, $instance) {
        $selected_categories = isset($options['selected_categories']) ? $options['selected_categories'] : array();
        $tag_groups = isset($options['tag_groups']) ? $options['tag_groups'] : array();

        // 現在のURL構造を保持するためのベースURL
        $base_url = home_url('/');
        if (!empty($context['region'])) {
            $base_url .= $context['region'] . '/';
            if (!empty($context['area'])) {
                $base_url .= $context['area'] . '/';
                if (!empty($context['genre'])) {
                    $base_url .= $context['genre'] . '/';
                }
            }
        }

        ?>
        <div class="umaten-search-widget umaten-enterprise">
            <!-- モバイル用浮動検索ボタン -->
            <button type="button" class="umaten-mobile-fab" aria-label="検索を開く">
                <span class="umaten-fab-icon">🔍</span>
            </button>

            <!-- 検索フォームコンテナ（モバイルではモーダル、デスクトップでは通常表示） -->
            <div class="umaten-search-container">
                <!-- モバイル用モーダルヘッダー -->
                <div class="umaten-modal-header">
                    <h3 class="umaten-modal-title">絞り込み検索</h3>
                    <button type="button" class="umaten-modal-close" aria-label="閉じる">
                        <span>×</span>
                    </button>
                </div>

                <form method="get" action="<?php echo esc_url($base_url); ?>" class="umaten-search-form">

                <!-- 現在のコンテキスト表示 -->
                <?php if (!empty($context['region']) || !empty($context['area']) || !empty($context['genre'])): ?>
                <div class="umaten-current-context">
                    <div class="umaten-context-label">現在の絞り込み:</div>
                    <div class="umaten-context-value">
                        <?php
                        $context_parts = array();
                        if (!empty($context['region'])) $context_parts[] = $context['region'];
                        if (!empty($context['area'])) $context_parts[] = $context['area'];
                        if (!empty($context['genre'])) $context_parts[] = $context['genre'];
                        echo esc_html(implode(' > ', $context_parts));
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- カテゴリフィルター -->
                <?php if ($options['enable_category_filter'] === 'yes'): ?>
                <div class="umaten-search-field">
                    <label for="umaten-category-filter">カテゴリで絞り込み:</label>
                    <select name="umaten_category" id="umaten-category-filter" class="umaten-select">
                        <option value="">-- カテゴリを選択 --</option>

                        <?php
                        $categories_grouped = $this->get_categories_grouped($selected_categories);
                        foreach ($categories_grouped as $group):
                            $parent = $group['parent'];
                            $children = $group['children'];

                            if ($parent):
                        ?>
                            <optgroup label="<?php echo esc_attr($parent->name); ?>">
                                <option value="<?php echo esc_attr($parent->term_id); ?>">
                                    <?php echo esc_html($parent->name); ?> すべて (<?php echo $parent->count; ?>)
                                </option>
                                <?php foreach ($children as $child): ?>
                                <option value="<?php echo esc_attr($child->term_id); ?>">
                                    &nbsp;&nbsp;└ <?php echo esc_html($child->name); ?> (<?php echo $child->count; ?>)
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php
                            else:
                                foreach ($children as $child):
                        ?>
                            <option value="<?php echo esc_attr($child->term_id); ?>">
                                <?php echo esc_html($child->name); ?> (<?php echo $child->count; ?>)
                            </option>
                        <?php
                                endforeach;
                            endif;
                        endforeach;
                        ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- タグフィルター -->
                <?php if ($options['enable_tag_filter'] === 'yes'): ?>
                <div class="umaten-search-field">
                    <label for="umaten-tag-filter">属性で絞り込み:</label>
                    <select name="umaten_tag" id="umaten-tag-filter" class="umaten-select">
                        <option value="">-- 属性を選択 --</option>

                        <?php
                        $enable_all = isset($options['enable_all_tags']) && $options['enable_all_tags'] === 'yes';
                        $tags_grouped = $this->get_tags_grouped($tag_groups, $enable_all);
                        foreach ($tags_grouped as $group_name => $tags):
                            if (!empty($tags)):
                        ?>
                            <optgroup label="<?php echo esc_attr($group_name); ?>">
                                <?php foreach ($tags as $tag): ?>
                                <option value="<?php echo esc_attr($tag->term_id); ?>">
                                    <?php echo esc_html($tag->name); ?> (<?php echo $tag->count; ?>)
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- フリーワード検索 -->
                <?php if ($options['enable_keyword'] === 'yes'): ?>
                <div class="umaten-search-field">
                    <label for="umaten-keyword">キーワード:</label>
                    <input type="text"
                           name="umaten_keyword"
                           id="umaten-keyword"
                           class="umaten-search-input"
                           placeholder="店名、料理名などで検索"
                           value="<?php echo esc_attr(get_query_var('umaten_keyword', '')); ?>">
                </div>
                <?php endif; ?>

                <!-- Hidden fields -->
                <input type="hidden" name="umaten_search" value="1">
                <?php wp_nonce_field('umaten_search_action', 'umaten_search_nonce'); ?>
                <?php if (!empty($context['region'])): ?>
                <input type="hidden" name="umaten_region" value="<?php echo esc_attr($context['region']); ?>">
                <?php endif; ?>
                <?php if (!empty($context['area'])): ?>
                <input type="hidden" name="umaten_area" value="<?php echo esc_attr($context['area']); ?>">
                <?php endif; ?>
                <?php if (!empty($context['genre'])): ?>
                <input type="hidden" name="umaten_genre" value="<?php echo esc_attr($context['genre']); ?>">
                <?php endif; ?>

                <!-- 検索ボタン -->
                <div class="umaten-search-submit">
                    <button type="submit" class="umaten-search-button">
                        <span class="umaten-search-icon">🔍</span>
                        検索する
                    </button>
                    <button type="button" class="umaten-reset-button" onclick="this.form.reset(); this.form.elements['umaten_keyword'].value=''; this.form.elements['umaten_category'].selectedIndex=0; this.form.elements['umaten_tag'].selectedIndex=0;">
                        リセット
                    </button>
                </div>
            </form>
            </div><!-- .umaten-search-container -->

            <!-- モバイル用オーバーレイ -->
            <div class="umaten-modal-overlay"></div>
        </div>
        <?php
    }

    /**
     * Back-end widget form
     */
    public function form($instance) {
        $title = isset($instance['title']) ? $instance['title'] : '飲食店を検索';
        $enable_category_filter = isset($instance['enable_category_filter']) ? $instance['enable_category_filter'] : 'yes';
        $enable_tag_filter = isset($instance['enable_tag_filter']) ? $instance['enable_tag_filter'] : 'yes';
        $enable_keyword = isset($instance['enable_keyword']) ? $instance['enable_keyword'] : 'yes';
        $enable_all_tags = isset($instance['enable_all_tags']) ? $instance['enable_all_tags'] : 'no';
        $selected_categories = isset($instance['selected_categories']) ? $instance['selected_categories'] : array();
        $tag_groups = isset($instance['tag_groups']) ? $instance['tag_groups'] : array();

        // 全カテゴリを取得
        $all_categories = get_categories(array(
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));

        // 全タグを取得
        $all_tags = get_tags(array(
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 100,
            'hide_empty' => false
        ));

        // 既存のタググループ名
        $existing_groups = array_keys($tag_groups);
        ?>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                タイトル:
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('enable_category_filter')); ?>">
                カテゴリフィルターを有効化:
            </label>
            <select class="widefat"
                    id="<?php echo esc_attr($this->get_field_id('enable_category_filter')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('enable_category_filter')); ?>">
                <option value="yes" <?php selected($enable_category_filter, 'yes'); ?>>有効</option>
                <option value="no" <?php selected($enable_category_filter, 'no'); ?>>無効</option>
            </select>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('enable_tag_filter')); ?>">
                タグフィルターを有効化:
            </label>
            <select class="widefat"
                    id="<?php echo esc_attr($this->get_field_id('enable_tag_filter')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('enable_tag_filter')); ?>">
                <option value="yes" <?php selected($enable_tag_filter, 'yes'); ?>>有効</option>
                <option value="no" <?php selected($enable_tag_filter, 'no'); ?>>無効</option>
            </select>
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('enable_keyword')); ?>">
                フリーワード検索を有効化:
            </label>
            <select class="widefat"
                    id="<?php echo esc_attr($this->get_field_id('enable_keyword')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('enable_keyword')); ?>">
                <option value="yes" <?php selected($enable_keyword, 'yes'); ?>>有効</option>
                <option value="no" <?php selected($enable_keyword, 'no'); ?>>無効</option>
            </select>
        </p>

        <hr>

        <p><strong>カテゴリ設定</strong></p>
        <p>
            <label>検索に表示するカテゴリを選択:</label>
            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9; margin-top: 8px;">
                <?php if (!empty($all_categories)): ?>
                    <?php
                    // 親カテゴリごとにグループ化して表示
                    $parent_cats = array_filter($all_categories, function($cat) { return $cat->parent == 0; });
                    foreach ($parent_cats as $parent):
                    ?>
                        <div style="margin-bottom: 10px; padding: 8px; background: #fff; border-left: 3px solid #2271b1;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px;">
                                <input type="checkbox"
                                       name="<?php echo esc_attr($this->get_field_name('selected_categories')); ?>[]"
                                       value="<?php echo esc_attr($parent->term_id); ?>"
                                       <?php checked(in_array($parent->term_id, $selected_categories)); ?>>
                                <?php echo esc_html($parent->name); ?>
                                <small>(<?php echo $parent->count; ?>)</small>
                            </label>
                            <?php
                            // 子カテゴリを表示
                            $children = array_filter($all_categories, function($cat) use ($parent) {
                                return $cat->parent == $parent->term_id;
                            });
                            if (!empty($children)):
                                foreach ($children as $child):
                            ?>
                            <label style="display: block; margin-left: 20px; margin-bottom: 3px;">
                                <input type="checkbox"
                                       name="<?php echo esc_attr($this->get_field_name('selected_categories')); ?>[]"
                                       value="<?php echo esc_attr($child->term_id); ?>"
                                       <?php checked(in_array($child->term_id, $selected_categories)); ?>>
                                └ <?php echo esc_html($child->name); ?>
                                <small>(<?php echo $child->count; ?>)</small>
                            </label>
                            <?php
                                endforeach;
                            endif;
                            ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>カテゴリがまだ登録されていません。</p>
                <?php endif; ?>
            </div>
            <small>チェックしたカテゴリが検索フォームに表示されます（親子階層で表示）</small>
        </p>

        <hr>

        <p><strong>タググループ設定</strong></p>
        <p><small>タグを属性別にグループ化できます（例: ラーメン、寿司など）</small></p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('enable_all_tags')); ?>">
                <input type="checkbox"
                       id="<?php echo esc_attr($this->get_field_id('enable_all_tags')); ?>"
                       name="<?php echo esc_attr($this->get_field_name('enable_all_tags')); ?>"
                       value="yes"
                       <?php checked($enable_all_tags, 'yes'); ?>>
                <strong>すべてのタグを一括で表示する</strong>
            </label>
            <br><small>チェックすると、個別のタググループ設定を無視してすべてのタグを表示します</small>
        </p>

        <?php
        // デフォルトグループ
        $default_groups = array('料理ジャンル', '特徴・雰囲気', '価格帯');
        foreach ($default_groups as $group_name):
            $group_tags = isset($tag_groups[$group_name]) ? $tag_groups[$group_name] : array();
        ?>
        <p>
            <label><strong><?php echo esc_html($group_name); ?>:</strong></label>
            <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 8px; background: #f9f9f9; margin-top: 5px;">
                <?php if (!empty($all_tags)): ?>
                    <?php foreach ($all_tags as $tag): ?>
                    <label style="display: inline-block; margin-right: 10px; margin-bottom: 5px;">
                        <input type="checkbox"
                               name="<?php echo esc_attr($this->get_field_name('tag_groups')); ?>[<?php echo esc_attr($group_name); ?>][]"
                               value="<?php echo esc_attr($tag->term_id); ?>"
                               <?php checked(in_array($tag->term_id, $group_tags)); ?>>
                        <?php echo esc_html($tag->name); ?>
                    </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </p>
        <?php endforeach; ?>

        <?php
    }

    /**
     * Sanitize widget form values as they are saved
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['enable_category_filter'] = (!empty($new_instance['enable_category_filter'])) ? sanitize_text_field($new_instance['enable_category_filter']) : 'yes';
        $instance['enable_tag_filter'] = (!empty($new_instance['enable_tag_filter'])) ? sanitize_text_field($new_instance['enable_tag_filter']) : 'yes';
        $instance['enable_keyword'] = (!empty($new_instance['enable_keyword'])) ? sanitize_text_field($new_instance['enable_keyword']) : 'yes';
        $instance['enable_all_tags'] = (!empty($new_instance['enable_all_tags'])) ? sanitize_text_field($new_instance['enable_all_tags']) : 'no';

        // カテゴリの配列を保存
        if (!empty($new_instance['selected_categories']) && is_array($new_instance['selected_categories'])) {
            $instance['selected_categories'] = array_map('intval', $new_instance['selected_categories']);
        } else {
            $instance['selected_categories'] = array();
        }

        // タググループを保存
        if (!empty($new_instance['tag_groups']) && is_array($new_instance['tag_groups'])) {
            $instance['tag_groups'] = array();
            foreach ($new_instance['tag_groups'] as $group_name => $tag_ids) {
                if (is_array($tag_ids)) {
                    $instance['tag_groups'][sanitize_text_field($group_name)] = array_map('intval', $tag_ids);
                }
            }
        } else {
            $instance['tag_groups'] = array();
        }

        return $instance;
    }
}
