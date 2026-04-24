<?php
namespace DWDOdooWooSync;

if (!defined('ABSPATH')) {
    exit;
}

class SyncLogger
{
    protected $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'odoo_sync_logs';
    }

    public function log($status, $type, $direction, $entity_id, $message, $odoo_id = null, array $details = [])
    {
        global $wpdb;
        $wpdb->insert(
            $this->table,
            [
                'sync_type' => sanitize_text_field((string) $type),
                'direction' => sanitize_text_field((string) $direction),
                'entity_id' => sanitize_text_field((string) $entity_id),
                'odoo_id' => $odoo_id ? sanitize_text_field((string) $odoo_id) : null,
                'status' => sanitize_text_field((string) $status),
                'message' => wp_kses_post((string) $message),
                'details' => wp_json_encode($details),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    public function log_success(string $type, string $direction, $entity_id, $odoo_id, string $message, array $details = []): void
    {
        $this->log('success', $type, $direction, $entity_id, $message, $odoo_id, $details);
    }

    public function log_error(string $type, string $direction, $entity_id, string $message, array $details = []): void
    {
        $this->log('error', $type, $direction, $entity_id, $message, null, $details);
    }

    public function log_warning(string $type, string $direction, $entity_id, string $message, array $details = []): void
    {
        $this->log('warning', $type, $direction, $entity_id, $message, null, $details);
    }

    public function get_logs(array $filters = [], int $page = 1, int $per_page = 20): array
    {
        global $wpdb;

        $page     = max(1, $page);
        $per_page = max(1, $per_page);
        $offset   = ($page - 1) * $per_page;

        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['type'])) {
            $where[]  = 'sync_type = %s';
            $params[] = sanitize_text_field((string) $filters['type']);
        }
        if (!empty($filters['status'])) {
            $where[]  = 'status = %s';
            $params[] = sanitize_text_field((string) $filters['status']);
        }
        if (!empty($filters['entity_id'])) {
            $where[]  = 'entity_id = %s';
            $params[] = sanitize_text_field((string) $filters['entity_id']);
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'created_at >= %s';
            $params[] = sanitize_text_field((string) $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'created_at <= %s';
            $params[] = sanitize_text_field((string) $filters['date_to']);
        }

        $where_sql   = implode(' AND ', $where);
        $count_sql   = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}";
        $total       = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));

        $data_sql    = "SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $data_params = array_merge($params, [$per_page, $offset]);
        $rows        = $wpdb->get_results($wpdb->prepare($data_sql, $data_params), ARRAY_A);

        return [
            'items'      => is_array($rows) ? $rows : [],
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => (int) ceil($total / max(1, $per_page)),
            ],
        ];
    }

    public function get_error_count(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = %s", 'error'));
    }

    public function get_pending_reprocess(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE status = %s AND reprocessed = %d ORDER BY created_at DESC", 'error', 0),
            ARRAY_A
        );
    }

    public function reprocess_failed(array $log_ids): array
    {
        global $wpdb;

        $result = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'messages' => [],
        ];

        foreach ($log_ids as $log_id) {
            $log_id = absint($log_id);
            if ($log_id <= 0) {
                continue;
            }

            $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $log_id), ARRAY_A);
            if (!$log || $log['status'] !== 'error') {
                continue;
            }

            $result['processed']++;
            $details = json_decode((string) ($log['details'] ?? ''), true);
            $details = is_array($details) ? $details : [];

            $ok = $this->attempt_reprocess($log, $details);
            $wpdb->update($this->table, ['reprocessed' => 1], ['id' => $log_id], ['%d'], ['%d']);

            if ($ok) {
                $result['succeeded']++;
                $result['messages'][] = sprintf(__('Log #%d reprocessado com sucesso.', DWD_OWS_TEXT_DOMAIN), $log_id);
            } else {
                $result['failed']++;
                $result['messages'][] = sprintf(__('Falha ao reprocessar log #%d.', DWD_OWS_TEXT_DOMAIN), $log_id);
            }
        }

        return $result;
    }

    public function cleanup_old_logs(int $days = 30): int
    {
        global $wpdb;
        $days = max(1, $days);
        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days));
        return (int) $deleted;
    }

    public function export_csv(array $filters = []): string
    {
        $data = $this->get_logs($filters, 1, 5000);
        $rows = $data['items'] ?? [];

        $upload = wp_upload_dir();
        $file_path = trailingslashit($upload['basedir']) . 'odoo-sync-logs-' . gmdate('Ymd-His') . '.csv';
        $fh = fopen($file_path, 'w');
        if (!$fh) {
            return '';
        }

        fputcsv($fh, ['id', 'created_at', 'sync_type', 'direction', 'entity_id', 'odoo_id', 'status', 'message']);
        foreach ($rows as $row) {
            fputcsv($fh, [
                $row['id'] ?? '',
                $row['created_at'] ?? '',
                $row['sync_type'] ?? '',
                $row['direction'] ?? '',
                $row['entity_id'] ?? '',
                $row['odoo_id'] ?? '',
                $row['status'] ?? '',
                $row['message'] ?? '',
            ]);
        }
        fclose($fh);

        return $file_path;
    }

    public function get_stats(): array
    {
        global $wpdb;

        $today = gmdate('Y-m-d');

        $total_today   = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE DATE(created_at) = %s",
            $today
        ));
        $success_today = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE DATE(created_at) = %s AND status = %s",
            $today, 'success'
        ));
        $errors_today  = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE DATE(created_at) = %s AND status = %s",
            $today, 'error'
        ));
        $last_sync     = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$this->table} ORDER BY created_at DESC LIMIT %d",
            1
        ));

        $by_type_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT sync_type, COUNT(*) as total FROM {$this->table} GROUP BY sync_type LIMIT %d",
                50
            ),
            ARRAY_A
        );

        $by_type = [];
        foreach ((array) $by_type_rows as $row) {
            $by_type[(string) $row['sync_type']] = (int) $row['total'];
        }

        return [
            'total_today'   => $total_today,
            'success_today' => $success_today,
            'errors_today'  => $errors_today,
            'last_sync'     => $last_sync,
            'by_type'       => $by_type,
        ];
    }

    private function attempt_reprocess(array $log, array $details): bool
    {
        static $reprocessing = false;
        if ($reprocessing) {
            return false; // Evita loop de reentrada
        }

        $type      = (string) ($log['sync_type'] ?? '');
        $direction = (string) ($log['direction'] ?? '');
        $entity_id = (int) ($log['entity_id'] ?? 0);

        $reprocessing = true;
        try {
            switch ($type) {
            case 'product':
                if ($direction === 'to_odoo' && $entity_id > 0) {
                    do_action('woocommerce_update_product', $entity_id);
                    return true;
                }
                return false;
            case 'order':
                if ($direction === 'to_odoo' && $entity_id > 0) {
                    do_action('woocommerce_checkout_order_processed', $entity_id);
                    return true;
                }
                return false;
            case 'stock':
                do_action('odoo_sync_stock_hourly');
                return true;
            case 'customer':
                if (!empty($details['order_id'])) {
                    do_action('woocommerce_checkout_order_processed', (int) $details['order_id']);
                    return true;
                }
                return false;
            default:
                return false;
        }
        } finally {
            $reprocessing = false;
        }
    }
}
