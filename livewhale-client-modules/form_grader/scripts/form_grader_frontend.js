(function($) {

  // randomize graded fields
  $('body').find('.lw_forms_form.graded_quiz').each(function() {
    var $this = $(this);
    var $fields = $this.find('fieldset.graded');
    var $prev = $fields.eq(0).prev();
    var index = $fields.length;
    var $randomized = $('<div/>');
    var random_index;
    var $field;

    // While there remain elements to shuffle...
    while (0 !== index) {
      // Pick a remaining element...
      random_index = Math.floor(Math.random() * index);
      index -= 1;

      // And swap it with the current element.
      $field = $fields.eq(random_index);
      $randomized.append($field);
      $fields = $fields.not($field);
    }

    if ($prev.length) {
      $prev.after($randomized.children());
    } else {
      $this.prepend($randomized.children());
    }
    $this.addClass('visible');
  });

}(livewhale.jQuery));