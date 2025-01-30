<?php

declare(strict_types=1);

namespace WordpressRecaptchaEnterprise;

/*
 *
 * @link              https://github.com/midweste
 * @since             1.0.0
 * @package           Wordpress Recaptcha Enterprise
 *
 * @wordpress-plugin
 * Plugin Name:       Wordpress Recaptcha Enterprise
 * Plugin URI:        https://github.com/midweste/wp-recaptcha-enterprise/
 * Description:       Adds support for Google Recaptcha Enterprise to Wordpress.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Midweste
 * Author URI:        https://github.com/midweste/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://api.github.com/repos/midweste/wp-recaptcha-enterprise/commits/main
 * Text Domain:       wp-recaptcha-enterprise
 * Domain Path:       /languages
 * Requires Plugins:  wp-trait-mu
 */

spl_autoload_register(function ($class) {
    $prefix = 'WordpressRecaptchaEnterprise\\';
    $base_dir = __DIR__ . '/src/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $file = $base_dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// file_exists(__DIR__ . '/vendor/autoload.php') && require_once __DIR__ . '/vendor/autoload.php';

use WPTrait\Plugin;

class WordpressRecaptchaEnterprise extends Plugin
{
    public $main, $settings;

    public function __construct()
    {
        parent::__construct('wp_recaptcha_enterprise', ['main_file' => __FILE__]);
    }

    public function instantiate()
    {
        $this->settings = new Model\Settings($this->plugin, $this);
        $this->main = new Model\Main($this->plugin, $this->settings);
    }

    public function register_activation_hook() {}

    public function register_deactivation_hook() {}

    public static function register_uninstall_hook() {}
}

function wp_recaptcha_enterprise(): WordpressRecaptchaEnterprise
{
    global $wp_recaptcha_enterprise;
    if (!$wp_recaptcha_enterprise instanceof WordpressRecaptchaEnterprise) {
        $GLOBALS['wp_recaptcha_enterprise'] = new WordpressRecaptchaEnterprise();
    }
    return $GLOBALS['wp_recaptcha_enterprise'];
}

call_user_func(function () {
    wp_recaptcha_enterprise();
});
