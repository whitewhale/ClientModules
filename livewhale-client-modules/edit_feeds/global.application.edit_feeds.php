<?php
     $_LW->REGISTERED_APPS['edit_feeds']=[
       'title'=>'Edit Feeds',
       'handlers'=>['onBeforeSync'],
    ];

    class LiveWhaleApplicationEditFeeds {

        public function onBeforeSync($type, $subscription_id, $buffer) { 
            global $_LW;
                
            if ($type == 'events') {

                // if this is an Athletics feed
                if (strpos(@$_LW->linked_calendar_url, '://athletics.cmu.edu/')!==false) {
                
                    // if title contains "Carnegie Mellon at" (i.e., an away game)
                    if (strpos($buffer['title'], 'Carnegie Mellon at') !== false) {
                        
                        // hide this event on first import
                        $buffer['is_hidden']=1;

                    };

                };
                
            }

            return $buffer;
        }
    }
?>