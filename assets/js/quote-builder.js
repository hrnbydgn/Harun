var itemIndex = 0;

var units = ['Adet', 'Kg', 'Lt', 'M', 'M²', 'M³', 'Paket', 'Kutu', 'Saat', 'Gün', 'Ay', 'Yıl', 'Set', 'Takım', 'Hizmet'];
var taxRates = [0, 1, 8, 10, 18, 20];

function unitOptions(selected) {
    return units.map(function(u) {
        return '<option value="' + u + '"' + (u === selected ? ' selected' : '') + '>' + u + '</option>';
    }).join('');
}

function taxOptions(selected) {
    return taxRates.map(function(t) {
        return '<option value="' + t + '"' + (t == selected ? ' selected' : '') + '>%' + t + '</option>';
    }).join('');
}

function addItemRow(data) {
    data = data || {};
    var idx = itemIndex++;
    var row = $('<tr class="item-row" data-idx="' + idx + '">');
    row.html(
        '<td><span class="item-row-handle"><i class="bi bi-grip-vertical"></i></span></td>' +
        '<td><input type="text" name="item_code[]" class="form-control item-code" value="' + (data.code || '') + '" placeholder="SKU"></td>' +
        '<td>' +
            '<input type="text" name="item_name[]" class="form-control item-name mb-1" value="' + escHtml(data.name || '') + '" placeholder="Ürün/Hizmet Adı" required>' +
            '<input type="text" name="item_desc[]" class="form-control item-desc" style="font-size:12px" value="' + escHtml(data.desc || '') + '" placeholder="Açıklama (isteğe bağlı)">' +
            '<input type="hidden" name="item_product_id[]" class="item-pid" value="' + (data.id || '') + '">' +
        '</td>' +
        '<td><select name="item_unit[]" class="form-control item-unit">' + unitOptions(data.unit || 'Adet') + '</select></td>' +
        '<td><input type="number" name="item_qty[]" class="form-control item-qty" value="' + (data.qty || 1) + '" step="0.001" min="0.001" required></td>' +
        '<td><input type="number" name="item_price[]" class="form-control item-price" value="' + (data.price || 0).toFixed(2) + '" step="0.01" min="0" required></td>' +
        '<td><input type="number" name="item_disc[]" class="form-control item-disc" value="' + (data.disc || 0) + '" step="0.01" min="0" max="100"></td>' +
        '<td><select name="item_tax[]" class="form-control item-tax">' + taxOptions(data.tax !== undefined ? data.tax : DEFAULT_TAX) + '</select></td>' +
        '<td><span class="item-total" style="font-weight:700;color:var(--primary);white-space:nowrap">' + getCurrencySymbol(DEFAULT_CURRENCY) + '0,00</span></td>' +
        '<td><button type="button" class="btn btn-danger btn-sm btn-icon" onclick="removeRow(this)"><i class="bi bi-x"></i></button></td>'
    );
    $('#itemsBody').append(row);
    row.find('.item-qty, .item-price, .item-disc, .item-tax').on('input change', function() { calcRow($(this).closest('tr')); });
    calcRow(row);
    row.find('.item-name').focus();
    updateTotals();
    return row;
}

function addProductFromSearch(el) {
    var data = {
        id: el.dataset.id,
        name: el.dataset.name,
        code: el.dataset.code,
        desc: el.dataset.desc,
        unit: el.dataset.unit,
        price: parseFloat(el.dataset.price),
        tax: parseFloat(el.dataset.tax),
        qty: 1
    };
    addItemRow(data);
    // Close dropdown
    $('.dropdown-menu#productSearchDropdown, .dropdown-menu[id=productSearchDropdown]').closest('.dropdown').find('.dropdown-toggle').dropdown('hide');
    $('.dropdown .dropdown-menu').removeClass('show');
}

function removeRow(btn) {
    $(btn).closest('tr').remove();
    if ($('#itemsBody tr').length === 0) addItemRow();
    updateTotals();
}

function calcRow(row) {
    var qty = parseFloat(row.find('.item-qty').val()) || 0;
    var price = parseFloat(row.find('.item-price').val()) || 0;
    var disc = parseFloat(row.find('.item-disc').val()) || 0;
    var tax = parseFloat(row.find('.item-tax').val()) || 0;
    
    var lineSubtotal = qty * price;
    var lineDiscount = lineSubtotal * (disc / 100);
    var lineAfterDisc = lineSubtotal - lineDiscount;
    var lineTax = lineAfterDisc * (tax / 100);
    var lineTotal = lineAfterDisc + lineTax;
    
    row.find('.item-total').text(getCurrencySymbol(getSelectedCurrency()) + formatNum(lineTotal));
    updateTotals();
}

function getSelectedCurrency() {
    return $('#currencySelect').val() || DEFAULT_CURRENCY;
}

function updateTotals() {
    var subtotal = 0, taxTotal = 0;
    $('#itemsBody tr').each(function() {
        var row = $(this);
        var qty = parseFloat(row.find('.item-qty').val()) || 0;
        var price = parseFloat(row.find('.item-price').val()) || 0;
        var disc = parseFloat(row.find('.item-disc').val()) || 0;
        var tax = parseFloat(row.find('.item-tax').val()) || 0;
        var lineSubtotal = qty * price;
        var lineDisc = lineSubtotal * (disc / 100);
        var lineAfterDisc = lineSubtotal - lineDisc;
        var lineTax = lineAfterDisc * (tax / 100);
        subtotal += lineAfterDisc;
        taxTotal += lineTax;
    });

    var discType = $('#discTypeSelect').val();
    var discVal = parseFloat($('#discValueInput').val()) || 0;
    var discAmount = 0;
    if (discType === 'percent') {
        discAmount = subtotal * (discVal / 100);
    } else {
        discAmount = discVal;
    }
    var finalSub = subtotal - discAmount;
    var total = finalSub + taxTotal;
    var sym = getCurrencySymbol(getSelectedCurrency());

    $('#dispSubtotal').text(sym + formatNum(subtotal));
    $('#dispTax').text(sym + formatNum(taxTotal));
    $('#dispTotal').text(sym + formatNum(total));
    if (discAmount > 0) {
        $('#dispDiscount').text('-' + sym + formatNum(discAmount));
        $('#discountRow').show();
    } else {
        $('#discountRow').hide();
    }

    $('#totalInWords').text(total > 0 ? numberToWordsTR(total, getSelectedCurrency()) : '');
}

function numberToWordsTR(n, currency) {
    // Simple version
    var syms = {'TRY': 'Türk Lirası', 'USD': 'Dolar', 'EUR': 'Euro', 'GBP': 'Sterlin'};
    return n.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ' + (syms[currency] || currency);
}

function escHtml(s) {
    return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Currency change
$(document).on('change', '#currencySelect', function() {
    var sym = getCurrencySymbol($(this).val());
    updateTotals();
    $('#itemsBody tr').each(function() { calcRow($(this)); });
});

// Discount change
$(document).on('input change', '#discValueInput, #discTypeSelect', function() { updateTotals(); });

// Sortable drag
if (typeof Sortable !== 'undefined') {
    Sortable.create(document.getElementById('itemsBody'), { handle: '.item-row-handle', animation: 150 });
}
