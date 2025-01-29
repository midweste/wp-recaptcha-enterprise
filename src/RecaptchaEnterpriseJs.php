<?php

declare(strict_types=1);

namespace WordpressRecaptchaEnterprise;

class RecaptchaEnterpriseJs
{
    const JS_ENTERPRISE_BASE_URL = 'https://www.google.com/recaptcha/enterprise.js';
    const JS_ASSESSMENT_BASE_URL = 'https://recaptchaenterprise.googleapis.com/v1/projects';

    protected $site_key;
    protected $api_key;
    protected $project_id;
    protected $risk;
    protected $input_id_prefix = 'recaptcha_enterprise_';
    protected $input_id;

    public function __construct(string $site_key, string $api_key, string $project_id, float $risk = 0.5)
    {
        $this->site_key = $site_key;
        $this->api_key = $api_key;
        $this->project_id = $project_id;
        $this->setRisk($risk);
        $this->setInputId($this->getInputIdPrefix() . uniqid());
    }

    public function setInputId(string $inputId): self
    {
        $this->input_id = $inputId;
        return $this;
    }

    public function getInputId(): string
    {
        return $this->input_id;
    }

    public function setInputIdPrefix(string $prefix): self
    {
        $this->input_id_prefix = $prefix;
        return $this;
    }

    public function getInputIdPrefix(): string
    {
        return $this->input_id_prefix;
    }

    public function setRisk(float $risk): self
    {
        if ($risk < 0 || $risk > 1) {
            throw new \InvalidArgumentException('Risk score must be between 0 and 1.');
        }
        $this->risk = $risk;
        return $this;
    }

    public function getRisk(): float
    {
        return $this->risk;
    }

    public function getScriptSrc(): string
    {
        return sprintf('%s?render=%s', self::JS_ENTERPRISE_BASE_URL, $this->site_key);
    }

    public function getFormHiddenInput(): string
    {
        $input_id = $this->getInputId();
        return sprintf('<input type="hidden" name="%s" id="%s" value="">', $input_id, $input_id);
    }

    // Add the reCAPTCHA token to the form
    public function getScriptInit(): string
    {
        $input_id = $this->getInputId();
        $script = <<<HTML
        <script>
            (function() {
                var grecaptchaSiteKey = "{$this->site_key}";
                var grecaptchaHiddenId = "{$input_id}";
                grecaptcha.enterprise.ready(function () {
                    function refreshToken() {
                        grecaptcha.enterprise.execute(grecaptchaSiteKey, { action: "submit" })
                        .then(function (token) {
                            document.getElementById(grecaptchaHiddenId).value = token;
                        })
                        .catch(function (error) {
                            console.error("Error getting reCAPTCHA token:", error);
                        });
                    }

                    refreshToken();
                    setInterval(function () {
                        refreshToken();
                    }, 60000);
                });
            })();
        </script>
        HTML;
        return $script;
    }

    public function getFormRecaptcha(): string
    {
        return $this->getFormHiddenInput() . $this->getScriptInit();
    }

    public function parseFormDataForToken(array $data): string
    {
        foreach ($data as $key => $value) {
            if (strpos($key, $this->getInputIdPrefix()) === 0) {
                return $value;
            }
        }
        return '';
    }

    protected function error(string $message): void
    {
        error_log(sprintf('Recaptcha Enterprise: %s', $message));
    }

    protected function getUserIpAddress(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
            return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            return $_SERVER['HTTP_FORWARDED'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return 'UNKNOWN';
    }

    // Verify the token server-side
    public function verifyToken($token)
    {
        try {
            $url = sprintf('%s/%s/assessments?key=%s', self::JS_ASSESSMENT_BASE_URL, $this->project_id, $this->api_key);

            $payload = [
                'event' => [
                    'token' => $token,
                    'siteKey' => $this->site_key,
                    'userIpAddress' => $this->getUserIpAddress(),
                    'userAgent' => $_SERVER['HTTP_USER_AGENT'],
                    'expectedAction' => 'submit',
                ],
            ];

            $response = wp_remote_post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($payload),
            ]);

            if (is_wp_error($response)) {
                throw new \RuntimeException('There was an error. Try again later.');
            }

            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            // Check invalid response
            if (!is_array($response_body)) {
                throw new \RuntimeException('There was an error validating the form. Try again later.');
            }

            // Check invalid API key, site key, or project ID
            if (!empty($response_body['error']['message'])) {
                throw new \InvalidArgumentException('Form is unavailable at this time.');
            }

            // Check valid token
            if (!isset($response_body['tokenProperties']) || $response_body['tokenProperties']['valid'] !== true) {
                $reason = $response_body['tokenProperties']['invalidReason'] ?? 'invalid';
                throw new \InvalidArgumentException(sprintf('Refresh the form and try again (%s).', $reason));
            }

            // Check token action
            if (!isset($response_body['tokenProperties']['action']) || $response_body['tokenProperties']['action'] !== 'submit') {
                throw new \InvalidArgumentException('Something went wrong (action).');
            }

            // Assess risk score
            if (!isset($response_body['riskAnalysis']['score']) || $response_body['riskAnalysis']['score'] <= $this->risk) {
                throw new \RuntimeException(sprintf('Verification failed (%s).', $response_body['riskAnalysis']['score']));
            }

            // Pass only positive results
            if ($response_body['riskAnalysis']['score'] >= $this->risk) {
                return true;
            }

            throw new \RuntimeException('Unknown condition.');
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return $e->getMessage();
        }
    }
}
