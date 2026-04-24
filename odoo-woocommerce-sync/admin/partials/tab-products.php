<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('odoo_woocommerce_sync_settings', []);
$fields = $settings['product_fields'] ?? [];
?>
<p><?php esc_html_e('Configure a sincronizacao de produtos Odoo <-> WooCommerce.', 'odoo-woocommerce-sync'); ?></p>
<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e('Habilitar', 'odoo-woocommerce-sync'); ?></th>
        <td><label><input type="checkbox" name="odoo_woocommerce_sync_settings[sync_products]" value="1" <?php checked(!empty($settings['sync_products'])); ?> /> <?php esc_html_e('Ativar sync de produtos', 'odoo-woocommerce-sync'); ?></label></td>
    </tr>
    <tr>
        <th><?php esc_html_e('Direcao', 'odoo-woocommerce-sync'); ?></th>
        <td>
            <select name="odoo_woocommerce_sync_settings[product_direction]">
                <option value="odoo_to_wc" <?php selected(($settings['product_direction'] ?? ''), 'odoo_to_wc'); ?>>Odoo -> WooCommerce</option>
                <option value="wc_to_odoo" <?php selected(($settings['product_direction'] ?? ''), 'wc_to_odoo'); ?>>WooCommerce -> Odoo</option>
                <option value="bidirectional" <?php selected(($settings['product_direction'] ?? ''), 'bidirectional'); ?>>Bidirecional</option>
            </select>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e('Campos para sincronizar', 'odoo-woocommerce-sync'); ?></th>
        <td>
            <?php
            $options = ['name' => 'Nome', 'description' => 'Descricao', 'price' => 'Preco', 'sku' => 'SKU', 'categories' => 'Categorias', 'attributes' => 'Atributos', 'images' => 'Imagens'];
            foreach ($options as $value => $label) :
            ?>
                <label><input type="checkbox" name="odoo_woocommerce_sync_settings[product_fields][]" value="<?php echo esc_attr($value); ?>" <?php checked(in_array($value, $fields, true)); ?> /> <?php echo esc_html($label); ?></label><br />
            <?php endforeach; ?>
        </td>
    </tr>
    <tr>
        <th><?php esc_html_e('Traducao automatica', 'odoo-woocommerce-sync'); ?></th>
        <td><label><input type="checkbox" id="translation_enabled" name="odoo_woocommerce_sync_settings[translation_enabled]" value="1" <?php checked(!empty($settings['translation_enabled'])); ?> /> <?php esc_html_e('Ativar traducao', 'odoo-woocommerce-sync'); ?></label></td>
    </tr>
    <tr class="translation-row">
        <th><?php esc_html_e('Servico', 'odoo-woocommerce-sync'); ?></th>
        <td>
            <select name="odoo_woocommerce_sync_settings[translation_service]">
                <option value="google" <?php selected(($settings['translation_service'] ?? ''), 'google'); ?>>Google Translate</option>
                <option value="deepl" <?php selected(($settings['translation_service'] ?? ''), 'deepl'); ?>>DeepL</option>
            </select>
        </td>
    </tr>
    <tr class="translation-row">
        <th><?php esc_html_e('Chave API de traducao', 'odoo-woocommerce-sync'); ?></th>
        <td>
            <input class="regular-text" type="password"
                name="odoo_woocommerce_sync_settings[translation_api_key]"
                value=""
                placeholder="<?php esc_attr_e('Digite para atualizar a chave', 'odoo-woocommerce-sync'); ?>" />
            <?php if (!empty($settings['translation_api_key'])) : ?>
                <span class="description"><?php esc_html_e('Chave ja configurada. Deixe vazio para manter.', 'odoo-woocommerce-sync'); ?></span>
            <?php endif; ?>
        </td>
    </tr>
    <tr class="translation-row">
        <th><?php esc_html_e('Idioma origem/destino', 'odoo-woocommerce-sync'); ?></th>
        <td>
            <input class="small-text" name="odoo_woocommerce_sync_settings[source_lang]" value="<?php echo esc_attr($settings['source_lang'] ?? 'auto'); ?>" />
            <input class="small-text" name="odoo_woocommerce_sync_settings[target_lang]" value="<?php echo esc_attr($settings['target_lang'] ?? 'pt_BR'); ?>" />
        </td>
    </tr>
</table>
<p>
    <button type="button" class="button button-secondary odoo-run-sync" data-sync="products"><?php esc_html_e('Sincronizar Produtos Agora', 'odoo-woocommerce-sync'); ?></button>
</p>
<div class="odoo-progress"><span style="width:0%"></span></div>
