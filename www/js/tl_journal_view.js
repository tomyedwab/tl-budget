// This file is deprecated. It is being replaced by views/journal.js

(function() {
    window.TLJournalView = Backbone.View.extend({
        entryViews: [],
        viewDependencies: {
            indices: {
                "accounts": { name: "account_name", value: "*" },
                "transactions": { name: "trans_month", value: 0 }
            },
            templateFiles: [ "journal" ]
        },

        initialize: function() {
            _.bindAll(this, "render");
        },

        show: function() {
            this.viewDependencies.indices.transactions.value = this.options.month;
            TL_Budget.initializeViewDependencies(this.viewDependencies, this.render);
        },

        hide: function() {
            TL_Budget.deinitializeViewDependencies(this.viewDependencies);

            $(this.el).detach();
        },

        render: function() {
            var self = this;
            var oldEntryViews = self.entryViews;
            TL_Budget.debug("TLJournalView render!");

            self.entryViews = {};
            _.each(this.viewDependencies.indices.transactions.index.models, function(model) {
                if (oldEntryViews[model.id]) {
                    self.entryViews[model.id] = oldEntryViews[model.id];
                } else {
                    self.entryViews[model.id] = new TLJournalTransactionView({model: model});
                }
            });

            $(this.el).detach();

            _.each(oldEntryViews, function(view, id) {
                $(view.el).detach();
            });

            // Create a sorted list
            var sortedViews = _.sortBy(_.values(this.entryViews), function(view) { return DateToInt(view.model.get('date')); });

            var row = 0;
            _.each(sortedViews, function(view) {
                $(view.el).appendTo(self.el);
                $(view.el).find(".journal_transaction")
                    .removeClass("row0").removeClass("row1")
                    .addClass("row" + row);

                row = 1 - row;
            });

            $(this.el).appendTo('#content');
        }
    });

    window.TLJournalTransactionView = Backbone.View.extend({
        template: TL_Budget.getTemplate("journal_tmpl"),

        events: {
            "click .show_editor": "showEditor"
        },

        initialize: function() {
            _.bindAll(this, "render", "showEditor");

            this.render();

            this.model.bind("change", this.render);
        },

        processEntries: function(list) {
            if (!list) {
                return [];
            }

            _.each(list, function(entry) {
                var account = TL_DataStore.objects[entry.account];

                entry.accountName = account ? account.get('name') : '???';
                entry.accountType = account ? account.get('type') : '';

                entry.fundIfNotDefalt =
                    (account && account.get('type') == 'expense' &&
                        entry.fund != account.get('fund')) ? 
                        '(' + entry.fund + ')' : '';
            });

            return list;
        },

        render: function() {
            var viewModel = this.model.toJSON();

            viewModel.entries = this.processEntries(viewModel.entries);

            viewModel.totalEntries = 0;
            var allocs = 0, deallocs = 0;
            _.each(viewModel.entries, function(entry) {
                if (entry.credit || entry.debit) {
                    viewModel.totalEntries++;
                }
            });
            viewModel.numAllocs = viewModel.allocs ? viewModel.allocs.length : 0;

            viewModel.journalRowHeight = viewModel.totalEntries * 24 + viewModel.numAllocs * 24 + 30;
            viewModel.journalCommentHeight = viewModel.totalEntries * 22;
            viewModel.simpleDate = DateToStringSimple(DateToInt(viewModel.date));

            $(this.el).html(this.template(viewModel));
        },

        showEditor: function() {
            TL_TransactionEditor.showEditor(this.model.id);
        }
    });
})();

