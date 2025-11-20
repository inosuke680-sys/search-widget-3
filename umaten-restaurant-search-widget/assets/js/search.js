/**
 * UmaTen Restaurant Search Widget JavaScript
 *
 * @package UmaTen_Restaurant_Search
 */

(function($) {
    'use strict';

    /**
     * Search Widget Handler
     */
    var UmatenSearchWidget = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initAutocomplete();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // フォーム送信時の処理
            $('.umaten-search-form').on('submit', function(e) {
                self.handleFormSubmit($(this), e);
            });

            // 検索範囲変更時の処理
            $('.umaten-search-scope').on('change', function() {
                self.handleScopeChange($(this));
            });

            // キーワード入力時の処理（リアルタイム検索件数表示など）
            $('.umaten-search-input').on('input', function() {
                self.handleKeywordInput($(this));
            });
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function($form, event) {
            var keyword = $form.find('.umaten-search-input').val().trim();
            var scope = $form.find('.umaten-search-scope').val();

            // バリデーション
            if (!keyword && scope === 'all') {
                event.preventDefault();
                alert('検索キーワードを入力するか、検索範囲を指定してください。');
                return false;
            }

            // ローディング状態を追加
            $form.addClass('is-loading');
            $form.find('.umaten-search-button').prop('disabled', true);

            // フォームを送信（デフォルトの動作を継続）
            return true;
        },

        /**
         * Handle scope change
         */
        handleScopeChange: function($select) {
            var selectedScope = $select.val();
            var $form = $select.closest('.umaten-search-form');

            // スコープに応じた説明テキストを表示（オプション）
            this.updateScopeDescription($form, selectedScope);

            // 検索件数をプレビュー表示（オプション）
            if (typeof umatenSearch !== 'undefined' && umatenSearch.ajaxurl) {
                this.previewResultsCount($form);
            }
        },

        /**
         * Update scope description
         */
        updateScopeDescription: function($form, scope) {
            var $description = $form.find('.umaten-scope-description');

            if ($description.length === 0) {
                $description = $('<div class="umaten-scope-description" style="font-size: 12px; color: #666; margin-top: 5px;"></div>');
                $form.find('.umaten-search-scope').parent().append($description);
            }

            var descriptions = {
                'all': 'すべての飲食店から検索します',
                'region': '現在の都道府県内から検索します',
                'area': '現在のエリア内から検索します',
                'genre': '現在のジャンル内から検索します'
            };

            if (scope.indexOf('category_') === 0) {
                $description.text('現在のカテゴリ内から検索します');
            } else if (scope.indexOf('tag_') === 0) {
                $description.text('指定されたタグの飲食店から検索します');
            } else {
                $description.text(descriptions[scope] || '');
            }
        },

        /**
         * Handle keyword input
         */
        handleKeywordInput: function($input) {
            var keyword = $input.val().trim();
            var $form = $input.closest('.umaten-search-form');

            // デバウンス処理
            clearTimeout(this.keywordInputTimer);

            if (keyword.length >= 2) {
                this.keywordInputTimer = setTimeout(function() {
                    // オートコンプリートやサジェスト機能をここに実装可能
                    UmatenSearchWidget.showSuggestions($input, keyword);
                }, 300);
            } else {
                this.hideSuggestions($input);
            }
        },

        /**
         * Show keyword suggestions (autocomplete)
         */
        showSuggestions: function($input, keyword) {
            if (typeof umatenSearch === 'undefined' || !umatenSearch.ajaxurl) {
                return;
            }

            var $form = $input.closest('.umaten-search-form');
            var scope = $form.find('.umaten-search-scope').val();

            // AJAX リクエスト
            $.ajax({
                url: umatenSearch.ajaxurl,
                type: 'GET',
                data: {
                    action: 'umaten_search_suggestions',
                    keyword: keyword,
                    scope: scope,
                    nonce: umatenSearch.nonce
                },
                success: function(response) {
                    if (response.success && response.data.suggestions) {
                        UmatenSearchWidget.displaySuggestions($input, response.data.suggestions);
                    }
                }
            });
        },

        /**
         * Display suggestions dropdown
         */
        displaySuggestions: function($input, suggestions) {
            var $suggestions = $input.next('.umaten-suggestions');

            if ($suggestions.length === 0) {
                $suggestions = $('<div class="umaten-suggestions" style="position: absolute; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 1000; width: 100%; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"></div>');
                $input.parent().css('position', 'relative');
                $input.after($suggestions);
            }

            $suggestions.empty();

            if (suggestions.length === 0) {
                $suggestions.hide();
                return;
            }

            suggestions.forEach(function(suggestion) {
                var $item = $('<div class="suggestion-item" style="padding: 10px; cursor: pointer; border-bottom: 1px solid #f0f0f0;"></div>');
                $item.text(suggestion.text);
                $item.on('click', function() {
                    $input.val(suggestion.text);
                    $suggestions.hide();
                });
                $item.on('mouseenter', function() {
                    $(this).css('background-color', '#f5f5f5');
                });
                $item.on('mouseleave', function() {
                    $(this).css('background-color', 'white');
                });
                $suggestions.append($item);
            });

            $suggestions.show();
        },

        /**
         * Hide suggestions
         */
        hideSuggestions: function($input) {
            var $suggestions = $input.next('.umaten-suggestions');
            if ($suggestions.length > 0) {
                $suggestions.hide();
            }
        },

        /**
         * Preview results count
         */
        previewResultsCount: function($form) {
            var keyword = $form.find('.umaten-search-input').val().trim();
            var scope = $form.find('.umaten-search-scope').val();

            if (!keyword && scope === 'all') {
                return;
            }

            $.ajax({
                url: umatenSearch.ajaxurl,
                type: 'GET',
                data: {
                    action: 'umaten_preview_count',
                    keyword: keyword,
                    scope: scope,
                    nonce: umatenSearch.nonce
                },
                success: function(response) {
                    if (response.success && typeof response.data.count !== 'undefined') {
                        UmatenSearchWidget.displayResultsCount($form, response.data.count);
                    }
                }
            });
        },

        /**
         * Display results count
         */
        displayResultsCount: function($form, count) {
            var $countDisplay = $form.find('.umaten-results-count');

            if ($countDisplay.length === 0) {
                $countDisplay = $('<div class="umaten-results-count" style="font-size: 12px; color: #666; margin-top: 5px; text-align: right;"></div>');
                $form.find('.umaten-search-submit').before($countDisplay);
            }

            if (count > 0) {
                $countDisplay.html('<strong>' + count + '</strong> 件の飲食店が見つかります');
            } else {
                $countDisplay.html('該当する飲食店が見つかりません');
            }
        },

        /**
         * Initialize autocomplete
         */
        initAutocomplete: function() {
            // 外部クリックでサジェストを閉じる
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.umaten-search-input').length) {
                    $('.umaten-suggestions').hide();
                }
            });

            // Escapeキーでサジェストを閉じる
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.umaten-suggestions').hide();
                }
            });
        }
    };

    /**
     * Mobile Search Modal Handler
     */
    var UmatenMobileModal = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind mobile modal events
         */
        bindEvents: function() {
            var self = this;

            // FABボタンクリックでモーダルを開く
            $('.umaten-mobile-fab').on('click', function() {
                self.openModal();
            });

            // 閉じるボタンクリックでモーダルを閉じる
            $('.umaten-modal-close').on('click', function() {
                self.closeModal();
            });

            // オーバーレイクリックでモーダルを閉じる
            $('.umaten-modal-overlay').on('click', function() {
                self.closeModal();
            });

            // Escapeキーでモーダルを閉じる
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeModal();
                }
            });

            // フォーム送信時にモーダルを閉じる
            $('.umaten-search-widget .umaten-search-form').on('submit', function() {
                // 検索実行後もモーダルは開いたままにする場合はコメントアウト
                // self.closeModal();
            });
        },

        /**
         * Open modal
         */
        openModal: function() {
            $('.umaten-search-container').addClass('active');
            $('.umaten-modal-overlay').addClass('active');

            // モバイルでのスクロールを無効化
            $('body').css('overflow', 'hidden');

            // 最初の入力フィールドにフォーカス
            setTimeout(function() {
                $('.umaten-search-container .umaten-select:first, .umaten-search-container .umaten-search-input:first').focus();
            }, 300);
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.umaten-search-container').removeClass('active');
            $('.umaten-modal-overlay').removeClass('active');

            // スクロールを再有効化
            $('body').css('overflow', '');
        }
    };

    /**
     * Document ready
     */
    $(document).ready(function() {
        UmatenSearchWidget.init();
        UmatenMobileModal.init();
    });

})(jQuery);
