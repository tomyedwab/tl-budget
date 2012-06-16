(function() {
    window.JournalSummaryView = Backbone.View.extend({

        viewDependencies: {
            indices: {
                "cash_accounts": { name: "cash_account/name", value: "*" },
                "allocations": { name: "allocation/month", value: "*" },
            },
            sums: {
                "income": { name: "income/act_total/month", value: "" },
                "cash_out": { name: "cash_tfer/from_act_total/month", value: "" },
                "cash_in": { name: "cash_tfer/to_act_total/month", value: "" },
                "expenses": { name: "expense/act_total/month", value: "" }
            },

            templateFiles: [ "journal" ]
        },

        initialize: function() {
            _.bindAll(this, "render");
        },

        show: function() {
            var date = DateToJS(this.options.month*100 + 1);
            this.viewDependencies.sums.income.value = this.options.month;
            this.viewDependencies.sums.expenses.value = this.options.month;
            this.viewDependencies.sums.cash_in.value = this.options.month;
            this.viewDependencies.sums.cash_out.value = this.options.month;

            TL_Budget.initializeViewDependencies(this.viewDependencies, this.render);
        },

        hide: function() {
            TL_Budget.deinitializeViewDependencies(this.viewDependencies);
        },

        render: function() {
            var self = this;
            var template = TL_Budget.getTemplate("journal_summary_tmpl");

            TL_Budget.debug("Journal summary render");

/*
            var accounts = {};
            var accountsList = [];
            var totals = { "income": 0, "cash_in": 0, "cash_out": 0, "expenses": 0, "net": 0 };
            var self = this;
            _.each(this.viewDependencies.sums, function(sum, type) {
                var multiplier = (type == 'cash_in' || type == 'income') ? 1 : -1;
                _.each(sum.sum.values[self.options.month], function(amount, accountID) {
                    var account = TL_DataStore.getObject(accountID);
                    accounts[accountID] = accounts[accountID] || { name: account ? account.get('name') : '???' };
                    accounts[accountID][type] = amount*multiplier;
                    accounts[accountID]['net'] = accounts[accountID]['net'] || 0;
                    accounts[accountID]['net'] += amount*multiplier;
                    totals[type] += amount*multiplier;
                    totals['net'] += amount*multiplier;
                });
            });
            _.each(accounts, function(account) {
                accountsList.push(account);
            });

            $(this.el).html(template({accounts: accountsList, totals: totals}));
            */

            var incomeTotal = 0;
            var expenseTotal = 0;

            _.each(this.viewDependencies.sums, function(sum, type) {
                _.each(sum.sum.values[self.options.month], function(amount, accountID) {
                    if (type == "income") {
                        incomeTotal += amount*1;
                    }
                    if (type == "expenses") {
                        expenseTotal += amount*1;
                    }
                });
            });

            var allocationTotal = 0;
            var allocTable = {};
            var allocTotals = { "preAmount": 0, "postAmount": 0, "alloc": 0, "expenses": 0 };
            var toSavings = 0;

            var emptyFund = function(name, sort) {
                return {
                    fund: name,
                    preAmount: 0,
                    postAmount: 0,
                    alloc: 0,
                    expenses: 0,
                    sort: sort
                };
            }
            allocTable["Living Expenses"] = emptyFund("Living Expenses", 0);
            allocTable["Savings"] = emptyFund("Savings", 1);

            _.each(self.viewDependencies.indices.allocations.index.values, function(allocs, month) {
                if (month*1 < self.options.month) {
                    _.each(allocs[0].get("funds"), function(fund) {
                        var fund_amount = (fund.initial_balance||0) + (fund.alloc||0) - (fund.expenses||0);

                        allocTable[fund.fund] = allocTable[fund.fund] || emptyFund(fund.fund, 2);
                        if (fund.fund != "Living Expenses") {
                            allocTable[fund.fund].preAmount += fund_amount;
                        } else {
                            allocTable["Savings"].preAmount += fund_amount;
                            toSavings -= fund_amount;
                        }
                        allocTotals.preAmount += fund_amount;
                    });
                }
                if (month*1 <= self.options.month) {
                    _.each(allocs[0].get("funds"), function(fund) {
                        var fund_amount = (fund.initial_balance||0) + (fund.alloc||0) - (fund.expenses||0);

                        allocTable[fund.fund] = allocTable[fund.fund] || emptyFund(fund.fund, 2);
                        if (fund.fund != "Living Expenses") {
                            allocTable[fund.fund].postAmount += fund_amount;
                        } else {
                            allocTable["Savings"].postAmount += fund_amount;
                            toSavings += fund_amount;
                        }
                        allocTotals.postAmount += fund_amount;

                        if (month*1 == self.options.month) {
                            allocationTotal += fund.amount;
                            allocTable[fund.fund].alloc += fund.alloc;
                            allocTable[fund.fund].expenses += fund.expenses;
                            allocTotals.alloc += fund.alloc;
                            allocTotals.expenses += fund.expenses;
                        }
                    });
                }
            });

            $(this.el).html(template({
                incomeTotal: incomeTotal,
                expenseTotal: expenseTotal,
                netChange: incomeTotal-expenseTotal,
                toSavings: toSavings,
                toSavingsPositive: toSavings >= 0,
                fromLivingExpenses: -1*toSavings,
                allocTable: _.sortBy(_.map(allocTable, function(v) {return v;}), function(v) { return v.sort; }),
                allocTotals: allocTotals
            }));

        }
    });

})();


