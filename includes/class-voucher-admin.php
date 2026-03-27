<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WC_Voucher_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'voucher',
            'plural'   => 'vouchers',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'            => '<input type="checkbox" />',
            'code'          => 'Код ваучера',
            'discount_type' => 'Тип скидки',
            'amount'        => 'Сумма',
            'date_created'  => 'Создан',
            'date_expires'  => 'Истекает',
            'status'        => 'Статус',
            'usage'         => 'Использований',
            'used_by'       => 'Использован кем',
            'used_date'     => 'Дата использования',
        ];
    }

    public function get_sortable_columns() {
        return [
            'code'         => ['title', false],
            'date_created' => ['date', true],
            'date_expires' => ['date_expires', false],
        ];
    }

    protected function get_bulk_actions() {
        return [
            'bulk_trash'      => 'В корзину',
            'bulk_deactivate' => 'Деактивировать',
            'bulk_activate'   => 'Активировать',
        ];
    }

    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();

        $args = [
            'post_type'      => 'shop_coupon',
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
            'post_status'    => ['publish', 'draft', 'pending'],
        ];

        // Sorting
        $allowed_orderby = ['title', 'date', 'date_expires'];
        $orderby = sanitize_text_field($_GET['orderby'] ?? 'date');
        $args['orderby'] = in_array($orderby, $allowed_orderby, true) ? $orderby : 'date';
        $args['order'] = strtoupper(sanitize_text_field($_GET['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        if ($orderby === 'date_expires') {
            $args['meta_key'] = 'date_expires';
            $args['orderby']  = 'meta_value_num';
        }

        // Search
        $search = sanitize_text_field($_GET['s'] ?? '');
        if ($search) {
            $args['s'] = $search;
        }

        // Filter by status
        $status_filter = sanitize_text_field($_GET['voucher_status'] ?? '');
        if ($status_filter === 'active') {
            $args['post_status'] = 'publish';
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'date_expires',
                    'value'   => current_time('timestamp'),
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => 'date_expires',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => 'date_expires',
                    'value'   => '',
                    'compare' => '=',
                ],
            ];
        } elseif ($status_filter === 'expired') {
            $args['post_status'] = 'publish';
            $args['meta_query'] = [
                [
                    'key'     => 'date_expires',
                    'value'   => current_time('timestamp'),
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => 'date_expires',
                    'value'   => '0',
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ],
            ];
        } elseif ($status_filter === 'used') {
            $args['meta_query'] = [
                [
                    'key'     => 'usage_count',
                    'value'   => 0,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ],
            ];
        } elseif ($status_filter === 'unused') {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'usage_count',
                    'value'   => 0,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => 'usage_count',
                    'compare' => 'NOT EXISTS',
                ],
            ];
        } elseif ($status_filter === 'draft') {
            $args['post_status'] = 'draft';
        }

        // Filter by batch
        $batch_filter = sanitize_text_field($_GET['voucher_batch'] ?? '');
        if ($batch_filter) {
            if (!isset($args['meta_query'])) {
                $args['meta_query'] = [];
            }
            $args['meta_query'][] = [
                'key'   => '_voucher_batch_id',
                'value' => $batch_filter,
            ];
        }

        $query = new WP_Query($args);

        $this->items = $query->posts;

        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => ceil($query->found_posts / $per_page),
        ]);

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="voucher_ids[]" value="%d" />', $item->ID);
    }

    public function column_code($item) {
        $code = $item->post_title;
        $edit_url = admin_url('post.php?post=' . $item->ID . '&action=edit');
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=wc-vouchers&action=trash_voucher&voucher_id=' . $item->ID),
            'trash_voucher_' . $item->ID
        );
        $actions = [
            'edit'  => sprintf('<a href="%s">Редактировать</a>', esc_url($edit_url)),
            'trash' => sprintf(
                '<a href="%s" class="voucher-trash-link">В корзину</a>',
                esc_url($delete_url)
            ),
        ];
        return sprintf(
            '<strong><a href="%s" class="voucher-code">%s</a></strong>%s',
            esc_url($edit_url),
            esc_html($code),
            $this->row_actions($actions)
        );
    }

    public function column_discount_type($item) {
        $type = get_post_meta($item->ID, 'discount_type', true);
        $types = [
            'percent'       => '<span class="voucher-type-badge voucher-type-percent">%</span> Процент',
            'fixed_cart'    => '<span class="voucher-type-badge voucher-type-fixed">&#8381;</span> Корзина',
            'fixed_product' => '<span class="voucher-type-badge voucher-type-fixed">&#8381;</span> Товар',
        ];
        return $types[$type] ?? esc_html($type);
    }

    public function column_amount($item) {
        $amount = get_post_meta($item->ID, 'coupon_amount', true);
        $type = get_post_meta($item->ID, 'discount_type', true);
        if ($type === 'percent') {
            return '<strong>' . esc_html($amount) . '%</strong>';
        }
        return '<strong>' . wc_price($amount) . '</strong>';
    }

    public function column_date_created($item) {
        $date = get_the_date('d.m.Y', $item->ID);
        $time = get_the_date('H:i', $item->ID);
        return sprintf('<span class="voucher-date">%s</span><br><small class="voucher-time">%s</small>', $date, $time);
    }

    public function column_date_expires($item) {
        $expires = get_post_meta($item->ID, 'date_expires', true);
        if (!$expires) {
            return '<span class="voucher-badge-infinity" title="Бессрочный">&infin;</span>';
        }

        $ts = intval($expires);
        $now = current_time('timestamp');
        $date = wp_date('d.m.Y', $ts);
        $time = wp_date('H:i', $ts);

        if ($ts <= $now) {
            return sprintf('<span class="voucher-date voucher-date-expired">%s</span><br><small class="voucher-time">%s</small>', $date, $time);
        }

        $diff = $ts - $now;
        $days = floor($diff / 86400);
        $hint = '';
        if ($days < 7) {
            $hint = sprintf(' <small class="voucher-expiry-soon">(%d дн.)</small>', $days);
        }

        return sprintf('<span class="voucher-date">%s</span>%s<br><small class="voucher-time">%s</small>', $date, $hint, $time);
    }

    public function column_status($item) {
        $usage_count = (int) get_post_meta($item->ID, 'usage_count', true);
        $usage_limit = (int) get_post_meta($item->ID, 'usage_limit', true);
        $expires = get_post_meta($item->ID, 'date_expires', true);
        $post_status = $item->post_status;

        if ($post_status === 'trash') {
            return '<span class="voucher-status voucher-status-trash"><span class="voucher-status-dot"></span>В корзине</span>';
        }
        if ($post_status === 'draft') {
            return '<span class="voucher-status voucher-status-draft"><span class="voucher-status-dot"></span>Черновик</span>';
        }
        if ($expires && intval($expires) > 0 && intval($expires) <= current_time('timestamp')) {
            return '<span class="voucher-status voucher-status-expired"><span class="voucher-status-dot"></span>Истёк</span>';
        }
        if ($usage_limit > 0 && $usage_count >= $usage_limit) {
            return '<span class="voucher-status voucher-status-exhausted"><span class="voucher-status-dot"></span>Исчерпан</span>';
        }
        if ($usage_count > 0) {
            return '<span class="voucher-status voucher-status-partial"><span class="voucher-status-dot"></span>Частично</span>';
        }
        return '<span class="voucher-status voucher-status-active"><span class="voucher-status-dot"></span>Активен</span>';
    }

    public function column_usage($item) {
        $count = (int) get_post_meta($item->ID, 'usage_count', true);
        $limit = (int) get_post_meta($item->ID, 'usage_limit', true);
        if ($limit > 0) {
            $pct = min(100, round($count / $limit * 100));
            return sprintf(
                '<div class="voucher-usage-bar-wrap"><div class="voucher-usage-bar" style="width:%d%%"></div></div><small>%d / %d</small>',
                $pct, $count, $limit
            );
        }
        return sprintf('<small>%d / &infin;</small>', $count);
    }

    public function column_used_by($item) {
        $used_by = get_post_meta($item->ID, '_used_by', false);
        if (empty($used_by)) return '<span class="voucher-muted">&mdash;</span>';

        $emails = [];
        foreach (array_slice($used_by, -3) as $user_id_or_email) {
            if (is_numeric($user_id_or_email)) {
                $user = get_userdata(intval($user_id_or_email));
                $emails[] = $user ? $user->user_email : '#' . $user_id_or_email;
            } else {
                $emails[] = sanitize_email($user_id_or_email);
            }
        }
        $result = esc_html(implode(', ', $emails));
        if (count($used_by) > 3) {
            $result .= sprintf(' <span class="voucher-muted">(+%d)</span>', count($used_by) - 3);
        }
        return $result;
    }

    public function column_used_date($item) {
        $date = get_post_meta($item->ID, '_voucher_last_used_date', true);
        if (!$date) return '<span class="voucher-muted">&mdash;</span>';
        $ts = strtotime($date);
        return sprintf(
            '<span class="voucher-date">%s</span><br><small class="voucher-time">%s</small>',
            wp_date('d.m.Y', $ts),
            wp_date('H:i', $ts)
        );
    }

    public function column_default($item, $column_name) {
        return '<span class="voucher-muted">&mdash;</span>';
    }

    protected function extra_tablenav($which) {
        if ($which !== 'top') return;
        $current = sanitize_text_field($_GET['voucher_status'] ?? '');
        ?>
        <div class="alignleft actions voucher-filters">
            <select name="voucher_status" class="voucher-filter-select">
                <option value="">Все статусы</option>
                <option value="active" <?php selected($current, 'active'); ?>>Активные</option>
                <option value="expired" <?php selected($current, 'expired'); ?>>Истёкшие</option>
                <option value="used" <?php selected($current, 'used'); ?>>Использованные</option>
                <option value="unused" <?php selected($current, 'unused'); ?>>Неиспользованные</option>
                <option value="draft" <?php selected($current, 'draft'); ?>>Черновики</option>
            </select>
            <?php submit_button('Фильтр', 'secondary', 'filter_action', false); ?>
        </div>
        <?php
    }

    public function no_items() {
        echo '<div class="voucher-empty-state">';
        echo '<div class="voucher-empty-icon">&#127915;</div>';
        echo '<p>Ваучеры не найдены</p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wc-vouchers&tab=generate')) . '" class="button button-primary">Создать ваучеры</a>';
        echo '</div>';
    }
}


class WC_Voucher_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'handle_actions']);
    }

    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            'Менеджер ваучеров',
            'Ваучеры',
            'manage_woocommerce',
            'wc-vouchers',
            [$this, 'render_page']
        );
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'wc-vouchers') === false) return;

        wp_enqueue_style(
            'wc-voucher-admin',
            WC_VOUCHER_URL . 'assets/css/admin.css',
            [],
            WC_VOUCHER_VERSION
        );
        wp_enqueue_script(
            'wc-voucher-admin',
            WC_VOUCHER_URL . 'assets/js/admin.js',
            ['jquery'],
            WC_VOUCHER_VERSION,
            true
        );
        wp_localize_script('wc-voucher-admin', 'wcVoucher', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wc_voucher_nonce'),
        ]);
    }

    public function handle_actions() {
        // Redirect POST search/filter to GET (form is POST for bulk actions compat)
        if (
            isset($_POST['page'])
            && $_POST['page'] === 'wc-vouchers'
            && (isset($_POST['filter_action']) || isset($_POST['s']) || isset($_POST['voucher_search']))
            && empty($_POST['voucher_ids'])
        ) {
            $redirect_args = ['page' => 'wc-vouchers', 'tab' => 'list'];
            foreach (['voucher_status', 'voucher_batch', 's', 'orderby', 'order'] as $p) {
                $val = sanitize_text_field($_POST[$p] ?? '');
                if ($val) $redirect_args[$p] = $val;
            }
            wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
            exit;
        }

        // Trash single voucher
        if (
            isset($_GET['page'], $_GET['action'], $_GET['voucher_id'])
            && $_GET['page'] === 'wc-vouchers'
            && $_GET['action'] === 'trash_voucher'
        ) {
            $voucher_id = intval($_GET['voucher_id']);
            check_admin_referer('trash_voucher_' . $voucher_id);

            if (current_user_can('manage_woocommerce')) {
                wp_trash_post($voucher_id);
            }

            wp_safe_redirect(admin_url('admin.php?page=wc-vouchers&msg=trashed'));
            exit;
        }

        // Bulk actions
        if (
            isset($_POST['_wpnonce'], $_POST['voucher_ids'])
            && !empty($_POST['voucher_ids'])
            && isset($_REQUEST['page'])
            && $_REQUEST['page'] === 'wc-vouchers'
        ) {
            $action = sanitize_text_field($_POST['action'] ?? '');
            if ($action === '-1') {
                $action = sanitize_text_field($_POST['action2'] ?? '');
            }

            if (!in_array($action, ['bulk_trash', 'bulk_deactivate', 'bulk_activate'], true)) {
                return;
            }

            check_admin_referer('bulk-vouchers');

            if (!current_user_can('manage_woocommerce')) {
                return;
            }

            $ids = array_map('intval', (array) $_POST['voucher_ids']);

            foreach ($ids as $id) {
                if ($action === 'bulk_trash') {
                    wp_trash_post($id);
                } elseif ($action === 'bulk_deactivate') {
                    wp_update_post(['ID' => $id, 'post_status' => 'draft']);
                } elseif ($action === 'bulk_activate') {
                    wp_update_post(['ID' => $id, 'post_status' => 'publish']);
                }
            }

            wp_safe_redirect(admin_url('admin.php?page=wc-vouchers&msg=bulk_done&count=' . count($ids)));
            exit;
        }
    }

    public function render_page() {
        $tab = sanitize_text_field($_GET['tab'] ?? 'list');
        ?>
        <div class="wrap wc-voucher-wrap">
            <div class="voucher-header">
                <div class="voucher-header-left">
                    <h1 class="wp-heading-inline">
                        <span class="voucher-header-icon">&#127915;</span>
                        Менеджер ваучеров
                    </h1>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=shop_coupon')); ?>" class="page-title-action">+ Добавить ваучер</a>
                </div>
            </div>
            <hr class="wp-header-end">

            <nav class="nav-tab-wrapper voucher-nav">
                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-vouchers&tab=list')); ?>"
                   class="nav-tab <?php echo $tab === 'list' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-list-view"></span> Все ваучеры
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-vouchers&tab=generate')); ?>"
                   class="nav-tab <?php echo $tab === 'generate' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-plus-alt"></span> Массовое создание
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-vouchers&tab=stats')); ?>"
                   class="nav-tab <?php echo $tab === 'stats' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-chart-bar"></span> Статистика
                </a>
            </nav>

            <div class="voucher-tab-content">
                <?php $this->render_messages(); ?>
                <?php
                switch ($tab) {
                    case 'generate':
                        $this->render_generate_tab();
                        break;
                    case 'stats':
                        $this->render_stats_tab();
                        break;
                    default:
                        $this->render_list_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function render_messages() {
        $msg = sanitize_text_field($_GET['msg'] ?? '');
        if (!$msg) return;

        $messages = [
            'trashed'   => 'Ваучер перемещён в корзину.',
            'bulk_done' => 'Действие выполнено.',
        ];

        if (isset($messages[$msg])) {
            $text = $messages[$msg];
            $count = intval($_GET['count'] ?? 0);
            if ($msg === 'bulk_done' && $count > 0) {
                $text = sprintf('Обработано ваучеров: %d', $count);
            }
            printf('<div class="notice notice-success is-dismissible voucher-notice"><p>%s</p></div>', esc_html($text));
        }
    }

    private function render_list_tab() {
        $table = new WC_Voucher_List_Table();
        $table->prepare_items();
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="wc-vouchers" />
            <input type="hidden" name="tab" value="list" />
            <?php
            // Preserve current filters as hidden fields for search (form is POST for bulk actions)
            $keep_params = ['voucher_status', 'voucher_batch', 's', 'orderby', 'order'];
            foreach ($keep_params as $param) {
                $val = sanitize_text_field($_REQUEST[$param] ?? '');
                if ($val) {
                    printf('<input type="hidden" name="%s" value="%s" />', esc_attr($param), esc_attr($val));
                }
            }
            $table->search_box('Найти ваучер', 'voucher_search');
            $table->display();
            ?>
        </form>
        <div class="voucher-export">
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=wc_voucher_export_csv'), 'wc_voucher_nonce', '_wpnonce')); ?>"
               class="button button-secondary">
                <span class="dashicons dashicons-download" style="vertical-align:text-bottom"></span> Экспорт в CSV
            </a>
        </div>
        <?php
    }

    private function render_generate_tab() {
        ?>
        <div class="voucher-generate-form">
            <div class="voucher-form-header">
                <h2>Массовое создание ваучеров</h2>
                <p class="voucher-form-desc">Создайте сразу несколько ваучеров с одинаковыми параметрами. Уникальные коды генерируются автоматически.</p>
            </div>

            <div class="voucher-form-grid">
                <div class="voucher-form-section">
                    <h3 class="voucher-section-title">Основные параметры</h3>

                    <div class="voucher-field">
                        <label for="voucher_count">Количество</label>
                        <div class="voucher-field-input">
                            <input type="number" id="voucher_count" value="10" min="1" max="1000" />
                            <span class="voucher-field-hint">от 1 до 1000</span>
                        </div>
                    </div>

                    <div class="voucher-field">
                        <label for="voucher_prefix">Префикс кода</label>
                        <div class="voucher-field-input">
                            <input type="text" id="voucher_prefix" value="VOUCHER-" />
                            <span class="voucher-field-hint">VOUCHER-, SALE-, PROMO-</span>
                        </div>
                    </div>

                    <div class="voucher-field">
                        <label for="voucher_code_length">Длина кода</label>
                        <div class="voucher-field-input">
                            <input type="number" id="voucher_code_length" value="8" min="4" max="20" />
                            <span class="voucher-field-hint">случайная часть без префикса</span>
                        </div>
                    </div>

                    <div class="voucher-field-row">
                        <div class="voucher-field">
                            <label for="voucher_discount_type">Тип скидки</label>
                            <select id="voucher_discount_type">
                                <option value="percent">Процент (%)</option>
                                <option value="fixed_cart">Фикс. на корзину</option>
                                <option value="fixed_product">Фикс. на товар</option>
                            </select>
                        </div>
                        <div class="voucher-field">
                            <label for="voucher_amount">Сумма</label>
                            <input type="number" id="voucher_amount" value="10" min="0" step="0.01" />
                        </div>
                    </div>
                </div>

                <div class="voucher-form-section">
                    <h3 class="voucher-section-title">Ограничения</h3>

                    <div class="voucher-field">
                        <label for="voucher_expiry">Дата истечения</label>
                        <div class="voucher-field-input">
                            <input type="datetime-local" id="voucher_expiry" />
                            <span class="voucher-field-hint">пусто = бессрочные</span>
                        </div>
                    </div>

                    <div class="voucher-field-row">
                        <div class="voucher-field">
                            <label for="voucher_usage_limit">Лимит использований</label>
                            <div class="voucher-field-input">
                                <input type="number" id="voucher_usage_limit" value="1" min="0" />
                                <span class="voucher-field-hint">0 = без лимита</span>
                            </div>
                        </div>
                        <div class="voucher-field">
                            <label for="voucher_min_amount">Мин. сумма заказа</label>
                            <div class="voucher-field-input">
                                <input type="number" id="voucher_min_amount" value="0" min="0" step="0.01" />
                                <span class="voucher-field-hint">0 = без ограничений</span>
                            </div>
                        </div>
                    </div>

                    <div class="voucher-field">
                        <label class="voucher-checkbox-label">
                            <input type="checkbox" id="voucher_individual" checked />
                            <span>Индивидуальное использование</span>
                        </label>
                    </div>

                    <div class="voucher-field">
                        <label class="voucher-checkbox-label">
                            <input type="checkbox" id="voucher_free_shipping" />
                            <span>Бесплатная доставка</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="voucher-generate-actions">
                <button type="button" id="btn-generate-vouchers" class="button button-primary button-hero">
                    <span class="dashicons dashicons-tickets-alt" style="vertical-align:text-bottom;margin-right:4px"></span>
                    Создать ваучеры
                </button>
                <div id="voucher-preview-code" class="voucher-preview-code"></div>
            </div>

            <div id="voucher-progress" style="display:none;">
                <div class="voucher-progress-bar">
                    <div class="voucher-progress-fill" style="width:0%">
                        <span class="voucher-progress-percent">0%</span>
                    </div>
                </div>
                <p class="voucher-progress-text">
                    Создано: <strong><span id="voucher-progress-count">0</span></strong> из <strong><span id="voucher-progress-total">0</span></strong>
                </p>
            </div>

            <div id="voucher-result" style="display:none;">
                <div class="voucher-result-success">
                    <div class="voucher-result-icon">&#10004;</div>
                    <div class="voucher-result-text">
                        <strong>Готово!</strong> Создано ваучеров: <span id="voucher-result-count">0</span>
                    </div>
                </div>
                <div class="voucher-result-actions">
                    <a href="#" id="btn-download-csv" class="button button-primary">
                        <span class="dashicons dashicons-download" style="vertical-align:text-bottom"></span> Скачать CSV
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wc-vouchers&tab=list')); ?>" class="button">
                        Перейти к списку
                    </a>
                    <button type="button" id="btn-copy-codes" class="button">
                        <span class="dashicons dashicons-clipboard" style="vertical-align:text-bottom"></span> Скопировать
                    </button>
                </div>
                <textarea id="voucher-result-codes" class="large-text code" rows="10" readonly></textarea>
            </div>
        </div>
        <?php
    }

    private function render_stats_tab() {
        global $wpdb;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_status IN ('publish','draft')");

        $used = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'usage_count'
            WHERE p.post_type = 'shop_coupon' AND p.post_status IN ('publish','draft')
            AND CAST(pm.meta_value AS UNSIGNED) > 0
        ");

        $now = current_time('timestamp');
        $expired = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'date_expires'
            WHERE p.post_type = 'shop_coupon' AND p.post_status IN ('publish','draft')
            AND CAST(pm.meta_value AS UNSIGNED) > 0 AND CAST(pm.meta_value AS UNSIGNED) <= %d
        ", $now));

        $unused = max(0, $total - $used);
        $active = max(0, $total - $expired);

        // Recent batches
        $batches = $wpdb->get_results("
            SELECT pm.meta_value as batch_id, COUNT(*) as cnt, MIN(p.post_date) as created
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_voucher_batch_id' AND p.post_type = 'shop_coupon'
            GROUP BY pm.meta_value
            ORDER BY created DESC
            LIMIT 5
        ");
        ?>
        <div class="voucher-stats">
            <div class="voucher-stats-grid">
                <div class="voucher-stat-card">
                    <div class="voucher-stat-icon">&#127915;</div>
                    <div class="voucher-stat-number"><?php echo $total; ?></div>
                    <div class="voucher-stat-label">Всего</div>
                </div>
                <div class="voucher-stat-card voucher-stat-active">
                    <div class="voucher-stat-icon">&#9989;</div>
                    <div class="voucher-stat-number"><?php echo $active; ?></div>
                    <div class="voucher-stat-label">Активных</div>
                </div>
                <div class="voucher-stat-card voucher-stat-used">
                    <div class="voucher-stat-icon">&#128178;</div>
                    <div class="voucher-stat-number"><?php echo $used; ?></div>
                    <div class="voucher-stat-label">Использовано</div>
                </div>
                <div class="voucher-stat-card voucher-stat-expired">
                    <div class="voucher-stat-icon">&#9200;</div>
                    <div class="voucher-stat-number"><?php echo $expired; ?></div>
                    <div class="voucher-stat-label">Истёкших</div>
                </div>
            </div>

            <?php if (!empty($batches)) : ?>
            <div class="voucher-batches">
                <h3>Последние партии</h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Партия</th>
                            <th>Кол-во</th>
                            <th>Создана</th>
                            <th>Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batches as $b) : ?>
                        <tr>
                            <td><code><?php echo esc_html(substr($b->batch_id, 0, 8)); ?>...</code></td>
                            <td><strong><?php echo intval($b->cnt); ?></strong></td>
                            <td><?php echo wp_date('d.m.Y H:i', strtotime($b->created)); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-vouchers&tab=list&voucher_batch=' . urlencode($b->batch_id))); ?>" class="button button-small">
                                    Показать
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
