// ==UserScript==
// @name iRacing Qualifying Results CSV Export
// @namespace iRacing.com
// @description Allows CSV Export of Qualifying
// @include http://members.iracing.com/membersite/member/EventResult.do?*
// ==/UserScript==

// Author: Dave Gymer
// Created: 2011/01/31

// Test against http://members.iracing.com/membersite/member/EventResult.do?subsessionid=1375489
// and http://members.iracing.com/membersite/member/EventResult.do?&subsessionid=1331681&custid=17509

var e = document.getElementsByTagName('script');
var mainJS = undefined;

var decodeURIComponentEx = unsafeWindow.decodeURIComponentEx;

for (var i = 0; i < e.length; ++i) {
    mainJS = e[i];
    var baseResultInfoJS = /^var result=\{[\s\S]*?^\};\s*$/mi.exec(mainJS.text);
    if (baseResultInfoJS) {
        eval(baseResultInfoJS[0]);
        break;
    }
    mainJS = undefined;
}

mainJS || alert("No result found");

result.resultset_practice = new Array();
result.resultset_qualify = new Array();
result.resultset_race = new Array();

var resultObjRE = /^\s+var resultOBJ\s+=\{[\s\S]*?\};\s*$/gim;
var fields = Array();
for (var i = 0; i < 999; ++i) {
	var resultObjJS = resultObjRE.exec(mainJS.text);
	if (resultObjJS == null) {
		break;
	}
	resultObjJS = resultObjJS[0];
	resultObjJS = resultObjJS.replace(/^\s+(helmetsrc|carImg(?:Big)?|clubName):\S+,\s*$/gim, '');
	resultObjJS = resultObjJS.replace(/^(\s+car_dirpath:"[^"]+)\\([^"]+",\s*)$/gim, "$1\\\\$2");
	eval(resultObjJS);
//if (i == 0) alert(resultOBJ.car_dirpath + '\n' + resultObjJS);
	switch (resultOBJ.simSessTypeID) {
	case 2:
	case 3:
		result.resultset_practice.push(resultOBJ);
		break;
	case 4:
	case 5:
		result.resultset_qualify.push(resultOBJ);
		break;
	case 6:
		result.resultset_race.push(resultOBJ);
		break;
	}
	for (var prop in resultOBJ) {
		if (fields.indexOf(prop) == -1
			&& prop != 'isOfficial'
			&& prop != 'friend' && prop != 'watch'
			&& prop != 'points' && prop != 'dropped' && prop != 'clubpoints' && prop != 'multiplier' && prop != 'clubID'
			&& prop != 'oldCPI' && prop != 'newCPI'
			&& prop != 'oldiRating' && prop != 'newiRating'
			&& prop != 'division' && prop != 'divisionName'
			&& prop != 'newttRating' && prop != 'oldttRating'
			&& prop != 'sr_prime' && prop != 'sr_sub' && prop != 'sr_new' && prop != 'sr_old'
			&& prop != 'newLicenseGroupName' && prop != 'newLicenseColor' && prop != 'newLicenseLevel' && prop != 'oldLicenseLevel')
		{
			fields.push(prop);
		}
	}
}
//alert('result: ' + result);
//alert(result.resultset_qualify[1].car_dirpath);

// Bah, forget the fields and just slap out some specific bits.
fields = [ 'carnum', 'custid', 'displayName', 'fastestlaptime', 'fastestlapnum', 'lapscomplete' ];

function renderAsCsv(array) {
	var csv = '', sep = '';
	for (var i in fields) {
		csv += sep + csvEncodeValue(fields[i]);
			sep = ',';
	}
	for each (var resultOBJ in array) {
		sep = '';
		csv += '\n';
		for each (var prop in fields) {
			csv += sep + csvEncodeValue(resultOBJ[prop]);
			sep = ',';
		}
	}
	return csv;
}

function csvEncodeValue(value) {
	//TODO - quote quotes
	return value == null ? '' : ('"' + ('' + value).replace(/"/g, '""') + '"').replace(/\\/g, '\\\\');
}

var form = document.createElement('form');
form.action = 'http://www.simracing.org.uk/lm2/genfile.php';
form.method = 'POST';

var csvBlock = document.createElement('textarea');
csvBlock.name = 'csv';
csvBlock.cols = 100;
csvBlock.rows = 5;
csvBlock.value = renderAsCsv(result.resultset_qualify);
//alert(csvBlock.value);
form.appendChild(csvBlock);

var filename = document.createElement('input');
filename.name = 'filename';
filename.type = 'text';
var when = new Date(result.date).toLocaleFormat('%Y%m%d-%H%M%S'); // Warning! toLocaleFormat is a Firefox-only function! :-(
filename.value = when + '_' + result.subsessionid + '-qual.csv';
form.appendChild(filename);

var button = document.createElement('input');
button.type = 'submit';
button.value = 'Download';
form.appendChild(button);

for each (var img in document.getElementsByTagName('img')) {
	if (img.src == 'http://membersmedia.iracing.com/member_images/titles/table_titles/qualify_results.gif') {
		img.parentNode.insertBefore(form, img);
		break;
	}
}