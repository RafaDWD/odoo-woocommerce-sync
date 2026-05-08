# Smoke test (dev/homolog) — Odoo WooCommerce Sync

Este guia define um **roteiro mínimo e repetível** para validar (em poucos minutos) se as correções principais do plugin estão funcionando: **sync manual**, **reprocessamento**, **estoque**, **cron**, **tokens REST/webhook** e **logs**.

## Pré-requisitos
- WordPress + WooCommerce instalados.
- Plugin **Odoo Woocommerce Sync - By DWD Multicom** ativo.
- Credenciais do Odoo configuradas em **Odoo Sync → Configurações**.
- Pelo menos 1 produto no Odoo (idealmente 1 simples e 1 com variantes) e permissão de acesso via API.

## Dataset mínimo recomendado
- **Produto Odoo simples**: `type=product` com `qty_available` definido e (se possível) SKU (`default_code`).
- **Produto Odoo com variantes**: `product.template` com pelo menos 2 variantes (`product.product`) para validar o mapeamento `product_tmpl_id`.
- **Pedido Odoo** (opcional para import): um `sale.order` em estado `sale` ou `done` com ao menos 1 linha.
- **Pedido Woo** (opcional para export): um pedido criado no Woo com itens que tenham `_odoo_product_id` preenchido (via import de produto do Odoo).

## Roteiro rápido (admin)
### 1) Teste de conexão
1. Acesse **Odoo Sync → Configurações**.
2. Clique **Testar conexão**.
3. Esperado:
   - Mensagem de sucesso e (se disponível) versão.

### 2) Sync manual (C1)
1. Acesse **Odoo Sync → Produtos** e clique **Sincronizar Produtos Agora**.
2. Acesse **Odoo Sync → Logs**.
3. Esperado:
   - Logs de sucesso/erro reais (não “progresso falso”).
   - Produtos criados/atualizados no Woo com meta `_odoo_product_id` (ID do `product.template`).

Repita para:
- **Odoo Sync → Pedidos** → **Sincronizar Pedidos Agora**
- **Odoo Sync → Estoque** → **Sincronizar Estoque Agora**

### 3) Reprocessar falhas (C2)
1. Provoque 1 erro (ex.: URL do Odoo inválida temporariamente) e rode um sync manual para gerar log `status=error`.
2. Corrija a causa do erro.
3. Em **Odoo Sync → Logs**, clique **Reprocessar Falhas**.
4. Esperado:
   - Mensagem `Reprocessados: X | Sucesso: Y | Falhas: Z`.
   - Os logs reprocessados devem ter `reprocessed=1` no banco.

## Validações específicas
### Estoque (C3)
1. Garanta que o Woo tenha produtos com `_odoo_product_id` (template).
2. Rode **Sincronizar Estoque Agora**.
3. Esperado:
   - Estoque atualizado via busca em `product.product` filtrando por `product_tmpl_id`.
   - Para produtos com variantes: o comportamento atual atualiza o produto pai baseado nas linhas retornadas (verificar se atende sua regra de negócio).

### Cron (A1/A2)
1. Desative e ative o plugin novamente.
2. Esperado:
   - Eventos `odoo_sync_products_cron` e `odoo_sync_orders_cron` agendados (schedules custom).
3. Ajuste **Frequência de estoque** (realtime/hourly/daily) e carregue uma página admin.
4. Esperado:
   - O plugin não fica limpando e recriando crons a cada request (apenas quando necessário).

## REST (opcional)
### Token REST
1. Configure `rest_token` nas configurações.
2. Faça request com header `x-odoo-sync-token: <token>`:
   - `GET /wp-json/odoo-sync/v1/status`
   - `POST /wp-json/odoo-sync/v1/sync/products`
   - `POST /wp-json/odoo-sync/v1/sync/orders`
   - `POST /wp-json/odoo-sync/v1/sync/stock`
3. Esperado:
   - 200 quando token correto, 403/401 quando ausente/incorreto (exceto admin logado, que é permitido).

### Webhook de estoque
1. Configure `webhook_token` e chame:
   - `POST /wp-json/odoo-sync/v1/stock-webhook`
   - Header `x-odoo-sync-token: <token>`
   - Body/params: `product_id`, `new_qty`, `location_id`
2. Esperado:
   - 200 e atualização de estoque no Woo.

