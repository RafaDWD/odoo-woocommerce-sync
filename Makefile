up:
	docker compose up -d

down:
	docker compose down

install-plugin:
	docker compose exec wordpress bash -lc "mkdir -p /var/www/html/wp-content/plugins/odoo-woocommerce-sync"

activate-plugin:
	docker compose exec wordpress bash -lc "wp plugin activate odoo-woocommerce-sync --allow-root || true"

install-woocommerce:
	docker compose exec wordpress bash -lc "wp plugin install woocommerce --activate --allow-root || true"

setup-odoo-demo:
	bash scripts/setup-demo-data.sh
