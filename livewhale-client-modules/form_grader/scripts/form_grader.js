;(function($) {

	// toggling grading
    $('body').on('change', '#is_graded', function() { // toggle correct answer elements according to grading toggle
		if ($(this).prop('checked')) {
			$('.element_correctanswer').removeClass('lw_hidden');
			$('.element_messages').removeClass('lw_hidden');
			$('section#grading').removeClass('lw_hidden');
		}
		else {
			$('.element_correctanswer').addClass('lw_hidden');
			$('.element_messages').addClass('lw_hidden');
			$('section#grading').addClass('lw_hidden');
		};
    });
	$('#is_graded').change();
	
}(livewhale.jQuery));