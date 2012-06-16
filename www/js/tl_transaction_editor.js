 window.TL_TransactionEditor = {
	lastTransactionDate: null,
	currentTransactionKey: '',
	transactionModel: null,
	emptyEntry: {'account':'','fund':''},
	emptyAlloc: {'fund':'','alloc':0},
	emptyTransaction: {
            date: '',
			description: '',
			location: '',
			comments: '',
			type: 'expense',

			entries: [],
            allocs: []
		},
	active_entry: null,

	autocompleteInitialized: false,
	autocompleteAccounts: {
		'all': [],
		'allocate': [],
		'cash': [],
		'expense': [],
		'income': [],
		'loan': [],
	},
	autocompleteFunds: [],

	transactionTypeMatrix: {
		'expense':       [ 'cash',   'expense' ],
		'cash_transfer': [ 'cash',   'cash' ],
		'income':        [ 'income', 'cash' ],
		'loan_repay':    [ 'cash',   'loan' ],
		'new_loan':      [ 'loan',   'cash' ],
		'custom':        [ 'all',    'all' ],
	},

    bindGenericHandlers: function() {
		var self = this;

		ko.bindingHandlers.amountValue = {
			update: function(element, valueAccessor) {
				$(element)
                    .val(IntToCurrency(ko.utils.unwrapObservable(valueAccessor())))
                    .change(function() {
                        var newValue = CurrencyToInt($(this).val());
                        valueAccessor()(newValue);
                    });
			},
		};
        ko.bindingHandlers.dateValue = {
            update: function(element, valueAccessor) {
                $(element)
                    .val(DateToString(DateToInt(ko.utils.unwrapObservable(valueAccessor()))))
                    .change(function() {
                        var date = Date.parseDate($(this).val(), '%m/%d/%Y');
                        var newDate = DateToSQL(date.getFullYear()*10000+(date.getMonth()+1)*100+date.getDate());
                        valueAccessor()(newDate);
                        $(this).val(DateToString(DateToInt(newDate)));
                    });
            }
        };
		ko.bindingHandlers.amountContent = {
			update: function(element, valueAccessor) {
				$(element).html(IntToCurrency(ko.utils.unwrapObservable(valueAccessor())));
			},
		};
		ko.bindingHandlers.accountValue = {
			update: function(element, valueAccessor, allBindingsAccessor, entry) {
				var bindings = allBindingsAccessor();
				var transaction_type = self.transactionModel.type();
				var account_type = "expense";

				var account = TL_DataStore.objects[ko.utils.unwrapObservable(valueAccessor())];
				$(element).val(account ? account.get('name') : '???');

                if (account) {
                    account_type = account.get("type");
                } else if (entry.accountType) {
                    account_type = entry.accountType;
                }
				$(element).autocomplete({source:self.autocompleteAccounts[account_type],minLength:0});

				$(element).change(function() {
					var accountName = $(this).val();
                    var index = TL_DataStore.getIndex('account_name', '*');
					var accounts = index.values[accountName.toLowerCase()];
                    var oldAccount = TL_DataStore.objects[ko.utils.unwrapObservable(valueAccessor())];
					if (accounts && accounts[0] && valueAccessor() != accounts[0].id) {
						entry.fund(accounts[0].get('fund'));
                        delete entry.accountType;
						valueAccessor()(accounts[0].id);
					} else if (accountName != "") {
                        TLAccountEditor.showDialog(null, accountName, account_type, function(key) {
                            if (!key) {
                                $(element).val(oldAccount ? oldAccount.get('name') : '???');
                            } else {
                                var account = TL_DataStore.objects[key];
                                $(element).val(account ? account.get('name') : '???');
                                entry.fund(account.get('fund'));
                                delete entry.accountType;
                                valueAccessor()(key);
                            }
                        });
                    } else {
                        $(element).val(oldAccount ? oldAccount.get('name') : '???');
                    }
				});
			},
		};
		ko.bindingHandlers.fundValue = {
			update: function(element, valueAccessor, allBindingsAccessor) {
				var bindings = allBindingsAccessor();
				var transaction_type = self.transactionModel.type();

				$(element).val(ko.utils.unwrapObservable(valueAccessor()));

				$(element).autocomplete({source:self.autocompleteFunds,minLength:0});

				$(element).change(function() {
					valueAccessor()($(this).val());
				});
			},
		};
    },

    accountFilter: function(model, accountType) {
        return _.filter(model.entries(), function(entry) {
            var account = TL_DataStore.objects[entry.account()];
            return (account && account.get("type") == accountType) || (entry.accountType && entry.accountType == accountType);
            
        });
    },

	bindHandlers: function() {
		var self = this;

        this.bindGenericHandlers();

		this.transactionModel = ko.mapping.fromJS(this.emptyTransaction, knockoutMapping);

        this.transactionModel.cashAccounts = ko.dependentObservable(function() { return self.accountFilter(this, "cash"); }, self.transactionModel);
        this.transactionModel.expenseAccounts = ko.dependentObservable(function() { return self.accountFilter(this, "expense"); }, self.transactionModel);
        this.transactionModel.incomeAccounts = ko.dependentObservable(function() { return self.accountFilter(this, "income"); }, self.transactionModel);
        this.transactionModel.loanAccounts = ko.dependentObservable(function() { return self.accountFilter(this, "loan"); }, self.transactionModel);

		this.transactionModel.creditsTotal = ko.dependentObservable(function() {
            var total = 0;
            _.each(this.entries(), function(entry) {
                if (entry.creditPreset() != -9999999) {
                    total += entry.creditPreset()
                } else {
                    total += entry.credit();
                }
            });
            return total;
        }, this.transactionModel);

		this.transactionModel.debitsTotal = ko.dependentObservable(function() {
            var total = 0;
            _.each(this.entries(), function(entry) {
                if (entry.debitPreset() != -9999999) {
                    total += entry.debitPreset();
                } else {
                    total += entry.debit();
                }
            });
            return total;
        }, this.transactionModel);

		this.transactionModel.allocsTotal = ko.dependentObservable(function() {
            var total = 0;
            _.each(this.allocs(), function(alloc) {
                total += alloc.alloc();
            });
            return total;
        }, this.transactionModel);

		this.transactionModel.incomesTotal = ko.dependentObservable(function() {
            var total = 0;
            _.each(self.accountFilter(this, "income"), function(entry) {
                if (entry.creditPreset() != -9999999) {
                    total += entry.creditPreset()
                } else {
                    total += entry.credit();
                }

                if (entry.debitPreset() != -9999999) {
                    total += entry.debitPreset();
                } else {
                    total += entry.debit();
                }
            });

            return total;
        }, this.transactionModel);

        var ensureCount = function(accountType, desiredCount) {
            var newEntries = _.filter(self.transactionModel.entries(), function(entry) {
                if (entry.account() || entry.credit() || entry.debit()) {
                    return true;
                }
                if (!entry.accountType || entry.accountType != accountType) {
                    return true;
                }
                return false;
            });
            self.transactionModel.entries(newEntries);

            var count = self.accountFilter(self.transactionModel, accountType).length;
            if (desiredCount > 0) {
                while (count < desiredCount) {
                    self.addEntry(accountType);
                    count++;
                }
            } else {
                var newEntries = _.filter(self.transactionModel.entries(), function(entry) {
                    var account = TL_DataStore.objects[entry.account()];
                    var matches = (account && account.get("type") == accountType) || (entry.accountType && entry.accountType == accountType);
                    return !matches;
                });
                self.transactionModel.entries(newEntries);
            }
        };
		this.transactionModel.type.subscribe(function(transactionType) {
            // Reset entry counts, possibly deleting entries

            if (transactionType == "expense") {
                ensureCount("cash", 1); // At least 1
                ensureCount("expense", 1); // At least 1
                ensureCount("loan", 0); // No loan
                ensureCount("income", 0); // No income
            }
            if (transactionType == "cash_transfer") {
                ensureCount("cash", 2); // At least 2
                ensureCount("expense", 0); // No expense
                ensureCount("loan", 0); // No loan
                ensureCount("income", 0); // No income
            }
            if (transactionType == "income") {
                ensureCount("cash", 1); // At least 1
                ensureCount("expense", 0); // No expense
                ensureCount("loan", 0); // No loan
                ensureCount("income", 1); // At least 1

                if (self.transactionModel.allocs().length < 1) {
                    self.addAlloc();
                }
            }
            if (transactionType == "fund_transfer") {
                ensureCount("cash", 0); // No cash
                ensureCount("expense", 0); // No expense
                ensureCount("loan", 0); // No loan
                ensureCount("income", 0); // No income
            }
            // "Custom" can be any number of accounts
        });
/*

		this.transactionModel.debits_by_fund = ko.dependentObservable(function() {
			var ret = [];
			$.each(this.debits(), function(idx, entry) {
				var fund = entry.fund();
				var found = false;
				$.each(ret, function(idx2, list) {
					if (list.fund_name == fund) {
						list.entries.push(entry);
						found = true;
						return false;
					}
				});
				if (!found) {
					ret.push({
						fund_name: fund,
						entries: [entry],
					});
				}
			});
			return ret;
		}, this.transactionModel);
        */

		ko.applyBindings(this.transactionModel, document.getElementById('transaction-editor'));

		$('#fund-chooser').dialog({
			autoOpen: false,
			buttons: { "Select": function() {
				self.active_entry.fund($('#fund-chooser-name').val());
				$('#fund-chooser').dialog('close');
			} },
			resizable: false,
			modal: true,
		});
	},

	createEntry: function(entry_js, accountType) {
		var self = this;
		var new_entry = ko.mapping.fromJS(entry_js);

        if (!new_entry.credit)
            new_entry.credit = ko.observable(0);
        if (!new_entry.debit)
            new_entry.debit = ko.observable(0);
        if (!new_entry.alloc)
            new_entry.alloc = ko.observable(0);
        if (!new_entry.dealloc)
            new_entry.dealloc = ko.observable(0);

        if (accountType && !new_entry.account()) {
            new_entry.accountType = accountType;
        } else {
			var account = TL_DataStore.objects[new_entry.account()];
            accountType = account ? account.get('type') : '';
        }

		new_entry.deleteEntry = function() {
			var idx = self.transactionModel.entries().indexOf(this);
			if (idx >= 0) {
                var entries = self.transactionModel.entries().slice(0);
                entries.splice(idx, 1);
				self.transactionModel.entries(entries);
            }
		};

		new_entry.switchFund = function() {
			self.active_entry = this;
			$('#fund-chooser-name').val(new_entry.fund());
			$('#fund-chooser-name').autocomplete({source:self.autocompleteFunds,minLength:0});
			$('#fund-chooser').dialog('open');
		};

		new_entry.entryCount = ko.dependentObservable(function() { return self.accountFilter(this, accountType).length; }, self.transactionModel);

		new_entry.getAccountType = ko.dependentObservable(function() { return accountType; }, new_entry);

        if (accountType == "cash") {
            new_entry.creditsAllowed = ko.dependentObservable(function() {
                var type = this.type();
                if (type == "cash_transfer"
                    && self.accountFilter(this, "cash").length == 2
                    && this.entries().indexOf(new_entry) == 1) {
                    return false;
                }
                return type == "custom" || type == "expense" || type == "cash_transfer";
            }, self.transactionModel);

            new_entry.debitsAllowed = ko.dependentObservable(function() {
                var type = this.type();
                if (type == "cash_transfer"
                    && self.accountFilter(this, "cash").length == 2
                    && this.entries().indexOf(new_entry) == 0) {
                    return false;
                }
                return type == "custom" || type == "cash_transfer" || type == "income";
            }, self.transactionModel);

            new_entry.creditPreset = function() { return -9999999; };
            new_entry.debitPreset = ko.dependentObservable(function() {
                var type = this.type();
                if (type == "cash_transfer"
                    && self.accountFilter(this, "cash").length == 2
                    && this.entries().indexOf(new_entry) == 1) {
                    return this.creditsTotal();
                }
                return -9999999;
            }, self.transactionModel);

        } else if (accountType == "expense") {
            new_entry.creditsAllowed = ko.dependentObservable(function() {
                var type = this.type();
                return type == "custom";
            }, self.transactionModel);

            new_entry.debitsAllowed = ko.dependentObservable(function() {
                var type = this.type();
                return type == "custom" || type == "expense";
            }, self.transactionModel);

            new_entry.creditPreset = function() { return -9999999; };
            new_entry.debitPreset = ko.dependentObservable(function() {
                var type = this.type();
                if (type == "expense" && self.accountFilter(this, "expense").length == 1)
                    return this.creditsTotal();
                return -9999999;
            }, self.transactionModel);

        } else if (accountType == "income") {
            new_entry.creditsAllowed = ko.dependentObservable(function() {
                var type = this.type();
                return type == "custom" || type == "income";
            }, self.transactionModel);

            new_entry.debitsAllowed = ko.dependentObservable(function() {
                var type = this.type();
                return type == "custom";
            }, self.transactionModel);

            new_entry.creditPreset = ko.dependentObservable(function() {
                var type = this.type();
                if (type == "income" && self.accountFilter(this, "income").length == 1)
                    return this.debitsTotal();
                return -9999999;
            }, self.transactionModel);
            new_entry.debitPreset = function() { return -9999999; };

        } else if (accountType == "loan") {
            new_entry.creditsAllowed = ko.dependentObservable(function() { return true; }, self.transactionModel);
            new_entry.debitsAllowed = ko.dependentObservable(function() { return true; }, self.transactionModel);

            new_entry.creditPreset = function() { return -9999999; };
            new_entry.debitPreset = function() { return -9999999; };
        }

		new_entry.creditsAllowed.subscribe(function(enabled) {
            if (!enabled) {
                new_entry.credit(0);
            }
        });
		new_entry.debitsAllowed.subscribe(function(enabled) {
            if (!enabled) {
                new_entry.debit(0);
            }
        });

		return new_entry;
	},

	createAlloc: function(alloc_js) {
		var self = this;
		var new_alloc = ko.mapping.fromJS(alloc_js);

		new_alloc.allocCount = ko.dependentObservable(function() { return this.allocs().length; }, self.transactionModel);

		new_alloc.deleteAlloc = function() {
			var idx = self.transactionModel.allocs().indexOf(this);
			if (idx >= 0) {
                var allocs = self.transactionModel.allocs().slice(0);
                allocs.splice(idx, 1);
				self.transactionModel.allocs(allocs);
            }
		};

        new_alloc.allocPercentage = ko.dependentObservable(function() { return new_alloc.alloc() / this.allocsTotal(); }, self.transactionModel);

		return new_alloc;
    },

    initAutoCompletes: function(force) {
		if (!this.autocompleteInitialized || force) {
            TL_Budget.debug("Initializing autocomplete lists...");

            var index = TL_DataStore.getIndex('account_name', '*');
            TL_TransactionEditor.autocompleteAccounts = {
                'all': [],
                'allocate': [],
                'cash': [],
                'expense': [],
                'income': [],
                'loan': [],
            };

			_.each(index.models, function(account) {
				TL_TransactionEditor.autocompleteAccounts.all.push(account.get('name'));
				TL_TransactionEditor.autocompleteAccounts[account.get('type')].push(account.get('name'));
				if (account.get('fund') && $.inArray(account.get('fund'), TL_TransactionEditor.autocompleteFunds) == -1)
					TL_TransactionEditor.autocompleteFunds.push(account.get('fund'));
			});

			TL_TransactionEditor.autocompleteAccounts.all.sort();
			TL_TransactionEditor.autocompleteAccounts.allocate.sort();
			TL_TransactionEditor.autocompleteAccounts.cash.sort();
			TL_TransactionEditor.autocompleteAccounts.expense.sort();
			TL_TransactionEditor.autocompleteAccounts.income.sort();
			TL_TransactionEditor.autocompleteAccounts.loan.sort();
			TL_TransactionEditor.autocompleteFunds.sort();

			this.autocompleteInitialized = true;

            var self = this;
            index.bind("all", function() { self.initAutoCompletes(true); });
		}
    },

	showEditor: function(key) {
		var transaction;

        this.initAutoCompletes();

        if (!this.loaded) {
            var self = this;
            $.get("/templates/editor.html", function(templates) {
                $("body").append(templates);
                self.bindHandlers();
                self.finishLoad(key);
            });
        } else {
            this.finishLoad(key);
        }
	},

    finishLoad: function(key) {
		if (this.currentTransactionKey != key)
		{
			// Initialize data

			ko.mapping.fromJS(this.emptyTransaction, this.transactionModel);

			if (key == null)
			{
				TL_Budget.debug('Creating new transaction.');
				this.addEntry('expense');
				this.addEntry('cash');
				this.currentTransactionKey = TL_DataStore.generateKey('T');

                var date = new Date();
                var newDate = DateToSQL(date.getFullYear()*10000+(date.getMonth()+1)*100+date.getDate());
                this.transactionModel.date(newDate);
			}
			else
			{
				TL_Budget.debug('Editing transaction ' + key);
				var transactionModel = TL_DataStore.objects[key];
				transaction = transactionModel.toJSON();

				ko.mapping.fromJS(transaction, this.transactionModel);

				if (transaction.comments == undefined)
					transaction.comments = '';
				this.currentTransactionKey = key;
			}

			$('#transaction-editor').animate({'right':'0px'});
			$('.main_body').animate({'margin-right':'440px'});
		}
    },

	hideEditor: function() {
        this.currentTransactionKey = '';
		$('#transaction-editor').animate({'right':'-440px'});
		$('.main_body').animate({'margin-right':'0px'});
	},

	addEntry: function(type) {
		var new_entry = this.createEntry(this.emptyEntry, type);
        this.transactionModel.entries.push(new_entry);
	},
    addAlloc: function() {
        this.transactionModel.allocs.push(this.createAlloc(this.emptyAlloc));
    },

	saveTransaction: function() {
		if (this.transactionModel.debitsTotal() != this.transactionModel.creditsTotal()) {
			if (!confirm('Total credits do not match total debits. Proceed anyway?'))
				return;
		}
/*		if (this.transactionModel.alloc_income_difference()*1 != 0) {
			if (!confirm('Total allocations do not match total income. Proceed anyway?'))
				return;
		}*/

        _.each(this.transactionModel.entries(), function(entry) {
            if (entry.creditPreset() != -9999999) {
                entry.credit(entry.creditPreset());
            }
            if (entry.debitPreset() != -9999999) {
                entry.debit(entry.debitPreset());
            }
        });

		var transaction = ko.mapping.toJS(this.transactionModel);

		transaction.month = transaction.date.substr(0,7);

		transaction.edit_by = TL_Auth.currentUser;
		now_date = new Date();
		transaction.edit_date = DateToSQL(now_date.getFullYear()*10000+(now_date.getMonth()+1)*100+now_date.getDate());

		var transaction_json = JSON.stringify(transaction);
		TL_Budget.debug("TRANSACTION " + this.currentTransactionKey + ": " + transaction_json);

        var existingObject = TL_DataStore.objects[this.currentTransactionKey];
        if (existingObject) {
            TL_Budget.debug("Updating transaction...");
            TL_DataStore.updateObject(existingObject, transaction_json);
        } else {
            TL_Budget.debug("Creating new transaction...");
            TL_DataStore.createObject(this.currentTransactionKey, "transaction", transaction_json);
        }
        TL_DataStore.poke();

        this.showEditor(null);
	},
};

var knockoutMapping = {
	'entries': { create: function(options) { return TL_TransactionEditor.createEntry(options.data, null); } },
	'allocs': { create: function(options) { return TL_TransactionEditor.createAlloc(options.data); } }
};
