<?php

/**
 * Removes content with a specific css class from the generated content.
 *
 * Uses the Simple Html DOM from http://sourceforge.net/projects/simplehtmldom/
 * (MIT-License, see simple_html_dom.php)
 *
 * @author		mr. mcs GmbH
 * @copyright	Copyright (C) 2009 - 2010 mr. mcs GmbH.
 * @license		GNU/GPL v2, see LICENSE.php
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );
jimport('joomla.session.session');

class plgSystemMcsremoveunsuitable extends JPlugin {

	function onAfterRender() {

		// Nicht für den Adminbereich
		if ($this->isAdmin()) return;

		// Plugin-Parameter holen
		$plugin = &JPluginHelper::getPlugin('system', 'mcsremoveunsuitable');
		$pluginParams = new JParameter($plugin->params);

		$pluginParams->def('stripcssclass', '');
		$pluginParams->def('onlyformobile', '');

		$stripcssclass = $pluginParams->get('stripcssclass');
		$onlyformobile = $pluginParams->get('onlyformobile');

		$session = &JFactory::getSession();
			
		$currentWurflData = $session->get('mcs.wurfldata');

		$content = JResponse::getBody();
		include_once 'simple_html_dom.php';
			
		$html = str_get_html($content);

		
		if ($currentWurflData) {

			if ($currentWurflData['product_info']['is_wireless_device']) {
				if ($stripcssclass) {
					foreach ($html->find('[class*='.$stripcssclass.']') as $element) {
						$element->outertext = "";
					}
					JResponse::setBody($html);
				}
			} else {
				if ($onlyformobile) {
					foreach ($html->find('[class*='.$onlyformobile.']') as $element) {
						$element->outertext = "";
					}
					JResponse::setBody($html);
				}
			}
		}  else {
			if ($onlyformobile) {
				foreach ($html->find('[class*='.$onlyformobile.']') as $element) {
					$element->outertext = "";
				}
				JResponse::setBody($html);
			}
		}
	}

	function isAdmin() {
		global $mainframe;
		return $mainframe->isAdmin();
	}
}

?>