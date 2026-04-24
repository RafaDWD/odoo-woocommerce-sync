# Guia de Configuracao no Odoo

## 1) Habilitar XML-RPC e JSON-RPC

1. Acesse o servidor do Odoo.
2. Garanta que a instancia esteja acessivel externamente via HTTPS.
3. Verifique se os endpoints estao ativos:
   - XML-RPC: `/xmlrpc/2/common` e `/xmlrpc/2/object`
   - JSON-RPC: `/web/session/authenticate` e `/web/dataset/call_kw`

## 2) Criar usuario de API

1. Crie um usuario dedicado ao plugin.
2. Use senha forte e nao reutilizada.
3. Restrinja acesso ao minimo necessario.

## 3) Permissoes minimas por modelo

Conceda acesso de leitura/escrita apenas ao necessario:

- `product.template`
- `product.product`
- `res.partner`
- `sale.order`
- `res.company`
- `res.country`
- `stock.quant` (quando usar localizacao de estoque)

## 4) Configurar webhook de estoque (opcional)

Se usar modo em tempo real:

1. Configure no Odoo uma chamada HTTP para:
   - `POST https://SEU-WP/wp-json/odoo-sync/v1/stock-webhook`
2. Envie payload com:
   - `product_id`
   - `new_qty`
   - `location_id`
3. Inclua header:
   - `x-odoo-sync-token: <token_configurado_no_plugin>`

## 5) Boas praticas

- Use HTTPS valido.
- Monitore logs de erro no WordPress e Odoo.
- Teste primeiro em homologacao.
- Limite o usuario de API ao escopo do projeto.
