<?php
/***********************************************************************
| Cerberus Helpdesk(tm) developed by WebGroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2012, WebGroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerberusweb.com	  http://www.webgroupmedia.com/
***********************************************************************/

class PageSection_ProfilesDomain extends Extension_PageSection {
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$visit = CerberusApplication::getVisit();
		$request = DevblocksPlatform::getHttpRequest();
		$translate = DevblocksPlatform::getTranslationService();
		
		$active_worker = CerberusApplication::getActiveWorker();
		
		$stack = $request->path;
		@array_shift($stack); // profiles
		@array_shift($stack); // domain
		@$identifier = array_shift($stack);
		
		if(is_numeric($identifier)) {
			$id = intval($identifier);
		} elseif(preg_match("#.*?\-(\d+)$#", $identifier, $matches)) {
			@$id = intval($matches[1]);
		} else {
			@$id = intval($identifier);
		}
		
		if(is_numeric($id) && null != ($domain = DAO_Domain::get($id)))
			$tpl->assign('domain', $domain);

		// Remember the last tab/URL
		
		$point = 'cerberusweb.datacenter.domain.tab';
		$tpl->assign('point', $point);

		@$selected_tab = array_shift($stack);
		
		if(null == $selected_tab) {
			$selected_tab = $visit->get($point, '');
		}
		$tpl->assign('selected_tab', $selected_tab);
		
		$tab_manifests = DevblocksPlatform::getExtensions('cerberusweb.datacenter.domain.tab', false);
		DevblocksPlatform::sortObjects($tab_manifests, 'name');
		$tpl->assign('tab_manifests', $tab_manifests);

		// Custom fields

		$custom_fields = DAO_CustomField::getAll();
		$tpl->assign('custom_fields', $custom_fields);

		// Properties

		$properties = array();

		if(!empty($domain->server_id)) {
			if(null != ($server = DAO_Server::get($domain->server_id))) {
				$properties['server'] = array(
					'label' => ucfirst($translate->_('cerberusweb.datacenter.common.server')),
					'type' => null,
					'server' => $server,
				);
			}
		}

		$properties['created'] = array(
			'label' => ucfirst($translate->_('common.created')),
			'type' => Model_CustomField::TYPE_DATE,
			'value' => $domain->created,
		);

		@$values = array_shift(DAO_CustomFieldValue::getValuesByContextIds('cerberusweb.contexts.datacenter.domain', $domain->id)) or array();

		foreach($custom_fields as $cf_id => $cfield) {
			if(!isset($values[$cf_id]))
				continue;

			$properties['cf_' . $cf_id] = array(
				'label' => $cfield->name,
				'type' => $cfield->type,
				'value' => $values[$cf_id],
			);
		}

		$tpl->assign('properties', $properties);

		// Macros
		$macros = DAO_TriggerEvent::getByOwner(CerberusContexts::CONTEXT_WORKER, $active_worker->id, 'event.macro.domain');
		$tpl->assign('macros', $macros);

		$tpl->display('devblocks:cerberusweb.datacenter.domains::domain/profile.tpl');		
	}
};