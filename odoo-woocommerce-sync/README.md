# Plugin Odoo WooCommerce Sync

Plugin para sincronizar produtos, pedidos, clientes e estoque entre WooCommerce e Odoo.

## Requisitos

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+
- Odoo 14+ (XML-RPC ou JSON-RPC habilitado)

## Instalacao

1. Copie a pasta `odoo-woocommerce-sync` para `wp-content/plugins`.
2. Ative o plugin no painel do WordPress.
3. Acesse o menu `Odoo Sync`.
4. Preencha credenciais e salve.
5. Teste a conexao com o Odoo.

## Configuracao Inicial

1. Inserir credenciais Odoo (URL, DB, usuario e senha).
2. Testar conexao.
3. Selecionar empresa (Company ID).
4. Configurar sincronizacao por entidade (produtos, pedidos, estoque).

## Configuracao Multistores

O plugin suporta ate 10 lojas em `odoo_sync_stores`, cada uma com:

- Credenciais Odoo independentes
- `company_id`
- Opcoes de sincronizacao por entidade
- `wc_store_id`

## Mapeamento de Status de Pedidos

Mapa padrao:

- `pending` -> `draft`
- `processing` -> `sale`
- `completed` -> `done`
- `cancelled` -> `cancel`
- `refunded` -> `cancel`

## Traducao Automatica

Suporte para:

- Google Translate API v2
- DeepL API (Free/Pro)

Configuracao:

1. Ative traducao na aba Produtos.
2. Escolha o servico.
3. Informe chave API.
4. Defina idiomas origem/destino.

## Webhooks

Endpoint para estoque:

- `POST /wp-json/odoo-sync/v1/stock-webhook`

Payload esperado:

```json
{
  "product_id": 123,
  "new_qty": 15,
  "location_id": 8
}
```

## Solucao de Problemas

- **Conexao falha**: confirme URL, DB, usuario, senha e protocolo.
- **Produtos nao sincronizam**: verifique se o WooCommerce esta ativo e o produto tem mapeamento.
- **Webhook rejeitado**: confirme token no header `x-odoo-sync-token`.
- **Cron nao roda**: valide `wp-cron.php` e eventos agendados.

## Seguranca

- Senha Odoo armazenada de forma criptografada com `openssl`.
- Endpoints REST exigem usuario autenticado com permissao ou token customizado.
- Inputs do painel sanitizados via WordPress Settings API.
