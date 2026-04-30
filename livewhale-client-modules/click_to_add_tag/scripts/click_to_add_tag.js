// JS functionality for a redundant checkbox above the tag suggest selector box, providing a checkbox for users to select to a specific chosen tag 

(function($, LW) {

  if ((LW.page === 'events_edit' || LW.page === 'events_sub_edit') && (LW.group === 'Center for Fine Arts' || LW.group === 'Arts and Culture')) {  // define pages to target with this transformation
    
    var $tag_select = $('.categories.cfa input[type="checkbox"]');

    var $suggest = $('.tag_suggest').bind('multisuggestchange', function(e) {
      var selected = $suggest.multisuggest('getSelected').map(function(obj) { return parseInt(obj.id, 10); });
    
      $tag_select.each(function() {
        var $this = $(this);
        var val = parseInt($this.val(), 10);
    
        if (_.includes(selected, val)) {
          $this.prop('checked', true);
        } else {
          $this.prop('checked', false);
        }
      });
    });
    $tag_select.click(function() {
      var $this = $(this);
      var tag_id = $this.val();
      var $label = $this.attr('data-name');
    
      if ($this.prop('checked')) {
        var selected = $suggest.multisuggest('getSelected');
        var tag_exists = _.includes(selected, tag_id);
    
        if (!tag_exists) {
          $suggest.multisuggest('addItem', { id: tag_id, title: $label });
        }
      } else {
        $suggest.multisuggest('removeItem', tag_id);
      }
    });
  }
}(livewhale.jQuery, livewhale));
