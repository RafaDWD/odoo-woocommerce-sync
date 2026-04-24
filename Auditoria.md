# Auditoria — Plugin DWD Multicom Odoo WooCommerce Sync

**Data da auditoria:** 2026-04-24  
**Auditor:** Claude Code (Anthropic)  
**Versão auditada:** 1.0.0  
**Escopo:** Análise estática completa de todos os arquivos do plugin

---

## 1. Visão Geral

O plugin sincroniza dados entre uma instalação Odoo e uma loja WooCommerce (WordPress). Suporta sincronização de produtos, pedidos, estoque e clientes, via JSON-RPC ou XML-RPC. Inclui painel administrativo, logs, agendamentos via WP-Cron, webhooks e serviço de tradução automática (Google Translate / DeepL).

### Estrutura de Arquivos

```
odoo-woocommerce-sync/
├── odoo-woocommerce-sync.php          # Bootstrap do plugin
├── includes/
│   ├── class-odoo-api.php             # Cliente Odoo (JSON-RPC / XML-RPC)
│   ├── class-odoo-woocommerce-sync.php         # Orquestrador principal
│   ├── class-odoo-woocommerce-sync-loader.php  # Gerenciador de hooks WP
│   ├── class-odoo-woocommerce-sync-activator.php
│   ├── class-odoo-woocommerce-sync-deactivator.php
│   ├── class-sync-products.php
│   ├── class-sync-orders.php
│   ├── class-sync-stock.php
│   ├── class-sync-customers.php
│   ├── class-sync-logger.php
│   └── class-translation-service.php
├── admin/
│   ├── class-odoo-woocommerce-sync-admin.php
│   ├── css/admin-style.css
│   ├── js/admin-script.js
│   └── partials/ (dashboard, credenciais, produtos, pedidos, estoque, logs)
├── public/
│   └── class-odoo-woocommerce-sync-public.php  # (vazio — placeholder)
└── tests/
    ├── test-odoo-api.php
    ├── test-sync-products.php
    └── test-sync-orders.php
```

---

## 2. Resumo Executivo

| Categoria | Qtd. de Problemas |
|---|---|
| **Críticos** (funcionalmente incorretos ou inseguros) | 5 |
| **Altos** (bugs latentes ou design problemático) | 7 |
| **Médios** (qualidade, manutenibilidade) | 6 |
| **Baixos** (sugestões) | 4 |
| **Pontos Positivos** | 11 |

O plugin apresenta uma arquitetura bem estruturada e boas práticas de segurança em grande parte do código. No entanto, há falhas críticas que **impedem o funcionamento correto** em produção: sincronização manual do painel não executa nada de fato, testes são stubs vazios, e há um bug de mapeamento de IDs entre `product.template` e `product.product` do Odoo que corromperá silenciosamente a sincronização de estoque.

---

## 3. Problemas Críticos

### C1 — `ajax_run_manual_sync` não sincroniza nada
**Arquivo:** `admin/class-odoo-woocommerce-sync-admin.php:170–194`

O método que responde ao botão "Sincronizar Agora" do painel apenas simula um progresso falso (0→100%) e registra um log de sucesso, mas **não chama nenhum método de sincronização real** (`sync_from_odoo`, `import_orders_from_odoo`, etc.).

```php
// Código atual — progresso simulado, sem sincronização real
for ($i = 1; $i <= 10; $i++) {
    $progress[] = $i * 10;
}
$logger->log('success', rtrim($type, 's'), 'manual', 'admin', 'Sincronizacao manual executada.');
wp_send_json_success(['message' => '...', 'progress' => $progress]);
```

**Impacto:** O administrador acredita que sincronizou, mas nenhum dado foi transferido.

**Correção sugerida:** Instanciar `SyncProducts`, `SyncOrders` ou `SyncStock` a partir das configurações salvas e chamar o método correspondente ao `$type` recebido.

---

### C2 — `ajax_reprocess_failures` não reprocessa nada
**Arquivo:** `admin/class-odoo-woocommerce-sync-admin.php:234–245`

O método apenas marca os registros de erro como `reprocessed = 1` no banco, sem invocar qualquer lógica de reprocessamento. A classe `SyncLogger` já possui o método `reprocess_failed()` com lógica via `do_action`, mas ele nunca é chamado aqui.

```php
// Código atual — apenas marca, não reprocessa
$updated = $wpdb->query("UPDATE {$table} SET reprocessed = 1 WHERE status = 'error' AND reprocessed = 0");
```

**Impacto:** Falhas de sincronização nunca são recuperadas automaticamente pelo botão do painel.

---

### C3 — Bug de mapeamento `product.template` vs `product.product` no estoque
**Arquivo:** `includes/class-sync-stock.php:56–58`

O meta `_odoo_product_id` armazena o ID de `product.template` (sincronizado em `class-sync-products.php`). Porém, `update_woocommerce_stock_from_odoo()` busca estoque em `product.product`, que usa IDs **diferentes** no Odoo.

```php
// IDs de product.template usados para buscar em product.product — IDs distintos no Odoo
$domain = [['id', 'in', array_keys($odoo_map)]];
$rows = $this->api->search_read('product.product', $domain, ['id', 'qty_available'], 200, 0);
```

**Impacto:** A sincronização de estoque retornará `updated: 0` para todos os produtos em lojas com produtos variáveis, e produzirá resultados incorretos mesmo para produtos simples (já que os IDs raramente coincidem entre `template` e `product`).

**Correção sugerida:** Usar o campo `product_tmpl_id` para filtrar em `product.product`, ou armazenar também o ID de `product.product` durante a sincronização de produtos.

---

### C4 — IV fixo na criptografia AES-256-CBC da senha
**Arquivo:** `includes/class-odoo-woocommerce-sync.php:287–296` e `admin/class-odoo-woocommerce-sync-admin.php:278–290`

O IV usado no AES-256-CBC é derivado deterministicamente de `SECURE_AUTH_KEY`:

```php
$iv = substr(hash('sha256', (string) SECURE_AUTH_KEY), 0, 16);
$encrypted = openssl_encrypt($new_value, 'AES-256-CBC', $key, 0, $iv);
```

AES-CBC exige que o IV seja **único e imprevisível por operação**. Com IV fixo:
- A mesma senha sempre produz o mesmo ciphertext (análise de padrões possível)
- Modo CBC com IV fixo é semanticamente inseguro

**Correção sugerida:** Gerar IV aleatório com `random_bytes(16)`, prefixar ao ciphertext e extrair ao descriptografar:
```php
$iv = random_bytes(16);
$encrypted = base64_encode($iv . openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv));
// Ao descriptografar:
$raw = base64_decode($encrypted);
$iv = substr($raw, 0, 16);
$plain = openssl_decrypt(substr($raw, 16), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
```

---

### C5 — Webhook de estoque sem autenticação quando token não configurado
**Arquivo:** `includes/class-sync-stock.php:126–133` e `135–155`

O endpoint `/odoo-sync/v1/stock-webhook` usa `permission_callback => '__return_true'` e só valida o token **se** `$expected !== ''`. Se o administrador não configurar `webhook_token`, qualquer requisição anônima pode atualizar o estoque de qualquer produto.

```php
register_rest_route('odoo-sync/v1', '/stock-webhook', [
    'permission_callback' => '__return_true', // Sempre permitido
]);

// Na callback:
if ($expected !== '' && !hash_equals($expected, $token)) {
    return new \WP_REST_Response([...], 403);
}
// Se $expected === '', passa direto sem autenticar
```

**Impacto:** Qualquer agente externo pode zerar estoques sem credenciais.

**Correção sugerida:** Rejeitar a requisição se `$expected === ''` (token não configurado = webhook desabilitado), ou mover a validação para `permission_callback`.

---

## 4. Problemas Altos

### A1 — Agendamento de cron falha silenciosamente na ativação
**Arquivo:** `includes/class-odoo-woocommerce-sync-activator.php:41` e `includes/class-odoo-woocommerce-sync.php:159–173`

`activate_cron_jobs()` chama `wp_schedule_event(time(), 'odoo_sync_every_15_minutes', ...)`. O schedule customizado `odoo_sync_every_15_minutes` é registrado via filtro `cron_schedules`, que não roda durante o processo de ativação do plugin. O WordPress descartará silenciosamente o agendamento com um schedule inválido.

**Impacto:** O cron de sincronização de produtos (a cada 15 min) e de pedidos (a cada 5 min) provavelmente não são registrados na ativação.

**Correção sugerida:** Adicionar os schedules customizados diretamente no método `activate()` antes de chamar `wp_schedule_event()`, ou usar `hourly`/`twicedaily` como fallback na ativação.

---

### A2 — `schedule_stock_sync` cancela e recria cron a cada requisição
**Arquivo:** `includes/class-sync-stock.php:106–124`

O método é chamado no hook `init` (toda requisição PHP). Ele executa `wp_clear_scheduled_hook()` e potencialmente `wp_schedule_event()` a cada página carregada.

**Impacto:** Sobrecarga desnecessária no banco de dados a cada requisição; risco de interrupção de crons se a lógica falhar por qualquer motivo.

**Correção sugerida:** Verificar a frequência atual antes de limpar/recriar, só agindo se houve mudança.

---

### A3 — Dupla sincronização para produtos novos
**Arquivo:** `includes/class-sync-products.php:201–218`

Para um produto novo, ambos os hooks disparam:
- `woocommerce_update_product` → `on_woocommerce_update_product` → `sync_to_odoo`
- `save_post_product` → `on_save_post_product` (apenas para produtos novos, `!$update`) → `sync_to_odoo`

**Impacto:** Produto criado duas vezes no Odoo na primeira vez que é salvo.

---

### A4 — `get_logs` com assinatura legada frágil
**Arquivo:** `includes/class-sync-logger.php:51–113`

A função tem assinatura `get_logs($filters = [], int $page = 1, int $per_page = 20)` mas os partials admin chamam `get_logs(10)` e `get_logs(20)` passando int no primeiro argumento, ativando um `legacy_mode` interno. O dashboard usa `get_logs(10)` e faz foreach esperando array plano, mas a função retorna `['items' => [...], 'pagination' => [...]]` no modo normal.

Isso só funciona por causa do `legacy_mode`, mas quebra se alguém chamar com a assinatura documentada.

---

### A5 — Chave da API de tradução exibida em texto no formulário
**Arquivo:** `admin/partials/tab-products.php:51`

```php
<input type="password" name="...[translation_api_key]"
    value="<?php echo esc_attr($settings['translation_api_key'] ?? ''); ?>" />
```

Embora seja `type="password"`, o valor é enviado de volta ao browser em texto claro no HTML fonte. Diferente da senha do Odoo (cujo campo é sempre enviado vazio), a chave de API é pré-preenchida no campo, expondo-a no HTML da página.

---

### A6 — CSV de logs salvo em diretório público sem proteção
**Arquivo:** `includes/class-sync-logger.php:186–207`

O CSV exportado é salvo em `wp-content/uploads/` com nome previsível (`odoo-sync-logs-YYYYMMDD-HHMMSS.csv`) e a URL é retornada ao cliente. Logs contêm emails de clientes, IDs de pedidos e mensagens de erro que podem revelar estrutura interna do sistema.

**Impacto:** Qualquer pessoa com a URL (ou que adivinhe o padrão) pode baixar dados de clientes.

**Correção sugerida:** Salvar em diretório protegido com `.htaccess`, ou gerar e transmitir via `readfile()` direto para o browser sem salvar em disco.

---

### A7 — `_odoo_image_1920` armazenada truncada (128 chars) e inutilizável
**Arquivo:** `includes/class-sync-products.php:332–334`

```php
update_post_meta($saved_id, '_odoo_image_1920', substr((string) $odoo_product['image_1920'], 0, 128));
```

Uma imagem base64 truncada a 128 caracteres é inútil. O campo é armazenado mas nunca usado no código para exibição. 

**Correção sugerida:** Processar a imagem como attachment do WordPress (`wp_upload_bits` + `wp_insert_attachment`) ou simplesmente não armazenar.

---

## 5. Problemas Médios

### M1 — `$_GET['tab']` sobrescrito diretamente nas callbacks do menu
**Arquivo:** `admin/class-odoo-woocommerce-sync-admin.php:103–137`

```php
public function display_products_page() {
    $_GET['tab'] = 'products'; // Sobrescrita direta de superglobal
    include DWD_OWS_PLUGIN_DIR . 'admin/partials/admin-display.php';
}
```

Anti-padrão: sobrescrever `$_GET` para comunicar estado ao partial. O partial poderia receber o tab como variável local.

---

### M2 — `rest_token` e `webhook_token` não são gerenciados pelo formulário de configurações
**Arquivo:** `admin/class-odoo-woocommerce-sync-admin.php:73–101`

Os campos `rest_token` e `webhook_token` são lidos em `rest_permission_check()` e `handle_stock_webhook()`, mas **nunca aparecem no `sanitize_settings()`** nem nos partials admin. Não há como configurá-los pela interface.

---

### M3 — `build_api_from_settings` duplicado
A lógica de instanciar `OdooAPI` a partir das configurações salvas está duplicada em:
- `includes/class-odoo-woocommerce-sync.php:61–68` (método `define_sync_hooks`)
- `admin/class-odoo-woocommerce-sync-admin.php:306–317` (método `build_api_from_settings`)

---

### M4 — `get_stats()` contém queries sem `$wpdb->prepare()`
**Arquivo:** `includes/class-sync-logger.php:218–220`

```php
$last_sync = (string) $wpdb->get_var("SELECT created_at FROM {$this->table} ORDER BY ...");
$by_type_rows = $wpdb->get_results("SELECT sync_type, COUNT(*) ... GROUP BY sync_type");
```

`$this->table` é construído de `$wpdb->prefix` (safe), mas viola a prática recomendada do WordPress de usar `prepare()` em todas as queries. O PHPCS/WordPress Coding Standards sinalizaria isso.

---

### M5 — `SyncLogger::attempt_reprocess` pode causar loops
**Arquivo:** `includes/class-sync-logger.php:235–267`

O método dispara `do_action('woocommerce_update_product', $entity_id)` para reprocessar produtos. Se a sincronização falhar novamente, um novo log de erro é inserido. Se reprocessamentos forem acionados em lote, pode haver avalanche de registros de log.

---

### M6 — `import_orders_from_odoo` importa pedidos sem linhas de produto
**Arquivo:** `includes/class-sync-orders.php:108–138`

Ao importar pedidos do Odoo para o WooCommerce, apenas cria o pedido com uma nota, sem importar itens, valores ou dados do cliente. O pedido fica vazio no WooCommerce.

---

## 6. Problemas Baixos (Sugestões)

### B1 — Testes são stubs vazios
**Arquivos:** `tests/test-*.php`

Todos os testes apenas fazem `$this->assertTrue(true)`. Não há cobertura real. Não há `phpunit.xml` configurado.

---

### B2 — `Odoo_WooCommerce_Sync_Public` está vazia
**Arquivo:** `public/class-odoo-woocommerce-sync-public.php`

A classe existe mas o método `init()` não tem implementação. Se não há necessidade de hooks públicos, a classe pode ser removida.

---

### B3 — Sem autoloading PSR-4
O plugin usa `require_once` manual para cada classe. Um `composer.json` com autoloading PSR-4 eliminaria os includes manuais e tornaria o código mais manutenível.

---

### B4 — `Plugin URI` aponta para `https://example.com/`
**Arquivo:** `odoo-woocommerce-sync.php:5`

Placeholder não substituído no header do plugin.

---

## 7. Pontos Positivos

O plugin demonstra boas práticas em diversas áreas:

1. **Namespace PHP** (`DWDOdooWooSync`) — evita colisões com outros plugins.
2. **Verificação de `ABSPATH`** em todos os arquivos PHP — impede acesso direto.
3. **Saídas HTML escapadas corretamente** — `esc_html()`, `esc_attr()`, `esc_url()` usados consistentemente nos partials.
4. **Comparação de tokens com `hash_equals()`** — previne timing attacks.
5. **`$wpdb->prepare()`** usado na maioria das queries SQL.
6. **Retry automático com até 3 tentativas** na comunicação com o Odoo.
7. **Caching com transients** para country IDs e traduções — reduz chamadas externas.
8. **Suporte a dois protocolos** (JSON-RPC e XML-RPC) com implementação correta de ambos.
9. **Sanitização robusta nas configurações** — whitelist de valores, `absint`, `sanitize_text_field`, `esc_url_raw`.
10. **`gmdate()` em vez de `date()`** — datas sempre em UTC.
11. **Tratamento de erros com `\Throwable`** — captura exceções e erros PHP 7+.

---

## 8. Status dos Testes

| Tipo | Status |
|---|---|
| Testes unitários (PHPUnit) | **Não executáveis** — stubs sem lógica real; sem `phpunit.xml`; sem autoloader configurado |
| Teste de integração com Odoo | **Não executável** — requer instância Odoo e WooCommerce |
| Análise estática (manual) | **Executada** — todos os arquivos revisados |
| Testes de interface admin | **Não executáveis** — requer WordPress instalado |

Os testes existentes (`tests/test-*.php`) são marcadores de posição. Todos os métodos chamam `$this->assertTrue(true)` sem verificar comportamento real.

---

## 9. Priorização de Correções

| # | Problema | Prioridade | Esforço |
|---|---|---|---|
| C1 | Sincronização manual do painel não funciona | **CRÍTICO** | Médio |
| C2 | Reprocessamento de falhas não funciona | **CRÍTICO** | Baixo |
| C3 | Bug de mapeamento template/product no estoque | **CRÍTICO** | Médio |
| C4 | IV fixo na criptografia AES | **CRÍTICO** | Baixo |
| C5 | Webhook sem autenticação quando token vazio | **CRÍTICO** | Baixo |
| A1 | Cron com schedule customizado falha na ativação | Alto | Baixo |
| A5 | Chave de API de tradução exposta no HTML | Alto | Baixo |
| A6 | CSV de logs em diretório público | Alto | Médio |
| A3 | Dupla sincronização em produtos novos | Alto | Baixo |
| M2 | `rest_token` e `webhook_token` sem UI de configuração | Médio | Médio |
| M6 | Pedidos importados sem itens | Médio | Alto |
| B1 | Testes sem implementação real | Baixo | Alto |

---

## 10. Conclusão

O plugin é uma base sólida com arquitetura bem pensada e muitas boas práticas de segurança WordPress. A maior preocupação imediata são os **bugs críticos que afetam a funcionalidade central**: o botão de sincronização manual não faz nada, o reprocessamento de falhas não reprocessa, e a sincronização de estoque usa IDs errados do Odoo.

Após corrigir os críticos, a segurança da criptografia da senha e a autenticação do webhook devem ser endereçadas antes de uso em produção com dados reais de clientes.

O projeto está próximo de ser utilizável — os problemas encontrados são corrigíveis sem refatoração arquitetural.
