function DateToInt(in_date) {
	return (in_date.substring(0, 4) + in_date.substring(5, 7) + in_date.substring(8, 10))*1;
};

function DateToString(in_date) {
	var year = (in_date/10000).toFixed(0);
	var month = ((in_date/100)%100).toFixed(0);
	var day = (in_date%100);
	var ret = '';
	if (month > 9)
		ret = month + '/';
	else
		ret = '0' + month + '/';
	if (day > 9)
		ret += day + '/';
	else
		ret += '0' + day + '/';
	ret += year;
	return ret;
};
function DateToStringSimple(in_date) {
	var month = ((in_date/100)%100).toFixed(0);
	var day = (in_date%100);
	var year = ((in_date/10000)%10000).toFixed(0) % 100;
	var months = [ '', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ];
	return  day + ' ' + months[month] + ' \'' + year;
};
function DateToSQL(in_date) {
	var year = (in_date/10000).toFixed(0);
	var month = ((in_date/100)%100).toFixed(0);
	var day = (in_date%100);
	var ret = '';
	ret += year + '-';
	if (month > 9)
		ret += month + '-';
	else
		ret += '0' + month + '-';
	if (day > 9)
		ret += day;
	else
		ret += '0' + day;
	ret += ' 00:00:00';
	return ret;
};
function DateToJS(in_date) {
	var year = (in_date/10000).toFixed(0);
	var month = ((in_date/100)%100).toFixed(0);
	var day = (in_date%100);
	return new Date(year, month-1, day);
};

function IntToCurrency(amount) {
	var amt_left, amt_rem, amt_cur, dec;
	var ret = '';
	var neg = '';
	var delim = '.';
	var oops = 0;

	if (amount == 0 || amount*1 != amount)
		return '$0.00';

	amount = Math.round(amount*100)*0.01;

	if (amount < 0)
	{
		neg = '-';
		amt_cur = -amount;
	}
	else
	{
		amt_cur = amount;	
	}
	dec = Math.round((amt_cur-Math.floor(amt_cur))*100);
	if (dec < 10)
		ret = '0' + dec;
	else
		ret = dec;
	amt_cur = Math.floor(amt_cur);

	if (amt_cur == 0)
	{
		return '$0.' + ret;
	}

	while (amt_cur != 0)
	{
		amt_left = Math.floor(amt_cur/1000);
		amt_rem = amt_cur-(amt_left*1000);
		amt_cur = amt_left;
		if (amt_left != 0)
		{
			if (amt_rem < 10)
				ret = '00' + amt_rem + delim + ret;
			else if (amt_rem < 100)
				ret = '0' + amt_rem + delim + ret;
			else
				ret = amt_rem + delim + ret;
		}
		else
			ret = amt_rem + delim + ret;
		delim = ',';
		oops++;
		if (oops == 50)
		{
			alert('Oops!: ' + amount + ' / ' + ret);
			break;
		}
	}

	return neg + '$' + ret;
};
function IntToPercentage(amount) {
	return amount.toFixed(2) + '%';
};
function IntToDate(date_in) {
	var date_int = date_in*1;
	var year = Math.floor(date_int/10000);
	var month = Math.floor((date_int/100)%100);
	if (month*1 < 10)
		month = '0' + month;
	var day = (date_int%100);
	if (day*1 < 10)
		day = '0' + day;
	return month + '/' + day + '/' + year;
}
function CurrencyToInt(in_str)
{
	return in_str.replace("$","").replace(",","")*1;
}
function PercentageToFloat(in_str)
{
	return in_str.replace("%","")*1;
}

function NearSameFloat(a, b)
{
	if ((a-b).toFixed(2) == 0)
		return true;
	return false;
}

Date.parseDate = function(str, fmt) {
	var today = new Date();
	var y = 0;
	var m = -1;
	var d = 0;
	var a = str.split(/\W+/);
	var b = fmt.match(/%./g);
	var i = 0, j = 0;
	var hr = 0;
	var min = 0;
	for (i = 0; i < a.length; ++i) {
		if (!a[i])
			continue;
		switch (b[i]) {
		    case "%d":
		    case "%e":
			d = parseInt(a[i], 10);
			break;

		    case "%m":
			m = parseInt(a[i], 10) - 1;
			break;

		    case "%Y":
		    case "%y":
			y = parseInt(a[i], 10);
			(y < 100) && (y += (y > 29) ? 1900 : 2000);
			break;

		    case "%b":
		    case "%B":
			for (j = 0; j < 12; ++j) {
				if (Calendar._MN[j].substr(0, a[i].length).toLowerCase() == a[i].toLowerCase()) { m = j; break; }
			}
			break;

		    case "%H":
		    case "%I":
		    case "%k":
		    case "%l":
			hr = parseInt(a[i], 10);
			break;

		    case "%P":
		    case "%p":
			if (/pm/i.test(a[i]) && hr < 12)
				hr += 12;
			else if (/am/i.test(a[i]) && hr >= 12)
				hr -= 12;
			break;

		    case "%M":
			min = parseInt(a[i], 10);
			break;
		}
	}
	if (isNaN(y)) y = today.getFullYear();
	if (isNaN(m)) m = today.getMonth();
	if (isNaN(d)) d = today.getDate();
	if (isNaN(hr)) hr = today.getHours();
	if (isNaN(min)) min = today.getMinutes();
	if (y != 0 && m != -1 && d != 0)
		return new Date(y, m, d, hr, min, 0);
	y = 0; m = -1; d = 0;
	for (i = 0; i < a.length; ++i) {
		if (a[i].search(/[a-zA-Z]+/) != -1) {
			var t = -1;
			for (j = 0; j < 12; ++j) {
				if (Calendar._MN[j].substr(0, a[i].length).toLowerCase() == a[i].toLowerCase()) { t = j; break; }
			}
			if (t != -1) {
				if (m != -1) {
					d = m+1;
				}
				m = t;
			}
		} else if (parseInt(a[i], 10) <= 12 && m == -1) {
			m = a[i]-1;
		} else if (parseInt(a[i], 10) > 31 && y == 0) {
			y = parseInt(a[i], 10);
			(y < 100) && (y += (y > 29) ? 1900 : 2000);
		} else if (d == 0) {
			d = a[i];
		}
	}
	if (y == 0)
		y = today.getFullYear();
	if (m != -1 && d != 0)
		return new Date(y, m, d, hr, min, 0);
	return today;
};
