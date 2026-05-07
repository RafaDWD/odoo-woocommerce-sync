<?php
namespace DWDOdooWooSync;

if (!defined('ABSPATH')) {
    exit;
}

class OdooAPI
{
    protected $url;
    protected $db;
    protected $username;
    protected $password;
    protected $company_id;
    protected $protocol;
    protected $timeout;
    protected $verify_ssl;
    protected $uid;
    protected $session_id;

    public function __construct(array $config = [])
    {
        $this->url = isset($config['url']) ? rtrim((string) $config['url'], '/') : '';
        $this->db = (string) ($config['db'] ?? '');
        $this->username = (string) ($config['username'] ?? '');
        $this->password = (string) ($config['password'] ?? '');
        $this->company_id = isset($config['company_id']) ? (int) $config['company_id'] : 0;
        $this->protocol = strtolower((string) ($config['protocol'] ?? 'jsonrpc')) === 'xmlrpc' ? 'xmlrpc' : 'jsonrpc';
        $this->timeout = isset($config['timeout']) ? max(5, (int) $config['timeout']) : 30;
        $this->verify_ssl = isset($config['verify_ssl']) ? (bool) $config['verify_ssl'] : true;
        $this->uid = null;
        $this->session_id = null;
    }

    public function authenticate()
    {
        if (empty($this->url) || empty($this->db) || empty($this->username) || empty($this->password)) {
            throw new \RuntimeException(__('Credenciais incompletas para autenticar no Odoo.', DWD_OWS_TEXT_DOMAIN));
        }

        if ($this->protocol === 'xmlrpc') {
            return $this->authenticate_xmlrpc();
        }

        return $this->authenticate_jsonrpc();
    }

    public function call(string $model, string $method, array $args = [], array $kwargs = [])
    {
        if (empty($model) || empty($method)) {
            throw new \InvalidArgumentException(__('Modelo e metodo sao obrigatorios.', DWD_OWS_TEXT_DOMAIN));
        }

        if (!$this->uid) {
            $this->authenticate();
        }

        $kwargs = $this->apply_company_context($kwargs);

        if ($this->protocol === 'xmlrpc') {
            return $this->call_xmlrpc($model, $method, $args, $kwargs);
        }

        return $this->call_jsonrpc($model, $method, $args, $kwargs);
    }

    public function test_connection()
    {
        try {
            $authenticated = $this->authenticate();
            $version = $this->get_server_version();

            return [
                'success' => (bool) $authenticated,
                'message' => __('Conexao com Odoo estabelecida com sucesso.', DWD_OWS_TEXT_DOMAIN),
                'version' => $version,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'version' => '',
            ];
        }
    }

    public function search_read(string $model, array $domain, array $fields, int $limit = 100, int $offset = 0): array
    {
        $result = $this->call(
            $model,
            'search_read',
            [$domain],
            [
                'fields' => $fields,
                'limit' => max(1, $limit),
                'offset' => max(0, $offset),
            ]
        );

        return is_array($result) ? $result : [];
    }

    public function create(string $model, array $values): int
    {
        $result = $this->call($model, 'create', [$values]);
        return (int) $result;
    }

    public function write(string $model, array $ids, array $values): bool
    {
        $result = $this->call($model, 'write', [$ids, $values]);
        return (bool) $result;
    }

    public function search(string $model, array $domain): array
    {
        $result = $this->call($model, 'search', [$domain]);
        return is_array($result) ? $result : [];
    }

    public function get_companies(): array
    {
        $companies = $this->search_read('res.company', [], ['id', 'name'], 200, 0);
        $output = [];
        foreach ($companies as $company) {
            if (!is_array($company)) {
                continue;
            }
            $output[] = [
                'id' => isset($company['id']) ? (int) $company['id'] : 0,
                'name' => isset($company['name']) ? (string) $company['name'] : '',
            ];
        }

        return $output;
    }

    private function authenticate_jsonrpc(): bool
    {
        $response = $this->request_json('/web/session/authenticate', [
            'db' => $this->db,
            'login' => $this->username,
            'password' => $this->password,
        ]);

        $uid = (int) ($response['uid'] ?? 0);
        if ($uid <= 0) {
            throw new \RuntimeException(__('Falha na autenticacao JSON-RPC do Odoo.', DWD_OWS_TEXT_DOMAIN));
        }

        $this->uid = $uid;
        if (!empty($response['session_id'])) {
            $this->session_id = (string) $response['session_id'];
        }
        return true;
    }

    private function authenticate_xmlrpc(): bool
    {
        $xml = $this->build_xmlrpc_request('authenticate', [
            $this->db,
            $this->username,
            $this->password,
            new \stdClass(),
        ]);

        $result = $this->request_xml('/xmlrpc/2/common', $xml);
        $uid = (int) $result;
        if ($uid <= 0) {
            throw new \RuntimeException(__('Falha na autenticacao XML-RPC do Odoo.', DWD_OWS_TEXT_DOMAIN));
        }

        $this->uid = $uid;
        return true;
    }

    private function call_jsonrpc(string $model, string $method, array $args, array $kwargs)
    {
        return $this->request_json('/web/dataset/call_kw', [
            'model' => $model,
            'method' => $method,
            'args' => $args,
            'kwargs' => $kwargs,
        ]);
    }

    private function call_xmlrpc(string $model, string $method, array $args, array $kwargs)
    {
        $xml = $this->build_xmlrpc_request('execute_kw', [
            $this->db,
            (int) $this->uid,
            $this->password,
            $model,
            $method,
            $args,
            $kwargs,
        ]);

        return $this->request_xml('/xmlrpc/2/object', $xml);
    }

    private function request_json(string $path, array $params)
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => $params,
            'id' => wp_generate_uuid4(),
        ];

        $headers = ['Content-Type' => 'application/json'];
        if ($this->session_id !== null) {
            $headers['Cookie'] = 'session_id=' . $this->session_id;
        }

        $response = $this->http_post_with_retry($path, wp_json_encode($payload), $headers);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($decoded)) {
            throw new \RuntimeException(__('Resposta JSON invalida recebida do Odoo.', DWD_OWS_TEXT_DOMAIN));
        }
        if (isset($decoded['error'])) {
            $message = $decoded['error']['data']['message'] ?? $decoded['error']['message'] ?? __('Erro desconhecido da API Odoo.', DWD_OWS_TEXT_DOMAIN);
            throw new \RuntimeException(sprintf(__('Odoo retornou erro: %s', DWD_OWS_TEXT_DOMAIN), (string) $message));
        }
        if (!array_key_exists('result', $decoded)) {
            throw new \RuntimeException(__('Resposta JSON-RPC sem campo result.', DWD_OWS_TEXT_DOMAIN));
        }

        return $decoded['result'];
    }

    private function request_xml(string $path, string $xml_body)
    {
        $response = $this->http_post_with_retry($path, $xml_body, ['Content-Type' => 'text/xml']);
        $body = wp_remote_retrieve_body($response);
        if ($body === '') {
            throw new \RuntimeException(__('Resposta XML vazia recebida do Odoo.', DWD_OWS_TEXT_DOMAIN));
        }

        $xml = @simplexml_load_string($body);
        if ($xml === false) {
            throw new \RuntimeException(__('Resposta XML invalida recebida do Odoo.', DWD_OWS_TEXT_DOMAIN));
        }

        if (isset($xml->fault)) {
            $fault = $this->xmlrpc_parse_value($xml->fault->value);
            $fault_message = is_array($fault) && isset($fault['faultString']) ? (string) $fault['faultString'] : __('Erro XML-RPC no Odoo.', DWD_OWS_TEXT_DOMAIN);
            throw new \RuntimeException($fault_message);
        }

        if (!isset($xml->params->param->value)) {
            throw new \RuntimeException(__('Resposta XML-RPC sem valor retornado.', DWD_OWS_TEXT_DOMAIN));
        }

        return $this->xmlrpc_parse_value($xml->params->param->value);
    }

    private function http_post_with_retry(string $path, string $body, array $headers)
    {
        $url = $this->url . $path;
        $attempt = 0;
        $last_error = null;

        while ($attempt < 3) {
            $attempt++;
            $response = wp_remote_post($url, [
                'headers' => $headers,
                'body' => $body,
                'timeout' => $this->timeout,
                'sslverify' => $this->verify_ssl,
            ]);

            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                if ($this->is_timeout_error($response)) {
                    continue;
                }
                throw new \RuntimeException(sprintf(__('Erro de comunicacao com Odoo: %s', DWD_OWS_TEXT_DOMAIN), $last_error));
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code >= 200 && $code < 300) {
                return $response;
            }

            if ($code === 408 || $code === 504) {
                $last_error = sprintf(__('Timeout HTTP ao comunicar com Odoo (status %d).', DWD_OWS_TEXT_DOMAIN), $code);
                continue;
            }

            $resp_body = wp_remote_retrieve_body($response);
            throw new \RuntimeException(sprintf(__('Erro HTTP %1$d no Odoo. Resposta: %2$s', DWD_OWS_TEXT_DOMAIN), $code, wp_strip_all_tags($resp_body)));
        }

        throw new \RuntimeException($last_error ?: __('Falha apos 3 tentativas de conexao com Odoo.', DWD_OWS_TEXT_DOMAIN));
    }

    private function is_timeout_error(\WP_Error $error): bool
    {
        $code = (string) $error->get_error_code();
        $message = strtolower((string) $error->get_error_message());
        return strpos($code, 'timeout') !== false || strpos($message, 'timed out') !== false || strpos($message, 'curl error 28') !== false;
    }

    private function apply_company_context(array $kwargs): array
    {
        if ($this->company_id <= 0) {
            // Não enviar context vazio: array [] seria codificado como <array> (lista)
            // pelo XML-RPC, mas o Odoo espera um struct (dict). Omitir é mais seguro.
            return $kwargs;
        }

        $context = is_array($kwargs['context'] ?? null) ? $kwargs['context'] : [];
        $context['company_id']           = $this->company_id;
        $context['allowed_company_ids']  = [$this->company_id];
        $kwargs['context']               = $context;
        return $kwargs;
    }

    private function get_server_version(): string
    {
        if ($this->protocol === 'xmlrpc') {
            $xml = $this->build_xmlrpc_request('version', []);
            $result = $this->request_xml('/xmlrpc/2/common', $xml);
            if (is_array($result) && isset($result['server_version'])) {
                return (string) $result['server_version'];
            }
            return '';
        }

        $response = wp_remote_get($this->url . '/web/webclient/version_info', [
            'timeout' => $this->timeout,
            'sslverify' => $this->verify_ssl,
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($decoded)) {
            return '';
        }

        if (isset($decoded['result']['server_version'])) {
            return (string) $decoded['result']['server_version'];
        }

        return (string) ($decoded['server_version'] ?? '');
    }

    private function build_xmlrpc_request(string $method_name, array $params): string
    {
        $xml = '<?xml version="1.0"?>';
        $xml .= '<methodCall><methodName>' . esc_html($method_name) . '</methodName><params>';
        foreach ($params as $param) {
            $xml .= '<param><value>' . $this->xmlrpc_encode_value($param) . '</value></param>';
        }
        $xml .= '</params></methodCall>';
        return $xml;
    }

    private function xmlrpc_encode_value($value): string
    {
        if (is_bool($value)) {
            return '<boolean>' . ($value ? '1' : '0') . '</boolean>';
        }
        if (is_int($value)) {
            return '<int>' . $value . '</int>';
        }
        if (is_float($value)) {
            return '<double>' . $value . '</double>';
        }
        if (is_string($value)) {
            return '<string>' . htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</string>';
        }
        if ($value instanceof \stdClass) {
            return '<struct></struct>';
        }
        if (is_array($value)) {
            if ($this->is_assoc($value)) {
                $xml = '<struct>';
                foreach ($value as $key => $member_value) {
                    $xml .= '<member><name>' . htmlspecialchars((string) $key, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</name><value>' . $this->xmlrpc_encode_value($member_value) . '</value></member>';
                }
                $xml .= '</struct>';
                return $xml;
            }

            $xml = '<array><data>';
            foreach ($value as $item) {
                $xml .= '<value>' . $this->xmlrpc_encode_value($item) . '</value>';
            }
            $xml .= '</data></array>';
            return $xml;
        }

        return '<string>' . htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</string>';
    }

    private function xmlrpc_parse_value(\SimpleXMLElement $value)
    {
        if (isset($value->int)) {
            return (int) $value->int;
        }
        if (isset($value->i4)) {
            return (int) $value->i4;
        }
        if (isset($value->double)) {
            return (float) $value->double;
        }
        if (isset($value->boolean)) {
            return ((string) $value->boolean) === '1';
        }
        if (isset($value->string)) {
            return (string) $value->string;
        }
        if (isset($value->array->data->value)) {
            $items = [];
            foreach ($value->array->data->value as $item) {
                $items[] = $this->xmlrpc_parse_value($item);
            }
            return $items;
        }
        if (isset($value->struct->member)) {
            $assoc = [];
            foreach ($value->struct->member as $member) {
                $assoc[(string) $member->name] = $this->xmlrpc_parse_value($member->value);
            }
            return $assoc;
        }

        return (string) $value;
    }

    private function is_assoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    public function get_uid(): ?int
    {
        return $this->uid ? (int) $this->uid : null;
    }

    public function get_protocol(): string
    {
        return $this->protocol;
    }

    public function set_company_id(int $company_id): void
    {
        $this->company_id = max(0, $company_id);
    }

    public function set_timeout(int $timeout): void
    {
        $this->timeout = max(5, $timeout);
    }

    public function set_verify_ssl(bool $verify_ssl): void
    {
        $this->verify_ssl = $verify_ssl;
    }

    public function reset_session(): void
    {
        $this->uid = null;
        $this->session_id = null;
    }

    public function get_safe_config(): array
    {
        return [
            'url' => $this->url,
            'db' => $this->db,
            'username' => $this->username,
            'company_id' => $this->company_id,
            'protocol' => $this->protocol,
            'timeout' => $this->timeout,
            'verify_ssl' => $this->verify_ssl,
        ];
    }
}
