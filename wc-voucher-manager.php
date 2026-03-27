<?php
/**
 * Plugin Name: WC Voucher Manager
 * Plugin URI: https://github.com/al-nemirov/wc-voucher-manager
 * Description: WooCommerce Voucher Manager — replaces coupons with vouchers. Bulk generation, usage tracking, beautiful UI, i18n ready.
 * Version: 1.2.0
 * Author: Alexander Nemirov
 * Author URI: https://russ-project.ru
 * Text Domain: wc-voucher-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 * License: MIT
 */

if (!defined('ABSPATH')) exit;

define('WC_VOUCHER_VERSION', '1.2.0');
define('WC_VOUCHER_PATH', plugin_dir_path(__FILE__));
define('WC_VOUCHER_URL', plugin_dir_url(__FILE__));
define('WC_VOUCHER_BASENAME', plugin_basename(__FILE__));

class WC_Voucher_Manager {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('before_woocommerce_init', [$this, 'declare_hpos_compat']);
    }

    public function init() {
        $this->load_textdomain();

        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'wc_missing_notice']);
            return;
        }

        $this->load_includes();
        $this->init_classes();

        // Settings link on plugins page
        add_filter('plugin_action_links_' . WC_VOUCHER_BASENAME, [$this, 'plugin_action_links']);
    }

    private function load_textdomain() {
        load_plugin_textdomain('wc-voucher-manager', false, dirname(WC_VOUCHER_BASENAME) . '/languages');
    }

    public function declare_hpos_compat() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    private function load_includes() {
        require_once WC_VOUCHER_PATH . 'includes/class-voucher-settings.php';
        require_once WC_VOUCHER_PATH . 'includes/class-voucher-renamer.php';
        require_once WC_VOUCHER_PATH . 'includes/class-voucher-admin.php';
        require_once WC_VOUCHER_PATH . 'includes/class-voucher-generator.php';
        require_once WC_VOUCHER_PATH . 'includes/class-voucher-tracker.php';
        require_once WC_VOUCHER_PATH . 'includes/class-voucher-frontend.php';
    }

    private function init_classes() {
        new WC_Voucher_Settings();
        new WC_Voucher_Renamer();
        new WC_Voucher_Admin();
        new WC_Voucher_Generator();
        new WC_Voucher_Tracker();
        new WC_Voucher_Frontend();
    }

    public function plugin_action_links($links) {
        $settings_url = admin_url('admin.php?page=wc-vouchers&tab=settings');
        array_unshift($links, sprintf(
            '<a href="%s">%s</a>',
            esc_url($settings_url),
            esc_html__('Settings', 'wc-voucher-manager')
        ));
        return $links;
    }

    public function wc_missing_notice() {
        printf(
            '<div class="error"><p><strong>WC Voucher Manager</strong> %s</p></div>',
            esc_html__('requires WooCommerce to be installed and activated.', 'wc-voucher-manager')
        );
    }
}

WC_Voucher_Manager::instance();
