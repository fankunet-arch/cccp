/**
 * ABCABC CP Platform - Main JavaScript
 * 主要JavaScript功能
 * Version: 2.0
 */

(function($) {
    'use strict';

    // ==================== Sidebar Toggle ====================
    function initSidebarToggle() {
        const $sidebar = $('.main-sidebar');
        const $backdrop = $('.sidebar-backdrop');
        const $toggleBtn = $('.sidebar-toggle-btn');

        // Toggle sidebar on mobile
        $toggleBtn.on('click', function() {
            $sidebar.toggleClass('show');
            $backdrop.toggleClass('show');
        });

        // Close sidebar when clicking backdrop
        $backdrop.on('click', function() {
            $sidebar.removeClass('show');
            $backdrop.removeClass('show');
        });
    }

    // ==================== Tree Menu Toggle ====================
    function initTreeMenu() {
        $('.menu-toggle').on('click', function(e) {
            e.preventDefault();
            const $parent = $(this).closest('.treeview');
            const $menu = $parent.find('.treeview-menu').first();

            // Toggle current menu
            $parent.toggleClass('menu-open');
            $menu.slideToggle(300);
        });
    }

    // ==================== Alert Auto Dismiss ====================
    function initAlertAutoDismiss() {
        // Auto dismiss alerts after 5 seconds
        $('.alert, .feedback-bar').each(function() {
            const $alert = $(this);
            setTimeout(function() {
                $alert.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        });

        // Manual close button
        $('.close').on('click', function() {
            $(this).closest('.alert, .feedback-bar').fadeOut(300, function() {
                $(this).remove();
            });
        });
    }

    // ==================== Form Validation Helpers ====================
    function initFormValidation() {
        // Add validation styling
        $('form').on('submit', function(e) {
            const $form = $(this);
            let isValid = true;

            // Check required fields
            $form.find('[required]').each(function() {
                const $field = $(this);
                if (!$field.val() || $field.val().trim() === '') {
                    $field.addClass('is-invalid');
                    isValid = false;
                } else {
                    $field.removeClass('is-invalid');
                }
            });

            if (!isValid) {
                e.preventDefault();
                showAlert('请填写所有必填项', 'error');
            }
        });

        // Remove error styling on input
        $('[required]').on('input change', function() {
            $(this).removeClass('is-invalid');
        });
    }

    // ==================== Show Alert ====================
    function showAlert(message, type = 'info') {
        const iconMap = {
            success: 'check',
            error: 'ban',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };

        const icon = iconMap[type] || 'info-circle';
        const alertClass = type === 'error' ? 'alert-danger' : `alert-${type}`;

        const $alert = $(`
            <div class="alert ${alertClass}" role="alert" style="animation: slideDown 0.3s ease;">
                <i class="fas fa-${icon}"></i>
                <span>${message}</span>
                <button type="button" class="close" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `);

        // Insert at top of content
        $('.view-content-wrapper').prepend($alert);

        // Auto dismiss
        setTimeout(function() {
            $alert.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);

        // Manual close
        $alert.find('.close').on('click', function() {
            $alert.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }

    // ==================== Table Sorting ====================
    function initTableSorting() {
        $('.table th[data-sortable]').css('cursor', 'pointer').on('click', function() {
            const $th = $(this);
            const $table = $th.closest('table');
            const columnIndex = $th.index();
            const $tbody = $table.find('tbody');
            const rows = $tbody.find('tr').toArray();

            const isAscending = $th.hasClass('sort-asc');

            // Remove sorting classes from all headers
            $table.find('th').removeClass('sort-asc sort-desc');

            // Sort rows
            rows.sort(function(a, b) {
                const aValue = $(a).find('td').eq(columnIndex).text().trim();
                const bValue = $(b).find('td').eq(columnIndex).text().trim();

                // Try to parse as number
                const aNum = parseFloat(aValue.replace(/[^0-9.-]/g, ''));
                const bNum = parseFloat(bValue.replace(/[^0-9.-]/g, ''));

                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return isAscending ? bNum - aNum : aNum - bNum;
                } else {
                    return isAscending
                        ? bValue.localeCompare(aValue)
                        : aValue.localeCompare(bValue);
                }
            });

            // Update DOM
            $tbody.html(rows);

            // Add sorting class
            $th.addClass(isAscending ? 'sort-desc' : 'sort-asc');
        });
    }

    // ==================== Number Formatting ====================
    function formatNumber(num, decimals = 2) {
        return parseFloat(num).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function formatCurrency(num, currency = 'EUR', decimals = 2) {
        const formatted = formatNumber(num, decimals);
        const symbols = {
            EUR: '€',
            USD: '$',
            CNY: '¥'
        };
        return `${symbols[currency] || currency} ${formatted}`;
    }

    // ==================== Date Formatting ====================
    function formatDate(dateStr, format = 'YYYY-MM-DD') {
        const date = new Date(dateStr);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return format
            .replace('YYYY', year)
            .replace('MM', month)
            .replace('DD', day);
    }

    // ==================== Loading Indicator ====================
    function showLoading($element) {
        $element.addClass('loading').prop('disabled', true);
        if ($element.is('button')) {
            $element.data('original-text', $element.html());
            $element.html('<i class="fas fa-spinner fa-spin"></i> 加载中...');
        }
    }

    function hideLoading($element) {
        $element.removeClass('loading').prop('disabled', false);
        if ($element.is('button') && $element.data('original-text')) {
            $element.html($element.data('original-text'));
        }
    }

    // ==================== Confirmation Dialog ====================
    function confirm(message, callback) {
        if (window.confirm(message)) {
            callback();
        }
    }

    // ==================== AJAX Error Handler ====================
    function handleAjaxError(xhr, status, error) {
        console.error('AJAX Error:', status, error);
        showAlert('操作失败，请稍后重试', 'error');
    }

    // ==================== Money Input Formatting ====================
    function initMoneyInputs() {
        $('[data-is-money="true"]').on('blur', function() {
            const $input = $(this);
            const value = $input.val().replace(/[^0-9.-]/g, '');
            if (value && !isNaN(value)) {
                $input.val(parseFloat(value).toFixed(2));
            }
        });
    }

    // ==================== Export Utilities ====================
    window.CPUtils = {
        showAlert: showAlert,
        formatNumber: formatNumber,
        formatCurrency: formatCurrency,
        formatDate: formatDate,
        showLoading: showLoading,
        hideLoading: hideLoading,
        confirm: confirm,
        handleAjaxError: handleAjaxError
    };

    // ==================== Initialize on Document Ready ====================
    $(document).ready(function() {
        initSidebarToggle();
        initTreeMenu();
        initAlertAutoDismiss();
        initFormValidation();
        initTableSorting();
        initMoneyInputs();

        console.log('CP Platform initialized');
    });

})(jQuery);
