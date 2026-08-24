<?php
set_time_limit(3600*2); // same generous budget core's own CSV import exec scripts use -- a full wipe + regenerate
// of a large sample set (hundreds of events, each with image/place lookups) can otherwise exceed the default
// execution time limit and get killed mid-run with no error logged, since a timeout isn't a catchable PHP error
ini_set('memory_limit', '2G');
$result = $_LW->a_demo->populateEvents();
$_LW->dbo->query('update', 'livewhale_scheduler', ['next_execution'=>'"'.date('Y/m/d H:i:s', (int)mktime(3, 0, 0, date('m'), date('d')+7)).'"'], 'name="demo_weekly"')->run(); // set next run at 3am, 7 days out
echo json_encode($result); // so a manual trigger (see live/demo.php) can relay the outcome back to the caller

?>
