// JS functionality for a redundant checkbox above the tag suggest selector box, providing a checkbox for users to select to a specific chosen tag 

(function($, LW) {
  var tag_id = '64';
  var tag_name = 'Daily Eagle';

  if (LW.page === 'events_edit' || LW.page === 'events_sub_edit' || LW.page === 'news_edit') {  // define pages to target with this transformation

    // suggest to main calendar checkbox
    var $share_checkbox = $('input[name="dailyeagle_newsletter"]');
    var $suggest = $('.tag_suggest').bind('multisuggestchange', function(e) {
      var selected = $suggest.multisuggest('getSelected'),
          main_exists = (_.findIndex(selected, { id: tag_id }) > -1);

      if (main_exists && !$share_checkbox.prop('checked')) {
        $share_checkbox.prop('checked', true);
      }
      if (!main_exists && $share_checkbox.prop('checked')) {
        $share_checkbox.prop('checked', false);
      }
    });
    $share_checkbox.click(function() {
      var checked = $(this).prop('checked'),
          selected, main_exists;

      if ($(this).prop('checked')) {
        selected = $suggest.multisuggest('getSelected');
        main_exists = (_.findIndex(selected, { id: tag_id }) > -1);

        if (!main_exists) {
          $suggest.multisuggest('addItem', { id: tag_id, title: tag_name });
        }
      } else {
        $suggest.multisuggest('removeItem', tag_id);
      }
    });
  }
}(livewhale.jQuery, livewhale));
