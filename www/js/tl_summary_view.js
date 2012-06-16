(function() {
    window.TLSummaryView = Backbone.View.extend({
        viewDependencies: {
            indices: {
                "accounts": { name: "account_name", value: "*" },
                "account_totals": { name: "account_total", value: "*" },
                "fund_totals": { name: "fund_total", value: "*" }
            },
            templateFiles: [ "summary" ]
        },
        template: TL_Budget.getTemplate("summary_tmpl"),

        initialize: function() {
            _.bindAll(this, "render");
        },

        show: function() {
            TL_Budget.initializeViewDependencies(this.viewDependencies, this.render);
        },

        hide: function() {
            TL_Budget.deinitializeViewDependencies(this.viewDependencies);

            $(this.el).detach();
        },

        render: function() {
            var accountTotalsIndex = this.viewDependencies.indices.account_totals.index;
            var fundTotalsIndex = this.viewDependencies.indices.fund_totals.index;
            var viewModel = {
                categories: []
            };

            if (accountTotalsIndex.models.length > 0) {
                TL_Budget.debug("TL_Summary_View update!");

                var accounts = {};
                _.each(accountTotalsIndex.models, function(total) {
                    var accountKey = total.get('account');
                    if (!accounts[accountKey] || total.get('month') > accounts[accountKey].get('month')) {
                        accounts[accountKey] = total;
                    }
                });

                var categories = {};
                _.each(accounts, function(total) {
                    var account = TL_DataStore.objects[total.get('account')];
                    if (account && (account.get("type") == "cash" || account.get("type") == "loan")) {
                        var category = account ? account.get("category") : "Unknown";
                        if (!categories[category]) {
                            categories[category] = [];
                        }

                        var totalData = total.toJSON();
                        totalData.accountName = account.get("name");
                        totalData.total = totalData.running_total * -1;

                        categories[category].push(totalData);
                    }
                });

                viewModel.categories = _.map(categories, function(accountsList, category) {
                    return { category: category, accountsList: accountsList };
                });
            }

            if (fundTotalsIndex.models.length > 0) {
                var funds = {};
                _.each(fundTotalsIndex.models, function(total) {
                    var fundName = total.get('fund');
                    if (!funds[fundName] || total.get('month') > funds[fundName].get('month')) {
                        funds[fundName] = total;
                    }
                });

                viewModel.funds = _.map(funds, function(total, fundName) {
                    return { fund: fundName, total: total.toJSON() };
                });
            }

            $(this.el).html(this.template(viewModel));
            $(this.el).appendTo('#content');
        }
    });
})();
