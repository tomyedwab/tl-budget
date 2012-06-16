$(function() {
    Handlebars.registerHelper('int_to_currency', function(value) {
        return IntToCurrency(value);
    });
    Handlebars.registerHelper('percentage', function(numer, denom) {
        if (denom > 0) {
            return Math.round(numer*100.0/denom) + "%";
        }
        return "0%";
    })
    Handlebars.registerHelper('diff', function(out_amount, in_amount) {
        var diff = in_amount-out_amount;
        if (diff > 0) {
            return "+" + IntToCurrency(diff);
        } else {
            return IntToCurrency(diff);
        }
    });
});
