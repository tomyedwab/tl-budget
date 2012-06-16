(function() {
    window.TLAccountEditor = {
        currentAccountKey: '',
        accountModel: null,
        emptyAccount: {
            "name": "New Account",
            "category": "",
            "type": "expense",
            "closed": false,
            "fund": "",
            "typeLocked": false // Not saved
        },
        el: null,
        dialogCallback: null,

        showDialog: function(key, name, type, callback) {
            if (this.el) {
                return;
            }
            this.el = 1;
            this.dialogCallback = callback;

            var self = this;

            TL_TransactionEditor.initAutoCompletes();
            TL_TransactionEditor.bindGenericHandlers();

            this.accountModel = ko.mapping.fromJS(this.emptyAccount, {});

            if (key && TL_DataStore.objects[key]) {
                var account = TL_DataStore.objects[key];
                ko.mapping.fromJS(account.toJSON(), this.accountModel);
				this.currentAccountKey = key;
            } else {
				this.currentAccountKey = TL_DataStore.generateKey('A');
                if (name) {
                    this.accountModel.name(name);
                }
                if (type) {
                    this.accountModel.type(type);
                    this.accountModel.typeLocked(true);
                }
            }

            this.el = $('<div data-bind="template:{name:\'account_editor_main\'}">').dialog({
                title: "Create New Account",
                buttons: { "Save": function() { TLAccountEditor.saveAndCloseDialog(); }, "Cancel": function() { TLAccountEditor.closeDialog(null); } },
                resizable: false,
                modal: true
            });

            TL_Budget.loadTemplateFile("account_editor", function() {
                ko.applyBindings(self.accountModel, self.el.get(0));
            });
        },

        saveAndCloseDialog: function() {
            var self = this;
            this.save(function() { self.closeDialog(self.currentAccountKey); });
        },

        closeDialog: function(key) {
            this.el.dialog("close");
            this.el = null;
            this.dialogCallback(key);
        },

        save: function(callback) {
            var account = ko.mapping.toJS(this.accountModel);

            delete account.typeLocked;

            var account_json = JSON.stringify(account);
            TL_Budget.debug("ACCOUNT " + this.currentAccountKey + ": " + account_json);

            var existingObject = TL_DataStore.objects[this.currentAccountKey];
            if (existingObject) {
                TL_Budget.debug("Updating account...");
                TL_DataStore.updateObject(existingObject, account_json, callback);
            } else {
                TL_Budget.debug("Creating new account...");
                TL_DataStore.createObject(this.currentAccountKey, "account", account_json, callback);
            }
            TL_DataStore.poke();
        }
    };
})();
