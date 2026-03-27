<?php
/**
 * Plugin Name: WC Voucher Manager
 * Plugin URI: https://russ-project.ru
 * Description: Менеджер ваучеров для WooCommerce — замена стандартной системы купонов. Массовое создание, отслеживание использования, красивый интерфейс.
 * Version: 1.1.0
 * Author: Nemirov
 * Author URI: https://russ-project.ru
 * Text Domain: wc-voucher-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) exit;

define('WC_VOUCHER_VERSION', '1.1.0');
define('WC_VOUCHER_PATH', plugin_dir_path(__FILE__));
define('WC_VOUCHER_URL', plugin_dir_url(__FILE__));

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
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'wc_missing_notice']);
            return;
        }

        $this->load_includes();
        $this->init_classes();
    }

    public function declare_hpos_compat() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    private function load_includes() {
        require_once WC_VOUCHER_PATH . 'includes/class-voucher-renamer.php';
        require_once WC_VOUCHER_PATH . 'includes/class-voucher-admin.php';
        require_once WC_VOUCHER_PATH . 'includes/class-voucher-generator.php';
        require_once WC_VOUCHER_PATH . 'includes/class-voucher-tracker.php';
        require_once WC_VOUCHER_PATH . 'includes/class-voucher-frontend.php';
    }

    private function init_classes() {
        new WC_Voucher_Renamer();
        new WC_Voucher_Admin();
        new WC_Voucher_Generator();
        new WC_Voucher_Tracker();
        new WC_Voucher_Frontend();
    }

    public function wc_missing_notice() {
        echo '<div class="error"><p><strong>WC Voucher Manager</strong> требует установленный и активированный WooCommerce.</p></div>';
    }
}

WC_Voucher_Manager::instance();
