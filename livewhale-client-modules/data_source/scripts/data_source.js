$(function($) { // on DOM ready
	if (livewhale.page=='widgets_edit') { // if on widget editor

		$('body').on('click', '#data_sources_list_fields', function() {
			var source_name=$('select[name="source"]').val(),
			table_name=$('input[name="table"]').val(),
			url=$('#lw_widget_preview').attr('src'),
			table_fields='';
			if (source_name && table_name) {
		        $.getJSON(url+'&livewhale=ajax&function=getDataSourceTableFields&source=' + source_name + '&table=' + table_name, function(items) {
		          _.each(items, function(key, val) {
					  table_fields+='<li>'+val+'</li>';
		          });
				  if (table_fields) {
					  livewhale.prompt('Available Fields', '<ul>' + table_fields + '</ul>', 'success');
				  }
				  else {
					  livewhale.prompt('Available Table Fields', '<p>This is not a valid source and table name.</p>', 'failure');
				  };
		        });
			}
			else {
				livewhale.prompt('Available Table Fields', '<p>You must enter a source and table name first.</p>', 'failure');
			};
			return false;
		});
		
	};
});