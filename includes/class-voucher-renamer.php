<?php
if (!defined('ABSPATH')) exit;

class WC_Voucher_Renamer {

    private $all_replacements = null;
    private $has_custom_name = false;

    public function __construct() {
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

        // Kill ALL standard coupon menu entries — priority 9999 to run after everything
        add_action('admin_menu', [$this, 'kill_all_coupon_menus'], 9999);

        // Relabel post type for edit screens
        add_filter('register_post_type_args', [$this, 'modify_coupon_post_type'], 20, 2);
    }

    public function build_replacements() {
        $this->has_custom_name = WC_Voucher_Settings::has_custom_name();

        // Only build replacements if user set a custom name
        if (!$this->has_custom_name) {
            $this->all_replacements = null;
            return;
        }

        $singular  = WC_Voucher_Settings::get('name_singular');
        $plural    = WC_Voucher_Settings::get('name_plural');
        $code_lbl  = WC_Voucher_Settings::get('name_code_label');
        $apply_btn = WC_Voucher_Settings::get('name_apply_btn');

        $singular_lc = mb_strtolower($singular);
        $plural_lc   = mb_strtolower($plural);

        $this->all_replacements = [
            // English → custom
            'Coupon code'             => $code_lbl,
            'coupon code'             => mb_strtolower($code_lbl),
            'Apply coupon'            => $apply_btn,
            'apply coupon'            => mb_strtolower($apply_btn),
            'Coupon has been applied' => sprintf(__('%s has been applied', 'wc-voucher-manager'), $singular),
            'Coupon does not exist'   => sprintf(__('%s does not exist', 'wc-voucher-manager'), $singular),
            'Invalid coupon'          => sprintf(__('Invalid %s', 'wc-voucher-manager'), $singular_lc),
            'Add coupon'              => sprintf(__('Add %s', 'wc-voucher-manager'), $singular_lc),
            'Remove coupon'           => sprintf(__('Remove %s', 'wc-voucher-manager'), $singular_lc),
            'Coupons'                 => $plural,
            'coupons'                 => $plural_lc,
            'Coupon'                  => $singular,
            'coupon'                  => $singular_lc,
            // Russian → custom
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
        if (stripos($translated, 'coupon') === false && stripos($translated, 'купон') === false) return $translated;
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

    /**
     * Remove ALL standard coupon menu entries from everywhere.
     * Runs at priority 9999 to catch anything WooCommerce adds.
     */
    public function kill_all_coupon_menus() {
        global $submenu;

        // Remove from WooCommerce menu
        remove_submenu_page('woocommerce', 'edit.php?post_type=shop_coupon');

        // Remove from Marketing menu (WC 4.0+)
        remove_submenu_page('woocommerce-marketing', 'edit.php?post_type=shop_coupon');

        // Remove top-level if exists
        remove_menu_page('edit.php?post_type=shop_coupon');

        // Nuclear option: scan ALL submenus and remove any pointing to shop_coupon
        if (is_array($submenu)) {
            foreach ($submenu as $parent => &$items) {
                foreach ($items as $key => $item) {
                    if (isset($item[2]) && $item[2] === 'edit.php?post_type=shop_coupon') {
                        unset($items[$key]);
                    }
                }
            }
        }
    }

    public function modify_coupon_post_type($args, $post_type) {
        if ($post_type !== 'shop_coupon') return $args;

        $singular = WC_Voucher_Settings::get('name_singular');
        $plural   = WC_Voucher_Settings::get('name_plural');
        $singular_lc = mb_strtolower($singular);
        $plural_lc   = mb_strtolower($plural);

        $labels = [
            'name'               => $plural,
            'singular_name'      => $singular,
            'add_new'            => sprintf(__('Add %s', 'wc-voucher-manager'), $singular_lc),
            'add_new_item'       => sprintf(__('Add new %s', 'wc-voucher-manager'), $singular_lc),
            'edit_item'          => sprintf(__('Edit %s', 'wc-voucher-manager'), $singular_lc),
            'new_item'           => sprintf(__('New %s', 'wc-voucher-manager'), $singular_lc),
            'view_item'          => sprintf(__('View %s', 'wc-voucher-manager'), $singular_lc),
            'search_items'       => sprintf(__('Search %s', 'wc-voucher-manager'), $plural_lc),
            'not_found'          => sprintf(__('%s not found', 'wc-voucher-manager'), $plural),
            'not_found_in_trash' => sprintf(__('No %s found in trash', 'wc-voucher-manager'), $plural_lc),
            'menu_name'          => $plural,
        ];

        if (isset($args['labels']) && is_array($args['labels'])) {
            $args['labels'] = array_merge($args['labels'], $labels);
        } else {
            $args['labels'] = $labels;
        }

        // Hide from ALL menus — our custom page is the only entry
        $args['show_in_menu'] = false;

        return $args;
    }
}
