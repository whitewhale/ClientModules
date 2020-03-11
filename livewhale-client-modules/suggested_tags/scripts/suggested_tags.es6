(function() {
  var eventEditor = {
    init: function() {
      this.initSuggestedTags();
    },
    initSuggestedTags: function() {
      if (!livewhale.suggested_event_tags) {
        return false;
      }
      var that = this,
        $tags = $('fieldset.tags'),
        $tag_suggest = $tags.find('.tag_suggest');
      this.$suggested_tags = $('<div class="suggested_tags lw-multisuggest"></div>').hide().appendTo($tags);
      this.$categories = $('fieldset.categories');
      if (livewhale.suggested_event_tags) {
        this.showSuggestedTags();
      }
      this.$categories.find('input[type=checkbox]').on('click', function() {
        that.showSuggestedTags();
        return true;
      });
      $tag_suggest.bind('multisuggestchange', $.proxy(this.showSuggestedTags, this));
      this.$suggested_tags.on('click', '.suggested_tag', function(e) {
        e.preventDefault();
        var $this = $(this);
        $tag_suggest.multisuggest('addItem', {
          id: $this.attr('href'),
          title: $this.text()
        });
        $this.remove();
        if (!that.$suggested_tags.find('.suggested_tag').length) {
          that.$suggested_tags.hide();
        }
        return true;
      });
    },
    showSuggestedTags: function() {
      var that = this,
        suggested_event_tags = [];
      this.$suggested_tags.hide();
      this.$categories.find('input[type=checkbox]:checked').each(function(key, cb) {
        var title = $(cb).parent().text().trim();
        $.each(livewhale.suggested_event_tags, function(category, tags) {
          if (category === title) {
            $.each(tags, function(key2, tag) {
              if ($.inArray(tag, suggested_event_tags) !== false && !$('.lw-multisuggest-tags .lw-item .lw-name:contains("' + tag + '")').length) {
                suggested_event_tags.push(tag);
              }
            });
          }
        });
      });
      this.$suggested_tags.empty();
      if (suggested_event_tags.length) {
        $.each(suggested_event_tags, function(key, tag) {
          $.each(livewhale.tags, function(key2, tag2) {
            if (tag2.title === tag) {
              that.$suggested_tags.append('<a href="' + tag2.id + '" class="suggested_tag lw-item">' + tag + '</a> ');
            }
          });
        });
        if (!this.$suggested_tags.is(':empty')) {
          this.$suggested_tags.show().prepend('<h4>Suggested tags for event type(s):</h4>');
        }
      }
    }
  };
  var eventTypeManager = {
    init: function() {
      var that = this;
      this.$form = $('#manager');
      this.$list = $('#manager_events_categories');
      this.data = livewhale.suggested_event_tags || [];
      this.tags = livewhale.global_tags;

      this.insertTagInputsAndLabels();

      this.$list.on('click', '.add-tags', function(e) {
        var $this = $(this);
        var $li = $this.closest('li');
        var html;

       $li.find('.item_info').hide();

        if (!$li.find('.tags-wrapper').length) {
          html = '<fieldset class="tags-wrapper" style="margin-top: 12px; margin-bottom: 8px;">'
               + '<label>Suggested tags for event type</label>'
               + '<div class="tags_menu"></div>'
               + '</fieldset>';
          $li.append(html);
          that.initTagsMenu($li);
        } else {
          that.showTags($li);
        }
      })
      .on('click', '.category_save, .lw_cancel a', function(e) {
        e.preventDefault();
        var $li = $(this).closest('li');
        that.hideTags($li);
        $li.find('.item_info').show();
        return true;
      });

      $('body')
        .bind('eventTypeManagerBeforeLoad', $.proxy(this.updateDataFromInputs, this))
        .bind('eventTypeManagerLoad', $.proxy(this.refresh, this));
    },
    updateDataFromInputs: function() {
      var that = this;
      var data = {};
      this.$list.find('input[name=suggested_tags\\[\\]]').each(function() {
        var val = $(this).val();
        var vals = val.split('-');
        var type_id = vals[0];
        var tag_id = vals[1];
        var tag_title = _.find(that.tags, {id:tag_id});

        if (typeof data[type_id] === 'undefined') {
          data[type_id] = [];
        }
        data[type_id].push({
          id: val,
          title: tag_title.title
        });
      });
      this.data = data;
    },
    refresh: function() {
      this.insertTagInputsAndLabels();
    },
    insertTagInputsAndLabels: function() {
      var that = this;
      if ($.isPlainObject(this.data) || $.isArray(this.data)) {
        this.$list.find('li').each(function() {
          var $this = $(this);
          var id = $this.find('.with_this').val();
          if ($.isArray(that.data[id])) {
            var tags = [];
            $.each(that.data[id], function(i, tag) {
              $this.prepend('<input type="hidden" name="suggested_tags[]" value="' + tag.id + '">');
              tags.push(tag.title);
            });
          };
          $this.find('h5').after('<div class="item_info" style="clear: both;"><span class="tags">' + (tags ? tags.join(', ') : '') + ' <a href="#" class="add-tags">Add Tags...</a></span></div>');
        });
      }
    },
    initTagsMenu: function($li) {
      var that = this;
      var id = $li.find('.with_this').val();
      var $wrapper = $li.find('.tags-wrapper');
      var $tags = $li.find('.tags_menu');

      if (typeof $tags.data('lw-multisuggest') === 'undefined') {
        var tags = _.map(that.tags, function(obj) {
          var res = _.clone(obj);
          res.id = $li.find('.with_this').val() + '-' + res.id;
          return res;
        });
        $li.find('input[name=suggested_tags\\[\\]]').remove();
        $tags.multisuggest({
          name: 'suggested_tags',
          type: 'tags',
          selected: that.data[id] || [],
          data: tags,
          create: true,
          change: function(a, b, c) {
            livewhale.lib.changedData.show();
  	      	$.each($(this).find('input[name^="suggested_tags_added"]'), function(i, added_tag) { // update format of suggested_tags_added so that we can create/associate them
				if (!$(this).val().match('-')) {
					$(this).val(id+'-'+$(this).val());
				};
  	      	});
          }
        });
      }
    },
    hideTags: function($li) {
      $li.find('.tags-wrapper').hide();
    },
    showTags: function($li) {
      $li.find('.tags-wrapper').show();
    },
    showTagsToggleButton: function($li) {
      $li.find('.tags-toggle').show();
    },
    hideTagsToggleButton: function($li) {
      $li.find('.tags-toggle').hide();
    }
  };
  if (livewhale.page === 'events_edit' || livewhale.page === 'events_sub_edit') {
    eventEditor.init();
  }
  else if (livewhale.page === 'events_categories') {
    eventTypeManager.init();
  }
})();