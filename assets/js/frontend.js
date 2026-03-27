(function($) {
    'use strict';

    // === Show toast when voucher is applied ===
    $(document).ready(function() {
        if (typeof window.wcVoucherToast !== 'undefined' && window.wcVoucherToast) {
            setTimeout(function() {
                showVoucherToast(window.wcVoucherToast);
                window.wcVoucherToast = null;
            }, 600);
        }
    });

    // Also listen for WooCommerce AJAX coupon apply
    $(document.body).on('applied_coupon', function() {
        // Small delay to let the server set the transient
        setTimeout(function() {
            if (typeof window.wcVoucherToast !== 'undefined' && window.wcVoucherToast) {
                showVoucherToast(window.wcVoucherToast);
                window.wcVoucherToast = null;
            }
        }, 300);
    });

    function showVoucherToast(data) {
        // Remove any existing toast
        $('.voucher-toast').remove();

        var html = '' +
            '<div class="voucher-toast">' +
                '<div class="voucher-toast-gradient"></div>' +
                '<button class="voucher-toast-close" type="button">&times;</button>' +
                '<div class="voucher-toast-body">' +
                    '<div class="voucher-toast-emoji">&#127881;</div>' +
                    '<div class="voucher-toast-content">' +
                        '<div class="voucher-toast-title">Ваучер применён!</div>' +
                        '<div class="voucher-toast-code">' + escapeHtml(data.code) + '</div>' +
                        '<div class="voucher-toast-discount">-' + escapeHtml(data.discount) + '</div>' +
                        '<div class="voucher-toast-message">' + escapeHtml(data.message) + '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

        var $toast = $(html).appendTo('body');

        // Show confetti
        showConfetti();

        // Close button
        $toast.find('.voucher-toast-close').on('click', function() {
            $toast.addClass('voucher-toast-out');
            setTimeout(function() { $toast.remove(); }, 400);
        });

        // Auto-hide after 6 seconds
        setTimeout(function() {
            if ($toast.length && !$toast.hasClass('voucher-toast-out')) {
                $toast.addClass('voucher-toast-out');
                setTimeout(function() { $toast.remove(); }, 400);
            }
        }, 6000);
    }

    function showConfetti() {
        var container = $('<div class="voucher-confetti-container"></div>').appendTo('body');
        var colors = ['#ff6b6b', '#ffd93d', '#6bcb77', '#4d96ff', '#ff6ec7', '#845ef7'];

        for (var i = 0; i < 40; i++) {
            var color = colors[Math.floor(Math.random() * colors.length)];
            var left = Math.random() * 100;
            var delay = Math.random() * 0.8;
            var size = 6 + Math.random() * 8;
            var duration = 2 + Math.random() * 2;

            var particle = $(
                '<div class="voucher-confetti" style="' +
                'left:' + left + '%;' +
                'background:' + color + ';' +
                'width:' + size + 'px;' +
                'height:' + size + 'px;' +
                'animation-delay:' + delay + 's;' +
                'animation-duration:' + duration + 's;' +
                'top:-10px;' +
                '"></div>'
            );
            container.append(particle);
        }

        // Clean up after animation
        setTimeout(function() {
            container.remove();
        }, 4000);
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

})(jQuery);
