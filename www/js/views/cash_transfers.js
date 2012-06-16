(function() {
    window.JournalCashTransfersView = Backbone.View.extend({
        entryViews: [], // A cache of entry views
        summaryView: null,

        viewDependencies: {
            indices: {
                "cash_accounts": { name: "cash_account/name", value: "*" },
                "cash_transfers": { name: "cash_transfer/month", value: "" },
            },
            templateFiles: [ "journal" ]
        },

        initialize: function() {
            _.bindAll(this, "render");
        },

        show: function() {
            var date = DateToJS(this.options.month*100 + 1);
            this.viewDependencies.indices.cash_transfers.value = this.options.month;
            TL_Budget.initializeViewDependencies(this.viewDependencies, this.render);
        },

        hide: function() {
            TL_Budget.deinitializeViewDependencies(this.viewDependencies);
        },

        render: function() {
            var self = this;
            var oldEntryViews = self.entryViews;
            TL_Budget.debug("Journal cash transfers render: " + this.options.month);

            $(this.el).attr("data-id", this.options.month);

            self.entryViews = {};
            _.each(this.viewDependencies.indices.cash_transfers.index.models, function(model) {
                if (oldEntryViews[model.id]) {
                    self.entryViews[model.id] = oldEntryViews[model.id];
                } else {
                    self.entryViews[model.id] = new JournalCashTransferView({model: model});
                }
            });

            _.each(oldEntryViews, function(view, id) {
                $(view.el).detach();
            });

            // Create a sorted list
            var sortedViews = _.sortBy(_.values(this.entryViews), function(view) { return view.model.get('date')*1; });

            var row = 0;
            var lastDate = 0;

            _.each(sortedViews, function(view) {
                var sameDate = true;
                if (view.model.get('date') != lastDate) {
                    lastDate = view.model.get('date');
                    sameDate = false;
                }

                $(view.el)
                    .appendTo(self.el)
                    .find(".journal_transaction")
                        .removeClass("row0")
                        .removeClass("row1")
                        .addClass("row" + row)
                        .end()
                    .find(".journal_date")
                        .toggleClass("same", sameDate);


                row = 1 - row;
            });
        }
    });

    window.JournalCashTransferView = Backbone.View.extend({
        template: TL_Budget.getTemplate("journal_cash_transfer_tmpl"),

        initialize: function() {
            _.bindAll(this, "render");

            this.render();

            this.model.bind("change", this.render);
        },

        render: function() {
            var viewModel = this.model.toJSON();

            viewModel.simpleDate = DateToStringSimple(viewModel.date*1);

            var account = TL_DataStore.getObject(viewModel.from_account);
            viewModel.fromAccountName = account ? account.get('name') : '???';

            var account = TL_DataStore.getObject(viewModel.to_account);
            viewModel.toAccountName = account ? account.get('name') : '???';

            $(this.el).html(this.template(viewModel));
        }
    });

})();

