ko.bindingHandlers.accountCSS = {
	update: function(element, valueAccessor, allBindingsAccessor, viewModel) {
		$(element).removeClass('account_expense');
		$(element).removeClass('account_loan');
		$(element).removeClass('account_cash');
		$(element).removeClass('account_income');
		var obj = TL_DataStore.objects[ko.utils.unwrapObservable(valueAccessor())];
		if (obj)
			$(element).addClass('account_' + obj.get('type'));
    }
};
ko.bindingHandlers.amountText = {
	update: function(element, valueAccessor, allBindingsAccessor, viewModel) {
		$(element).html(IntToCurrency(ko.utils.unwrapObservable(valueAccessor())));
	}
};
ko.bindingHandlers.percentText = {
	update: function(element, valueAccessor, allBindingsAccessor, viewModel) {
		$(element).html("%" + (ko.utils.unwrapObservable(valueAccessor())*100).toFixed(1));
	}
};

function koDependentObservableWrapper(fn, model) {
    var observable = ko.dependentObservable(fn, model);
    return function() {
        return observable();
    };
}

