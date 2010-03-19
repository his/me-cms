<?php
/**
 * @copyright	Copyright (C) 2005 - 2008 Open Source Matters. All rights reserved.
 * @license		GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// TODO Old-style plugin, convert to 1.5 style
$mainframe->registerEvent( 'onPrepareContent', 'plgContentMcsmobilepagebreak' );

/**
 * Page break plugin
 *
 * A stripped down variant of the standard pagebreak plugin.
 */
function plgContentMcsmobilepagebreak( &$row, &$params, $page=0 )
{

	// expression to search for
	$regex = '#<hr([^>]*?)class=(\"|\')mcsmobile-pagebreak(\"|\')([^>]*?)\/*>#iU';

	// Get Plugin info
	$plugin			=& JPluginHelper::getPlugin('content', 'mcsmobilepagebreak');
	$pluginParams	= new JParameter( $plugin->params );

	$session = &JFactory::getSession();

	$currentWurflData = $session->get('mcs.wurfldata');

	if (!($currentWurflData && $currentWurflData['product_info']['is_wireless_device'])) {
		$row->text = preg_replace( $regex, '', $row->text );
		return;
	}

	$print   = JRequest::getBool('print');
	$showall = JRequest::getBool('showall');

	$showallparameter = $pluginParams->get('showall', 1);
	
	pglContentMcsmobilepagebreakRemoveDefaultPagebreaks($row);

	JPlugin::loadLanguage( 'plg_content_mcsmobilepagebreak' );

	if ($print || !$pluginParams->get('enabled',1)) {
		$row->text = preg_replace( $regex, '<br />', $row->text );
		return true;
	}

	//simple performance check to determine whether bot should process further
	if ( strpos( $row->text, 'class="mcsmobile-pagebreak' ) === false && strpos( $row->text, 'class=\'mcsmobile-pagebreak' ) === false ) {
		return true;
	}

	$view  = JRequest::getCmd('view');

	if(!$page) {
		$page = 0;
	}

	// check whether plugin has been unpublished
	if (!JPluginHelper::isEnabled('content', 'mcsmobilepagebreak') || $params->get( 'intro_only' )|| $params->get( 'popup' ) || $view != 'article') {
		$row->text = preg_replace( $regex, '<br />', $row->text );
		return;
	}

	// find all instances of plugin and put in $matches
	$matches = array();
	preg_match_all( $regex, $row->text, $matches, PREG_SET_ORDER );

	if (($showall && $pluginParams->get('showall', 1) ))
	{
		$row->text = preg_replace( $regex, '<br/>', $row->text );
		return true;
	}

	// split the text around the plugin
	$text = preg_split( $regex, $row->text );

	// count the number of pages
	$n = count( $text );

	// we have found at least one plugin, therefore at least 2 pages
	if ($n > 1)
	{
		// Get plugin parameters
		$pluginParams = new JParameter( $plugin->params );
		$title	= $pluginParams->get( 'title', 1 );
		$hasToc = $pluginParams->get( 'multipage_toc', 1 );

		// reset the text, we already hold it in the $text array
		$row->text = '';

		$row->toc = '';

		// traditional mos page navigation
		jimport('joomla.html.pagination');
		$pageNav = new JPagination( $n, $page, 1 );

		// page counter
		$row->text .= '<div class="pagenavcounter">';
		$row->text .= $pageNav->getPagesCounter();
		$row->text .= '</div>';

		// page text
		$text[$page] = str_replace("<hr id=\"\"system-readmore\"\" />", "", $text[$page]);
		$row->text .= $text[$page];

		$row->text .= '<br />';
		$row->text .= '<div class="pagenavbar">';

		// page links shown at bottom of page if TOC disabled
		$row->text .= $pageNav->getPagesLinks();

		if ($showallparameter) {
			$row->text .= ' <strong><a href="'.JRoute::_( '&showall=1&limitstart=').'">'.JText::_('Show All').'</a></strong>';
		}

		$row->text .= '</div><br />';
	}

	return true;
}

/**
 * Removes default pagebreaks from the content so the normal pagination will not interfere with the mobile pagination.
 * @param $row
 * @return unknown_type
 */
function pglContentMcsmobilepagebreakRemoveDefaultPagebreaks(&$row) {
	$regex = '#<hr([^>]*?)class=(\"|\')system-pagebreak(\"|\')([^>]*?)\/*>#iU';
	$row->text = preg_replace( $regex, '<br />', $row->text );
}