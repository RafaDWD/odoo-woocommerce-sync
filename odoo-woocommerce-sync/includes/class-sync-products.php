<?php
namespace DWDOdooWooSync;

if (!defined('ABSPATH')) {
    exit;
}

class SyncProducts
{
    protected $api;
    protected $logger;
    protected $translator;
    protected $settings;

    public function __construct(OdooAPI $api, SyncLogger $logger, ?TranslationService $translator = null)
    {
        $this->api = $api;
        $this->logger = $logger;
        $this->translator = $translator ?: new TranslationService();
        $this->settings = get_option('odoo_woocommerce_sync_settings', []);
    }

    public function sync_from_odoo(array $options = []): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'log' => [],
        ];

        try {
            if (!class_exists('WooCommerce') || !class_exists('\WC_Product_Simple')) {
                throw new \RuntimeException(__('WooCommerce nao esta disponivel para sincronizacao.', DWD_OWS_TEXT_DOMAIN));
            }

            $batch_size = isset($options['batch_size']) ? max(1, (int) $options['batch_size']) : 50;
            $offset = isset($options['offset']) ? max(0, (int) $options['offset']) : 0;
            $domain = $options['domain'] ?? [['active', '=', true], ['type', 'in', ['consu', 'product']]];
            $fields = [
                'id',
                'name',
                'description',
                'description_sale',
                'list_price',
                'default_code',
                'categ_id',
                'attribute_line_ids',
                'qty_available',
                // image_1920 excluído do batch — cada imagem pode ter centenas de KB
                // e retornar 50 imagens em um único XML-RPC/JSON-RPC esgota memória.
                // A imagem é buscada individualmente em sync_single_odoo_product().
            ];

            do {
                $records = $this->api->search_read('product.template', $domain, $fields, $batch_size, $offset);
                if (!is_array($records) || empty($records)) {
                    break;
                }

                foreach ($records as $record) {
                    try {
                        $synced = $this->sync_single_odoo_product($record);
                        if ($synced['action'] === 'created') {
                            $result['created']++;
                        } else {
                            $result['updated']++;
                        }
                        $result['log'][] = $synced['message'];
                    } catch (\Throwable $e) {
                        $result['errors']++;
                        $message = sprintf(__('Erro ao sincronizar produto Odoo %1$s: %2$s', DWD_OWS_TEXT_DOMAIN), (string) ($record['id'] ?? 'N/A'), $e->getMessage());
                        $result['log'][] = $message;
                        $this->logger->log('error', 'product', 'from_odoo', (string) ($record['id'] ?? ''), $message);
                    }
                }

                $offset += $batch_size;
            } while (count($records) === $batch_size);

            $this->logger->log(
                'success',
                'product',
                'from_odoo',
                'batch',
                __('Sincronizacao de produtos finalizada.', DWD_OWS_TEXT_DOMAIN),
                null,
                $result
            );
        } catch (\Throwable $e) {
            $result['errors']++;
            $result['log'][] = $e->getMessage();
            $this->logger->log('error', 'product', 'from_odoo', 'batch', $e->getMessage());
        }

        return $result;
    }

    public function sync_to_odoo(int $wc_product_id): bool
    {
        try {
            $product = wc_get_product($wc_product_id);
            if (!$product) {
                throw new \RuntimeException(__('Produto WooCommerce nao encontrado.', DWD_OWS_TEXT_DOMAIN));
            }

            $payload = [
                'name' => $product->get_name(),
                'list_price' => (float) $product->get_regular_price(),
                'default_code' => $product->get_sku(),
                'description_sale' => $product->get_short_description(),
                'description' => $product->get_description(),
                'active' => $product->get_status() === 'publish',
            ];

            $odoo_id = (int) get_post_meta($wc_product_id, '_odoo_product_id', true);
            if ($odoo_id > 0) {
                $ok = $this->api->write('product.template', [$odoo_id], $payload);
                if (!$ok) {
                    throw new \RuntimeException(__('Nao foi possivel atualizar produto no Odoo.', DWD_OWS_TEXT_DOMAIN));
                }
            } else {
                $odoo_id = $this->api->create('product.template', $payload);
                if ($odoo_id <= 0) {
                    throw new \RuntimeException(__('Nao foi possivel criar produto no Odoo.', DWD_OWS_TEXT_DOMAIN));
                }
                update_post_meta($wc_product_id, '_odoo_product_id', $odoo_id);
            }

            update_post_meta($wc_product_id, '_odoo_sync_date', gmdate('Y-m-d H:i:s'));
            $this->logger->log('success', 'product', 'to_odoo', (string) $wc_product_id, __('Produto exportado para Odoo.', DWD_OWS_TEXT_DOMAIN), (string) $odoo_id, $payload);
            return true;
        } catch (\Throwable $e) {
            $this->logger->log('error', 'product', 'to_odoo', (string) $wc_product_id, $e->getMessage());
            return false;
        }
    }

    public function sync_categories(array $odoo_categories): array
    {
        $mapping = get_option('odoo_sync_category_map', []);
        if (!is_array($mapping)) {
            $mapping = [];
        }

        foreach ($odoo_categories as $category) {
            if (!is_array($category)) {
                continue;
            }

            $odoo_id = (int) ($category['id'] ?? 0);
            $name = (string) ($category['name'] ?? '');
            if ($odoo_id <= 0 || $name === '') {
                continue;
            }

            $term_id = isset($mapping[$odoo_id]) ? (int) $mapping[$odoo_id] : 0;
            if ($term_id > 0 && get_term($term_id, 'product_cat')) {
                continue;
            }

            $term = term_exists($name, 'product_cat');
            if (!$term) {
                $term = wp_insert_term($name, 'product_cat');
            }

            if (!is_wp_error($term)) {
                $mapping[$odoo_id] = (int) (is_array($term) ? $term['term_id'] : $term);
            }
        }

        update_option('odoo_sync_category_map', $mapping);
        return $mapping;
    }

    public function sync_variations(\WC_Product_Variable $wc_product, array $odoo_attributes): void
    {
        // Implementacao inicial: registra atributos do produto pai.
        if (empty($odoo_attributes)) {
            return;
        }

        $attributes = [];
        foreach ($odoo_attributes as $attr) {
            if (!is_array($attr) || empty($attr['name'])) {
                continue;
            }

            $product_attr = new \WC_Product_Attribute();
            $product_attr->set_name(sanitize_text_field((string) $attr['name']));
            $product_attr->set_visible(true);
            $product_attr->set_variation(true);
            $product_attr->set_options(array_map('sanitize_text_field', (array) ($attr['values'] ?? [])));
            $attributes[] = $product_attr;
        }

        if (!empty($attributes)) {
            $wc_product->set_attributes($attributes);
            $wc_product->save();
        }
    }

    public function on_woocommerce_update_product(int $product_id): void
    {
        if ($product_id <= 0 || wp_is_post_revision($product_id)) {
            return;
        }
        $this->sync_to_odoo($product_id);
    }

    public function on_save_post_product(int $post_id, \WP_Post $post, bool $update): void
    {
        if ($post->post_type !== 'product' || wp_is_post_revision($post_id)) {
            return;
        }
        if (!$update) {
            // Produto novo: woocommerce_update_product dispara logo após e cobre este caso.
            // Retornar aqui evita dupla exportação para o Odoo.
            return;
        }
        // Update direto via wp_update_post sem passar pelo WooCommerce
        $this->sync_to_odoo($post_id);
    }

    public function get_sync_status(): array
    {
        $wc_ids = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'fields' => 'ids',
            'numberposts' => -1,
        ]);

        $synced = 0;
        $last_sync_date = '';
        foreach ($wc_ids as $wc_id) {
            $odoo_id = (int) get_post_meta((int) $wc_id, '_odoo_product_id', true);
            if ($odoo_id > 0) {
                $synced++;
                $sync_date = (string) get_post_meta((int) $wc_id, '_odoo_sync_date', true);
                if ($sync_date !== '' && $sync_date > $last_sync_date) {
                    $last_sync_date = $sync_date;
                }
            }
        }

        $total_wc = count($wc_ids);
        $total_odoo = 0;
        try {
            $total_odoo = count($this->api->search('product.template', [['active', '=', true]]));
        } catch (\Throwable $e) {
            $this->logger->log('warning', 'product', 'from_odoo', 'status', $e->getMessage());
        }

        return [
            'total_odoo' => $total_odoo,
            'total_wc' => $total_wc,
            'synced' => $synced,
            'not_synced' => max(0, $total_wc - $synced),
            'last_sync_date' => $last_sync_date,
        ];
    }

    private function sync_single_odoo_product(array $odoo_product): array
    {
        $odoo_id = (int) ($odoo_product['id'] ?? 0);
        if ($odoo_id <= 0) {
            throw new \RuntimeException(__('Produto Odoo sem ID valido.', DWD_OWS_TEXT_DOMAIN));
        }

        $wc_id = $this->find_wc_product_by_odoo_id($odoo_id);
        $name = (string) ($odoo_product['name'] ?? '');
        $description = (string) ($odoo_product['description'] ?? '');
        $short_description = (string) ($odoo_product['description_sale'] ?? '');

        if (!empty($this->settings['translation_enabled'])) {
            $target = (string) ($this->settings['target_lang'] ?? 'pt_BR');
            $name = $this->translator->translate($name, $target);
            $description = $this->translator->translate($description, $target);
            $short_description = $this->translator->translate($short_description, $target);
        }

        $product_data = [
            'name' => $name,
            'description' => $description,
            'short_description' => $short_description,
            'regular_price' => (string) ((float) ($odoo_product['list_price'] ?? 0)),
            'sku' => sanitize_text_field((string) ($odoo_product['default_code'] ?? '')),
            'manage_stock' => true,
            'stock_quantity' => (int) ($odoo_product['qty_available'] ?? 0),
            'status' => 'publish',
        ];

        $category_data = isset($odoo_product['categ_id']) ? $this->extract_category_data($odoo_product['categ_id']) : [];
        if (!empty($category_data)) {
            $map = $this->sync_categories([$category_data]);
            $cat_odoo_id = (int) ($category_data['id'] ?? 0);
            if (isset($map[$cat_odoo_id])) {
                $product_data['category_ids'] = [(int) $map[$cat_odoo_id]];
            }
        }

        $action = 'updated';
        if ($wc_id > 0) {
            $product = wc_get_product($wc_id);
            if (!$product) {
                throw new \RuntimeException(__('Produto WooCommerce nao encontrado para atualizacao.', DWD_OWS_TEXT_DOMAIN));
            }
        } else {
            $product = new \WC_Product_Simple();
            $action = 'created';
        }

        $product->set_name($product_data['name']);
        $product->set_description($product_data['description']);
        $product->set_short_description($product_data['short_description']);
        $product->set_regular_price($product_data['regular_price']);
        if ($product_data['sku'] !== '') {
            $product->set_sku($product_data['sku']);
        }
        $product->set_manage_stock(true);
        $product->set_stock_quantity($product_data['stock_quantity']);
        $product->set_status('publish');

        $saved_id = $product->save();
        if (!empty($product_data['category_ids'])) {
            wp_set_object_terms($saved_id, $product_data['category_ids'], 'product_cat');
        }

        update_post_meta($saved_id, '_odoo_product_id', $odoo_id);
        update_post_meta($saved_id, '_odoo_sync_date', gmdate('Y-m-d H:i:s'));

        if (in_array('images', (array) ($this->settings['product_fields'] ?? []), true)) {
            try {
                $img_records = $this->api->search_read('product.template', [['id', '=', $odoo_id]], ['image_1920'], 1, 0);
                $image_b64   = is_string($img_records[0]['image_1920'] ?? null) ? (string) $img_records[0]['image_1920'] : '';
                if ($image_b64 !== '') {
                    $this->attach_odoo_image_to_product($product, $image_b64, $odoo_id);
                }
            } catch (\Throwable $e) {
                $this->logger->log('warning', 'product', 'from_odoo', (string) $saved_id, 'Imagem nao sincronizada: ' . $e->getMessage(), (string) $odoo_id);
            }
        }

        if (!empty($odoo_product['attribute_line_ids']) && $product instanceof \WC_Product_Variable) {
            $this->sync_variations($product, (array) $odoo_product['attribute_line_ids']);
        }

        $message = sprintf(__('Produto %1$s com sucesso (Odoo #%2$d / WooCommerce #%3$d).', DWD_OWS_TEXT_DOMAIN), $action === 'created' ? __('criado', DWD_OWS_TEXT_DOMAIN) : __('atualizado', DWD_OWS_TEXT_DOMAIN), $odoo_id, $saved_id);
        $this->logger->log('success', 'product', 'from_odoo', (string) $saved_id, $message, (string) $odoo_id, ['action' => $action]);

        return ['action' => $action, 'message' => $message];
    }

    private function find_wc_product_by_odoo_id(int $odoo_id): int
    {
        $query = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_odoo_product_id',
                    'value' => $odoo_id,
                    'compare' => '=',
                ],
            ],
        ]);

        if (!empty($query->posts)) {
            return (int) $query->posts[0];
        }
        return 0;
    }

    private function extract_category_data($categ_id): array
    {
        if (is_array($categ_id)) {
            if (isset($categ_id[0])) {
                return [
                    'id' => (int) $categ_id[0],
                    'name' => (string) ($categ_id[1] ?? ('Categoria ' . (int) $categ_id[0])),
                ];
            }
        }
        if (is_int($categ_id)) {
            return ['id' => $categ_id, 'name' => 'Categoria ' . $categ_id];
        }
        return [];
    }

    private function attach_odoo_image_to_product(\WC_Product $product, string $image_b64, int $odoo_id): void
    {
        $data = base64_decode($image_b64, true);
        if ($data === false || strlen($data) < 8) {
            return;
        }

        $saved_id = $product->get_id();
        if ($saved_id <= 0) {
            return;
        }

        // Só re-envia se a imagem mudou no Odoo
        $new_hash = md5($data);
        if ((string) get_post_meta($saved_id, '_odoo_image_hash', true) === $new_hash
            && $product->get_image_id() > 0) {
            return;
        }

        // Detecta tipo MIME sem extensão de arquivo
        $size_info = @getimagesizefromstring($data);
        if ($size_info === false) {
            return;
        }
        $mime    = $size_info['mime'] ?? '';
        $ext_map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        if (!isset($ext_map[$mime])) {
            return;
        }

        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return;
        }

        $filename  = 'odoo-product-' . $odoo_id . '.' . $ext_map[$mime];
        $file_path = trailingslashit($upload_dir['path']) . $filename;

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        if (file_put_contents($file_path, $data) === false) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $attach_id = wp_insert_attachment(
            [
                'post_mime_type' => $mime,
                'post_title'     => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ],
            $file_path,
            $saved_id,
            true
        );

        if (is_wp_error($attach_id) || $attach_id <= 0) {
            @unlink($file_path); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            return;
        }

        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $file_path));

        // Remove imagem anterior criada pelo sync para evitar acúmulo na mídia
        $old_attach_id = (int) $product->get_image_id();
        if ($old_attach_id > 0 && (string) get_post_meta($old_attach_id, '_odoo_synced_image', true) === '1') {
            wp_delete_attachment($old_attach_id, true);
        }

        update_post_meta($attach_id, '_odoo_synced_image', '1');
        update_post_meta($saved_id, '_odoo_image_hash', $new_hash);
        $product->set_image_id($attach_id);
        $product->save();
    }
}
