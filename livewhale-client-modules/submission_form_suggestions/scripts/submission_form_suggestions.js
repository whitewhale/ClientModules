(function ($, livewhale, undefined) {
    var $win = $(window),
        $body = $('body');


    // ALL FILTER DROPDOWNS
    // ----------------------------------------------------------------
    var initFilters = function () {

        // console.log('initFilters');

        var $filters = $('.filter');

        if ($filters.length) {

            var $dropdowns = $filters.find('.filter-dropdown');

            // toggle filter dropdowns on click or keypress
            $body.on('click keydown', '.filter .filter-label', function (e) {
                // e.preventDefault();

                var $this = $(this),
                    $thisFilter = $this.closest('.filter'),
                    $thisDropdown = $thisFilter.find('.filter-dropdown'),
                    $thisFilterButton = $thisFilter.find('.filter-label');

                // if clicking or pressing return key
                if (e.type == 'click' || e.type == 'keydown' && e.which == 13) {

                    // when the dropdown is collapsed
                    if (!$thisDropdown.is(':visible')) {

                        // close other dropdowns 
                        $filters.not($thisFilter).removeClass('is-active');
                        $dropdowns.not($thisDropdown).slideUp(0);

                        // open this dropdown if closed
                        $thisDropdown.slideDown(200);
                        $thisFilter.addClass('is-active');
                        $thisFilterButton.attr("aria-expanded", true);

                        // move focus to the first selected option, otherwise to the first option (check if online events checkbox)
                        var $selectedOptions = $thisDropdown.find('.filter-option').find('input:checked');
                        var $focusedOption;

                        if ($selectedOptions.length) {
                            $focusedOption = $selectedOptions.first()
                        } else if ($thisDropdown.find(":first-child").attr("id") == "lw_cal_online_selector") {
                            $focusedOption = $thisDropdown.find("#lw_cal_online_selector input");
                        } else {
                            $focusedOption = $thisDropdown.find('.filter-option').find('input, a').first();

                        }
                        $focusedOption.focus();

                    } else {

                        // when the dropdown is expanded, close it 
                        $thisDropdown.slideUp(100);
                        $thisFilter.removeClass('is-active')
                        $thisFilterButton.attr("aria-expanded", false);

                    }
                }
            });


            $body.on('click touchstart keydown', function (e) {

                // close all dropdowns if clicking/keypressing elsewhere
                if (!$filters.is(e.target) && $filters.has(e.target).length === 0) {
                    $filters.removeClass('is-active');
                    $dropdowns.slideUp(0);
                }

                // close all dropdowns if pressing escape while focused inside dropdown
                if (($filters.is(e.target) || $filters.has(e.target).length) && e.which == 27) {
                    $filters.removeClass('is-active');
                    $dropdowns.slideUp(0);
                    $(e.target).closest('.filter').find('.filter-label').focus(); // move focus back to label
                }
            });


            // when tabbing on a collapsed dropdown, close expanded dropdowns
            $body.on('keyup', '.filter .filter-label', function (e) {

                var $this = $(this),
                    $thisFilter = $this.closest('.filter'),
                    $thisDropdown = $thisFilter.find('.filter-dropdown');

                if (!$thisDropdown.is(':visible') && e.which == 9) {
                    $filters.removeClass('is-active');
                    $dropdowns.slideUp(0);
                }
            });

        }


        // show the number of selected filters next to the filter title
        var countFilters = function () {

            // do not count filters for the date range selector
            $filters.not('#lw_cal_date_range_selector').each(function () {
                var $thisFilter = $(this);
                var $titles = $thisFilter.find('.filter-title');
                var $origTitle = $titles.not('.is-active');
                var $activeTitle = $titles.filter('.is-active');
                var numSelected = $thisFilter.find('.filter-dropdown').find('input:checked').length;

                // if filters are selected
                if (numSelected > 0) {

                    // hide original title
                    $origTitle.hide();

                    if (!$activeTitle.length) {

                        // if there is no active title, replace original title with new title text
                        // remove "All" from start of title and capitalize first letter
                        var newTitleText = $origTitle.text().replace('All ', '');
                        newTitleText = newTitleText.charAt(0).toUpperCase() + newTitleText.slice(1);
                        $origTitle.before('<span class="filter-title is-active">' + newTitleText + ' (' + numSelected + ')</span>');

                    } else {

                        // if active title already exists, show it and update the number 
                        var updatedText = $activeTitle.text().replace(/\(.*?\)/, '(' + numSelected + ')');
                        $activeTitle.show().text(updatedText);
                    }

                } else {
                    // otherwise reinstate original title if no filters are selected
                    $activeTitle.hide();
                    $origTitle.show();
                }
            });
        };

        // Count selected filters on initial load
        countFilters();

        // Count selected filters when the calendar loads new view data
        $body.on('change', function (e, controller, data) {
            // wait before counting filters, in case clear filters button is clicked
            // in which case calendar view is loaded and then checkboxes are unchecked
            setTimeout(function () {
                countFilters();
            }, 300);
        });
    };


    if ($('.body_submit')) {
        initFilters();
    }


    $body.bind('calInit.lwcal', function (e, controller, data) {

        initFilters();

    });

}(livewhale.jQuery, livewhale));
