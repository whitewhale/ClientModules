/*globals _, livewhale */

// JS functionality for a redundant checkbox above the group suggest selector box, providing a checkbox for users to select to a specific chosen group (like the homepage or main communications team).
(function($, LW) {
	// define pages to target with this transformation
	if (LW.page === 'events_edit' || LW.page === 'events_sub_edit' || LW.page === 'news_edit') {
		/*
		 * suggest to main calendar checkbox
		 */
		var group_ids = LW.suggest_to_groups.map(function(obj) { return obj.group_id; });
		var $share_checkbox = $('.main_group_share input[type="checkbox"]');

		var getGroupName = function(id) {
			var group = _.find(LW.groups, {id: String(id)});
			return (group && group.title) ? group.title : '';
		};

		var $suggest = $('.group_suggest').bind('multisuggestchange multiselectchange', function(e) {
			let func = (e.type === 'multiselectchange') ? 'multiselect' : 'multisuggest';
			let selected = $suggest[func]('getSelected').map(function(obj) { return parseInt(obj.id, 10); });																																																					 $share_checkbox.each(function() {
				var $this = $(this);
				var val = parseInt($this.val(), 10);

				if (_.includes(selected, val)) {
					$this.prop('checked', true);
				} else {
					$this.prop('checked', false);
				}
			});
		});

		$share_checkbox.on('click', function() {
			var $this = $(this);
			var group_id = parseInt($this.val());
			var func_name = $suggest.data('lwMultiselect') !== undefined ? 'multiselect' : 'multisuggest';

			var selected = $suggest[func_name]('getSelected');
			var group_exists;

			if (func_name === 'multiselect') {
				group_exists = selected.find(o => parseInt(o.id) === group_id);

				if ($this.prop('checked') && !group_exists) {
					selected.push({id: group_id});
				}
				if (!$this.prop('checked') && group_exists) {
					selected = selected.filter(o => parseInt(o.id) !== group_id);
				}
				$suggest.multiselect('option', 'selected', selected);
			} else {
				group_exists = _.includes(selected, group_id);

				if ($this.prop('checked') && !group_exists) {
					$suggest.multisuggest('addItem', { id: group_id, title: getGroupName(group_id) });
				}
				if (!$this.prop('checked') && group_exists) {
					$suggest.multisuggest('removeItem', group_id);
				}
			}
		});
	}
}(livewhale.jQuery, livewhale));