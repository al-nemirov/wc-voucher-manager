<?php
if (!defined('ABSPATH')) exit;

class WC_Voucher_Renamer {

    private $all_replacements;

    public function __construct() {
        // Build replacements dynamically from settings
        add_action('init', [$this, 'build_replacements'], 5);

        add_filter('gettext', [$this, 'replace_text'], 20, 3);
        add_filter('gettext_with_context', [$this, 'replace_text_ctx'], 20, 4);
        add_filter('ngettext', [$this, 'replace_ntext'], 20, 5);
        add_filter('ngettext_with_context', [$this, 'replace_ntext_ctx'], 20, 6);

        add_filter('woocommerce_coupon_discount_amount_html', [$this, 'replace_in_html'], 20);
        add_filter('woocommerce_coupon_message', [$this, 'replace_in_html'], 20);
        add_filter('woocommerce_coupon_error', [$this, 'replace_in_html'], 20);
        add_filter('woocommerce_cart_totals_coupon_label', [$this, 'replace_in_html'], 20);
        add_filter('woocommerce_checkout_coupon_message', [$this, 'replace_in_html'], 20);

        add_action('admin_menu', [$this, 'modify_admin_menu'], 99);
        add_filter('register_post_type_args', [$this, 'modify_coupon_post_type'], 20, 2);
    }

    public function build_replacements() {
        $singular  = WC_Voucher_Settings::get('name_singular');
        $plural    = WC_Voucher_Settings::get('name_plural');
        $code_lbl  = WC_Voucher_Settings::get('name_code_label');
        $apply_btn = WC_Voucher_Settings::get('name_apply_btn');

        $singular_lc = mb_strtolower($singular);
        $plural_lc   = mb_strtolower($plural);

        // English source → user-defined target
        $this->all_replacements = [
            'Coupon code'             => $code_lbl,
            'coupon code'             => mb_strtolower($code_lbl),
            'Apply coupon'            => $apply_btn,
            'apply coupon'            => mb_strtolower($apply_btn),
            'Coupon has been applied' => sprintf(
                /* translators: already translated via settings */
                __('%s has been applied', 'wc-voucher-manager'),
                $singular
            ),
            'Coupon does not exist'   => sprintf(__('%s does not exist', 'wc-voucher-manager'), $singular),
            'Invalid coupon'          => sprintf(__('Invalid %s', 'wc-voucher-manager'), $singular_lc),
            'Add coupon'              => sprintf(__('Add %s', 'wc-voucher-manager'), $singular_lc),
            'Remove coupon'           => sprintf(__('Remove %s', 'wc-voucher-manager'), $singular_lc),
            'Coupons'                 => $plural,
            'coupons'                 => $plural_lc,
            'Coupon'                  => $singular,
            'coupon'                  => $singular_lc,
            // Russian source → user-defined target
            'Применить купон'         => $apply_btn,
            'Код купона'              => $code_lbl,
            'код купона'              => mb_strtolower($code_lbl),
            'Купоны'                  => $plural,
            'купоны'                  => $plural_lc,
            'купонов'                 => $plural_lc,
            'купону'                  => $singular_lc,
            'купоном'                 => $singular_lc,
            'купоне'                  => $singular_lc,
            'купона'                  => $singular_lc,
            'Купон'                   => $singular,
            'купон'                   => $singular_lc,
        ];
    }

    public function replace_text($translated, $text, $domain) {
        if (!$this->all_replacements) return $translated;
        if (stripos($translated, 'coupon') === false && stripos($translated, 'купон') === false) {
            return $translated;
        }
        return strtr($translated, $this->all_replacements);
    }

    public function replace_text_ctx($translated, $text, $context, $domain) {
        return $this->replace_text($translated, $text, $domain);
    }

    public function replace_ntext($translated, $single, $plural, $number, $domain) {
        return $this->replace_text($translated, $single, $domain);
    }

    public function replace_ntext_ctx($translated, $single, $plural, $number, $context, $domain) {
        return $this->replace_text($translated, $single, $domain);
    }

    public function replace_in_html($html) {
        if (!is_string($html) || !$this->all_replacements) return $html;
        return strtr($html, $this->all_replacements);
    }

    public function modify_admin_menu() {
        remove_submenu_page('woocommerce', 'edit.php?post_type=shop_coupon');
        remove_menu_page('edit.php?post_type=shop_coupon');
    }

    public function modify_coupon_post_type($args, $post_type) {
        if ($post_type !== 'shop_coupon') return $args;

        $singular = WC_Voucher_Settings::get('name_singular');
        $plural   = WC_Voucher_Settings::get('name_plural');

        $labels = [
            'name'               => $plural,
            'singular_name'      => $singular,
            /* translators: %s: singular voucher name */
            'add_new'            => sprintf(__('Add %s', 'wc-voucher-manager'), $singular),
            'add_new_item'       => sprintf(__('Add new %s', 'wc-voucher-manager'), mb_strtolower($singular)),
            'edit_item'          => sprintf(__('Edit %s', 'wc-voucher-manager'), mb_strtolower($singular)),
            'new_item'           => sprintf(__('New %s', 'wc-voucher-manager'), mb_strtolower($singular)),
            'view_item'          => sprintf(__('View %s', 'wc-voucher-manager'), mb_strtolower($singular)),
            'search_items'       => sprintf(__('Search %s', 'wc-voucher-manager'), mb_strtolower($plural)),
            'not_found'          => sprintf(__('%s not found', 'wc-voucher-manager'), $plural),
            'not_found_in_trash' => sprintf(__('No %s found in trash', 'wc-voucher-manager'), mb_strtolower($plural)),
            'menu_name'          => $plural,
        ];

        if (isset($args['labels']) && is_array($args['labels'])) {
            $args['labels'] = array_merge($args['labels'], $labels);
        } else {
            $args['labels'] = $labels;
        }

        $args['show_in_menu'] = false;
        return $args;
    }
}
