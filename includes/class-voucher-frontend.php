<?php
if (!defined('ABSPATH')) exit;

class WC_Voucher_Frontend {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        // Custom success message when voucher applied
        add_filter('woocommerce_coupon_message', [$this, 'custom_applied_message'], 20, 3);

        // Beautiful voucher notice on checkout/cart
        add_action('woocommerce_applied_coupon', [$this, 'add_beautiful_notice']);

        // Thank-you page message when voucher was used
        add_action('woocommerce_thankyou', [$this, 'thankyou_voucher_message'], 5);
    }

    public function enqueue_frontend_assets() {
        if (!is_cart() && !is_checkout() && !is_wc_endpoint_url('order-received')) return;

        wp_enqueue_style(
            'wc-voucher-frontend',
            WC_VOUCHER_URL . 'assets/css/frontend.css',
            [],
            WC_VOUCHER_VERSION
        );

        wp_enqueue_script(
            'wc-voucher-frontend',
            WC_VOUCHER_URL . 'assets/js/frontend.js',
            ['jquery'],
            WC_VOUCHER_VERSION,
            true
        );
    }

    public function custom_applied_message($msg, $msg_code, $coupon) {
        if ($msg_code === WC_Coupon::WC_COUPON_SUCCESS) {
            $amount = $coupon->get_amount();
            $type = $coupon->get_discount_type();

            if ($type === 'percent') {
                $discount_text = $amount . '%';
            } else {
                $discount_text = wc_price($amount);
            }

            return sprintf(
                'Ваучер <strong>%s</strong> применён! Ваша скидка: <strong>%s</strong>. Приятных покупок!',
                esc_html(strtoupper($coupon->get_code())),
                $discount_text
            );
        }
        return $msg;
    }

    public function add_beautiful_notice($coupon_code) {
        $coupon = new WC_Coupon($coupon_code);
        if (!$coupon->get_id()) return;

        $amount = $coupon->get_amount();
        $type = $coupon->get_discount_type();

        if ($type === 'percent') {
            $discount_text = $amount . '%';
        } else {
            $discount_text = strip_tags(wc_price($amount));
        }

        // Set a transient for the frontend JS to pick up
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
            'message'  => 'Приятных покупок!',
        ], 60);

        // Inject toast data via footer
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

        $messages = [
            'Спасибо за покупку! Вы сэкономили %s с ваучером. Ждём вас снова!',
            'Отличная покупка! Ваш ваучер сэкономил вам %s. До новых встреч!',
            'Замечательно! Скидка %s по ваучеру уже применена. Приятного дня!',
        ];

        $msg = $messages[array_rand($messages)];
        ?>
        <div class="voucher-thankyou-banner">
            <div class="voucher-thankyou-icon">&#127873;</div>
            <div class="voucher-thankyou-content">
                <div class="voucher-thankyou-title">Ваучер применён!</div>
                <div class="voucher-thankyou-text">
                    <?php printf(esc_html($msg), '<strong>' . wc_price($discount) . '</strong>'); ?>
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
