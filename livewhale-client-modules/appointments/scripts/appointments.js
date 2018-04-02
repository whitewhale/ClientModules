(function($) {
  // do nothing if this isn't the appointments or events module
  if (livewhale.module !== 'appointments' && livewhale.module !== 'events') return;

  function appointments() {
	  var $ul = $('ul#manager_appointments');

    if (!$ul.length && $('.lw_nonefound').length) {
      $('.lw_nonefound').parent().append('<ul id="manager_appointments" class="manager list-group"></ul>');
	  $ul = $('ul#manager_appointments');
    }

    // edit existing
    $ul.on('click', 'h5 a', function(e) {
		e.preventDefault();

      var $this = $(this),
          $h5   = $this.parent().hide(), // cache and hide h5
          appointment = $this.text(),
          html, $form;

      html = '<div class="appointment_fields">'
           + '<input type="text" class="appointment_input form-control input-sm"/>'
           + '<input type="button" value="Save appointment" class="appointment_save btn btn-default btn-sm"/>'
           + '<span class="lw_cancel">or <a href="#">cancel</a></span>'
           + '</div>';
      $form = $(html).insertAfter($h5);
      $form.find('input[type=text]')
        .val(appointment)
        .focus()
        .keypress(function(e) {
          if (e.keyCode === 13) { // capture enter
            $(this).next().click();
            return false;
          }
        });
      return true; // cancel the link click
    })
    // when clicking to save the appointment
    .on('click', '.appointment_save', function() {
      var $this = $(this),
          title = encodeURIComponent( $this.prev().val() ),
          id    = $this.closest('li').find('.with_this').val() || '',
          url   = '/livewhale/backend.php?livewhale=ajax&function=saveAppointmentsTitle&id=' + id + '&title=' + title;

      $.getJSON(url, function(data) {
		  var appointments_url = '/livewhale/backend.php?livewhale=ajax&function=getAppointmentsList';

        // reload the appointments list
        $ul.load(appointments_url + ' ul#manager_appointments > li', function() {

        });
        if (data.error) alert(data.error); // alert if appointment already exists
      });
    }).on('click', '.lw_cancel a', function() {
      var $this = $(this),
          $li   = $this.closest('li'),
          appointment   = $.trim( $li.find('h5 a').text() );

      if (!appointment) { // if this is a new appointment
        $li.remove(); // kill the whole li
        if (!$ul.children().length) { // if this was the last LI
          $ul.append('<li class="lw_nonefound list-group-item">None found.</li>');
        }
      } else {
        $this.closest('.appointment_fields')
          .siblings('h5')
            .show()
            .end()
          .remove();
      }
      return false; // cancel the original click
    });

    // add new
    $('.addnew').click(function() {
      var html;

      // do nothing if a new item already exists
      if ($ul.children('.new_appointment').length) return;

      if ($('.lw_nonefound').length) {
        $('.lw_nonefound').remove();
      }

      html = '<li class="new_appointment list-group-item">'
           + '<input type="checkbox" style="display:none;" value="" name="items[]"/>'
           + '<h5><a></a></h5>'
           + '</li>';
      $ul.prepend(html).children().eq(0).find('a').click();
      return false; // cancel the click
    });
  }

  function addAppointmentSelectorToEventEditor() {
    var $selector = $('.appointment_selector');

    if ($.isArray(livewhale.appointments) && livewhale.appointments.length) {
       $selector.multisuggest({
        name: 'appointments',
        type: 'times',
        create: true,
        data: livewhale.appointments,
        selected: livewhale.editor.values.appointments
      }).after('<div class="note">Use [Tab] to separate times</div>');
    } else {
      $selector.html('<p>No appointment slots. <a href="?appointments">Add one?</a></p>');
    }
  }


  $(function($) { // on DOM ready
    if (livewhale.page === 'appointments') {
      appointments();
    }
    if (livewhale.page === 'events_edit' || livewhale.page === 'events_sub_edit') {
      addAppointmentSelectorToEventEditor();
    }
  });
}(jQuery));
