function set_visibility(check, change) {
	var elem_check = document.getElementById(check);
	var elem_change = document.getElementById(change);

	if (elem_check.name == "wpgr_outputtype") {
		if (elem_check.value == "plain") {
			elem_change.style.visibility = "visible";
		} else {
			elem_change.style.visibility = "collapse";
		}
	} else {
		if (elem_check.checked == true) {
			elem_change.style.visibility = "visible";
		} else {
			elem_change.style.visibility = "collapse";
		}
	}
}

function validate_data() {
	var formWPGR = document.forms[1];
	var msg = "";

	if (formWPGR.wpgr_name.value == "") {
		msg = "Please enter a name for the Gallery.";
		formWPGR.wpgr_name.focus();
	} else if (formWPGR.wpgr_url.value == "") {
		msg = "Please enter the URL for the Gallery.";
		formWPGR.wpgr_url.focus()
	} else if (!formWPGR.wpgr_thumbsize.value.match(/[0..9]+/)) {
		msg = "Only numerical values allowed for thumbnail size.";
		formWPGR.wpgr_thumbsize.focus();
	} else if (formWPGR.wpgr_usecaching.check == true && !formWPGR.wpgr_cachetimeoutalbums.value.match(/[0..9]+/)) {
		msg = "Only numerical values allowed for timeout of album cache.";
		formWPGR.wpgr_cachetimeoutalbums.focus();
	} else if (formWPGR.wpgr_usecaching.check == true && !formWPGR.wpgr_cachetimeoutimages.value.match(/[0..9]+/)) {
		msg = "Only numerical values allowed for timeout of album image cache.";
		formWPGR.wpgr_cachetimeoutimages.focus();
	}
	
	if (msg != "") {
		alert(msg);
		return false;
	}
}

function confirm_rebuild() {
	if (check_changed()) {
		return confirm("This will retrieve all image information from all albums of the currently selected Gallery. Depending on the connection and on the amount of albums, this can take some time.");
	} else {
		return false;
	}
}

var wpgr_gallery_changed = false;

function set_changed() {
	wpgr_gallery_changed = true;
}

function check_changed() {
	if (wpgr_gallery_changed) {
		alert("Please save your changes first.");
		return false;
	} else {
		return true;
	}
}