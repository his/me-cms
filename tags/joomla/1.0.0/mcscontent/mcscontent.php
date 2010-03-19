<?php

/*
 * Plugin für die Wurfl-Abfrage.
 *
 * Falls die Daten noch nicht in der Session liegen oder ein Auffrischen bei jedem
 * Request gewünscht ist, werden die Wurfl-Daten in die Session eingef�gt.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );
jimport('joomla.session.session');

class plgSystemMcscontent extends JPlugin {

	function onAfterRender() {

		// Nicht für den Adminbereich
		if ($this->isAdmin()) return;

		// Plugin-Parameter holen
		$plugin = &JPluginHelper::getPlugin('system', 'mcscontent');
		$pluginParams = new JParameter($plugin->params);

		$pluginParams->def('onlyonmobile', 1);
		$pluginParams->def('removetables', 1);
		$pluginParams->def('removeiframes', 1);
		$pluginParams->def('removeobjects', 1);
		$pluginParams->def('removeapplets', 1);
		$pluginParams->def('removescript', 1);

		$onlyonmobile = $pluginParams->get('onlyonmobile');
		$removetables = $pluginParams->get('removetables');
		$removeiframes = $pluginParams->get('removeiframes');
		$removeobjects = $pluginParams->get('removeobjects');
		$removeapplets = $pluginParams->get('removeapplets');
		$removescript = $pluginParams->get('removescript');

		$session = &JFactory::getSession();
			
		$currentWurflData = $session->get('mcs.wurfldata');

		// Run only when a) it should always run or b) it should run only for mobiles and wurfl indicates it is one
		
		if (!$onlyonmobile || ($onlyonmobile && $currentWurflData && $currentWurflData['product_info']['is_wireless_device'])) {
						
			$content = JResponse::getBody();
				
			if ($removetables) {
				$this->removeTables($content);
			}
				
			if ($removeiframes) {
				$this->removeIframes($content);
			}
				
			if ($removeobjects) {
				$this->removeObjects($content);
			}
				
			if ($removeapplets) {
				$this->removeApplets($content);
			}
				
			if ($removescript) {
				$this->removeScript($content);
			}
				
			JResponse::setBody($content);
		}

	}

	/**
	 * Removes table, tr and th tags. Replaces td tags with divs.
	 *
	 * @param $content
	 * @return unknown_type
	 */
	function removeTables(&$content) {
		$content = preg_replace('@<table\s[^>]+>@Uis','', $content);
		$content = preg_replace('@</table\s[^>]+>@Uis','<br/>', $content);

		$content = preg_replace('@<tr\s[^>]+>@Uis','<div>', $content);
		$content = preg_replace('@</tr\s[^>]+>@Uis','</div><br/>', $content);

		$content = preg_replace('@<th\s[^>]+>@Uis','<div>', $content);
		$content = preg_replace('@</th\s[^>]+>@Uis','</div><br/>', $content);

		$content = preg_replace('@<td\s[^>]+>@Uis','<span>', $content);
		$content = preg_replace('@</td\s[^>]+>@Uis','</span>', $content);
	}

	/**
	 * Removes iframes.
	 *
	 * @param $content
	 * @return unknown_type
	 */
	function removeIframes(&$content) {
		$content = preg_replace('@<iframe.+</iframe>@Uis','', $content);
		$content = preg_replace('@<iframe\s[^>]+/>@Uis','', $content);
	}

	/**
	 * Removes objects (including embeds).
	 *
	 * @param $content
	 * @return unknown_type
	 */
	function removeObjects(&$content) {
		$content = preg_replace('@<object.+<\/object>@Uis','', $content);
		$content = preg_replace('@<object\s[^>]+/>@Uis','', $content);

		$content = preg_replace('@<embed.+<\/embed>@Uis','', $content);
		$content = preg_replace('@<embed\s[^>]+/>@Uis','', $content);
	}

	/**
	 * Removes applets.
	 *
	 * @param $content
	 * @return unknown_type
	 */
	function removeApplets(&$content) {
		$content = preg_replace('@<applet.+<\/applet>@Uis','', $content);
		$content = preg_replace('@<applet\s[^>]+/>@Uis','', $content);
	}

	/**
	 * Removes (Java) Scripts.
	 * @param $content
	 * @return unknown_type
	 */
	function removeScript(&$content) {
		$content = preg_replace('@<script.+<\/script>@Uis','', $content);
		$content = preg_replace('@<script\s[^>]+/>@Uis','', $content);
	}

	function isAdmin() {
		global $mainframe;
		return $mainframe->isAdmin();
	}

}

?>