if (livewhale.module == 'migration') { // if using the migrate tool
	$(function($) { // on DOM ready
		switch (livewhale.page) { // Actions to perform only on specific pages
		case 'migration':
			$('#migration_form').on('click', '#migration_submit', function() { // on submission
				if (!$('input[name=mode]:checked').length) { // validate migration mode
					alert('You must choose a migration mode.');
				}
				else {
					var approve = confirm('Are you sure you wish to run this migration task?' + ($('input[name=mode]:checked').val() == 'live' ? ' Taking content live will overwrite any existing content at the destination location. Please ensure that there are no conflicts before running.': '')); // approve submission
					if (approve) return true;
				};
				return false;
			});
			break;
		}
	});
}

