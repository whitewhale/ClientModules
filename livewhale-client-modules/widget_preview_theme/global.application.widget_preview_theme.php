<?php

$_LW->REGISTERED_APPS['widget_preview_theme']=[
	'title'=>'Widget Preview Theme',
	'handlers'=>['onLoad', 'onWidgetPreviewOutput']
]; // configure this module

class LiveWhaleApplicationWidgetPreviewTheme {

public function onLoad() { // on module load
global $_LW; 
if (!empty($_LW->_GET['use_theme'])) { // set a requested theme for widget preview
	$_LW->setcookie($_LW->cookie_prefix.'use_theme', $_LW->_GET['use_theme'], 32503698000, '/', $_LW->cookie_host, false, true);
	die('Now using theme "'.$_LW->setFormatClean($_LW->_GET['use_theme']).'" for widget previews.');
};
}

public function onWidgetPreviewOutput($buffer) { // on widget preview
global $_LW;
if (!empty($_LW->_COOKIE[$_LW->cookie_prefix.'use_theme'])) { // allow override of theme
	return $buffer.'<xphp var="theme">'.$_LW->setFormatClean($_LW->_COOKIE[$_LW->cookie_prefix.'use_theme']).'</xphp>';
};
return $buffer;
}

}

?>