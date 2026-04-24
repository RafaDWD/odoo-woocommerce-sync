(function ($) {
    'use strict';

    function setInlineResult(selector, message, ok) {
        var $el = $(selector);
        $el.text(message || '');
        $el.removeClass('ok fail').addClass(ok ? 'ok' : 'fail');
    }

    function ajaxPost(data) {
        return $.post(odooSyncAdmin.ajaxUrl, data);
    }

    function setProgress(progress) {
        var value = Math.max(0, Math.min(100, progress));
        $('.odoo-progress span').css('width', value + '%');
    }

    $(function () {
        $('#odoo-test-connection').on('click', function () {
            setInlineResult('#odoo-test-connection-result', odooSyncAdmin.messages.testing, true);
            ajaxPost({
                action: 'odoo_sync_test_connection',
                nonce: odooSyncAdmin.nonce
            }).done(function (res) {
                if (res.success) {
                    setInlineResult('#odoo-test-connection-result', res.data.message + (res.data.version ? ' (v' + res.data.version + ')' : ''), true);
                } else {
                    setInlineResult('#odoo-test-connection-result', (res.data && res.data.message) || 'Falha ao conectar.', false);
                }
            }).fail(function (xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Erro de comunicacao.';
                setInlineResult('#odoo-test-connection-result', msg, false);
            });
        });

        $('#odoo-load-companies').on('click', function () {
            var $companySelect = $('#company_id');
            ajaxPost({
                action: 'odoo_sync_load_companies',
                nonce: odooSyncAdmin.nonce
            }).done(function (res) {
                if (!res.success) {
                    setInlineResult('#odoo-test-connection-result', (res.data && res.data.message) || 'Falha ao carregar empresas.', false);
                    return;
                }
                var selected = String($companySelect.data('selected') || '0');
                $companySelect.empty().append('<option value="0">Selecione</option>');
                (res.data.companies || []).forEach(function (company) {
                    var id = String(company.id || 0);
                    var selectedAttr = id === selected ? ' selected="selected"' : '';
                    $companySelect.append('<option value="' + id + '"' + selectedAttr + '>' + company.name + ' (#' + id + ')</option>');
                });
                setInlineResult('#odoo-test-connection-result', 'Empresas carregadas.', true);
            }).fail(function (xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Erro ao carregar empresas.';
                setInlineResult('#odoo-test-connection-result', msg, false);
            });
        });

        $('.odoo-run-sync').on('click', function () {
            var syncType = $(this).data('sync');
            setProgress(0);
            setInlineResult('#odoo-test-connection-result', odooSyncAdmin.messages.syncing, true);

            ajaxPost({
                action: 'odoo_sync_run_manual_sync',
                nonce: odooSyncAdmin.nonce,
                sync_type: syncType
            }).done(function (res) {
                if (!res.success) {
                    setInlineResult('#odoo-test-connection-result', (res.data && res.data.message) || 'Falha na sincronizacao.', false);
                    return;
                }
                var progress = res.data.progress || [];
                progress.forEach(function (p, idx) {
                    setTimeout(function () {
                        setProgress(p);
                    }, idx * 100);
                });
                setTimeout(function () {
                    setInlineResult('#odoo-test-connection-result', res.data.message || 'Sincronizacao finalizada.', true);
                }, (progress.length + 1) * 100);
            }).fail(function (xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Erro na sincronizacao.';
                setInlineResult('#odoo-test-connection-result', msg, false);
            });
        });

        $('#odoo-cleanup-logs').on('click', function () {
            ajaxPost({ action: 'odoo_sync_cleanup_logs', nonce: odooSyncAdmin.nonce }).done(function (res) {
                setInlineResult('#odoo-logs-result', res.data.message || 'Logs limpos.', !!res.success);
            });
        });

        $('#odoo-reprocess-failures').on('click', function () {
            ajaxPost({ action: 'odoo_sync_reprocess_failures', nonce: odooSyncAdmin.nonce }).done(function (res) {
                setInlineResult('#odoo-logs-result', res.data.message || 'Falhas reprocessadas.', !!res.success);
            });
        });

        $('#odoo-export-logs').on('click', function () {
            // Download direto via redirect — o endpoint envia Content-Disposition: attachment
            var url = odooSyncAdmin.ajaxUrl
                + '?action=odoo_sync_export_logs'
                + '&nonce=' + encodeURIComponent(odooSyncAdmin.nonce);
            window.location.href = url;
        });

        var translationEnabled = $('#translation_enabled').is(':checked');
        $('.translation-row').toggle(translationEnabled);
        $('#translation_enabled').on('change', function () {
            $('.translation-row').toggle($(this).is(':checked'));
        });
    });
})(jQuery);
