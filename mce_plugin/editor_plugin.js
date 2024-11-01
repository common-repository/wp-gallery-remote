(function() {
	tinymce.create('tinymce.plugins.WPGR', {
		init : function(ed, url) {
			ed.addButton('wpgrChooser', {
				title : 'wpgr.chooser',
				image : url + '/wpgr.gif',
				onclick : function() {
					wp_gallery_remote_open();
				}
			});
		},

		createControl : function(n, cm) {
			return null;
		},

		getInfo : function() {
			return {
				longname : 'WP-Gallery-Remote',
				author : 'Christian Bartels',
				authorurl : 'http://blog.thebartels.de',
				infourl : 'http://blog.thebartels.de',
				version : "1.1"
			};
		}
	});

	tinymce.PluginManager.add('wpgr', tinymce.plugins.WPGR);
})();