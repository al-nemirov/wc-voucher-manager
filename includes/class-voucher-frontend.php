<?php
if (!defined('ABSPATH')) exit;

class WC_Voucher_Frontend {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_filter('woocommerce_coupon_message', [$this, 'custom_applied_message'], 20, 3);
        add_action('woocommerce_applied_coupon', [$this, 'add_beautiful_notice']);
        add_action('woocommerce_thankyou', [$this, 'thankyou_voucher_message'], 5);
    }

    public function enqueue_frontend_assets() {
        if (!is_cart() && !is_checkout() && !is_wc_endpoint_url('order-received')) return;

        wp_enqueue_style('wc-voucher-frontend', WC_VOUCHER_URL . 'assets/css/frontend.css', [], WC_VOUCHER_VERSION);
        wp_enqueue_script('wc-voucher-frontend', WC_VOUCHER_URL . 'assets/js/frontend.js', ['jquery'], WC_VOUCHER_VERSION, true);
        wp_localize_script('wc-voucher-frontend', 'wcVoucherFront', [
            'i18n' => [
                'applied_title' => sprintf(
                    /* translators: %s: singular voucher name */
                    __('%s applied!', 'wc-voucher-manager'),
                    WC_Voucher_Settings::get('name_singular')
                ),
            ],
            'confetti' => WC_Voucher_Settings::get('confetti_enabled'),
        ]);
    }

    public function custom_applied_message($msg, $msg_code, $coupon) {
        if ($msg_code !== WC_Coupon::WC_COUPON_SUCCESS) return $msg;

        $singular = WC_Voucher_Settings::get('name_singular');
        $amount = $coupon->get_amount();
        $type = $coupon->get_discount_type();
        $discount_text = $type === 'percent' ? $amount . '%' : wc_price($amount);

        /* translators: 1: voucher name, 2: code, 3: discount amount, 4: custom message */
        return sprintf(
            __('%1$s <strong>%2$s</strong> applied! Your discount: <strong>%3$s</strong>. %4$s', 'wc-voucher-manager'),
            esc_html($singular),
            esc_html(strtoupper($coupon->get_code())),
            $discount_text,
            esc_html(WC_Voucher_Settings::get('frontend_message'))
        );
    }

    public function add_beautiful_notice($coupon_code) {
        if (WC_Voucher_Settings::get('toast_enabled') !== 'yes') return;

        $coupon = new WC_Coupon($coupon_code);
        if (!$coupon->get_id()) return;

        $amount = $coupon->get_amount();
        $type = $coupon->get_discount_type();
        $discount_text = $type === 'percent' ? $amount . '%' : strip_tags(wc_price($amount));

        $user_id = get_current_user_id();
        if ($user_id) {
            $key = 'voucher_toast_' . $user_id;
        } elseif (WC()->session) {
            $key = 'voucher_toast_' . md5(WC()->session->get_customer_id());
        } else {
            $key = 'voucher_toast_' . md5(wp_get_session_token() ?: uniqid('guest_', true));
        }

        set_transient($key, [
            'code'     => strtoupper($coupon_code),
            'discount' => $discount_text,
            'type'     => $type,
            'message'  => WC_Voucher_Settings::get('frontend_message'),
        ], 60);

        add_action('wp_footer', function() use ($key) {
            $data = get_transient($key);
            if (!$data) return;
            delete_transient($key);
            ?>
            <script>
            if (typeof window.wcVoucherToast === 'undefined') {
                window.wcVoucherToast = <?php echo wp_json_encode($data); ?>;
            }
            </script>
            <?php
        });
    }

    public function thankyou_voucher_message($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $coupons = $order->get_coupon_codes();
        if (empty($coupons)) return;

        $discount = $order->get_discount_total();
        $singular = WC_Voucher_Settings::get('name_singular');
        $msg_template = WC_Voucher_Settings::get('thankyou_message');
        ?>
        <div class="voucher-thankyou-banner">
            <div class="voucher-thankyou-icon">&#127873;</div>
            <div class="voucher-thankyou-content">
                <div class="voucher-thankyou-title"><?php printf(esc_html__('%s applied!', 'wc-voucher-manager'), esc_html($singular)); ?></div>
                <div class="voucher-thankyou-text">
                    <?php printf(esc_html($msg_template), '<strong>' . wc_price($discount) . '</strong>'); ?>
                </div>
                <div class="voucher-thankyou-codes">
                    <?php foreach ($coupons as $code) : ?>
                        <span class="voucher-thankyou-code"><?php echo esc_html(strtoupper($code)); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
