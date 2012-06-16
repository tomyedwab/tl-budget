var TL_Nav_View = {
    visible: false,

	show: function()
	{
        if (this.visible) {
            return;
        }

		var div = window.document.getElementById('logged_in');
		div.innerHTML = "Welcome, " + TL_Auth.currentUserFullname + ".<BR><A HREF='javascript:TL_Auth.logOut();'>Log Out</A>";

		$('#side_nav').show();

		//m_StatusDiv = window.document.getElementById('status');
		//m_AlertsDiv = window.document.getElementById('alerts');
	},
	hide: function()
	{
		//m_StatusDiv = null;

		$('#side_nav').hide();
		$('#breadcrumb_nav').hide();

		//Application.m_DataStore = null;
	},


/*
	updateData: function(req)
	{
		if (m_StateStack.m_StackSize > 0)
			m_StateStack.m_Stack[m_StateStack.m_StackSize-1].updateData(req);

		var down = Application.m_DataStore.getDownCount();
		var up = Application.m_DataStore.getUpCount();
		var html = '';
		if (down > 0)
			html += '<DIV CLASS="dn_data_icon">&nbsp;</DIV>';
		if (up > 0)
			html += '<DIV CLASS="up_data_icon">&nbsp;</DIV>';
		m_StatusDiv.innerHTML = html;

		if (Application.m_DataStore.critical_alert_count > 0)
			m_AlertsDiv.innerHTML = '!! (' + (Application.m_DataStore.critical_alert_count+Application.m_DataStore.warning_alert_count) + ')';
		else if (Application.m_DataStore.warning_alert_count > 0)
			m_AlertsDiv.innerHTML = '** (' + Application.m_DataStore.warning_alert_count + ')';
		else
			m_AlertsDiv.innerHTML = '';
	},
    */
};
