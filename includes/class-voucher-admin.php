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
            'code'          => WC_Voucher_Settings::get('name_code_label'),
            'discount_type' => __('Discount type', 'wc-voucher-manager'),
            'amount'        => __('Amount', 'wc-voucher-manager'),
            'date_created'  => __('Created', 'wc-voucher-manager'),
            'date_expires'  => __('Expires', 'wc-voucher-manager'),
            'status'        => __('Status', 'wc-voucher-manager'),
            'usage'         => __('Usage', 'wc-voucher-manager'),
            'used_by'       => __('Used by', 'wc-voucher-manager'),
            'used_date'     => __('Used date', 'wc-voucher-manager'),
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
            'bulk_trash'      => __('Move to trash', 'wc-voucher-manager'),
            'bulk_deactivate' => __('Deactivate', 'wc-voucher-manager'),
            'bulk_activate'   => __('Activate', 'wc-voucher-manager'),
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

        $allowed_orderby = ['title', 'date', 'date_expires'];
        $orderby = sanitize_text_field($_GET['orderby'] ?? 'date');
        $args['orderby'] = in_array($orderby, $allowed_orderby, true) ? $orderby : 'date';
        $args['order'] = strtoupper(sanitize_text_field($_GET['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        if ($orderby === 'date_expires') {
            $args['meta_key'] = 'date_expires';
            $args['orderby']  = 'meta_value_num';
        }

        $search = sanitize_text_field($_GET['s'] ?? '');
        if ($search) {
            $args['s'] = $search;
        }

        $status_filter = sanitize_text_field($_GET['voucher_status'] ?? '');
        if ($status_filter === 'active') {
            $args['post_status'] = 'publish';
            $args['meta_query'] = [
                'relation' => 'OR',
                ['key' => 'date_expires', 'value' => current_time('timestamp'), 'compare' => '>', 'type' => 'NUMERIC'],
                ['key' => 'date_expires', 'compare' => 'NOT EXISTS'],
                ['key' => 'date_expires', 'value' => '', 'compare' => '='],
            ];
        } elseif ($status_filter === 'expired') {
            $args['post_status'] = 'publish';
            $args['meta_query'] = [
                ['key' => 'date_expires', 'value' => current_time('timestamp'), 'compare' => '<=', 'type' => 'NUMERIC'],
                ['key' => 'date_expires', 'value' => '0', 'compare' => '>', 'type' => 'NUMERIC'],
            ];
        } elseif ($status_filter === 'used') {
            $args['meta_query'] = [['key' => 'usage_count', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC']];
        } elseif ($status_filter === 'unused') {
            $args['meta_query'] = [
                'relation' => 'OR',
                ['key' => 'usage_count', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'],
                ['key' => 'usage_count', 'compare' => 'NOT EXISTS'],
            ];
        } elseif ($status_filter === 'draft') {
            $args['post_status'] = 'draft';
        }

        $batch_filter = sanitize_text_field($_GET['voucher_batch'] ?? '');
        if ($batch_filter) {
            if (!isset($args['meta_query'])) $args['meta_query'] = [];
            $args['meta_query'][] = ['key' => '_voucher_batch_id', 'value' => $batch_filter];
        }

        $query = new WP_Query($args);
        $this->items = $query->posts;
        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => ceil($query->found_posts / $per_page),
        ]);
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="voucher_ids[]" value="%d" />', $item->ID);
    }

    public function column_code($item) {
        $edit_url = admin_url('post.php?post=' . $item->ID . '&action=edit');
        $trash_url = wp_nonce_url(admin_url('admin.php?page=wc-vouchers&action=trash_voucher&voucher_id=' . $item->ID), 'trash_voucher_' . $item->ID);
        $actions = [
            'edit'  => sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html__('Edit', 'wc-voucher-manager')),
            'trash' => sprintf('<a href="%s" class="voucher-trash-link">%s</a>', esc_url($trash_url), esc_html__('Trash', 'wc-voucher-manager')),
        ];
        return sprintf('<strong><a href="%s" class="voucher-code">%s</a></strong>%s', esc_url($edit_url), esc_html($item->post_title), $this->row_actions($actions));
    }

    public function column_discount_type($item) {
        $type = get_post_meta($item->ID, 'discount_type', true);
        $types = [
            'percent'       => '<span class="voucher-type-badge voucher-type-percent">%</span> ' . __('Percent', 'wc-voucher-manager'),
            'fixed_cart'    => '<span class="voucher-type-badge voucher-type-fixed">&#8381;</span> ' . __('Cart', 'wc-voucher-manager'),
            'fixed_product' => '<span class="voucher-type-badge voucher-type-fixed">&#8381;</span> ' . __('Product', 'wc-voucher-manager'),
        ];
        return $types[$type] ?? esc_html($type);
    }

    public function column_amount($item) {
        $amount = get_post_meta($item->ID, 'coupon_amount', true);
        $type = get_post_meta($item->ID, 'discount_type', true);
        return '<strong>' . ($type === 'percent' ? esc_html($amount) . '%' : wc_price($amount)) . '</strong>';
    }

    public function column_date_created($item) {
        return sprintf('<span class="voucher-date">%s</span><br><small class="voucher-time">%s</small>', get_the_date('d.m.Y', $item->ID), get_the_date('H:i', $item->ID));
    }

    public function column_date_expires($item) {
        $expires = get_post_meta($item->ID, 'date_expires', true);
        if (!$expires) return '<span class="voucher-badge-infinity" title="' . esc_attr__('No expiry', 'wc-voucher-manager') . '">&infin;</span>';

        $ts = intval($expires);
        $now = current_time('timestamp');
        $date = wp_date('d.m.Y', $ts);
        $time = wp_date('H:i', $ts);

        if ($ts <= $now) {
            return sprintf('<span class="voucher-date voucher-date-expired">%s</span><br><small class="voucher-time">%s</small>', $date, $time);
        }
        $days = floor(($ts - $now) / 86400);
        $hint = $days < 7 ? sprintf(' <small class="voucher-expiry-soon">(%d %s)</small>', $days, __('days', 'wc-voucher-manager')) : '';
        return sprintf('<span class="voucher-date">%s</span>%s<br><small class="voucher-time">%s</small>', $date, $hint, $time);
    }

    public function column_status($item) {
        $usage_count = (int) get_post_meta($item->ID, 'usage_count', true);
        $usage_limit = (int) get_post_meta($item->ID, 'usage_limit', true);
        $expires = get_post_meta($item->ID, 'date_expires', true);

        if ($item->post_status === 'trash') return '<span class="voucher-status voucher-status-trash"><span class="voucher-status-dot"></span>' . __('Trashed', 'wc-voucher-manager') . '</span>';
        if ($item->post_status === 'draft') return '<span class="voucher-status voucher-status-draft"><span class="voucher-status-dot"></span>' . __('Draft', 'wc-voucher-manager') . '</span>';
        if ($expires && intval($expires) > 0 && intval($expires) <= current_time('timestamp')) return '<span class="voucher-status voucher-status-expired"><span class="voucher-status-dot"></span>' . __('Expired', 'wc-voucher-manager') . '</span>';
        if ($usage_limit > 0 && $usage_count >= $usage_limit) return '<span class="voucher-status voucher-status-exhausted"><span class="voucher-status-dot"></span>' . __('Exhausted', 'wc-voucher-manager') . '</span>';
        if ($usage_count > 0) return '<span class="voucher-status voucher-status-partial"><span class="voucher-status-dot"></span>' . __('Used', 'wc-voucher-manager') . '</span>';
        return '<span class="voucher-status voucher-status-active"><span class="voucher-status-dot"></span>' . __('Active', 'wc-voucher-manager') . '</span>';
    }

    public function column_usage($item) {
        $count = (int) get_post_meta($item->ID, 'usage_count', true);
        $limit = (int) get_post_meta($item->ID, 'usage_limit', true);
        if ($limit > 0) {
            $pct = min(100, round($count / $limit * 100));
            return sprintf('<div class="voucher-usage-bar-wrap"><div class="voucher-usage-bar" style="width:%d%%"></div></div><small>%d / %d</small>', $pct, $count, $limit);
        }
        return sprintf('<small>%d / &infin;</small>', $count);
    }

    public function column_used_by($item) {
        $used_by = get_post_meta($item->ID, '_used_by', false);
        if (empty($used_by)) return '<span class="voucher-muted">&mdash;</span>';
        $emails = [];
        foreach (array_slice($used_by, -3) as $uid) {
            if (is_numeric($uid)) {
                $user = get_userdata(intval($uid));
                $emails[] = $user ? $user->user_email : '#' . $uid;
            } else {
                $emails[] = sanitize_email($uid);
            }
        }
        $result = esc_html(implode(', ', $emails));
        if (count($used_by) > 3) $result .= sprintf(' <span class="voucher-muted">(+%d)</span>', count($used_by) - 3);
        return $result;
    }

    public function column_used_date($item) {
        $date = get_post_meta($item->ID, '_voucher_last_used_date', true);
        if (!$date) return '<span class="voucher-muted">&mdash;</span>';
        $ts = strtotime($date);
        return sprintf('<span class="voucher-date">%s</span><br><small class="voucher-time">%s</small>', wp_date('d.m.Y', $ts), wp_date('H:i', $ts));
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
                <option value=""><?php esc_html_e('All statuses', 'wc-voucher-manager'); ?></option>
                <option value="active" <?php selected($current, 'active'); ?>><?php esc_html_e('Active', 'wc-voucher-manager'); ?></option>
                <option value="expired" <?php selected($current, 'expired'); ?>><?php esc_html_e('Expired', 'wc-voucher-manager'); ?></option>
                <option value="used" <?php selected($current, 'used'); ?>><?php esc_html_e('Used', 'wc-voucher-manager'); ?></option>
                <option value="unused" <?php selected($current, 'unused'); ?>><?php esc_html_e('Unused', 'wc-voucher-manager'); ?></option>
                <option value="draft" <?php selected($current, 'draft'); ?>><?php esc_html_e('Drafts', 'wc-voucher-manager'); ?></option>
            </select>
            <?php submit_button(__('Filter', 'wc-voucher-manager'), 'secondary', 'filter_action', false); ?>
        </div>
        <?php
    }

    public function no_items() {
        $plural = WC_Voucher_Settings::get('name_plural');
        echo '<div class="voucher-empty-state"><div class="voucher-empty-icon">&#127915;</div>';
        echo '<p>' . sprintf(esc_html__('%s not found', 'wc-voucher-manager'), esc_html($plural)) . '</p>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=wc-vouchers&tab=generate')) . '" class="button button-primary">' . sprintf(esc_html__('Create %s', 'wc-voucher-manager'), esc_html(mb_strtolower($plural))) . '</a>';
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
        $plural = WC_Voucher_Settings::get('name_plural');
        add_submenu_page('woocommerce', $plural . ' — ' . __('Manager', 'wc-voucher-manager'), $plural, 'manage_woocommerce', 'wc-vouchers', [$this, 'render_page']);
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'wc-vouchers') === false) return;

        wp_enqueue_style('wc-voucher-admin', WC_VOUCHER_URL . 'assets/css/admin.css', [], WC_VOUCHER_VERSION);
        wp_enqueue_script('wc-voucher-admin', WC_VOUCHER_URL . 'assets/js/admin.js', ['jquery'], WC_VOUCHER_VERSION, true);
        wp_localize_script('wc-voucher-admin', 'wcVoucher', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wc_voucher_nonce'),
            'i18n'    => [
                'creating'      => __('Creating...', 'wc-voucher-manager'),
                'create_btn'    => __('Create coupons', 'wc-voucher-manager'),
                'count_error'   => __('Count must be between 1 and 1000', 'wc-voucher-manager'),
                'amount_error'  => __('Please specify discount amount', 'wc-voucher-manager'),
                'server_error'  => __('Server error', 'wc-voucher-manager'),
                'no_connection' => __('No connection to server', 'wc-voucher-manager'),
                'timeout'       => __('Timeout — try fewer coupons', 'wc-voucher-manager'),
                'created'       => __('Created %d coupons!', 'wc-voucher-manager'),
                'csv_done'      => __('CSV file downloaded', 'wc-voucher-manager'),
                'copied'        => __('Codes copied to clipboard', 'wc-voucher-manager'),
                'unknown_error' => __('Unknown error', 'wc-voucher-manager'),
            ],
        ]);
    }

    public function handle_actions() {
        // POST search/filter → redirect to GET
        if (isset($_POST['page']) && $_POST['page'] === 'wc-vouchers' && (isset($_POST['filter_action']) || isset($_POST['s']) || isset($_POST['voucher_search'])) && empty($_POST['voucher_ids'])) {
            $args = ['page' => 'wc-vouchers', 'tab' => 'list'];
            foreach (['voucher_status', 'voucher_batch', 's', 'orderby', 'order'] as $p) {
                $v = sanitize_text_field($_POST[$p] ?? '');
                if ($v) $args[$p] = $v;
            }
            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        }

        // Trash single
        if (isset($_GET['page'], $_GET['action'], $_GET['voucher_id']) && $_GET['page'] === 'wc-vouchers' && $_GET['action'] === 'trash_voucher') {
            $id = intval($_GET['voucher_id']);
            check_admin_referer('trash_voucher_' . $id);
            if (current_user_can('manage_woocommerce')) wp_trash_post($id);
            wp_safe_redirect(admin_url('admin.php?page=wc-vouchers&msg=trashed'));
            exit;
        }

        // Bulk
        if (isset($_POST['_wpnonce'], $_POST['voucher_ids']) && !empty($_POST['voucher_ids']) && isset($_REQUEST['page']) && $_REQUEST['page'] === 'wc-vouchers') {
            $action = sanitize_text_field($_POST['action'] ?? '');
            if ($action === '-1') $action = sanitize_text_field($_POST['action2'] ?? '');
            if (!in_array($action, ['bulk_trash', 'bulk_deactivate', 'bulk_activate'], true)) return;
            check_admin_referer('bulk-vouchers');
            if (!current_user_can('manage_woocommerce')) return;
            $ids = array_map('intval', (array) $_POST['voucher_ids']);
            foreach ($ids as $id) {
                if ($action === 'bulk_trash') wp_trash_post($id);
                elseif ($action === 'bulk_deactivate') wp_update_post(['ID' => $id, 'post_status' => 'draft']);
                elseif ($action === 'bulk_activate') wp_update_post(['ID' => $id, 'post_status' => 'publish']);
            }
            wp_safe_redirect(admin_url('admin.php?page=wc-vouchers&msg=bulk_done&count=' . count($ids)));
            exit;
        }
    }

    public function render_page() {
        $tab = sanitize_text_field($_GET['tab'] ?? 'list');
        $plural = WC_Voucher_Settings::get('name_plural');
        ?>
        <div class="wrap wc-voucher-wrap">
            <div class="voucher-header">
                <div class="voucher-header-left">
                    <h1 class="wp-heading-inline"><span class="voucher-header-icon">&#127915;</span> <?php echo esc_html($plural); ?></h1>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=shop_coupon')); ?>" class="page-title-action">+ <?php printf(esc_html__('Add %s', 'wc-voucher-manager'), esc_html(WC_Voucher_Settings::get('name_singular'))); ?></a>
                </div>
            </div>
            <hr class="wp-header-end">
            <nav class="nav-tab-wrapper voucher-nav">
                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-vouchers&tab=list')); ?>" class="nav-tab <?php echo $tab === 'list' ? 'nav-tab-active' : ''; ?>"><span class="dashicons dashicons-list-view"></span> <?php printf(esc_html__('All %s', 'wc-voucher-manager'), esc_html(mb_strtolower($plural))); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-vouchers&tab=generate')); ?>" class="nav-tab <?php echo $tab === 'generate' ? 'nav-tab-active' : ''; ?>"><span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e('Bulk create', 'wc-voucher-manager'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-vouchers&tab=stats')); ?>" class="nav-tab <?php echo $tab === 'stats' ? 'nav-tab-active' : ''; ?>"><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e('Statistics', 'wc-voucher-manager'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-vouchers&tab=settings')); ?>" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('Settings', 'wc-voucher-manager'); ?></a>
            </nav>
            <div class="voucher-tab-content">
                <?php $this->render_messages(); ?>
                <?php
                switch ($tab) {
                    case 'generate': $this->render_generate_tab(); break;
                    case 'stats': $this->render_stats_tab(); break;
                    case 'settings': WC_Voucher_Settings::render_settings_tab(); break;
                    default: $this->render_list_tab();
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
            'trashed'   => __('Coupon moved to trash.', 'wc-voucher-manager'),
            'bulk_done' => __('Action completed.', 'wc-voucher-manager'),
        ];
        if (!isset($messages[$msg])) return;
        $text = $messages[$msg];
        $count = intval($_GET['count'] ?? 0);
        if ($msg === 'bulk_done' && $count > 0) {
            /* translators: %d: number of vouchers */
            $text = sprintf(__('Processed coupons: %d', 'wc-voucher-manager'), $count);
        }
        printf('<div class="notice notice-success is-dismissible voucher-notice"><p>%s</p></div>', esc_html($text));
    }

    private function render_list_tab() {
        $table = new WC_Voucher_List_Table();
        $table->prepare_items();
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="wc-vouchers" />
            <input type="hidden" name="tab" value="list" />
            <?php
            foreach (['voucher_status', 'voucher_batch', 's', 'orderby', 'order'] as $p) {
                $v = sanitize_text_field($_REQUEST[$p] ?? '');
                if ($v) printf('<input type="hidden" name="%s" value="%s" />', esc_attr($p), esc_attr($v));
            }
            $table->search_box(__('Search', 'wc-voucher-manager'), 'voucher_search');
            $table->display();
            ?>
        </form>
        <div class="voucher-export">
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=wc_voucher_export_csv'), 'wc_voucher_nonce', '_wpnonce')); ?>" class="button button-secondary">
                <span class="dashicons dashicons-download" style="vertical-align:text-bottom"></span> <?php esc_html_e('Export CSV', 'wc-voucher-manager'); ?>
            </a>
        </div>
        <?php
    }

    private function render_generate_tab() {
        $singular = WC_Voucher_Settings::get('name_singular');
        $plural_lc = mb_strtolower(WC_Voucher_Settings::get('name_plural'));
        ?>
        <div class="voucher-generate-form">
            <div class="voucher-form-header">
                <h2><?php printf(esc_html__('Bulk create %s', 'wc-voucher-manager'), esc_html($plural_lc)); ?></h2>
                <p class="voucher-form-desc"><?php esc_html_e('Create multiple coupons with the same parameters. Unique codes are generated automatically.', 'wc-voucher-manager'); ?></p>
            </div>
            <div class="voucher-form-grid">
                <div class="voucher-form-section">
                    <h3 class="voucher-section-title"><?php esc_html_e('Main parameters', 'wc-voucher-manager'); ?></h3>
                    <div class="voucher-field">
                        <label for="voucher_count"><?php esc_html_e('Quantity', 'wc-voucher-manager'); ?></label>
                        <div class="voucher-field-input">
                            <input type="number" id="voucher_count" value="10" min="1" max="1000" />
                            <span class="voucher-field-hint"><?php esc_html_e('1 to 1000', 'wc-voucher-manager'); ?></span>
                        </div>
                    </div>
                    <div class="voucher-field">
                        <label for="voucher_prefix"><?php esc_html_e('Code prefix', 'wc-voucher-manager'); ?></label>
                        <div class="voucher-field-input">
                            <input type="text" id="voucher_prefix" value="VOUCHER-" />
                            <span class="voucher-field-hint">VOUCHER-, SALE-, PROMO-</span>
                        </div>
                    </div>
                    <div class="voucher-field">
                        <label for="voucher_code_length"><?php esc_html_e('Code length', 'wc-voucher-manager'); ?></label>
                        <div class="voucher-field-input">
                            <input type="number" id="voucher_code_length" value="8" min="4" max="20" />
                            <span class="voucher-field-hint"><?php esc_html_e('random part without prefix', 'wc-voucher-manager'); ?></span>
                        </div>
                    </div>
                    <div class="voucher-field-row">
                        <div class="voucher-field">
                            <label for="voucher_discount_type"><?php esc_html_e('Discount type', 'wc-voucher-manager'); ?></label>
                            <select id="voucher_discount_type">
                                <option value="percent"><?php esc_html_e('Percent (%)', 'wc-voucher-manager'); ?></option>
                                <option value="fixed_cart"><?php esc_html_e('Fixed cart', 'wc-voucher-manager'); ?></option>
                                <option value="fixed_product"><?php esc_html_e('Fixed product', 'wc-voucher-manager'); ?></option>
                            </select>
                        </div>
                        <div class="voucher-field">
                            <label for="voucher_amount"><?php esc_html_e('Amount', 'wc-voucher-manager'); ?></label>
                            <input type="number" id="voucher_amount" value="10" min="0" step="0.01" />
                        </div>
                    </div>
                </div>
                <div class="voucher-form-section">
                    <h3 class="voucher-section-title"><?php esc_html_e('Restrictions', 'wc-voucher-manager'); ?></h3>
                    <div class="voucher-field">
                        <label for="voucher_expiry"><?php esc_html_e('Expiry date', 'wc-voucher-manager'); ?></label>
                        <div class="voucher-field-input">
                            <input type="datetime-local" id="voucher_expiry" />
                            <span class="voucher-field-hint"><?php esc_html_e('empty = no expiry', 'wc-voucher-manager'); ?></span>
                        </div>
                    </div>
                    <div class="voucher-field-row">
                        <div class="voucher-field">
                            <label for="voucher_usage_limit"><?php esc_html_e('Usage limit', 'wc-voucher-manager'); ?></label>
                            <div class="voucher-field-input">
                                <input type="number" id="voucher_usage_limit" value="1" min="0" />
                                <span class="voucher-field-hint"><?php esc_html_e('0 = unlimited', 'wc-voucher-manager'); ?></span>
                            </div>
                        </div>
                        <div class="voucher-field">
                            <label for="voucher_min_amount"><?php esc_html_e('Min. order amount', 'wc-voucher-manager'); ?></label>
                            <div class="voucher-field-input">
                                <input type="number" id="voucher_min_amount" value="0" min="0" step="0.01" />
                                <span class="voucher-field-hint"><?php esc_html_e('0 = no minimum', 'wc-voucher-manager'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="voucher-field">
                        <label class="voucher-checkbox-label"><input type="checkbox" id="voucher_individual" checked /><span><?php esc_html_e('Individual use only', 'wc-voucher-manager'); ?></span></label>
                    </div>
                    <div class="voucher-field">
                        <label class="voucher-checkbox-label"><input type="checkbox" id="voucher_free_shipping" /><span><?php esc_html_e('Free shipping', 'wc-voucher-manager'); ?></span></label>
                    </div>
                </div>
            </div>
            <div class="voucher-generate-actions">
                <button type="button" id="btn-generate-vouchers" class="button button-primary button-hero"><span class="dashicons dashicons-tickets-alt" style="vertical-align:text-bottom;margin-right:4px"></span> <?php printf(esc_html__('Create %s', 'wc-voucher-manager'), esc_html($plural_lc)); ?></button>
                <div id="voucher-preview-code" class="voucher-preview-code"></div>
            </div>
            <div id="voucher-progress" style="display:none;">
                <div class="voucher-progress-bar"><div class="voucher-progress-fill" style="width:0%"><span class="voucher-progress-percent">0%</span></div></div>
                <p class="voucher-progress-text"><?php esc_html_e('Created:', 'wc-voucher-manager'); ?> <strong><span id="voucher-progress-count">0</span></strong> / <strong><span id="voucher-progress-total">0</span></strong></p>
            </div>
            <div id="voucher-result" style="display:none;">
                <div class="voucher-result-success"><div class="voucher-result-icon">&#10004;</div><div class="voucher-result-text"><strong><?php esc_html_e('Done!', 'wc-voucher-manager'); ?></strong> <?php esc_html_e('Created:', 'wc-voucher-manager'); ?> <span id="voucher-result-count">0</span></div></div>
                <div class="voucher-result-actions">
                    <a href="#" id="btn-download-csv" class="button button-primary"><span class="dashicons dashicons-download" style="vertical-align:text-bottom"></span> <?php esc_html_e('Download CSV', 'wc-voucher-manager'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wc-vouchers&tab=list')); ?>" class="button"><?php esc_html_e('View list', 'wc-voucher-manager'); ?></a>
                    <button type="button" id="btn-copy-codes" class="button"><span class="dashicons dashicons-clipboard" style="vertical-align:text-bottom"></span> <?php esc_html_e('Copy', 'wc-voucher-manager'); ?></button>
                </div>
                <textarea id="voucher-result-codes" class="large-text code" rows="10" readonly></textarea>
            </div>
        </div>
        <?php
    }

    private function render_stats_tab() {
        global $wpdb;
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_status IN ('publish','draft')");
        $used = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'usage_count' WHERE p.post_type = 'shop_coupon' AND p.post_status IN ('publish','draft') AND CAST(pm.meta_value AS UNSIGNED) > 0");
        $now = current_time('timestamp');
        $expired = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'date_expires' WHERE p.post_type = 'shop_coupon' AND p.post_status IN ('publish','draft') AND CAST(pm.meta_value AS UNSIGNED) > 0 AND CAST(pm.meta_value AS UNSIGNED) <= %d", $now));
        $active = max(0, $total - $expired);

        $batches = $wpdb->get_results("SELECT pm.meta_value as batch_id, COUNT(*) as cnt, MIN(p.post_date) as created FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_voucher_batch_id' AND p.post_type = 'shop_coupon' GROUP BY pm.meta_value ORDER BY created DESC LIMIT 5");
        ?>
        <div class="voucher-stats">
            <div class="voucher-stats-grid">
                <div class="voucher-stat-card"><div class="voucher-stat-icon">&#127915;</div><div class="voucher-stat-number"><?php echo $total; ?></div><div class="voucher-stat-label"><?php esc_html_e('Total', 'wc-voucher-manager'); ?></div></div>
                <div class="voucher-stat-card voucher-stat-active"><div class="voucher-stat-icon">&#9989;</div><div class="voucher-stat-number"><?php echo $active; ?></div><div class="voucher-stat-label"><?php esc_html_e('Active', 'wc-voucher-manager'); ?></div></div>
                <div class="voucher-stat-card voucher-stat-used"><div class="voucher-stat-icon">&#128178;</div><div class="voucher-stat-number"><?php echo $used; ?></div><div class="voucher-stat-label"><?php esc_html_e('Used', 'wc-voucher-manager'); ?></div></div>
                <div class="voucher-stat-card voucher-stat-expired"><div class="voucher-stat-icon">&#9200;</div><div class="voucher-stat-number"><?php echo $expired; ?></div><div class="voucher-stat-label"><?php esc_html_e('Expired', 'wc-voucher-manager'); ?></div></div>
            </div>
            <?php if (!empty($batches)) : ?>
            <div class="voucher-batches">
                <h3><?php esc_html_e('Recent batches', 'wc-voucher-manager'); ?></h3>
                <table class="widefat striped"><thead><tr><th><?php esc_html_e('Batch', 'wc-voucher-manager'); ?></th><th><?php esc_html_e('Count', 'wc-voucher-manager'); ?></th><th><?php esc_html_e('Created', 'wc-voucher-manager'); ?></th><th><?php esc_html_e('Action', 'wc-voucher-manager'); ?></th></tr></thead><tbody>
                <?php foreach ($batches as $b) : ?>
                <tr><td><code><?php echo esc_html(substr($b->batch_id, 0, 8)); ?>...</code></td><td><strong><?php echo intval($b->cnt); ?></strong></td><td><?php echo wp_date('d.m.Y H:i', strtotime($b->created)); ?></td><td><a href="<?php echo esc_url(admin_url('admin.php?page=wc-vouchers&tab=list&voucher_batch=' . urlencode($b->batch_id))); ?>" class="button button-small"><?php esc_html_e('Show', 'wc-voucher-manager'); ?></a></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
