# Prompts Cursor — Plugin WordPress: Integração Odoo ↔ WooCommerce

> **Como usar:** Execute cada prompt em sequência no Cursor. Aguarde a geração completa antes de passar para o próximo. Use o modo **Agent** para melhor resultado.

---

## 📁 PROMPT 1 — Estrutura Base do Plugin (Boilerplate)

```
Crie a estrutura completa de um plugin WordPress seguindo o WordPress Plugin Boilerplate padrão, com as seguintes especificações:

Nome do plugin: "DWD Multicom Odoo WooCommerce Sync"
Slug: dwd-odoo-woocommerce-sync
Versão: 1.0.0
Namespace PHP: DWDOdooWooSync

Estrutura de diretórios:
odoo-woocommerce-sync/
├── odoo-woocommerce-sync.php          ← arquivo principal
├── includes/
│   ├── class-odoo-woocommerce-sync.php       ← classe core
│   ├── class-odoo-woocommerce-sync-loader.php
│   ├── class-odoo-woocommerce-sync-activator.php
│   ├── class-odoo-woocommerce-sync-deactivator.php
│   ├── class-odoo-api.php                    ← cliente Odoo RPC
│   ├── class-sync-products.php               ← sync de produtos
│   ├── class-sync-orders.php                 ← sync de pedidos
│   ├── class-sync-stock.php                  ← sync de estoque
│   ├── class-sync-customers.php              ← sync de clientes
│   ├── class-sync-logger.php                 ← sistema de logs
│   └── class-translation-service.php         ← tradução automática
├── admin/
│   ├── class-odoo-woocommerce-sync-admin.php
│   ├── css/admin-style.css
│   ├── js/admin-script.js
│   └── partials/
│       ├── admin-display.php                 ← painel principal
│       ├── tab-credentials.php               ← aba credenciais
│       ├── tab-products.php                  ← aba produtos
│       ├── tab-orders.php                    ← aba pedidos
│       ├── tab-stock.php                     ← aba estoque
│       └── tab-logs.php                      ← aba logs
├── public/
│   └── class-odoo-woocommerce-sync-public.php
└── languages/
    └── odoo-woocommerce-sync.pot

Requisitos:
- PHP 7.4+ compatível
- Verificar se WooCommerce está ativo antes de inicializar
- Usar WordPress Options API para armazenar configurações
- Registrar hooks de ativação e desativação
- Criar tabela de logs no banco de dados na ativação (wp_odoo_sync_logs)
- Internacionalização com text domain "odoo-woocommerce-sync"

Gere todos os arquivos com código funcional, não apenas esqueletos.
```

---

## 🔌 PROMPT 2 — Cliente Odoo API (XML-RPC e JSON-RPC)

```
No arquivo includes/class-odoo-api.php, implemente um cliente completo para a API do Odoo com suporte a XML-RPC e JSON-RPC.

Requisitos da classe OdooAPI:

PROPRIEDADES:
- $url (string): URL base do Odoo
- $db (string): nome do banco de dados
- $uid (int|null): ID do usuário autenticado
- $password (string): senha
- $company_id (int): ID da empresa para multistores
- $protocol (string): 'xmlrpc' ou 'jsonrpc'

MÉTODOS PÚBLICOS:

1. authenticate(): bool
   - Conecta via /web/dataset/call_kw ou /xmlrpc/2/common
   - Armazena $uid em caso de sucesso
   - Lança exceção com mensagem clara em caso de falha
   - Retorna true/false

2. call(string $model, string $method, array $args, array $kwargs = []): mixed
   - Executa chamadas autenticadas via /xmlrpc/2/object ou /web/dataset/call_kw
   - Aplica automaticamente o company_id no contexto
   - Trata erros HTTP e erros da API Odoo
   - Retry automático (3 tentativas) em caso de timeout

3. search_read(string $model, array $domain, array $fields, int $limit = 100, int $offset = 0): array
   - Atalho para buscar registros com paginação

4. create(string $model, array $values): int
   - Cria registro e retorna ID

5. write(string $model, array $ids, array $values): bool
   - Atualiza registros

6. search(string $model, array $domain): array
   - Retorna lista de IDs

7. get_companies(): array
   - Retorna lista de empresas disponíveis [{id, name}]

8. test_connection(): array
   - Testa conexão e retorna ['success' => bool, 'message' => string, 'version' => string]

SEGURANÇA:
- Nunca logar a senha em texto claro
- Usar wp_remote_post/wp_remote_get do WordPress para requisições HTTP
- Timeout configurável (padrão: 30s)
- Validar SSL (com opção para desabilitar em ambientes de dev)

Inclua tratamento de erros robusto com mensagens em português.
```

---

## ⚙️ PROMPT 3 — Painel de Administração (Settings Page)

```
Implemente o painel de administração completo do plugin em admin/class-odoo-woocommerce-sync-admin.php e seus partials.

ESTRUTURA DO PAINEL:
- Menu: "Odoo Sync" no menu lateral do WordPress
- Submenus: Dashboard, Produtos, Pedidos, Estoque, Logs, Configurações

ABA CONFIGURAÇÕES (tab-credentials.php):
Campos obrigatórios:
- URL do Odoo (text, validar formato URL)
- Nome do banco de dados (text)
- Usuário (text)
- Senha (password, armazenar criptografado com openssl_encrypt + chave derivada de AUTH_KEY)
- Empresa/Company ID (select, carregado via AJAX após autenticação bem-sucedida)
- Protocolo: XML-RPC ou JSON-RPC (radio)
- Botão "Testar Conexão" (AJAX, exibe resultado inline)

ABA PRODUTOS (tab-products.php):
- Habilitar/desabilitar sync de produtos (checkbox)
- Direção: Odoo→WooCommerce, WooCommerce→Odoo, Bidirecional (select)
- Campos para sincronizar (checkboxes): nome, descrição, preço, SKU, categorias, atributos, imagens
- Habilitar tradução automática (checkbox)
- Serviço de tradução: Google Translate, DeepL (select, aparece se tradução habilitada)
- Chave da API de tradução (password)
- Idioma de origem / destino (select)
- Botão "Sincronizar Produtos Agora" (AJAX com barra de progresso)

ABA PEDIDOS (tab-orders.php):
- Habilitar sync de pedidos (checkbox)
- Mapeamento de status (tabela editável):
  WooCommerce Status → Odoo Status
  pending → draft
  processing → sale
  completed → done
  cancelled → cancel
  refunded → cancel
- Exportar cliente junto com pedido (checkbox)
- Botão "Sincronizar Pedidos Agora"

ABA ESTOQUE (tab-stock.php):
- Habilitar sync de estoque (checkbox)
- Frequência de atualização: tempo real (webhook), a cada hora, diariamente (select)
- Localização de estoque no Odoo (text: stock.location ID)
- Botão "Sincronizar Estoque Agora"

ABA LOGS (tab-logs.php):
- Tabela paginada com colunas: Data/Hora, Tipo, Entidade, ID, Status, Mensagem
- Filtros: tipo (produto/pedido/estoque/cliente), status (sucesso/erro), período
- Botão "Reprocessar Falhas"
- Botão "Limpar Logs Antigos" (manter últimos 30 dias)
- Export CSV dos logs

DASHBOARD (admin-display.php):
- Cards resumo: Total produtos sincronizados, Pedidos hoje, Erros pendentes
- Últimas 10 sincronizações
- Status da conexão com Odoo (verde/vermelho)

JAVASCRIPT (admin-script.js):
- AJAX para testar conexão, carregar empresas, sincronização manual
- Barra de progresso para sincronizações longas usando heartbeat API ou polling
- Notificações de sucesso/erro com WordPress notices

Use WordPress Settings API, nonces para segurança, e sanitização de todos os inputs.
```

---

## 📦 PROMPT 4 — Sincronização de Produtos

```
Implemente a classe completa de sincronização de produtos em includes/class-sync-products.php.

CLASSE: SyncProducts

MÉTODO PRINCIPAL: sync_from_odoo(array $options = []): array
Importa produtos do Odoo para WooCommerce:

1. Buscar produtos no Odoo:
   - Modelo: product.template
   - Campos: id, name, description, description_sale, list_price, default_code, 
             categ_id, attribute_line_ids, uom_id, image_1920, active, type,
             product_variant_ids, qty_available
   - Domain padrão: [['active', '=', True], ['type', 'in', ['consu', 'product']]]
   - Usar paginação em lotes de 50 (batch processing)

2. Para cada produto Odoo:
   a. Verificar se já existe no WooCommerce (via meta _odoo_product_id)
   b. Mapear campos:
      - name → post_title
      - description/description_sale → post_content/post_excerpt  
      - list_price → regular_price
      - default_code → sku
      - categ_id → categoria WooCommerce (criar se não existir)
      - qty_available → stock_quantity
   c. Se produto tem attribute_line_ids → criar como produto variável
   d. Se tradução habilitada → traduzir name, description, description_sale
   e. Fazer upload da imagem se image_1920 presente
   f. Criar ou atualizar via wc_create_product() / wc_update_product()
   g. Salvar meta: _odoo_product_id, _odoo_sync_date

3. Retornar: ['created' => int, 'updated' => int, 'errors' => int, 'log' => array]

MÉTODO: sync_to_odoo(int $wc_product_id): bool
Exporta produto WooCommerce para Odoo:
- Mapear campos WooCommerce → Odoo
- Se produto existe (_odoo_product_id) → write(), senão → create()
- Atualizar meta _odoo_product_id

MÉTODO: sync_categories(array $odoo_categories): array
- Cria/atualiza categorias WooCommerce mapeadas das categorias Odoo
- Armazena mapeamento odoo_categ_id → wc_category_id em opção WordPress

MÉTODO: sync_variations(WC_Product_Variable $wc_product, array $odoo_attributes): void
- Cria atributos e variações no WooCommerce baseado em attribute_line_ids do Odoo

HOOKS:
- Adicionar hook em woocommerce_update_product para exportar ao Odoo quando produto WC é salvo
- Adicionar hook em save_post_product para detectar novos produtos

MÉTODO: get_sync_status(): array
- Retorna estatísticas: total_odoo, total_wc, synced, not_synced, last_sync_date

Usar OdooAPI para chamadas RPC e SyncLogger para registrar operações.
Toda operação deve ser wrapped em try/catch com log detalhado.
```

---

## 🛒 PROMPT 5 — Sincronização de Pedidos e Clientes

```
Implemente as classes de sincronização de pedidos e clientes.

ARQUIVO 1: includes/class-sync-orders.php
CLASSE: SyncOrders

MÉTODO: export_order_to_odoo(int $wc_order_id): bool|int

1. Carregar pedido WooCommerce via wc_get_order()
2. Sincronizar cliente primeiro (chamar SyncCustomers::sync_customer())
3. Montar array para sale.order no Odoo:
   {
     'partner_id': odoo_partner_id,
     'company_id': company_id_configurado,
     'client_order_ref': número do pedido WC,
     'date_order': data do pedido ISO,
     'order_line': [
       [0, 0, {
         'product_id': odoo_product_id,  // via meta _odoo_product_id
         'product_uom_qty': quantidade,
         'price_unit': preço unitário,
         'name': nome do produto,
         'tax_id': [[6, 0, []]]  // impostos (simplificado)
       }]
     ],
     'note': notas do pedido
   }
4. Criar pedido no Odoo: OdooAPI::create('sale.order', data)
5. Confirmar pedido se status WC for 'processing': chamar action_confirm
6. Salvar meta no pedido WC: _odoo_order_id, _odoo_order_name
7. Logar resultado

MÉTODO: sync_order_status(int $wc_order_id, string $new_status): void
- Atualizar status no Odoo baseado no mapeamento configurado
- Mapa: processing→action_confirm, completed→action_done, cancelled→action_cancel

MÉTODO: import_orders_from_odoo(): array
- Buscar pedidos no Odoo não sincronizados com WooCommerce
- Criar pedidos no WooCommerce correspondentes

HOOK: woocommerce_order_status_changed para sincronizar mudanças de status
HOOK: woocommerce_checkout_order_processed para exportar novo pedido

---

ARQUIVO 2: includes/class-sync-customers.php
CLASSE: SyncCustomers

MÉTODO: sync_customer(WC_Order $order): int
Retorna odoo_partner_id:
1. Verificar se cliente já existe no Odoo (buscar por email em res.partner)
2. Se não existe: criar res.partner com:
   {
     'name': nome completo,
     'email': email,
     'phone': telefone,
     'street': endereço,
     'city': cidade,
     'zip': CEP,
     'country_id': ID do país (buscar por código ISO),
     'customer_rank': 1,
     'company_id': company_id configurado
   }
3. Se existe: atualizar dados se necessário
4. Salvar meta no user WP: _odoo_partner_id
5. Retornar partner_id do Odoo

MÉTODO: get_country_id(string $country_code): int
- Cache local dos IDs de países (armazenar em transient por 24h)
- Buscar em res.country via search_read
```

---

## 📊 PROMPT 6 — Sincronização de Estoque

```
Implemente a sincronização de estoque em includes/class-sync-stock.php.

CLASSE: SyncStock

MÉTODO: update_woocommerce_stock_from_odoo(): array
Atualiza estoque WooCommerce baseado no Odoo:
1. Buscar todos produtos WooCommerce que têm meta _odoo_product_id
2. Em batch de 50, buscar qty disponível no Odoo:
   - Modelo: product.product (não template)
   - Campo: qty_available
   - Domain: [['id', 'in', [lista_de_ids_odoo]]]
   - Usar stock_location_id configurado se especificado
3. Para cada produto: wc_update_product_stock()
4. Logar alterações

MÉTODO: update_odoo_stock_from_woocommerce_sale(int $order_id): void
Chamado após venda WooCommerce para decrementar no Odoo:
- Se sync de estoque habilitado E pedido já exportado para Odoo
- O próprio Odoo decrementa ao confirmar sale.order, então apenas verificar consistência
- Se pedido não exportado ainda: forçar exportação

MÉTODO: schedule_stock_sync(): void
- Registrar cron job WordPress baseado na frequência configurada:
  - 'realtime': usar webhook (registrar endpoint REST)
  - 'hourly': wp_schedule_event com hook odoo_sync_stock_hourly  
  - 'daily': wp_schedule_event com hook odoo_sync_stock_daily

MÉTODO: register_webhook_endpoint(): void
- Registrar REST route: /wp-json/odoo-sync/v1/stock-webhook
- Aceitar POST do Odoo com: {product_id, new_qty, location_id}
- Verificar token de segurança no header
- Atualizar estoque WooCommerce correspondente

MÉTODO: get_odoo_stock_location_qty(int $product_id, int $location_id): float
- Busca qty em localização específica via stock.quant model

HOOK: woocommerce_order_status_processing para sync em tempo real
Usar transient para cache de 5 minutos evitando excesso de chamadas API.
```

---

## 📝 PROMPT 7 — Sistema de Logs

```
Implemente o sistema completo de logs em includes/class-sync-logger.php.

TABELA NO BANCO (criada na ativação do plugin):
CREATE TABLE {prefix}odoo_sync_logs (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sync_type varchar(20) NOT NULL,   -- product, order, stock, customer
  direction varchar(10) NOT NULL,   -- to_odoo, from_odoo
  entity_id varchar(100),           -- ID WooCommerce ou Odoo
  odoo_id varchar(100),             -- ID correspondente no Odoo
  status varchar(10) NOT NULL,      -- success, error, warning
  message text,                     -- descrição da operação
  details longtext,                 -- JSON com dados completos
  reprocessed tinyint(1) DEFAULT 0,
  PRIMARY KEY (id),
  KEY sync_type (sync_type),
  KEY status (status),
  KEY created_at (created_at)
)

CLASSE: SyncLogger

MÉTODOS:
- log_success(string $type, string $direction, $entity_id, $odoo_id, string $message, array $details = []): void
- log_error(string $type, string $direction, $entity_id, string $message, array $details = []): void  
- log_warning(string $type, string $direction, $entity_id, string $message, array $details = []): void
- get_logs(array $filters = [], int $page = 1, int $per_page = 20): array
  Filtros suportados: type, status, date_from, date_to, entity_id
- get_error_count(): int
- get_pending_reprocess(): array  (erros não reprocessados)
- reprocess_failed(array $log_ids): array
  Para cada log de erro: tenta reexecutar a operação original
- cleanup_old_logs(int $days = 30): int  (retorna quantos deletados)
- export_csv(array $filters = []): string  (retorna path do arquivo temporário)
- get_stats(): array
  Retorna: total_today, success_today, errors_today, last_sync, by_type

Usar $wpdb para operações no banco.
Implementar reprocessamento: salvar em $details o tipo e ID suficientes para reexecutar.
```

---

## 🌐 PROMPT 8 — Serviço de Tradução Automática

```
Implemente o serviço de tradução em includes/class-translation-service.php.

CLASSE: TranslationService

Suporte a dois provedores:
1. Google Cloud Translation API v2
2. DeepL API Free/Pro

MÉTODO: translate(string $text, string $target_lang, string $source_lang = 'auto'): string
- Selecionar provedor conforme configuração
- Cache de traduções em transients WordPress (chave: md5(text+target+source))
- Cache duration: 7 dias (campos de produto mudam raramente)
- Retornar texto original em caso de falha (não quebrar o sync)
- Logar falhas de tradução

MÉTODO: translate_product_fields(array $fields, string $target_lang): array
Traduz campos de produto:
- Input: ['name' => '...', 'description' => '...', 'description_sale' => '...']
- Traduz em batch (uma única chamada API quando possível)
- Retorna mesma estrutura com campos traduzidos

MÉTODO: translate_batch(array $texts, string $target_lang): array
- Traduz múltiplos textos em uma chamada (economizar créditos API)
- Google: aceita até 128 textos por chamada
- DeepL: aceita múltiplos text[] por chamada

IMPLEMENTAÇÃO GOOGLE:
- Endpoint: https://translation.googleapis.com/language/translate/v2
- Parâmetros: q[], target, source, key
- Parser da resposta em data.translations[].translatedText

IMPLEMENTAÇÃO DEEPL:
- Endpoint: https://api-free.deepl.com/v2/translate (free) ou https://api.deepl.com/v2/translate (pro)
- Detectar automaticamente pela chave (termina em :fx = free)
- Parâmetros: text[], target_lang, source_lang
- Parser: translations[].text

MÉTODO: get_supported_languages(string $provider): array
- Retornar lista de idiomas suportados
- Cache por 24h

MÉTODO: detect_language(string $text): string
- Detectar idioma do texto (útil para source_lang = 'auto')

Usar wp_remote_post para chamadas HTTP.
Incluir validação da chave API com método test_api_key(): bool
```

---

## 🔁 PROMPT 9 — Multilojas e WP-Cron

```
Implemente o suporte a multilojas e agendamentos em includes/class-odoo-woocommerce-sync.php (classe core).

MULTILOJAS:
Estrutura de configuração por loja:
- Cada "loja" é um conjunto de credenciais Odoo + opções de sync
- Armazenar como array em wp_options: odoo_sync_stores
- Suporte a até 10 lojas

MÉTODO: get_stores(): array
Retorna todas as lojas configuradas:
[
  'store_1' => [
    'name' => 'Loja Principal',
    'odoo_url' => '...',
    'odoo_db' => '...',
    'odoo_user' => '...',
    'odoo_password' => '...',  // criptografado
    'company_id' => 1,
    'sync_products' => true,
    'sync_orders' => true,
    'sync_stock' => true,
    'wc_store_id' => 0,  // 0 = loja principal
  ]
]

MÉTODO: run_sync_for_store(string $store_key): array
- Inicializar OdooAPI com credenciais da loja
- Executar syncs habilitados para aquela loja
- Retornar resultado consolidado

WP-CRON:

Registrar schedules personalizados:
- odoo_sync_every_5_minutes: a cada 5 minutos
- odoo_sync_every_15_minutes: a cada 15 minutos

Hooks cron:
- odoo_sync_products_cron → SyncProducts::sync_from_odoo()
- odoo_sync_orders_cron → processar pedidos pendentes
- odoo_sync_stock_cron → SyncStock::update_woocommerce_stock_from_odoo()
- odoo_cleanup_logs_cron → SyncLogger::cleanup_old_logs()

MÉTODO: activate_cron_jobs(): void (chamado na ativação do plugin)
MÉTODO: deactivate_cron_jobs(): void (chamado na desativação)

REST API ENDPOINTS:
Registrar em /wp-json/odoo-sync/v1/:
- GET /status → retorna status de todas as lojas
- POST /sync/products → aciona sync manual de produtos
- POST /sync/orders → aciona sync manual de pedidos  
- POST /sync/stock → aciona sync manual de estoque
- POST /webhook/stock → recebe atualizações de estoque do Odoo
- GET /logs → retorna logs recentes

Autenticação dos endpoints: Application Passwords do WordPress ou token customizado.
```

---

## ✅ PROMPT 10 — Testes, Validação e README

```
Crie os arquivos de teste e documentação do plugin.

ARQUIVO 1: tests/test-odoo-api.php
Testes PHPUnit para a classe OdooAPI:
- test_authenticate_success(): mock de resposta HTTP com uid válido
- test_authenticate_failure(): mock com credenciais inválidas
- test_search_read_returns_array(): mock de produtos
- test_connection_timeout_retry(): simular timeout e verificar 3 tentativas
- test_company_id_in_context(): verificar que company_id é passado nas chamadas

ARQUIVO 2: tests/test-sync-products.php
- test_import_simple_product(): produto sem variações
- test_import_variable_product(): produto com attribute_line_ids
- test_skip_already_synced(): produto com _odoo_product_id recente
- test_create_category_if_missing()
- test_export_to_odoo()

ARQUIVO 3: tests/test-sync-orders.php  
- test_export_order_creates_sale_order()
- test_status_mapping_processing_to_sale()
- test_customer_created_if_not_exists()

ARQUIVO 4: README.md
Criar documentação completa em português com:

# Plugin Odoo WooCommerce Sync

## Requisitos
- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+
- Odoo 14+ (XML-RPC ou JSON-RPC habilitado)

## Instalação
Passo a passo detalhado

## Configuração Inicial
1. Inserir credenciais Odoo
2. Testar conexão
3. Selecionar empresa
4. Configurar sync por entidade

## Configuração Multistores
Como configurar múltiplas lojas

## Mapeamento de Status de Pedidos
Tabela completa de mapeamento

## Tradução Automática
Como configurar Google Translate e DeepL

## Webhooks
Como configurar o Odoo para enviar atualizações de estoque

## Solução de Problemas
Erros comuns e como resolver

## Segurança
Como as credenciais são armazenadas

ARQUIVO 5: odoo-setup-guide.md
Guia de configuração no lado do Odoo:
- Habilitar XML-RPC/JSON-RPC
- Criar usuário API com permissões mínimas necessárias
- Configurar webhook de estoque (se aplicável)
- Permissões necessárias por modelo
```

---

## 🧪 PROMPT 11 (Opcional) — Configuração do Ambiente de Dev

```
Crie os arquivos de configuração para ambiente de desenvolvimento local.

ARQUIVO: docker-compose.yml
Ambiente com:
- WordPress + WooCommerce (imagem oficial)
- Odoo 17 Community
- PostgreSQL para o Odoo
- MySQL para WordPress
- Adminer para gerenciar bancos

ARQUIVO: .env.example
Variáveis necessárias para o ambiente

ARQUIVO: Makefile com comandos:
- make up: subir containers
- make down: derrubar
- make install-plugin: copiar plugin para wp-content/plugins
- make activate-plugin: ativar via WP-CLI
- make install-woocommerce: instalar e configurar WooCommerce
- make setup-odoo-demo: configurar dados de demo no Odoo

ARQUIVO: scripts/setup-demo-data.sh
Script para:
1. Criar produto de teste no Odoo
2. Configurar usuário API no Odoo
3. Configurar WooCommerce com produtos zerados para testar importação

ARQUIVO: .cursor/rules (Cursor Rules)
Regras para o Cursor seguir durante o desenvolvimento:
- Sempre usar wp_nonce para formulários
- Sanitizar inputs com sanitize_text_field, absint, etc.
- Usar $wpdb->prepare() para queries SQL
- Testar compatibilidade com PHP 7.4 e 8.x
- Documentar todos os métodos públicos com PHPDoc
- Usar constantes do plugin para versão e paths
```

---

## 📋 Ordem de Execução Recomendada

| # | Prompt | Tempo Estimado | Dependências |
|---|--------|----------------|--------------|
| 1 | Estrutura Base | ~5 min | Nenhuma |
| 2 | Cliente Odoo API | ~5 min | Prompt 1 |
| 3 | Painel Admin | ~8 min | Prompts 1-2 |
| 4 | Sync Produtos | ~8 min | Prompts 1-2 |
| 5 | Sync Pedidos/Clientes | ~6 min | Prompts 1-2 |
| 6 | Sync Estoque | ~5 min | Prompts 1-2 |
| 7 | Sistema de Logs | ~4 min | Prompt 1 |
| 8 | Tradução | ~4 min | Prompt 1 |
| 9 | Multilojas/Cron | ~5 min | Todos anteriores |
| 10 | Testes/README | ~5 min | Todos anteriores |
| 11 | Dev Environment | ~3 min | Opcional |

---

## 🛠️ Skills Necessárias para Desenvolvimento

### Skills Externas ao Plugin (não no Cursor)

| Skill/Tecnologia | Para quê | Nível necessário |
|------------------|----------|-----------------|
| **PHP OOP** | Toda a estrutura do plugin | Intermediário-Avançado |
| **WordPress Plugin API** | Hooks, Options API, REST API, WP-Cron | Intermediário |
| **WooCommerce Hooks & CRUD** | Manipular produtos, pedidos, estoque | Intermediário |
| **Odoo XML-RPC/JSON-RPC** | Comunicação com Odoo | Básico-Intermediário |
| **WordPress Settings API** | Painel de configurações | Básico |
| **MySQL + $wpdb** | Tabela de logs customizada | Básico |
| **JavaScript/AJAX** | Interações no admin | Básico |
| **PHPUnit** | Testes automatizados | Básico |

### Recursos de Referência

- [WordPress Plugin Boilerplate](https://wppb.me/)
- [WooCommerce REST API Docs](https://woocommerce.github.io/woocommerce-rest-api-docs/)
- [Odoo External API Docs](https://www.odoo.com/documentation/17.0/developer/reference/external_api.html)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)

---

*Gerado para projeto: Plugin Integração Odoo ↔ WooCommerce*
*Baseado nos contratos e requisitos de operação Brasil — RTPS / Kiehberg*
