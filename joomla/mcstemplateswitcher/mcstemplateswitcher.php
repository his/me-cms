<?php

/**
 *Plugin for changing a template based on Wurfl data or custom user regexes.
 * @author		mr. mcs GmbH
 * @copyright	Copyright (C) 2009 - 2010 mr. mcs GmbH.
 * @license		GNU/GPL v2, see LICENSE.php
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );
jimport('joomla.session.session');

class plgSystemMcstemplateswitcher extends JPlugin {
	
	function onAfterInitialise() {
		
		// Nicht für den Adminbereich
		if ($this->isAdmin()) return;
		
		// Plugin-Parameter holen
		$plugin = &JPluginHelper::getPlugin('system', 'mcstemplateswitcher');
		$pluginParams = new JParameter($plugin->params);
		
		$pluginParams->def('templateLandscape', '');
		$pluginParams->def('templatePortrait', '');
		$pluginParams->def('templateSmall', '');
		$pluginParams->def('templateDefault', '');
		$pluginParams->def('smallSize', 128);
		$pluginParams->def('uaRegex1', '');
		$pluginParams->def('reTemplate1', '');
		$pluginParams->def('uaRegex2', '');
		$pluginParams->def('reTemplate2', '');
		$pluginParams->def('uaRegex3', '');
		$pluginParams->def('reTemplate3', '');
		
		$templateLandscape = $pluginParams->get('templateLandscape');
		$templatePortrait = $pluginParams->get('templatePortrait');
		$templateSmall = $pluginParams->get('templateSmall');
		$templateDefault = $pluginParams->get('templateDefault');
		
		
		
		$smallSize = $pluginParams->get('smallSize');
		$uaRegex1 = $pluginParams->get('uaRegex1');
		$reTemplate1 = $pluginParams->get('reTemplate1');
		$uaRegex2 = $pluginParams->get('uaRegex2');
		$reTemplate2 = $pluginParams->get('reTemplate2');
		$uaRegex3 = $pluginParams->get('uaRegex3');
		$reTemplate3 = $pluginParams->get('reTemplate3');
		
		$session = &JFactory::getSession();
			
		$currentWurflData = $session->get('mcs.wurfldata');
		
		if ($currentWurflData && $currentWurflData['product_info']['is_wireless_device']) {
			
			/* Überprüfen, welche der Bedingungen erfüllt ist
			
			1. Spezielle Bedingungen (i.e. regex 1..3)
			2. Klein
			3. Hoch
			4. Quer
			5. Default
			
			*/
			
			// Abbruch, wenn ein spezifisches Template gewählt
			$continueEvaluation = true;
			
			$userAgent = $_SERVER['HTTP_USER_AGENT'];
			$newtemplate = $templateDefault;
			$screenWidth = $currentWurflData['display']['resolution_width'];
			$screenHeight = $currentWurflData['display']['resolution_height'];
			
			if ($continueEvaluation && $reTemplate1 && preg_match("@".$uaRegex1."@i", $userAgent)) {
				$newtemplate = $reTemplate1;
				$continueEvaluation = false;
															
			}			
			
			if ($continueEvaluation && $reTemplate2 && preg_match("@".$uaRegex2."@i", $userAgent)) {
				$newtemplate = $reTemplate2;
				$continueEvaluation = false;
			}			
			
			if ($continueEvaluation && $reTemplate3 && preg_match("@".$uaRegex3."@i", $userAgent)) {
				$newtemplate = $reTemplate3;
				$continueEvaluation = false;
			}			
			
			if ($continueEvaluation && $templateSmall) {
				if ($screenHeight < $smallSize || $screenWidth < $smallSize) {
					$newtemplate = $templateSmall;
					$continueEvaluation = false;
				}
			}
			
			if ($continueEvaluation && $templateLandscape) {
				if ($screenWidth > $screenHeight) {
					$newtemplate = $templateLandscape;
					$continueEvaluation = false;
				}
			}
			
			if ($continueEvaluation && $templatePortrait) {
				if ($screenWidth <= $screenHeight) {
					$newtemplate = $templatePortrait;
					$continueEvaluation = false;
				}
			}
			
			if ($continueEvaluation) {
				$newtemplate = $templateDefault;
			}

			global $mainframe;
			$mainframe->set('setTemplate', $newtemplate);
		} 
		
	}
	
	function isAdmin() {
		global $mainframe;
		return $mainframe->isAdmin();
	}
	
}

?>