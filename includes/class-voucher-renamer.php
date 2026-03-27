<?php
if (!defined('ABSPATH')) exit;

class WC_Voucher_Renamer {

    private $replacements_en = [
        // Longer phrases first
        'Coupon code'            => 'Код ваучера',
        'coupon code'            => 'код ваучера',
        'Apply coupon'           => 'Применить ваучер',
        'apply coupon'           => 'применить ваучер',
        'Coupon has been applied' => 'Ваучер применён',
        'Coupon does not exist'  => 'Ваучер не существует',
        'Invalid coupon'         => 'Недействительный ваучер',
        'Add coupon'             => 'Добавить ваучер',
        'Remove coupon'          => 'Удалить ваучер',
        'Coupons'                => 'Ваучеры',
        'coupons'                => 'ваучеры',
        'Coupon'                 => 'Ваучер',
        'coupon'                 => 'ваучер',
    ];

    private $replacements_ru = [
        'Применить купон' => 'Применить ваучер',
        'Код купона'      => 'Код ваучера',
        'код купона'      => 'код ваучера',
        'Купоны'          => 'Ваучеры',
        'купоны'          => 'ваучеры',
        'купонов'         => 'ваучеров',
        'купону'          => 'ваучеру',
        'купоном'         => 'ваучером',
        'купоне'          => 'ваучере',
        'купона'          => 'ваучера',
        'Купон'           => 'Ваучер',
        'купон'           => 'ваучер',
    ];

    private $all_replacements;

    public function __construct() {
        $this->all_replacements = array_merge($this->replacements_en, $this->replacements_ru);

        // Text translation filters — catch ALL domains (WC blocks, themes, etc.)
        add_filter('gettext', [$this, 'replace_text'], 20, 3);
        add_filter('gettext_with_context', [$this, 'replace_text_with_context'], 20, 4);
        add_filter('ngettext', [$this, 'replace_ntext'], 20, 5);
        add_filter('ngettext_with_context', [$this, 'replace_ntext_with_context'], 20, 6);

        // WooCommerce-specific filters
        add_filter('woocommerce_coupon_discount_amount_html', [$this, 'replace_in_html'], 20);
        add_filter('woocommerce_coupon_message', [$this, 'replace_in_html'], 20);
        add_filter('woocommerce_coupon_error', [$this, 'replace_in_html'], 20);
        add_filter('woocommerce_cart_totals_coupon_label', [$this, 'replace_in_html'], 20);
        add_filter('woocommerce_checkout_coupon_message', [$this, 'replace_in_html'], 20);

        // Admin menu — hide standard, keep only our custom page
        add_action('admin_menu', [$this, 'modify_admin_menu'], 99);
        add_filter('register_post_type_args', [$this, 'modify_coupon_post_type'], 20, 2);
    }

    public function replace_text($translated, $text, $domain) {
        // Fast check — skip if no coupon/купон in the string
        if (stripos($translated, 'coupon') === false && stripos($translated, 'купон') === false) {
            return $translated;
        }
        return strtr($translated, $this->all_replacements);
    }

    public function replace_text_with_context($translated, $text, $context, $domain) {
        if (stripos($translated, 'coupon') === false && stripos($translated, 'купон') === false) {
            return $translated;
        }
        return strtr($translated, $this->all_replacements);
    }

    public function replace_ntext($translated, $single, $plural, $number, $domain) {
        if (stripos($translated, 'coupon') === false && stripos($translated, 'купон') === false) {
            return $translated;
        }
        return strtr($translated, $this->all_replacements);
    }

    public function replace_ntext_with_context($translated, $single, $plural, $number, $context, $domain) {
        if (stripos($translated, 'coupon') === false && stripos($translated, 'купон') === false) {
            return $translated;
        }
        return strtr($translated, $this->all_replacements);
    }

    public function replace_in_html($html) {
        if (!is_string($html)) return $html;
        return strtr($html, $this->all_replacements);
    }

    public function modify_admin_menu() {
        // Remove ALL standard WooCommerce coupon menu entries
        remove_submenu_page('woocommerce', 'edit.php?post_type=shop_coupon');
        remove_menu_page('edit.php?post_type=shop_coupon');
    }

    public function modify_coupon_post_type($args, $post_type) {
        if ($post_type !== 'shop_coupon') {
            return $args;
        }

        // Relabel for edit screens (post.php?post=X&action=edit)
        $labels = [
            'name'               => 'Ваучеры',
            'singular_name'      => 'Ваучер',
            'add_new'            => 'Добавить ваучер',
            'add_new_item'       => 'Добавить новый ваучер',
            'edit_item'          => 'Редактировать ваучер',
            'new_item'           => 'Новый ваучер',
            'view_item'          => 'Просмотреть ваучер',
            'search_items'       => 'Найти ваучер',
            'not_found'          => 'Ваучеры не найдены',
            'not_found_in_trash' => 'В корзине ваучеров не найдено',
            'menu_name'          => 'Ваучеры',
        ];

        if (isset($args['labels']) && is_array($args['labels'])) {
            $args['labels'] = array_merge($args['labels'], $labels);
        } else {
            $args['labels'] = $labels;
        }

        // Do NOT add to any menu — our custom page handles this
        $args['show_in_menu'] = false;

        return $args;
    }
}
