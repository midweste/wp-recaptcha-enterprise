<?php

declare(strict_types=1);

namespace WordpressRecaptchaEnterprise\Model;

use WPTrait\Model;
use WordpressRecaptchaEnterprise\RecaptchaEnterpriseJs;

class Main extends Model
{
    protected $settings;
    public array $actions = [
        'wp_loaded' => 'actions_wp_loaded',
    ];

    public function __construct($plugin, $settings)
    {
        if (is_admin()) {
            return;
        }
        $this->settings = $settings;
        parent::__construct($plugin);
    }

    public function actions_wp_loaded(): void
    {
        $settings = $this->settings->settings();
        if (
            empty($settings)
            || empty($settings['integrations'])
            || empty($settings['site_key'])
            || empty($settings['api_key'])
            || empty($settings['project_id'])
        ) {
            return;
        }

        // get enabled integrations
        $enabled = [];
        foreach ($settings['integrations'] as $integration => $status) {
            $integration_class = '\\WordpressRecaptchaEnterprise\\Integration\\' . $integration;
            if (class_exists($integration_class)) {
                $enabled[] = $integration_class;
            }
        }

        if (empty($enabled)) {
            return;
        }

        // load recaptcha
        $recaptcha = new RecaptchaEnterpriseJs($settings['site_key'], $settings['api_key'], $settings['project_id'], 0.5);
        wp_enqueue_script('google-recaptcha-enterprise', $recaptcha->getScriptSrc(), [], $this->plugin->version);
        foreach ($enabled as $integration) {
            $integration = new $integration($recaptcha);
        }
    }
}
