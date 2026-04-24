<?php
namespace DWDOdooWooSync;

if (!defined('ABSPATH')) {
    exit;
}

class TranslationService
{
    protected $logger;
    protected $settings;

    public function __construct(?SyncLogger $logger = null)
    {
        $this->logger = $logger ?: new SyncLogger();
        $this->settings = get_option('odoo_woocommerce_sync_settings', []);
    }

    public function translate(string $text, string $target_lang, string $source_lang = 'auto'): string
    {
        if (empty($text)) {
            return '';
        }

        $cache_key = $this->build_cache_key($text, $target_lang, $source_lang);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return (string) $cached;
        }

        $provider = $this->get_provider();
        try {
            $translated = $this->translate_batch([$text], $target_lang, $source_lang, $provider);
            $output = (string) ($translated[0] ?? $text);
            set_transient($cache_key, $output, 7 * DAY_IN_SECONDS);
            return $output;
        } catch (\Throwable $e) {
            $this->logger->log_warning('product', 'from_odoo', 'translation', $e->getMessage(), ['provider' => $provider]);
            return $text;
        }
    }

    public function translate_product_fields(array $fields, string $target_lang): array
    {
        $source_lang = (string) ($this->settings['source_lang'] ?? 'auto');
        $texts = [
            'name' => (string) ($fields['name'] ?? ''),
            'description' => (string) ($fields['description'] ?? ''),
            'description_sale' => (string) ($fields['description_sale'] ?? ''),
        ];

        $batch_values = array_values($texts);
        $translated = $this->translate_batch($batch_values, $target_lang, $source_lang, $this->get_provider());

        $result = $fields;
        $keys = array_keys($texts);
        foreach ($keys as $index => $key) {
            $result[$key] = (string) ($translated[$index] ?? $texts[$key]);
        }

        return $result;
    }

    public function translate_batch(array $texts, string $target_lang, string $source_lang = 'auto', ?string $provider = null): array
    {
        $provider = $provider ?: $this->get_provider();
        $texts = array_values(array_map(static function ($value) {
            return (string) $value;
        }, $texts));

        if (empty($texts)) {
            return [];
        }

        if ($provider === 'deepl') {
            return $this->translate_with_deepl($texts, $target_lang, $source_lang);
        }

        return $this->translate_with_google($texts, $target_lang, $source_lang);
    }

    public function get_supported_languages(string $provider): array
    {
        $provider = strtolower(sanitize_text_field($provider));
        $cache_key = 'odoo_translation_languages_' . md5($provider);
        $cached = get_transient($cache_key);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        try {
            if ($provider === 'deepl') {
                $api_key = (string) ($this->settings['translation_api_key'] ?? '');
                $endpoint = $this->deepl_endpoint_for_key($api_key) . '/languages';
                $response = wp_remote_get($endpoint, [
                    'timeout' => 30,
                    'headers' => ['Authorization' => 'DeepL-Auth-Key ' . $api_key],
                ]);
                if (is_wp_error($response)) {
                    throw new \RuntimeException($response->get_error_message());
                }
                $data = json_decode(wp_remote_retrieve_body($response), true);
                $langs = [];
                foreach ((array) $data as $row) {
                    if (!empty($row['language'])) {
                        $langs[] = (string) $row['language'];
                    }
                }
                set_transient($cache_key, $langs, DAY_IN_SECONDS);
                return $langs;
            }

            // Google: lista base para uso no painel.
            $langs = ['pt', 'pt-BR', 'en', 'es', 'fr', 'de', 'it', 'nl', 'ja', 'zh', 'ru'];
            set_transient($cache_key, $langs, DAY_IN_SECONDS);
            return $langs;
        } catch (\Throwable $e) {
            $this->logger->log_warning('product', 'from_odoo', 'translation', $e->getMessage(), ['provider' => $provider]);
            return [];
        }
    }

    public function detect_language(string $text): string
    {
        if ($text === '') {
            return 'auto';
        }

        try {
            $provider = $this->get_provider();
            if ($provider === 'deepl') {
                // DeepL nao fornece detect dedicado no endpoint padrao usado aqui.
                return 'auto';
            }

            $api_key = (string) ($this->settings['translation_api_key'] ?? '');
            if ($api_key === '') {
                return 'auto';
            }

            $endpoint = 'https://translation.googleapis.com/language/translate/v2/detect';
            $response = wp_remote_post($endpoint, [
                'timeout' => 30,
                'body' => [
                    'q' => $text,
                    'key' => $api_key,
                ],
            ]);
            if (is_wp_error($response)) {
                return 'auto';
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            $lang = $data['data']['detections'][0][0]['language'] ?? 'auto';
            return is_string($lang) ? $lang : 'auto';
        } catch (\Throwable $e) {
            return 'auto';
        }
    }

    public function test_api_key(): bool
    {
        $provider = $this->get_provider();
        $key = (string) ($this->settings['translation_api_key'] ?? '');
        if ($key === '') {
            return false;
        }

        if ($provider === 'deepl') {
            $response = wp_remote_get($this->deepl_endpoint_for_key($key) . '/usage', [
                'timeout' => 30,
                'headers' => ['Authorization' => 'DeepL-Auth-Key ' . $key],
            ]);
            return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;
        }

        $response = wp_remote_post('https://translation.googleapis.com/language/translate/v2', [
            'timeout' => 30,
            'body' => [
                'q' => 'test',
                'target' => 'pt',
                'key' => $key,
            ],
        ]);
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;
    }

    private function get_provider(): string
    {
        $provider = strtolower((string) ($this->settings['translation_service'] ?? 'google'));
        return $provider === 'deepl' ? 'deepl' : 'google';
    }

    private function translate_with_google(array $texts, string $target_lang, string $source_lang): array
    {
        $api_key = (string) ($this->settings['translation_api_key'] ?? '');
        if ($api_key === '') {
            throw new \RuntimeException(__('Chave da API do Google Translate nao configurada.', DWD_OWS_TEXT_DOMAIN));
        }

        $body = [
            'q' => array_slice($texts, 0, 128),
            'target' => $target_lang,
            'key' => $api_key,
        ];
        if ($source_lang !== 'auto') {
            $body['source'] = $source_lang;
        }

        $response = wp_remote_post('https://translation.googleapis.com/language/translate/v2', [
            'timeout' => 30,
            'body' => $body,
        ]);
        if (is_wp_error($response)) {
            throw new \RuntimeException(sprintf(__('Erro ao chamar Google Translate: %s', DWD_OWS_TEXT_DOMAIN), $response->get_error_message()));
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            throw new \RuntimeException(sprintf(__('Google Translate retornou HTTP %d.', DWD_OWS_TEXT_DOMAIN), $code));
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $rows = $data['data']['translations'] ?? [];
        $output = [];
        foreach ($rows as $row) {
            $output[] = isset($row['translatedText']) ? wp_kses_post(html_entity_decode((string) $row['translatedText'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
        }
        return $this->normalize_batch_output($texts, $output);
    }

    private function translate_with_deepl(array $texts, string $target_lang, string $source_lang): array
    {
        $api_key = (string) ($this->settings['translation_api_key'] ?? '');
        if ($api_key === '') {
            throw new \RuntimeException(__('Chave da API DeepL nao configurada.', DWD_OWS_TEXT_DOMAIN));
        }

        $endpoint = $this->deepl_endpoint_for_key($api_key) . '/translate';
        $body = [
            'text' => $texts,
            'target_lang' => strtoupper($target_lang),
        ];
        if ($source_lang !== 'auto') {
            $body['source_lang'] = strtoupper($source_lang);
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'headers' => ['Authorization' => 'DeepL-Auth-Key ' . $api_key],
            'body' => $body,
        ]);
        if (is_wp_error($response)) {
            throw new \RuntimeException(sprintf(__('Erro ao chamar DeepL: %s', DWD_OWS_TEXT_DOMAIN), $response->get_error_message()));
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            throw new \RuntimeException(sprintf(__('DeepL retornou HTTP %d.', DWD_OWS_TEXT_DOMAIN), $code));
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $rows = $data['translations'] ?? [];
        $output = [];
        foreach ($rows as $row) {
            $output[] = isset($row['text']) ? (string) $row['text'] : '';
        }
        return $this->normalize_batch_output($texts, $output);
    }

    private function deepl_endpoint_for_key(string $api_key): string
    {
        return substr($api_key, -3) === ':fx' ? 'https://api-free.deepl.com/v2' : 'https://api.deepl.com/v2';
    }

    private function normalize_batch_output(array $input, array $translated): array
    {
        $output = [];
        foreach ($input as $index => $source) {
            $value = isset($translated[$index]) && $translated[$index] !== '' ? (string) $translated[$index] : (string) $source;
            $output[] = $value;
        }
        return $output;
    }

    private function build_cache_key(string $text, string $target_lang, string $source_lang): string
    {
        return 'odoo_translation_' . md5($text . '|' . $target_lang . '|' . $source_lang);
    }
}
