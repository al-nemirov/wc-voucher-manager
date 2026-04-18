<?php
if (!defined('ABSPATH')) exit;

class WC_Voucher_Dashboard_Widget {

    const CACHE_KEY = 'wc_voucher_dashboard_stats';
    const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

    public function __construct() {
        add_action('wp_dashboard_setup', [$this, 'register_widget']);
        // Bust cache on coupon changes
        add_action('save_post_shop_coupon', [$this, 'flush_cache']);
        add_action('trashed_post', [$this, 'flush_cache']);
        add_action('deleted_post', [$this, 'flush_cache']);
    }

    public function register_widget() {
        if (!current_user_can('manage_woocommerce')) return;

        $plural = WC_Voucher_Settings::get('name_plural');
        /* translators: %s: plural voucher name */
        $title = sprintf(__('%s — Statistics', 'wc-voucher-manager'), $plural);

        wp_add_dashboard_widget('wc_voucher_dashboard_widget', $title, [$this, 'render_widget']);
    }

    public function flush_cache($post_id = 0) {
        if ($post_id && get_post_type($post_id) !== 'shop_coupon') return;
        delete_transient(self::CACHE_KEY);
    }

    private function get_stats() {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached)) return $cached;

        global $wpdb;
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_status IN ('publish','draft')");
        $used = (int) $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'usage_count' WHERE p.post_type = 'shop_coupon' AND p.post_status IN ('publish','draft') AND CAST(pm.meta_value AS UNSIGNED) > 0");
        $now = time();
        $expired = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'date_expires' WHERE p.post_type = 'shop_coupon' AND p.post_status IN ('publish','draft') AND CAST(pm.meta_value AS UNSIGNED) > 0 AND CAST(pm.meta_value AS UNSIGNED) <= %d", $now));

        // Last 7 days: coupons with any usage history entry in last 7 days (approx: last_used_date)
        $week_ago_mysql = wp_date('Y-m-d H:i:s', $now - 7 * DAY_IN_SECONDS);
        $recent_uses = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_voucher_last_used_date' AND meta_value >= %s", $week_ago_mysql));

        $stats = [
            'total'       => $total,
            'used'        => $used,
            'expired'     => $expired,
            'active'      => max(0, $total - $expired),
            'recent_uses' => $recent_uses,
        ];

        set_transient(self::CACHE_KEY, $stats, self::CACHE_TTL);
        return $stats;
    }

    public function render_widget() {
        $stats = $this->get_stats();
        $plural_lc = mb_strtolower(WC_Voucher_Settings::get('name_plural'));
        $list_url = admin_url('admin.php?page=wc-vouchers');
        ?>
        <div class="wc-voucher-dashboard-widget">
            <ul style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:0;">
                <li style="background:#f0f6fc;padding:10px;border-radius:4px;">
                    <strong style="font-size:20px;display:block;"><?php echo absint($stats['total']); ?></strong>
                    <span style="color:#646970;font-size:12px;"><?php esc_html_e('Total', 'wc-voucher-manager'); ?></span>
                </li>
                <li style="background:#edf7ed;padding:10px;border-radius:4px;">
                    <strong style="font-size:20px;display:block;color:#1e7d32;"><?php echo absint($stats['active']); ?></strong>
                    <span style="color:#646970;font-size:12px;"><?php esc_html_e('Active', 'wc-voucher-manager'); ?></span>
                </li>
                <li style="background:#fff4e5;padding:10px;border-radius:4px;">
                    <strong style="font-size:20px;display:block;color:#b26a00;"><?php echo absint($stats['used']); ?></strong>
                    <span style="color:#646970;font-size:12px;"><?php esc_html_e('Used', 'wc-voucher-manager'); ?></span>
                </li>
                <li style="background:#fdeded;padding:10px;border-radius:4px;">
                    <strong style="font-size:20px;display:block;color:#c62828;"><?php echo absint($stats['expired']); ?></strong>
                    <span style="color:#646970;font-size:12px;"><?php esc_html_e('Expired', 'wc-voucher-manager'); ?></span>
                </li>
            </ul>
            <p style="margin-top:12px;color:#646970;">
                <?php
                /* translators: %d: number of uses in the last 7 days */
                printf(esc_html__('Used in the last 7 days: %d', 'wc-voucher-manager'), absint($stats['recent_uses']));
                ?>
            </p>
            <p style="margin-top:8px;">
                <a href="<?php echo esc_url($list_url); ?>" class="button button-secondary">
                    <?php
                    /* translators: %s: plural voucher name (lowercased) */
                    printf(esc_html__('Manage %s', 'wc-voucher-manager'), esc_html($plural_lc));
                    ?>
                </a>
            </p>
        </div>
        <?php
    }
}
