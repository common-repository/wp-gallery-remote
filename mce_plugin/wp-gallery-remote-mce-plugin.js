function cancelAction() {
  window.close();
}

function insert_wp_gallery_remote_tag() {
  var formWPGR = document.forms[0];
  var images = "";  
  var tag  = '{wp-gallery-remote:'
  		+ ' gallery=' + formWPGR.wpgr_galleries.value + ';'
        + ' rootalbum=' + formWPGR.album.value + ';';
        
  if (formWPGR.hidden_clickimage.value != formWPGR.clickimage.value) {
  	tag = tag + ' clickimage=' + formWPGR.clickimage.value + ';';
  }
  
  if (formWPGR.hidden_showalbumtitle.checked != formWPGR.showalbumtitle.checked) {
  	tag = tag + ' showalbumtitle=' + formWPGR.showalbumtitle.checked + ';';
  }
  
  if (formWPGR.hidden_clickalbumtitle.value != formWPGR.clickalbumtitle.value) {
  	tag = tag + ' clickalbumtitle=' + formWPGR.clickalbumtitle.value + ';';
  }
  
  if (formWPGR.hidden_showsubalbums.checked != formWPGR.showsubalbums.checked) {
  	tag = tag + ' showsubalbums=' + formWPGR.showsubalbums.checked + ';';
  }
  
  if (formWPGR.hidden_showimagesheader.checked != formWPGR.showimagesheader.checked) {
  	tag = tag + ' showimagesheader=' + formWPGR.showimagesheader.checked + ';';
  }
  
  if (formWPGR.hidden_thumbsize.value != formWPGR.thumbsize.value) {
  	tag = tag + ' thumbsize=' + formWPGR.thumbsize.value + ';';
  }
  
  if (formWPGR.hidden_nocaching.checked != formWPGR.nocaching.checked) {
  	tag = tag + ' nocaching=' + formWPGR.nocaching.checked + ';';
  }
        
  if (formWPGR.useimagefilter && formWPGR.useimagefilter.checked) {
    var images = new Array();
    
    for (var i=0;i<formWPGR.images.length;i++) {
      if (formWPGR.images[i].checked) {
        images.push(formWPGR.images[i].value);
      }
    }
  
    tag = tag + ' imagefilter=' + formWPGR.filtertype.value + ':' + images.join(',') + ';';
  }
  
  var divstyle = document.getElementById("divstyle");
  
  if (divstyle != null && (formWPGR.divstyle.value.replace(/;/, '|') != formWPGR.hidden_divstyle.value)) {
  	tag = tag + ' divstyle=' + formWPGR.divstyle.value.replace(/;/, '|') + ';';
  }
  
  tag = tag + '}';
   
  if (window.tinyMCE && window.tinyMCE.selectedInstance) 
    window.opener.tinyMCE.execCommand("mceInsertContent", true, tag);
  else
    insert_wp_gallery_remote_tag_AtCursor(window.opener.document.forms[0].content, tag);
 
  window.close();
}

function insert_wp_gallery_remote_tag_AtCursor(myField, myValue) {
  //IE support
  if (document.selection && !window.opera) {
    myField.focus();
    sel = window.opener.document.selection.createRange();
    sel.text = myValue;
  }
  //MOZILLA/NETSCAPE/OPERA support
  else if (myField.selectionStart || myField.selectionStart == '0') {
    var startPos = myField.selectionStart;
    var endPos = myField.selectionEnd;
    myField.value = myField.value.substring(0, startPos)
    + myValue
    + myField.value.substring(endPos, myField.value.length);
  } else {
    myField.value += myValue;
  }
}

function activate_wpgr_filter() {
  document.forms[0].useimagefilter.checked = true;
}

function include_dom(script_filename) {
    var html_doc = document.getElementsByTagName('head').item(0);
    var js = document.createElement('script');
    js.setAttribute('language', 'javascript');
    js.setAttribute('type', 'text/javascript');
    js.setAttribute('src', script_filename);
    html_doc.appendChild(js);
    return false;
}

if (typeof window.opener.tinyMCE != "undefined") {
	include_dom("../../../../wp-includes/js/tinymce/tiny_mce_popup.js");
}