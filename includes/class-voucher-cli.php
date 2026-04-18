<?php
if (!defined('ABSPATH')) exit;
if (!defined('WP_CLI') || !WP_CLI) return;

/**
 * Manage WooCommerce vouchers from the command line.
 */
class WC_Voucher_CLI {

    /**
     * Bulk-generate vouchers.
     *
     * ## OPTIONS
     *
     * [--count=<number>]
     * : How many vouchers to create. Default: 10. Max: 1000.
     *
     * [--prefix=<string>]
     * : Code prefix. Default: VOUCHER-
     *
     * [--length=<number>]
     * : Random part length (4–20). Default: 8.
     *
     * [--type=<type>]
     * : Discount type: percent, fixed_cart, fixed_product. Default: percent.
     *
     * [--amount=<number>]
     * : Discount amount. Default: 10.
     *
     * [--usage-limit=<number>]
     * : Per-voucher usage limit. 0 = unlimited. Default: 1.
     *
     * [--min-amount=<number>]
     * : Minimum order amount. Default: 0.
     *
     * [--expiry=<date>]
     * : Expiry date (any strtotime-compatible format, site timezone).
     *
     * [--individual]
     * : Individual use only.
     *
     * [--free-shipping]
     * : Grant free shipping.
     *
     * [--format=<format>]
     * : Output format: table, csv, json, count. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp voucher generate --count=50 --prefix=SALE- --type=percent --amount=15 --expiry="2026-12-31 23:59"
     *
     * @when after_wp_load
     */
    public function generate($args, $assoc_args) {
        if (!class_exists('WC_Coupon')) {
            WP_CLI::error('WooCommerce is not active.');
        }

        $count       = min(1000, max(1, (int) ($assoc_args['count'] ?? 10)));
        $prefix      = isset($assoc_args['prefix']) ? sanitize_text_field($assoc_args['prefix']) : 'VOUCHER-';
        $length      = max(4, min(20, (int) ($assoc_args['length'] ?? 8)));
        $type        = in_array(($assoc_args['type'] ?? 'percent'), ['percent','fixed_cart','fixed_product'], true) ? $assoc_args['type'] : 'percent';
        $amount      = max(0, (float) ($assoc_args['amount'] ?? 10));
        $usage_limit = max(0, (int) ($assoc_args['usage-limit'] ?? 1));
        $min_amount  = max(0, (float) ($assoc_args['min-amount'] ?? 0));
        $individual  = isset($assoc_args['individual']);
        $free_ship   = isset($assoc_args['free-shipping']);
        $expiry_str  = $assoc_args['expiry'] ?? '';
        $format      = $assoc_args['format'] ?? 'table';

        $expiry_ts = 0;
        if ($expiry_str) {
            try {
                $tz = wp_timezone();
                $dt = date_create($expiry_str, $tz);
                if ($dt) $expiry_ts = $dt->getTimestamp();
            } catch (Exception $e) {
                WP_CLI::warning('Could not parse --expiry; ignoring.');
            }
        }

        $batch_id = wp_generate_uuid4();
        $created  = [];
        $progress = \WP_CLI\Utils\make_progress_bar('Generating vouchers', $count);

        for ($i = 0; $i < $count; $i++) {
            $code = $this->unique_code($prefix, $length);

            $coupon = new WC_Coupon();
            $coupon->set_code($code);
            $coupon->set_discount_type($type);
            $coupon->set_amount($amount);
            $coupon->set_individual_use($individual);
            $coupon->set_free_shipping($free_ship);
            if ($usage_limit > 0) $coupon->set_usage_limit($usage_limit);
            if ($min_amount > 0)  $coupon->set_minimum_amount($min_amount);
            if ($expiry_ts > 0)   $coupon->set_date_expires($expiry_ts);

            try {
                $id = $coupon->save();
            } catch (Exception $e) {
                $id = 0;
                WP_CLI::warning('Save failed: ' . $e->getMessage());
            }

            if ($id) {
                update_post_meta($id, '_voucher_batch_id', $batch_id);
                update_post_meta($id, '_voucher_created_date', current_time('mysql'));
                $created[] = ['id' => $id, 'code' => $code];
            }
            $progress->tick();
        }
        $progress->finish();

        delete_transient(WC_Voucher_Dashboard_Widget::CACHE_KEY);

        WP_CLI::success(sprintf('Created %d / %d vouchers (batch %s).', count($created), $count, $batch_id));
        \WP_CLI\Utils\format_items($format, $created, ['id', 'code']);
    }

    /**
     * Show voucher statistics.
     *
     * ## EXAMPLES
     *
     *     wp voucher stats
     *
     * @when after_wp_load
     */
    public function stats($args, $assoc_args) {
        global $wpdb;
        $total   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_status IN ('publish','draft')");
        $used    = (int) $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'usage_count' WHERE p.post_type = 'shop_coupon' AND CAST(pm.meta_value AS UNSIGNED) > 0");
        $expired = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'date_expires' WHERE p.post_type = 'shop_coupon' AND CAST(pm.meta_value AS UNSIGNED) > 0 AND CAST(pm.meta_value AS UNSIGNED) <= %d", time()));

        \WP_CLI\Utils\format_items('table', [
            ['metric' => 'total',   'value' => $total],
            ['metric' => 'active',  'value' => max(0, $total - $expired)],
            ['metric' => 'used',    'value' => $used],
            ['metric' => 'expired', 'value' => $expired],
        ], ['metric', 'value']);
    }

    private function unique_code($prefix, $length) {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $len = strlen($chars);
        for ($a = 0; $a < 10; $a++) {
            $rnd = '';
            for ($i = 0; $i < $length; $i++) $rnd .= $chars[random_int(0, $len - 1)];
            $code = strtoupper($prefix) . $rnd;
            $q = new WP_Query([
                'post_type' => 'shop_coupon', 'title' => $code,
                'post_status' => 'any', 'posts_per_page' => 1,
                'fields' => 'ids', 'no_found_rows' => true,
            ]);
            if (!$q->have_posts()) return $code;
        }
        return strtoupper($prefix) . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, $length));
    }
}

WP_CLI::add_command('voucher', 'WC_Voucher_CLI');
