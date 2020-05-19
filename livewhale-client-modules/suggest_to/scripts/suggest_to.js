/*globals _, livewhale */

// JS functionality for a redundant checkbox above the group suggest selector box, providing a checkbox for users to select to a specific chosen group (like the homepage or main communications team).
(function($, LW) {
	// define pages to target with this transformation
	if (LW.page === 'events_edit' || LW.page === 'events_sub_edit' || LW.page === 'news_edit') {
		/*
		 * suggest to main calendar checkbox
		 */
		var group_id = LW.suggest_to_group_id;
		var group_obj = _.find(LW.groups, {id: String(group_id)});
		var group_name = (typeof group_obj !== 'undefined') ? group_obj.title : '';
		var $share_checkbox = $('#main_group_share input[type="checkbox"]');

		var $suggest = $('.group_suggest').bind('multisuggestchange', function(e) {
			var selected = $suggest.multisuggest('getSelected'),
					main_exists = selected.filter(s => s.id == group_id).length > 0;

			if (main_exists && !$share_checkbox.prop('checked')) {
				$share_checkbox.prop('checked', true);
			}
			if (!main_exists && $share_checkbox.prop('checked')) {
				$share_checkbox.prop('checked', false);
			}
		});
		$share_checkbox.click(function() {
			var selected, main_exists;

			if ($(this).prop('checked')) {
				selected = $suggest.multisuggest('getSelected');
				main_exists = selected.filter(s => s.id == group_id).length > 0;

				if (!main_exists) {
					$suggest.multisuggest('addItem', { id: group_id, title: group_name });
				}
			} else {
				$suggest.multisuggest('removeItem', group_id);
			}
		});
	}
}(livewhale.jQuery, livewhale));
