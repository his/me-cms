<?php

/*
 * Plugin for getting the wurfl data and putting it into the session.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );
jimport('joomla.session.session');

class plgSystemMcswurfl extends JPlugin {
	
	function onAfterInitialise() {

		// Nicht für den Adminbereich
		if ($this->isAdmin()) return;

		
		// Get plugin parameter
		$plugin = &JPluginHelper::getPlugin('system', 'mcswurfl');
		$pluginParams = new JParameter($plugin->params);
		
		$pluginParams->def('refreshAlways', 0);
		
		$refreshAlways = $pluginParams->get('refreshAlways');

		$session = &JFactory::getSession();
			
		$currentWurflData = $session->get('mcs.wurfldata');
		

		// Only execute if no data loaded or if they should be loaded at every request.
		if (!$currentWurflData || $refreshAlways) {
			require_once 'wurfl/wurfl_config.php';
			require_once 'wurfl/wurfl_class.php';
			
			$device = new wurfl_class();

			try {
			
				$device->GetDeviceCapabilitiesFromAgent($_SERVER['HTTP_USER_AGENT']);

				$session->set('mcs.wurfldata', $device->capabilities);

			} catch (Exception $e) {
				
				// If no data is found an exception will be thrown. Storing a marker array avoids a recheck for every request.
				$session->set('mcs.wurfldata', array());
				
			}
		}
		
	}
	
	function isAdmin() {
		global $mainframe;
		return $mainframe->isAdmin();
	}
	
}

?>