var TL_Auth = {
	allowDialogToClose: false,
	currentUser: null,
	currentUserFullname: null,
    currentDomain: null,
    nextPage: null,
    inited: false,

    isLoggedIn: function() {
        return (this.currentUser != null);
    },

	showLogin: function(nextPage) {
        this.nextPage = nextPage;

        if (!this.inited) {
            $('#login-dialog').dialog({
                autoOpen: false,
                buttons: { "Log in": function() { TL_Auth.doLogin(); } },
                resizable: false,
                modal: true,
                beforeClose: function(event, ui) { return TL_Auth.allowDialogToClose; },
            });
            $('#login-progress').dialog({
                autoOpen: false,
                buttons: {  },
                resizable: false,
                modal: true,
                beforeClose: function(event, ui) { return TL_Auth.allowDialogToClose; },
            });
            this.inited = true;
        }

		if (this.currentUser == null) {
			this.allowDialogToClose = true;
			$('#login-progress').dialog('close');

			this.allowDialogToClose = false;
			$('#login-dialog').dialog('open');
		} else {
			this.finishLogin();
		}
	},

	doLogin: function() {
        var self = this;
		this.allowDialogToClose = true;
		$('#login-dialog').dialog('close');

		this.allowDialogToClose = false;
		$('#login-progress').dialog('open');
		$("#login-progress" ).dialog("option", "buttons", { } );
		$('#login-status').html('Logging in...');

		TL_Auth.currentUser = $('#login_username').val();
		$.ajax({
			url: '/script/login.php?user=' + $('#login_username').val() + '&pass=' + $('#login_password').val(),
			dataType: "json",
			success: function(json) {
				if (json.login == 1)
				{
					TL_Auth.currentUserFullname = json.user;
                    TL_Auth.currentDomain = json.domain;
					TL_Auth.finishLogin();
				}
				else
				{
					$('#login-status').html('Login failed. Please try again.');
					$("#login-progress" ).dialog("option", "buttons", { "OK": function() { TL_Auth.showLogin(self.nextPage); } } );
					TL_Auth.currentUser = null;
                    TL_Auth.currentDomain = null;
					TL_Auth.currentUserFullname = null;
				}
			},
			error: function() {
				$('#login-status').html('Login failed. Please try again.');
				$("#login-progress" ).dialog("option", "buttons", { "OK": function() { TL_Budget.showLogin(self.nextPage); } } );
				TL_Auth.currentUser = null;
                TL_Auth.currentDomain = null;
				TL_Auth.currentUserFullname = null;
			},
		});
	},

	finishLogin: function() {
		this.allowDialogToClose = true;
		$('#login-dialog').dialog('close');
		$('#login-progress').dialog('close');

		//TL_Run.enterState();
        this.nextPage();
        this.nextPage = null;
	},

    logOut: function() {
		$.ajax({
			url: "/script/login.php",
			dataType: "json",
			success: function(json) {
                TL_Auth.currentUser = null;
                TL_Auth.currentDomain = null;
                TL_Auth.currentUserFullname = null;
                TL_DataStore.clearAll();
                TL_Budget.router.navigate("logout", true);
            }
        });
    }
};

$(function() {
});
