var TL_Budget_Router = Backbone.Router.extend({
    routes: {
        "journal": "showJournal",
        "journal/:year/:month/:tab": "showJournal",
        "income": "showIncome",
        "income/:year/:month": "showIncome",
        "logout": "showLogOut"
    },
    
    setActiveView: function(view, name) {
        if (TL_Budget.activeView) {
            TL_Budget.activeView.hide();
        }

        TL_Budget.activeView = view;
        if (view) {
            view.show();
        }

        $('#side_nav .nav_label').removeClass("selected");
        if (name) {
            $('#side_nav .nav_label.' + name + '_icon').addClass("selected");
        }
    },

    showLogOut: function() {
        var self = this;
        this.setActiveView(null, null);
        TL_Nav_View.hide();
        TL_Auth.showLogin(function() { self.navigate("journal", true); });
    },

    showJournal: function(year, month, tab) {
        if (!year) {
            TL_Budget.router.navigate('journal/2011/01/summary', true);
            return;
        }
        if (!tab) {
            tab = "summary";
        }

        TL_Budget.debug("showJournal");

        if (!TL_Auth.isLoggedIn()) {
            var self = this;
            this.setActiveView(null, null);
            TL_Nav_View.hide();
            TL_Auth.showLogin(function() { self.showJournal(month, year); });
        } else {
            var month = (year * 100) + (month * 1);
            TL_Nav_View.show();
            if (!TL_Budget.journalViews[month])
                TL_Budget.journalViews[month] = new JournalView({ month: month });
            this.setActiveView(TL_Budget.journalViews[month], "journal");
            TL_Budget.journalViews[month].setActiveTab(tab);
        }
    },

    showIncome: function(year, month) {
        if (!month) {
            TL_Budget.router.navigate('income/2011/01', true);
            return;
        }

        TL_Budget.debug("showIncome");

        if (!TL_Auth.isLoggedIn()) {
            var self = this;
            this.setActiveView(null, null);
            TL_Nav_View.hide();
            TL_Auth.showLogin(function() { self.showIncome(month, year); });
        } else {
            var month = (year * 100) + (month * 1);
            TL_Nav_View.show();
            if (!TL_Budget.incomeViews[month])
                TL_Budget.incomeViews[month] = new IncomeView({ month: month });
            this.setActiveView(TL_Budget.incomeViews[month], "income");
        }
    },
});

var TL_Budget = {
    router: new TL_Budget_Router,
    activeView: null,

    journalViews: {},
    incomeViews: {},
    loadedTemplates: {},

	initialize: function() {
        if (!Backbone.history.start({pushState: true})) {
            TL_Budget.router.navigate('journal', true);
        }
	},

	debug: function(text)
	{
		if (window.console != undefined)
		{
			console.log(text);
		}
	},

    getTemplate: function(name)
    {
        return function(params) {
            var source = $("#" + name).html();
            if (!source) {
                source = "<div>ERROR LOADING TEMPLATE!</div>";
            }
            var template = Handlebars.compile(source);
            return template(params);
        }
    },

    loadTemplateFile: function(name, callback) {
        var self = this;
        if (!self.loadedTemplates[name]) {
            $.get("/templates/" + name + ".html", function(templates) {
                $("body").append(templates);
                self.loadedTemplates[name] = true;
                callback(name);
            });
        } else {
            callback(name);
        }
    },

    initializeViewDependencies: function(deps, renderCallback) {
        var self = this;
        var pendingTemplateFiles = deps.templateFiles.length;

        var callback = function() {
            var loadingIndex = _.find(deps.indices, function(indexDep) {
                if (indexDep.index && indexDep.index.latestTS == 0) {
                    return true; // Index is still loading
                }
                indexDep.loadedTS = indexDep.index.latestTS;
                return false;
            });
            if (loadingIndex) {
                TL_Budget.debug("Still loading index " + loadingIndex.name);
                return;
            }
            var loadingSum = _.find(deps.sums, function(sumDep) {
                if (sumDep.sum && sumDep.sum.latestTS == 0) {
                    return true; // Sum is still loading
                }
                sumDep.loadedTS = sumDep.sum.latestTS;
                return false;
            });
            if (loadingSum) {
                TL_Budget.debug("Still loading sum " + loadingSum.name);
                return;
            }
            if (pendingTemplateFiles) {
                TL_Budget.debug("Still loading " + pendingTemplateFiles + " template files.");
                return;
            }

            TL_Budget.debug("Done loading dependencies.");
            _.each(deps.indices, function(indexDep) {
                indexDep.index.bind("all", renderCallback);
            });
            _.each(deps.sums, function(sumDep) {
                sumDep.sum.bind("all", renderCallback);
            });
            renderCallback();
        };

        _.each(deps.indices, function(indexDep) {
            var index = TL_DataStore.getIndex(indexDep.name, indexDep.value);
            if (index) {
                indexDep.index = index.addRef();
                if (index.latestTS == 0) {
                    indexDep.index.bind("reset", callback);
                }
                indexDep.loadedTS = index.latestTS;
            }
        });
        _.each(deps.sums, function(sumDep) {
            var sum = TL_DataStore.getSum(sumDep.name, sumDep.value);
            if (sum) {
                sumDep.sum = sum.addRef();
                if (sum.latestTS == 0) {
                    sumDep.sum.bind("reset", callback);
                }
                sumDep.loadedTS = sum.latestTS;
            }
        });

        TL_DataStore.poke();

        _.each(deps.templateFiles, function(templateFile) {
            self.loadTemplateFile(templateFile, function() {
                pendingTemplateFiles--;
                callback();
            });
        });
    },

    deinitializeViewDependencies: function(deps) {
        _.each(deps.indices, function(indexDep) {
            if (indexDep.index) {
                indexDep.index.removeRef();
            }
        });
        _.each(deps.sums, function(sumDep) {
            if (sumDep.sum) {
                sumDep.sum.removeRef();
            }
        });
    }
}

$(function() { TL_Budget.initialize(); });
