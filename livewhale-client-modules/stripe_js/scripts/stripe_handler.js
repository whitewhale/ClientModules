(function($, LW) {

  var $price_fieldset = $('.stripe-price'); // find Stripe price field
  var scForm = $price_fieldset.parents('form'); // find Stripe form (parent of field)
  var scToken = scForm.find('input[name=stripeToken]'),
      scEmail = scForm.find('input[name=stripeEmail]'),
      scName = scForm.find('input[name=sc-name]'),
      scAmount = scForm.find('input[name=sc-amount]'),
      scDescription = scForm.find('input[name=sc-description]'),
      scSubmit = scForm.find('input[type=submit]');

  // set LW items that we'll need to process the form
  LW.stripe_public_key = scForm.find('input[name=stripe_public_key]').val();
  LW.stripe_checkout_icon = scForm.find('input[name=stripe_checkout_icon]').val();
    
  var price_field_name = $price_fieldset.find('input').first().attr('name');
  var $price_field = $('input[name=' + price_field_name + ']');

  // Check for hidden value overrides:

  var scTitle = scName.val(), // use name of form by default
      popup_title_id = scForm.find('textarea[name=stripe_popup_title]').attr('id');
  if (popup_title_id !== undefined) {
    scTitle = $('#'+popup_title_id.replace('_custom','')).val(); // or override with hidden inpt
  }
  var scButton = 'Register', // use Register by default
      button_title_id = scForm.find('textarea[name=stripe_button_title]').attr('id');
  if (button_title_id !== undefined) {
    scButton = $('#'+button_title_id.replace('_custom','')).val(); // or override with hidden inpt
  }

  var handler = StripeCheckout.configure({
    key: LW.stripe_public_key,
    image: LW.stripe_checkout_icon,
    token: function(token) {

      // At this point the Stripe checkout overlay is validated and submitted.

      // Set the values on our hidden elements to pass via POST when submitting the form for payment.
      scToken.val(token.id);
      scEmail.val(token.email);
      scAmount.val(window.finalPaymentAmount);
      scDescription.val(window.finalPaymentString);
      
      // alert('Token submitting: ' + token.id);
      
      scForm.submit(); // debug
    }
  });


  // When payment button is clicked
  scSubmit.on('click', function(e) {

    // Do a quick LW form validation for missing fields
    var do_charge = true;
    scForm.find('.lw_forms_required').each(function(i) {
      if ($(this).hasClass('lw_forms_text') || $(this).hasClass('lw_forms_email_address')) {
        if (!$(this).find('.lw_forms_field > input, .lw_forms_field > textarea').first().val()) {
          $(this).find('label').css('color','#cc0000');
          do_charge = false;
        }
      } else if ($(this).hasClass('lw_forms_textarea')) {
        if (!$(this).find('.lw_forms_field > textarea').first().val()) {
          $(this).find('label').css('color','#cc0000');
          do_charge = false;
        }
      } else if ($(this).hasClass('lw_forms_radio')) {
        // TO DO: add validation for other field types?
      }
    });

    // If validation was passed, open Stripe popup:
    if (do_charge === true) {
      // Get payment amount and description from form
      var paymentAmount = $price_field.filter('input:checked').val();
      var paymentString = $price_field.filter('input:checked').next('label').text().replace(/ *\([^)]*\) */g, ""); // remove parentheticals from label

      if (!isNaN(paymentAmount) && paymentAmount > 0) { // if form has a cost, submit it using Stripe
        e.preventDefault();

        // Set final values      
        window.finalPaymentAmount = (100 * Number(paymentAmount).toFixed(2));
        window.finalPaymentString = paymentString ? ' ' + paymentString : '';

        // Open Checkout with further options
        handler.open({
          name: scTitle,
          description: finalPaymentString,
          // email: $('#lw_field_email_address input').val(), // let's not pre-fill email
          amount: (100 * Number(paymentAmount).toFixed(2)),
          panelLabel: scButton,
          billingAddress: false,
          shippingAddress: true,
          allowRememberMe: false
        });
      }

    } else {
      e.preventDefault();
      scForm.find('.lw_form_error').addClass('debug1').html('<strong>Please complete all required fields.</strong>');
    }
  });

  // Default to first payment option on load, unless one is already checked
  if (!$price_field.filter('input:checked').length) {
    $price_field.first().attr('checked','checked');
  }

}(livewhale.jQuery, livewhale));
