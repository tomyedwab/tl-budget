(function() {
    window.JournalView = Backbone.View.extend({
        viewDependencies: {
            templateFiles: [ "journal" ]
        },

        initialize: function() {
            _.bindAll(this, "render", "previousMonth", "nextMonth");

            this.activeTab = "summary";
            this.queuedTab = null;
            this.rendered = false;
            this.subViews = {};
        },

        show: function() {
            var date = DateToJS(this.options.month*100 + 1);
            TL_Budget.initializeViewDependencies(this.viewDependencies, this.render);

            this.timePicker = new TimePickerView({
                title: "Month of " + date.toDateString(),
                previousCB: this.previousMonth,
                nextCB: this.nextMonth
            });

            $(this.el).appendTo('#content');
        },

        previousMonth: function() {
            var date = DateToJS(this.options.month*100 + 1);
            var year = date.getFullYear();
            var month = date.getMonth();
            month = month - 1;
            if (month < 0) {
                year -= 1;
                month = 11;
            }

            var new_fragment = "journal/" + year + "/" + (month+1) + "/" + this.activeTab;
            TL_Budget.router.navigate(new_fragment, true);
        },

        nextMonth: function() {
            var date = DateToJS(this.options.month*100 + 1);
            var year = date.getFullYear();
            var month = date.getMonth();
            month = month + 1;
            if (month > 11) {
                year += 1;
                month = 0;
            }

            var new_fragment = "journal/" + year + "/" + (month+1) + "/" + this.activeTab;
            TL_Budget.router.navigate(new_fragment, true);
        },

        hide: function() {
            TL_Budget.deinitializeViewDependencies(this.viewDependencies);

            $(this.el).detach();
            this.timePicker.hide();
            _.each(this.subViews, function(view) {
                view.hide();
            });
        },

        setActiveTab: function(name) {
            if (this.rendered) {
                this.showTab(name, true);
            } else {
                this.queuedTab = name;
            }
        },

        showTab: function(name, selectTab) {
            this.activeTab = name;
            
            // Update history URL
            var date = DateToJS(this.options.month*100 + 1);
            var year = date.getFullYear();
            var month = date.getMonth();
            TL_Budget.router.navigate("journal/" + year + "/" + (month+1) + "/" + name, false);

            // Update selected tab if necessary
            if (selectTab) {
                $(this.el).find(".journal-tabs").tabs("select", name);
            }

            // Render the content if necessary
            if (!this.subViews[name]) {
                if (name == "summary") {
                    this.subViews[name] = new JournalSummaryView({month: this.options.month});
                } else if (name == "expenses") {
                    this.subViews[name] = new JournalExpensesView({month: this.options.month});
                } else if (name == "cash-transfers") {
                    this.subViews[name] = new JournalCashTransfersView({month: this.options.month});
                } else if (name == "incomes") {
                    this.subViews[name] = new JournalIncomesView({month: this.options.month});
                }
                if (this.subViews[name]) {
                    var panelEl = $(this.el).find(".journal-tabs #" + name);
                    this.subViews[name].show();
                    $(this.subViews[name].el).appendTo(panelEl.get(0));
                }
            }
        },

        render: function() {
            if (!this.rendered) {
                var self = this;

                var template = TL_Budget.getTemplate("journal_tmpl");
                $(this.el).html(template());

                $(this.el).find(".journal-tabs")
                    .tabs()
                    .bind("tabsselect", function(evt, ui) {
                        self.showTab($(ui.panel).attr("id"), false);
                        return true;
                    });

                this.rendered = true;
            }
            if (this.queuedTab) {
                this.showTab(this.queuedTab, true);
                this.queuedTab = null;
            }
        }
    });

})();
