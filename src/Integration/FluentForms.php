<?php

declare(strict_types=1);

namespace WordpressRecaptchaEnterprise\Integration;

use WordpressRecaptchaEnterprise\RecaptchaEnterpriseJs;

class FluentForms
{
    public function __construct(RecaptchaEnterpriseJs $recaptcha)
    {
        // Verify the recaptcha token before inserting the form submission
        add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) use ($recaptcha) {
            try {
                $recaptcha_token = $recaptcha->parseFormDataForToken($data) ?? '';
                if (empty($recaptcha_token)) {
                    error_log('Recaptcha Enterprise: Missing recaptcha token');
                    wp_send_json_error(['message' => 'Something went wrong.']);
                    return;
                }

                $result = $recaptcha->verifyToken($recaptcha_token);
                if ($result !== true) {
                    wp_send_json_error(['message' => $result]);
                    return;
                }
            } catch (\Exception $e) {
                error_log('Recaptcha Enterprise: ' . $e->getMessage());
            }
        }, 10, 3);

        // Add the token input field and script to the form
        add_filter('fluentform/render_item_submit_button', function ($form) use ($recaptcha) {
            echo $recaptcha->getFormRecaptcha();
        });
    }
}
