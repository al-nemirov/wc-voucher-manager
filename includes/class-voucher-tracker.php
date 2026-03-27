<?php
if (!defined('ABSPATH')) exit;

class WC_Voucher_Tracker {

    public function __construct() {
        add_action('woocommerce_order_status_completed', [$this, 'track_usage_on_order']);
        add_action('woocommerce_order_status_processing', [$this, 'track_usage_on_order']);
        add_action('woocommerce_applied_coupon', [$this, 'track_applied']);

        add_action('add_meta_boxes', [$this, 'add_order_metabox']);

        // Classic orders
        add_filter('manage_edit-shop_order_columns', [$this, 'add_order_column']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'render_order_column'], 10, 2);

        // HPOS orders
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_order_column']);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'render_hpos_order_column'], 10, 2);
    }

    public function track_applied($coupon_code) {
        $coupon = new WC_Coupon($coupon_code);
        if (!$coupon->get_id()) return;

        $current_user = wp_get_current_user();
        $email = $current_user->ID ? sanitize_email($current_user->user_email) : 'guest';

        update_post_meta($coupon->get_id(), '_voucher_last_applied_date', current_time('mysql'));
        update_post_meta($coupon->get_id(), '_voucher_last_applied_by', $email);
    }

    public function track_usage_on_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Prevent double tracking
        $tracked = $order->get_meta('_voucher_usage_tracked');
        if ($tracked === 'yes') return;

        $coupons = $order->get_coupon_codes();
        if (empty($coupons)) return;

        foreach ($coupons as $code) {
            $coupon = new WC_Coupon($code);
            if (!$coupon->get_id()) continue;

            $billing_email = sanitize_email($order->get_billing_email());
            $customer_name = sanitize_text_field($order->get_formatted_billing_full_name());

            update_post_meta($coupon->get_id(), '_voucher_last_used_date', current_time('mysql'));
            update_post_meta($coupon->get_id(), '_voucher_last_used_by', $billing_email);
            update_post_meta($coupon->get_id(), '_voucher_last_used_order_id', $order_id);
            update_post_meta($coupon->get_id(), '_voucher_last_used_customer', $customer_name);

            $history = get_post_meta($coupon->get_id(), '_voucher_usage_history', true);
            if (!is_array($history)) $history = [];

            $history[] = [
                'order_id' => $order_id,
                'email'    => $billing_email,
                'customer' => $customer_name,
                'date'     => current_time('mysql'),
                'discount' => $order->get_discount_total(),
            ];

            update_post_meta($coupon->get_id(), '_voucher_usage_history', $history);
        }

        $order->update_meta_data('_voucher_usage_tracked', 'yes');
        $order->save();
    }

    public function add_order_metabox() {
        // Classic screen
        add_meta_box(
            'wc_voucher_order_info',
            'Использованные ваучеры',
            [$this, 'render_order_metabox'],
            'shop_order',
            'side',
            'default'
        );

        // HPOS screen
        $screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : null;
        if ($screen) {
            add_meta_box(
                'wc_voucher_order_info',
                'Использованные ваучеры',
                [$this, 'render_order_metabox'],
                $screen,
                'side',
                'default'
            );
        }
    }

    public function render_order_metabox($post_or_order) {
        $order = ($post_or_order instanceof WP_Post) ? wc_get_order($post_or_order->ID) : $post_or_order;

        if (!$order) {
            echo '<p>Заказ не найден</p>';
            return;
        }

        $coupons = $order->get_coupon_codes();
        if (empty($coupons)) {
            echo '<p style="color:#999">Ваучеры не использованы</p>';
            return;
        }

        echo '<ul class="voucher-order-list">';
        foreach ($coupons as $code) {
            $coupon = new WC_Coupon($code);
            if ($coupon->get_id()) {
                $type = $coupon->get_discount_type();
                $amount = $coupon->get_amount();
                $label = $type === 'percent' ? $amount . '%' : wc_price($amount);
                $edit_url = admin_url('post.php?post=' . $coupon->get_id() . '&action=edit');
                printf(
                    '<li><a href="%s"><strong>%s</strong></a> &mdash; %s</li>',
                    esc_url($edit_url),
                    esc_html(strtoupper($code)),
                    $label
                );
            } else {
                printf('<li><strong>%s</strong> <em>(удалён)</em></li>', esc_html(strtoupper($code)));
            }
        }
        echo '</ul>';
    }

    public function add_order_column($columns) {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'order_total') {
                $new['voucher_used'] = 'Ваучер';
            }
        }
        return $new;
    }

    public function render_order_column($column, $post_id) {
        if ($column !== 'voucher_used') return;
        $this->output_voucher_column(wc_get_order($post_id));
    }

    public function render_hpos_order_column($column, $order) {
        if ($column !== 'voucher_used') return;
        $this->output_voucher_column($order);
    }

    private function output_voucher_column($order) {
        if (!$order) {
            echo '&mdash;';
            return;
        }

        $coupons = $order->get_coupon_codes();
        if (empty($coupons)) {
            echo '&mdash;';
            return;
        }

        $badges = array_map(function($code) {
            return '<span class="voucher-order-badge">' . esc_html(strtoupper($code)) . '</span>';
        }, $coupons);

        echo implode(' ', $badges);
    }
}
