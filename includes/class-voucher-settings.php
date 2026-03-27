<?php
if (!defined('ABSPATH')) exit;

class WC_Voucher_Settings {

    const OPTION_KEY = 'wc_voucher_settings';

    private static $defaults = [
        'name_singular'    => '',
        'name_plural'      => '',
        'name_code_label'  => '',
        'name_apply_btn'   => '',
        'frontend_message' => '',
        'thankyou_message' => '',
        'toast_enabled'    => 'yes',
        'confetti_enabled' => 'yes',
    ];

    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
    }

    public static function get($key) {
        $opts = get_option(self::OPTION_KEY, []);
        $val = $opts[$key] ?? '';
        if ($val === '') {
            return self::get_default($key);
        }
        return $val;
    }

    public static function get_default($key) {
        $map = [
            'name_singular'    => __('Coupon', 'wc-voucher-manager'),
            'name_plural'      => __('Coupons', 'wc-voucher-manager'),
            'name_code_label'  => __('Coupon code', 'wc-voucher-manager'),
            'name_apply_btn'   => __('Apply coupon', 'wc-voucher-manager'),
            'frontend_message' => __('Happy shopping!', 'wc-voucher-manager'),
            'thankyou_message' => __('Thank you for your purchase! You saved %s with a coupon. See you again!', 'wc-voucher-manager'),
            'toast_enabled'    => 'yes',
            'confetti_enabled' => 'yes',
        ];
        return $map[$key] ?? '';
    }

    public static function get_all_defaults() {
        $result = [];
        foreach (array_keys(self::$defaults) as $key) {
            $result[$key] = self::get_default($key);
        }
        return $result;
    }

    /**
     * Check if user has set a custom name (different from WooCommerce defaults)
     */
    public static function has_custom_name() {
        $opts = get_option(self::OPTION_KEY, []);
        return !empty($opts['name_singular']) || !empty($opts['name_plural']);
    }

    public function register_settings() {
        register_setting('wc_voucher_settings_group', self::OPTION_KEY, [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    public function sanitize_settings($input) {
        $clean = [];
        foreach (self::$defaults as $key => $default) {
            if (in_array($key, ['toast_enabled', 'confetti_enabled'], true)) {
                $clean[$key] = isset($input[$key]) ? 'yes' : 'no';
            } else {
                $clean[$key] = sanitize_text_field($input[$key] ?? '');
            }
        }
        return $clean;
    }

    public static function render_settings_tab() {
        $opts = get_option(self::OPTION_KEY, []);
        $defaults = self::get_all_defaults();

        if (isset($_POST['wc_voucher_settings_nonce']) && wp_verify_nonce($_POST['wc_voucher_settings_nonce'], 'wc_voucher_save_settings')) {
            $instance = new self();
            $clean = $instance->sanitize_settings($_POST[self::OPTION_KEY] ?? []);
            update_option(self::OPTION_KEY, $clean);
            $opts = $clean;
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'wc-voucher-manager') . '</p></div>';
        }

        ?>
        <div class="voucher-settings-form">
            <form method="post">
                <?php wp_nonce_field('wc_voucher_save_settings', 'wc_voucher_settings_nonce'); ?>

                <div class="voucher-form-grid">
                    <div class="voucher-form-section">
                        <h3 class="voucher-section-title"><?php esc_html_e('Naming', 'wc-voucher-manager'); ?></h3>
                        <p class="voucher-form-desc"><?php esc_html_e('Customize how coupons are called throughout the site. Leave empty to use defaults.', 'wc-voucher-manager'); ?></p>

                        <div class="voucher-field">
                            <label for="vs_singular"><?php esc_html_e('Singular name', 'wc-voucher-manager'); ?></label>
                            <div class="voucher-field-input">
                                <input type="text" id="vs_singular" name="<?php echo self::OPTION_KEY; ?>[name_singular]"
                                       value="<?php echo esc_attr($opts['name_singular'] ?? ''); ?>"
                                       placeholder="<?php echo esc_attr($defaults['name_singular']); ?>" />
                                <span class="voucher-field-hint"><?php printf(esc_html__('Default: %s', 'wc-voucher-manager'), $defaults['name_singular']); ?></span>
                            </div>
                        </div>

                        <div class="voucher-field">
                            <label for="vs_plural"><?php esc_html_e('Plural name', 'wc-voucher-manager'); ?></label>
                            <div class="voucher-field-input">
                                <input type="text" id="vs_plural" name="<?php echo self::OPTION_KEY; ?>[name_plural]"
                                       value="<?php echo esc_attr($opts['name_plural'] ?? ''); ?>"
                                       placeholder="<?php echo esc_attr($defaults['name_plural']); ?>" />
                                <span class="voucher-field-hint"><?php printf(esc_html__('Default: %s', 'wc-voucher-manager'), $defaults['name_plural']); ?></span>
                            </div>
                        </div>

                        <div class="voucher-field">
                            <label for="vs_code_label"><?php esc_html_e('Code input label', 'wc-voucher-manager'); ?></label>
                            <div class="voucher-field-input">
                                <input type="text" id="vs_code_label" name="<?php echo self::OPTION_KEY; ?>[name_code_label]"
                                       value="<?php echo esc_attr($opts['name_code_label'] ?? ''); ?>"
                                       placeholder="<?php echo esc_attr($defaults['name_code_label']); ?>" />
                                <span class="voucher-field-hint"><?php esc_html_e('Placeholder text in the cart/checkout code input', 'wc-voucher-manager'); ?></span>
                            </div>
                        </div>

                        <div class="voucher-field">
                            <label for="vs_apply_btn"><?php esc_html_e('Apply button text', 'wc-voucher-manager'); ?></label>
                            <div class="voucher-field-input">
                                <input type="text" id="vs_apply_btn" name="<?php echo self::OPTION_KEY; ?>[name_apply_btn]"
                                       value="<?php echo esc_attr($opts['name_apply_btn'] ?? ''); ?>"
                                       placeholder="<?php echo esc_attr($defaults['name_apply_btn']); ?>" />
                            </div>
                        </div>
                    </div>

                    <div class="voucher-form-section">
                        <h3 class="voucher-section-title"><?php esc_html_e('Frontend Messages', 'wc-voucher-manager'); ?></h3>

                        <div class="voucher-field">
                            <label for="vs_frontend_msg"><?php esc_html_e('Success message (toast)', 'wc-voucher-manager'); ?></label>
                            <div class="voucher-field-input">
                                <input type="text" id="vs_frontend_msg" name="<?php echo self::OPTION_KEY; ?>[frontend_message]"
                                       value="<?php echo esc_attr($opts['frontend_message'] ?? ''); ?>"
                                       placeholder="<?php echo esc_attr($defaults['frontend_message']); ?>" />
                                <span class="voucher-field-hint"><?php esc_html_e('Shown in the toast notification when coupon is applied', 'wc-voucher-manager'); ?></span>
                            </div>
                        </div>

                        <div class="voucher-field">
                            <label for="vs_thankyou_msg"><?php esc_html_e('Thank you page message', 'wc-voucher-manager'); ?></label>
                            <div class="voucher-field-input">
                                <input type="text" id="vs_thankyou_msg" name="<?php echo self::OPTION_KEY; ?>[thankyou_message]"
                                       value="<?php echo esc_attr($opts['thankyou_message'] ?? ''); ?>"
                                       placeholder="<?php echo esc_attr($defaults['thankyou_message']); ?>" />
                                <span class="voucher-field-hint"><?php esc_html_e('Use %s for the discount amount', 'wc-voucher-manager'); ?></span>
                            </div>
                        </div>

                        <h3 class="voucher-section-title" style="margin-top:24px"><?php esc_html_e('Effects', 'wc-voucher-manager'); ?></h3>

                        <div class="voucher-field">
                            <label class="voucher-checkbox-label">
                                <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[toast_enabled]" value="yes"
                                    <?php checked($opts['toast_enabled'] ?? 'yes', 'yes'); ?> />
                                <span><?php esc_html_e('Show toast notification when coupon is applied', 'wc-voucher-manager'); ?></span>
                            </label>
                        </div>

                        <div class="voucher-field">
                            <label class="voucher-checkbox-label">
                                <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[confetti_enabled]" value="yes"
                                    <?php checked($opts['confetti_enabled'] ?? 'yes', 'yes'); ?> />
                                <span><?php esc_html_e('Show confetti animation', 'wc-voucher-manager'); ?></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="voucher-generate-actions">
                    <?php submit_button(esc_html__('Save Settings', 'wc-voucher-manager'), 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
        <?php
    }
}
