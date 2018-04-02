(function($) {
  // this script loads each time a form loads, don't execute if it has already run
  if (window.lw_calendar_appointments) return;
  window.lw_calendar_appointments = true;

  var $body = $('body');

  function initAppointmentSlots() {
    var $appointments = $('#appointments'),
        slots = [],
        $form, $select, $items;

    if ($appointments.length) {
      $select = $appointments.find('#appointment_select');
      $form = $('#lw_payments_table')
        .add('.lw_payments_field_events_comments_label')
        .add('#lw_payments_field_events_comments1')
        .add('.lw_payments_charge_submit_wrapper')
        .hide();

      // build array from time slots
      $select.children('span').each(function() {
        var $this = $(this),
            time = $this.attr('data-time'),
            is_filled = !!parseInt($this.attr('data-filled'), 10);

        slots.push({
          id: time,
          title: time,
          is_locked: is_filled
        });
      });
      $select.empty();

      // init multiselect
      if (slots.length) {
        console.log('select', $select.length, $select);
        $select.multiselect({
          name:     'appointments',
          onlyone:  true,
          type:     'times',
          data:     slots,
          change: function() {
            $form.show();
          }
        });

        // no easy way to add css for this module, and so we add CSS here
        $select.find('ul').css('padding-left', '0');
        $appointments.show();
      } else {
        $form.show();
      }
    }
  }

  // we can't rely on this event for the initial load because this file sometimes loads before the form
  // call initAppointmentSlots directly on the initial load, then bind to the event for subsequent loads
  setTimeout(function() {
    $body.bind('paymentFormLoad.lw', initAppointmentSlots);
  }, 2000);
  initAppointmentSlots();
}(livewhale.jQuery));
