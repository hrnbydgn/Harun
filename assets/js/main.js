$(function () {
    // Sidebar toggle
    $('#sidebarToggle').on('click', function () {
        $('#sidebar').toggleClass('show');
        $('#sidebarOverlay').toggle();
    });
    $('#sidebarOverlay').on('click', function () {
        $('#sidebar').removeClass('show');
        $(this).hide();
    });

    // Flatpickr init
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.datepicker', {
            locale: 'tr',
            dateFormat: 'Y-m-d',
            allowInput: true
        });
        flatpickr('.datepicker-display', {
            locale: 'tr',
            dateFormat: 'd.m.Y',
            allowInput: true
        });
    }

    // Select2 init
    if (typeof $.fn.select2 !== 'undefined') {
        $('.select2').select2({ theme: 'bootstrap-5', width: '100%' });
        $('.select2-ajax-customer').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Müşteri seçin...',
            ajax: {
                url: BASE_URL + '/customers/search.php',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data }; }
            }
        });
    }

    // DataTable init
    if (typeof $.fn.DataTable !== 'undefined') {
        $('.datatable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
            },
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']]
        });
    }

    // Auto close alerts
    setTimeout(function () {
        $('.alert.auto-close').fadeOut();
    }, 4000);

    // Confirm delete
    $(document).on('click', '.confirm-delete', function (e) {
        if (!confirm('Bu kaydı silmek istediğinizden emin misiniz?')) {
            e.preventDefault();
        }
    });

    // Number format
    window.formatNum = function (n, decimals = 2) {
        return parseFloat(n || 0).toLocaleString('tr-TR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    };

    // Currency symbol
    window.currencySymbols = { TRY: '₺', USD: '$', EUR: '€', GBP: '£' };

    window.getCurrencySymbol = function (c) {
        return window.currencySymbols[c] || c;
    };
});

// Sidebar overlay element
$(function () {
    if ($('#sidebarOverlay').length === 0) {
        $('body').append('<div id="sidebarOverlay"></div>');
    }
});
