<?php

declare(strict_types=1);

namespace WordpressRecaptchaEnterprise\Model;

use WPTrait\Model;
use WPTrait\Hook\AdminMenu;
use WPTrait\Hook\Notice;
use WPTrait\Hook\AdminSettings;

class Settings extends Model
{
    use AdminSettings;
    use AdminMenu;
    use Notice;

    protected $app;

    public function __construct($plugin, $app)
    {
        $this->app = $app;
        parent::__construct($plugin);
    }

    public function settings_fields(): array
    {
        $fields =
            [
                $this->setting_field('site_key', 'Site Key', [
                    'description' => 'Enter your Site Key. You can find this value in the <a href="https://console.cloud.google.com/security/recaptcha" target="_blank">Google Cloud reCAPTCHA Enterprise</a> page.',
                    'required' => true
                ]),
                $this->setting_field('api_key', 'API Key', [
                    'description' => 'Enter your API Key. You can find this value in the <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud API Credentials</a> page.',
                    'required' => true
                ]),
                $this->setting_field('project_id', 'Project ID', [
                    'description' => 'Enter your Google Cloud Project ID. You can find this value in the <a href="https://console.cloud.google.com/projectselector2/home/dashboard" target="_blank">Google Cloud Console</a>.',
                    'required' => true
                ]),
                $this->setting_field('risk', 'Risk Score Threshold', [
                    'type' => 'number',
                    'sanitize' => function ($value) {
                        if (empty($value)) {
                            return 0.5;
                        }
                        $value = (float) $value;
                        $valid = filter_var($value, FILTER_VALIDATE_FLOAT);
                        return $valid === false ? 0.5 : $value;
                    },
                    'validation' => 'is_float',
                    'attributes' => ['step' => '0.1', 'min' => '0', 'max' => '1'],
                    'description' => 'The score 1.0 indicates that the interaction poses low risk and is very likely legitimate, whereas 0.0 indicates that the interaction poses high risk and might be fraudulent.',
                    'default' => 0.5,
                    'required' => true
                ]),
                $this->setting_field('integrations', 'Integrations', [
                    'type' => 'checkbox_group',
                    'sanitize' => function ($values) {
                        return array_map('sanitize_text_field', (array) $values);
                    },
                    'validation' => function ($values) {
                        return is_array($values) && array_reduce($values, function ($carry, $item) {
                            return $carry && is_string($item);
                        }, true);
                    },
                    'enum' => [
                        'FluentForms' => 'Fluent Forms',
                        // 'ContactForm7' => 'Contact Form 7',
                        // 'GravityForms' => 'Gravity Forms',
                    ],
                    'description' => 'Enable reCAPTCHA Enterprise integration with the selected forms.',
                ]),
            ];
        return $fields;
    }

    public function admin_menu_add(): void
    {
        add_options_page('Recaptcha Enterprise', 'Recaptcha Enterprise', 'manage_options', $this->plugin->slug, function () {
            echo $this->settings_render('Recaptcha Enterprise');
        });
    }

    public function admin_notices(): void
    {
        $settings = $this->settings();
        $required = $this->settings_required();

        $missing_keys = array_diff_key($required, $settings);
        $empty_keys = array_filter($settings, function ($value) {
            return empty($value);
        });

        if (!empty($missing_keys) || !empty($empty_keys)) {
            $text = __('Please configure Recaptcha Enterprise settings.', $this->plugin->textDomain);

            $config_url = admin_url('options-general.php?page=' . $this->plugin->slug);
            $link = '<a href="' . esc_url($config_url) . '">' . $text . '</a>';

            echo $this->add_alert($link, 'warning', true);
        }
    }
}
