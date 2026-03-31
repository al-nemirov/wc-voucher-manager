<?php
if (!defined('ABSPATH')) exit;

class WC_Voucher_Generator {

    public function __construct() {
        add_action('wp_ajax_wc_voucher_generate_batch', [$this, 'ajax_generate_batch']);
        add_action('wp_ajax_wc_voucher_export_csv', [$this, 'ajax_export_csv']);
    }

    public function ajax_generate_batch() {
        check_ajax_referer('wc_voucher_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Недостаточно прав');
        }

        $count         = min(abs(intval($_POST['count'] ?? 10)), 1000);
        $prefix        = sanitize_text_field($_POST['prefix'] ?? 'VOUCHER-');
        $code_length   = max(4, min(20, intval($_POST['code_length'] ?? 8)));
        $discount_type = sanitize_text_field($_POST['discount_type'] ?? 'percent');
        $amount        = abs(floatval($_POST['amount'] ?? 10));
        $expiry        = sanitize_text_field($_POST['expiry'] ?? '');
        $usage_limit   = abs(intval($_POST['usage_limit'] ?? 1));
        $min_amount    = abs(floatval($_POST['min_amount'] ?? 0));
        $individual    = ($_POST['individual'] ?? 'no') === 'yes' ? 'yes' : 'no';
        $free_shipping = ($_POST['free_shipping'] ?? 'no') === 'yes' ? 'yes' : 'no';
        $offset        = abs(intval($_POST['offset'] ?? 0));
        $batch_size    = min(50, $count - $offset);

        if ($batch_size <= 0) {
            wp_send_json_success([
                'codes'    => [],
                'offset'   => $offset,
                'total'    => $count,
                'done'     => true,
                'batch_id' => sanitize_text_field($_POST['batch_id'] ?? ''),
            ]);
        }

        // Validate discount type
        $allowed_types = ['percent', 'fixed_cart', 'fixed_product'];
        if (!in_array($discount_type, $allowed_types, true)) {
            $discount_type = 'percent';
        }

        $batch_id = sanitize_text_field($_POST['batch_id'] ?? wp_generate_uuid4());

        $expiry_timestamp = 0;
        if ($expiry) {
            $expiry_timestamp = strtotime($expiry);
            if ($expiry_timestamp === false) {
                $expiry_timestamp = 0;
            }
        }

        $created_codes = [];

        for ($i = 0; $i < $batch_size; $i++) {
            $code = $this->generate_unique_code($prefix, $code_length);

            $coupon = new WC_Coupon();
            $coupon->set_code($code);
            $coupon->set_discount_type($discount_type);
            $coupon->set_amount($amount);
            $coupon->set_individual_use($individual === 'yes');
            $coupon->set_free_shipping($free_shipping === 'yes');

            if ($usage_limit > 0) {
                $coupon->set_usage_limit($usage_limit);
            }

            if ($min_amount > 0) {
                $coupon->set_minimum_amount($min_amount);
            }

            if ($expiry_timestamp > 0) {
                $coupon->set_date_expires($expiry_timestamp);
            }

            $coupon_id = $coupon->save();

            if ($coupon_id) {
                update_post_meta($coupon_id, '_voucher_batch_id', $batch_id);
                update_post_meta($coupon_id, '_voucher_created_date', current_time('mysql'));
                $created_codes[] = $code;
            }
        }

        $new_offset = $offset + count($created_codes);
        $done = $new_offset >= $count;

        wp_send_json_success([
            'codes'    => $created_codes,
            'offset'   => $new_offset,
            'total'    => $count,
            'done'     => $done,
            'batch_id' => $batch_id,
        ]);
    }

    private function generate_unique_code($prefix, $length) {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $chars_len = strlen($chars);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $random = '';
            for ($i = 0; $i < $length; $i++) {
                $random .= $chars[random_int(0, $chars_len - 1)];
            }
            $code = strtoupper($prefix) . $random;

            // WP 6.2+ compatible uniqueness check
            $existing = new WP_Query([
                'post_type'      => 'shop_coupon',
                'title'          => $code,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);

            if (!$existing->have_posts()) {
                return $code;
            }
        }

        // Absolute fallback with microtime
        return strtoupper($prefix) . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, $length));
    }

    public function ajax_export_csv() {
        check_admin_referer('wc_voucher_nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Недостаточно прав');
        }

        $args = [
            'post_type'      => 'shop_coupon',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft'],
        ];

        $batch_id = sanitize_text_field($_GET['batch_id'] ?? '');
        if ($batch_id) {
            $args['meta_query'] = [
                ['key' => '_voucher_batch_id', 'value' => $batch_id],
            ];
        }

        $coupons = get_posts($args);

        $filename = 'vouchers-' . wp_date('Y-m-d-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // BOM for Excel
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            'Код',
            'Тип скидки',
            'Сумма',
            'Дата создания',
            'Дата истечения',
            'Лимит',
            'Использовано',
            'Статус',
            'Покупатель',
            'Мин. сумма',
            'Партия',
        ], ';');

        foreach ($coupons as $coupon_post) {
            $coupon = new WC_Coupon($coupon_post->ID);
            $usage_count = $coupon->get_usage_count();
            $usage_limit = $coupon->get_usage_limit();
            $expires = $coupon->get_date_expires();

            $status = 'Активен';
            if ($expires && $expires->getTimestamp() <= time()) {
                $status = 'Истёк';
            } elseif ($usage_limit > 0 && $usage_count >= $usage_limit) {
                $status = 'Исчерпан';
            } elseif ($usage_count > 0) {
                $status = 'Использован';
            }
            if ($coupon_post->post_status === 'draft') {
                $status = 'Черновик';
            }

            $used_by = get_post_meta($coupon_post->ID, '_used_by', false);
            $used_by_str = '';
            if (!empty($used_by)) {
                $emails = [];
                foreach ($used_by as $uid) {
                    if (is_numeric($uid)) {
                        $user = get_userdata(intval($uid));
                        $emails[] = $user ? $user->user_email : '#' . $uid;
                    } else {
                        $emails[] = sanitize_email($uid);
                    }
                }
                $used_by_str = implode(', ', $emails);
            }

            $type_labels = [
                'percent'       => 'Процент',
                'fixed_cart'    => 'Фикс. корзина',
                'fixed_product' => 'Фикс. товар',
            ];

            fputcsv($output, [
                $coupon->get_code(),
                $type_labels[$coupon->get_discount_type()] ?? $coupon->get_discount_type(),
                $coupon->get_amount(),
                get_the_date('d.m.Y H:i', $coupon_post->ID),
                $expires ? $expires->date('d.m.Y H:i') : 'Бессрочный',
                $usage_limit ?: 'Без лимита',
                $usage_count,
                $status,
                $used_by_str,
                $coupon->get_minimum_amount() ?: '0',
                get_post_meta($coupon_post->ID, '_voucher_batch_id', true) ?: '-',
            ], ';');
        }

        fclose($output);
        exit;
    }
}
