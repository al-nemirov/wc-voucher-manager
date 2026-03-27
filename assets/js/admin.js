(function($) {
    'use strict';

    var allCodes = [];
    var isGenerating = false;
    var i18n = (wcVoucher && wcVoucher.i18n) ? wcVoucher.i18n : {};

    function updatePreview() {
        var prefix = $('#voucher_prefix').val() || 'VOUCHER-';
        var len = parseInt($('#voucher_code_length').val()) || 8;
        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        var sample = '';
        for (var i = 0; i < Math.min(len, 12); i++) {
            sample += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        $('#voucher-preview-code').text(prefix.toUpperCase() + sample);
    }

    $('#voucher_prefix, #voucher_code_length').on('input change', updatePreview);
    if ($('#voucher_prefix').length) updatePreview();

    $('#btn-generate-vouchers').on('click', function() {
        if (isGenerating) return;
        var count = parseInt($('#voucher_count').val()) || 10;
        if (count < 1 || count > 1000) { showAdminToast(i18n.count_error || 'Count: 1-1000', 'error'); return; }
        var amount = parseFloat($('#voucher_amount').val());
        if (!amount || amount <= 0) { showAdminToast(i18n.amount_error || 'Set amount', 'error'); return; }

        isGenerating = true;
        allCodes = [];
        $(this).prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="vertical-align:text-bottom;margin-right:4px"></span> ' + (i18n.creating || 'Creating...'));
        $('#voucher-progress').slideDown(300);
        $('#voucher-result').slideUp(200);
        $('#voucher-progress-total').text(count);
        $('#voucher-progress-count').text(0);
        $('.voucher-progress-fill').css('width', '0%');
        $('.voucher-progress-percent').text('0%');
        generateBatch(0, count, generateUUID());
    });

    function generateBatch(offset, total, batchId) {
        $.ajax({
            url: wcVoucher.ajaxUrl, type: 'POST', timeout: 30000,
            data: {
                action: 'wc_voucher_generate_batch', nonce: wcVoucher.nonce,
                count: total, offset: offset, batch_id: batchId,
                prefix: $('#voucher_prefix').val(), code_length: $('#voucher_code_length').val(),
                discount_type: $('#voucher_discount_type').val(), amount: $('#voucher_amount').val(),
                expiry: $('#voucher_expiry').val(), usage_limit: $('#voucher_usage_limit').val(),
                min_amount: $('#voucher_min_amount').val(),
                individual: $('#voucher_individual').is(':checked') ? 'yes' : 'no',
                free_shipping: $('#voucher_free_shipping').is(':checked') ? 'yes' : 'no'
            },
            success: function(response) {
                if (!response.success) { showAdminToast(response.data || i18n.unknown_error || 'Error', 'error'); resetGenerator(); return; }
                var data = response.data;
                allCodes = allCodes.concat(data.codes);
                var pct = Math.round((data.offset / data.total) * 100);
                $('.voucher-progress-fill').css('width', pct + '%');
                $('.voucher-progress-percent').text(pct + '%');
                $('#voucher-progress-count').text(data.offset);
                if (data.done) showResult(); else generateBatch(data.offset, total, batchId);
            },
            error: function(xhr) {
                var msg = i18n.server_error || 'Server error';
                if (xhr.status === 0) msg = i18n.no_connection || 'No connection';
                else if (xhr.status === 504) msg = i18n.timeout || 'Timeout';
                showAdminToast(msg, 'error');
                resetGenerator();
            }
        });
    }

    function showResult() {
        $('.voucher-progress-fill').css('width', '100%');
        $('.voucher-progress-percent').text('100%');
        setTimeout(function() {
            $('#voucher-progress').slideUp(300);
            $('#voucher-result').slideDown(400);
            $('#voucher-result-count').text(allCodes.length);
            $('#voucher-result-codes').val(allCodes.join('\n'));
            resetGenerator();
            showAdminToast((i18n.created || 'Created %d vouchers!').replace('%d', allCodes.length), 'success');
        }, 500);
    }

    function resetGenerator() {
        isGenerating = false;
        $('#btn-generate-vouchers').prop('disabled', false).html('<span class="dashicons dashicons-tickets-alt" style="vertical-align:text-bottom;margin-right:4px"></span> ' + (i18n.create_btn || 'Create vouchers'));
    }

    $('#btn-download-csv').on('click', function(e) {
        e.preventDefault();
        if (!allCodes.length) return;
        var csv = '\uFEFF' + 'Voucher code\n' + allCodes.join('\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'vouchers-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
        showAdminToast(i18n.csv_done || 'CSV downloaded', 'success');
    });

    $(document).on('click', '#btn-copy-codes', function() {
        var ta = document.getElementById('voucher-result-codes');
        if (!ta || !ta.value) return;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(ta.value).then(function() { showAdminToast(i18n.copied || 'Copied', 'success'); });
        } else {
            ta.select(); document.execCommand('copy'); showAdminToast(i18n.copied || 'Copied', 'success');
        }
    });

    function showAdminToast(message, type) {
        var bg = type === 'error' ? '#d63638' : '#00a32a';
        var icon = type === 'error' ? '&#10006;' : '&#10004;';
        var toast = $('<div class="voucher-admin-toast" style="position:fixed;top:40px;right:20px;z-index:999999;background:' + bg + ';color:#fff;padding:12px 20px 12px 16px;border-radius:10px;font-size:14px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,0.15);display:flex;align-items:center;gap:10px;max-width:400px;animation:vToastIn .4s cubic-bezier(.34,1.56,.64,1)"><span style="font-size:18px">' + icon + '</span><span>' + message + '</span></div>');
        if (!$('#voucher-toast-keyframes').length) {
            $('head').append('<style id="voucher-toast-keyframes">@keyframes vToastIn{0%{opacity:0;transform:translateX(60px)}100%{opacity:1;transform:translateX(0)}}.spin{animation:spin 1s linear infinite}@keyframes spin{100%{transform:rotate(360deg)}}</style>');
        }
        $('body').append(toast);
        setTimeout(function() { toast.css({transition:'opacity .3s,transform .3s',opacity:0,transform:'translateX(60px)'}); setTimeout(function(){toast.remove();},300); }, 3500);
    }

    function generateUUID() {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) { var r = Math.random()*16|0; return (c==='x'?r:(r&0x3|0x8)).toString(16); });
    }

})(jQuery);
