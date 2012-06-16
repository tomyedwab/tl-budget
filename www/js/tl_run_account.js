var TL_Run_Account = {
	el: null,
	trans_account: null,
	account_key: 'acct:tom:00040',

	initialize: function() {
	},

	enterState: function() {
		this.el = $('<div></div>').appendTo('#content').get(0);

		TL_DataStore.getIndex('account_name', '*').addRef();

		this.trans_account = TL_DataStore.getIndex('trans_account', this.account_key).addRef();
		this.trans_account.bind('add', this.updateAccounts, this);
		this.trans_account.bind('reset', this.updateAccounts, this);

		TL_DataStore.poke();
	},

	exitState: function() {
		this.trans_account.removeRef();
		TL_DataStore.getIndex('account_name', '*').removeRef();
	},

	updateAccounts: function() {
		var account_entries = {};
		var account_name = TL_DataStore.objects[this.account_key].get('name');

		_.each(this.trans_account.models, function(transaction) {
			var list = null;
			_.each(transaction.get('credits'), function(entry) {
				if (entry.account == TL_Run_Account.account_key)
					list = 'debits';
			});
			if (!list)
				list = 'credits';

			_.each(transaction.get(list), function(entry) {
				if (account_entries[entry.account] == undefined)
					account_entries[entry.account] = [entry.account,0,0];

				if (list == 'credits')
					account_entries[entry.account][1] += entry.amount*1;
				else
					account_entries[entry.account][2] += entry.amount*1;
			});
		});

		html = '<table cellpadding="10">';
		html += '<tr><td>Account</td><td>Source</td><td>Credits</td><td>Debits</td><td>Total</td></tr>';
		_.each(account_entries, function(account_entry) {
			account = TL_DataStore.objects[account_entry[0]];
			html += '<tr><td>' + account_name + '</td><td>' + account.get('name') + '</td><td>' + account_entry[1] + '</td><td>' + account_entry[2] + '</td><td>' + (account_entry[1]+account_entry[2]) + '</td></tr>';
		});
		html += '</table>';
		$(this.el).html(html);
	},

	getLabel: function() {
		return 'Account';
	},

	getParameters: function() {
		return {};
	},
};
