((LW, global) => {

	function initEMSGroupSelector() {
		let ems_group = LW.editor.values.ems_group || {};

		$('.ems_group_suggest').multisuggest({
			name: 'ems_group',
			type: 'ems_group',
			data: LW.ems_groups,
			selected: ems_group,
			create: false
		});
	}

	if (LW.page === 'groups_edit') {
		initEMSGroupSelector();
	}

})(livewhale, window);