<?php
if(class_exists('Extension_ContextProfileTab', true)):
class ChDomainsServerTab extends Extension_ContextProfileTab {
	function showTab($context, $context_id) {
		if(0 != strcasecmp($context, CerberusContexts::CONTEXT_SERVER))
			return;
		
		$tpl = DevblocksPlatform::getTemplateService();

		// View
		$view_id = 'server_domains';
		
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->class_name = 'View_Domain';
		
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->id = $view_id;
		$view->name = 'Domains';
		$tpl->assign('view', $view);
		
		$view->addParamsHidden(array(
			SearchFields_Domain::SERVER_ID,
		), true);
		$view->addParamsRequired(array(
			SearchFields_Domain::SERVER_ID => new DevblocksSearchCriteria(SearchFields_Domain::SERVER_ID, '=', $context_id),
		), true);
		
		C4_AbstractViewLoader::setView($view_id, $view);

		// Template
		$tpl->display('devblocks:cerberusweb.datacenter.domains::server_tab/index.tpl');
	}
};
endif;

// Controller
class Page_Domains extends CerberusPageExtension {
	function isVisible() {
		// The current session must be a logged-in worker to use this page.
		if(null == ($worker = CerberusApplication::getActiveWorker()))
			return false;
		return true;
	}
	
	function render() {
	}
	
	function saveDomainPeekAction() {
		$active_worker = CerberusApplication::getActiveWorker();
		
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer',0);
		@$name = DevblocksPlatform::importGPC($_REQUEST['name'],'string','');
		@$server_id = DevblocksPlatform::importGPC($_REQUEST['server_id'],'integer',0);
		@$created = DevblocksPlatform::importGPC($_REQUEST['created'],'string','');
		@$contact_address_ids = DevblocksPlatform::importGPC($_REQUEST['contact_address_id'],'array',array());
		@$comment = DevblocksPlatform::importGPC($_REQUEST['comment'], 'string', '');
		@$do_delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'],'integer',0);
		
		if($do_delete) { // delete
			DAO_Domain::delete($id);
			
		} else { // create | update
			if(false == (@$created = strtotime($created)))
				$created = time();
			
			$fields = array(
				DAO_Domain::NAME => $name,
				DAO_Domain::SERVER_ID => $server_id,
				DAO_Domain::CREATED => $created,
			);
			
			// Create/Update
			if(empty($id)) {
				$id = DAO_Domain::create($fields);
				
				@$is_watcher = DevblocksPlatform::importGPC($_REQUEST['is_watcher'],'integer',0);
				if($is_watcher)
					CerberusContexts::addWatchers('cerberusweb.contexts.datacenter.domain', $id, $active_worker->id);
				
			} else {
				DAO_Domain::update($id, $fields);
			}
			
			// If we're adding a comment
			if(!empty($comment)) {
				@$also_notify_worker_ids = DevblocksPlatform::importGPC($_REQUEST['notify_worker_ids'],'array',array());
				
				$fields = array(
					DAO_Comment::CREATED => time(),
					DAO_Comment::CONTEXT => 'cerberusweb.contexts.datacenter.domain',
					DAO_Comment::CONTEXT_ID => $id,
					DAO_Comment::COMMENT => $comment,
					DAO_Comment::ADDRESS_ID => $active_worker->getAddress()->id,
				);
				$comment_id = DAO_Comment::create($fields, $also_notify_worker_ids);
			}
			
			// Context Link (if given)
			@$link_context = DevblocksPlatform::importGPC($_REQUEST['link_context'],'string','');
			@$link_context_id = DevblocksPlatform::importGPC($_REQUEST['link_context_id'],'integer','');
			if(!empty($id) && !empty($link_context) && !empty($link_context_id)) {
				DAO_ContextLink::setLink(CerberusContexts::CONTEXT_DOMAIN, $id, $link_context, $link_context_id);
			}
			
			// Custom field saves
			@$field_ids = DevblocksPlatform::importGPC($_POST['field_ids'], 'array', array());
			DAO_CustomFieldValue::handleFormPost('cerberusweb.contexts.datacenter.domain', $id, $field_ids);
			
			// Address context links
			DAO_ContextLink::setContextOutboundLinks('cerberusweb.contexts.datacenter.domain', $id, CerberusContexts::CONTEXT_ADDRESS, $contact_address_ids);
		}
		
	}
	
	function showDomainBulkUpdateAction() {
		@$ids = DevblocksPlatform::importGPC($_REQUEST['ids']);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id']);

		$active_worker = CerberusApplication::getActiveWorker();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);

	    if(!empty($ids)) {
	        $id_list = DevblocksPlatform::parseCsvString($ids);
	        $tpl->assign('ids', implode(',', $id_list));
	    }
		
   		// Groups
		$groups = DAO_Group::getAll();
		$tpl->assign('groups', $groups);
		
		// Custom Fields
		$custom_fields = DAO_CustomField::getByContext('cerberusweb.contexts.datacenter.domain');
		$tpl->assign('custom_fields', $custom_fields);
		
		// Broadcast
		CerberusContexts::getContext('cerberusweb.contexts.datacenter.domain', null, $token_labels, $token_values);
		$tpl->assign('token_labels', $token_labels);
		
		// Macros
		$macros = DAO_TriggerEvent::getByOwner(CerberusContexts::CONTEXT_WORKER, $active_worker->id, 'event.macro.domain');
		$tpl->assign('macros', $macros);
		
		$tpl->display('devblocks:cerberusweb.datacenter.domains::domain/bulk.tpl');
	}
	
	function doDomainBulkUpdateAction() {
		$active_worker = CerberusApplication::getActiveWorker();
		
		// Filter: whole list or check
	    @$filter = DevblocksPlatform::importGPC($_REQUEST['filter'],'string','');
		$ids = array();
	    
	    // View
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		$view = C4_AbstractViewLoader::getView($view_id);
		
		// Scheduled behavior
		@$behavior_id = DevblocksPlatform::importGPC($_POST['behavior_id'],'string','');
		@$behavior_when = DevblocksPlatform::importGPC($_POST['behavior_when'],'string','');
		@$behavior_params = DevblocksPlatform::importGPC($_POST['behavior_params'],'array',array());
		
		$do = array();
		
		// Do: Due
//		$due = trim(DevblocksPlatform::importGPC($_POST['due'],'string',''));
//		if(0 != strlen($due))
//			$do['due'] = $due;
			
//		// Watchers
//		$watcher_params = array();
//		
//		@$watcher_add_ids = DevblocksPlatform::importGPC($_REQUEST['do_watcher_add_ids'],'array',array());
//		if(!empty($watcher_add_ids))
//			$watcher_params['add'] = $watcher_add_ids;
//			
//		@$watcher_remove_ids = DevblocksPlatform::importGPC($_REQUEST['do_watcher_remove_ids'],'array',array());
//		if(!empty($watcher_remove_ids))
//			$watcher_params['remove'] = $watcher_remove_ids;
//		
//		if(!empty($watcher_params))
//			$do['watchers'] = $watcher_params;
		
		$status = DevblocksPlatform::importGPC($_POST['status'],'string','');
		
		// Delete
		if(strlen($status) > 0) {
			switch($status) {
				case 'deleted':
					if($active_worker->hasPriv('datacenter.domains.actions.delete')) {
						$do['delete'] = true;
					}
					break;
			}
		}
		
		// Broadcast: Mass Reply
		if(1 || $active_worker->hasPriv('fill.in.the.acl.string')) {
			@$do_broadcast = DevblocksPlatform::importGPC($_REQUEST['do_broadcast'],'string',null);
			@$broadcast_group_id = DevblocksPlatform::importGPC($_REQUEST['broadcast_group_id'],'integer',0);
			@$broadcast_subject = DevblocksPlatform::importGPC($_REQUEST['broadcast_subject'],'string',null);
			@$broadcast_message = DevblocksPlatform::importGPC($_REQUEST['broadcast_message'],'string',null);
			@$broadcast_is_queued = DevblocksPlatform::importGPC($_REQUEST['broadcast_is_queued'],'integer',0);
			@$broadcast_is_closed = DevblocksPlatform::importGPC($_REQUEST['broadcast_next_is_closed'],'integer',0);
			if(0 != strlen($do_broadcast) && !empty($broadcast_subject) && !empty($broadcast_message)) {
				$do['broadcast'] = array(
					'subject' => $broadcast_subject,
					'message' => $broadcast_message,
					'is_queued' => $broadcast_is_queued,
					'next_is_closed' => $broadcast_is_closed,
					'group_id' => $broadcast_group_id,
					'worker_id' => $active_worker->id,
				);
			}
		}		
		
		// Do: Custom fields
		$do = DAO_CustomFieldValue::handleBulkPost($do);

		// Do: Scheduled Behavior
		if(0 != strlen($behavior_id)) {
			$do['behavior'] = array(
				'id' => $behavior_id,
				'when' => $behavior_when,
				'params' => $behavior_params,
			);
		}
		
		switch($filter) {
			// Checked rows
			case 'checks':
			    @$ids_str = DevblocksPlatform::importGPC($_REQUEST['ids'],'string');
				$ids = DevblocksPlatform::parseCsvString($ids_str);
				break;
			case 'sample':
				@$sample_size = min(DevblocksPlatform::importGPC($_REQUEST['filter_sample_size'],'integer',0),9999);
				$filter = 'checks';
				$ids = $view->getDataSample($sample_size);
				break;
			default:
				break;
		}
		
		$view->doBulkUpdate($filter, $do, $ids);
		
		$view->render();
		return;		
	}
	
	function doBulkUpdateBroadcastTestAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$tpl_builder = DevblocksPlatform::getTemplateBuilder();
		$view = C4_AbstractViewLoader::getView($view_id);

		$tpl = DevblocksPlatform::getTemplateService();
		
		// [TODO]
		if(1 || $active_worker->hasPriv('core.addybook.addy.view.actions.broadcast')) {
			@$broadcast_subject = DevblocksPlatform::importGPC($_REQUEST['broadcast_subject'],'string',null);
			@$broadcast_message = DevblocksPlatform::importGPC($_REQUEST['broadcast_message'],'string',null);

			@$filter = DevblocksPlatform::importGPC($_REQUEST['filter'],'string','');
			@$ids = DevblocksPlatform::importGPC($_REQUEST['ids'],'string','');
			
			// Filter to checked
			if('checks' == $filter && !empty($ids)) {
				$view->addParam(new DevblocksSearchCriteria(SearchFields_Domain::ID,'in',explode(',', $ids)));
			}
			
			$results = $view->getDataSample(1);
			
			if(empty($results)) {
				$success = false;
				$output = "There aren't any rows in this view!";
				
			} else {
				try {
					// Pull one of the addresses on this row
					$addresses = Context_Address::searchInboundLinks('cerberusweb.contexts.datacenter.domain', current($results));
					
					if(empty($addresses)) {
						$success = false;
						$output = "This row has no associated addresses. Try again.";
						throw new Exception();
					}
	
					// Randomize the address
					@$addy = DAO_Address::get(array_rand($addresses, 1));
	
					// Try to build the template
					CerberusContexts::getContext('cerberusweb.contexts.datacenter.domain', array('id'=>current($results),'address_id'=>$addy->id), $token_labels, $token_values);
	
					if(empty($broadcast_subject)) {
						$success = false;
						$output = "Subject is blank.";
					
					} else {
						$template = "Subject: $broadcast_subject\n\n$broadcast_message";
						
						if(false === ($out = $tpl_builder->build($template, $token_values))) {
							// If we failed, show the compile errors
							$errors = $tpl_builder->getErrors();
							$success= false;
							$output = @array_shift($errors);
						} else {
							// If successful, return the parsed template
							$success = true;
							$output = $out;
						}
					}
					
				} catch(Exception $e) {
					// nothing
				}
			}
			
			$tpl->assign('success', $success);
			$tpl->assign('output', $output);
			
			$tpl->display('devblocks:cerberusweb.core::internal/renderers/test_results.tpl');
		}
	}
	
	function viewDomainsExploreAction() {
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string');
		
		$active_worker = CerberusApplication::getActiveWorker();
		$url_writer = DevblocksPlatform::getUrlService();
		
		// Generate hash
		$hash = md5($view_id.$active_worker->id.time()); 
		
		// Loop through view and get IDs
		$view = C4_AbstractViewLoader::getView($view_id);

		// Page start
		@$explore_from = DevblocksPlatform::importGPC($_REQUEST['explore_from'],'integer',0);
		if(empty($explore_from)) {
			$orig_pos = 1+($view->renderPage * $view->renderLimit);
		} else {
			$orig_pos = 1;
		}
		
		$view->renderPage = 0;
		$view->renderLimit = 250;
		$pos = 0;
		
		do {
			$models = array();
			list($results, $total) = $view->getData();

			// Summary row
			if(0==$view->renderPage) {
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'title' => $view->name,
					'created' => time(),
					//'worker_id' => $active_worker->id,
					'total' => $total,
					'return_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $url_writer->writeNoProxy('c=search&type=domain', true),
//					'toolbar_extension_id' => 'cerberusweb.explorer.toolbar.',
				);
				$models[] = $model; 
				
				$view->renderTotal = false; // speed up subsequent pages
			}
			
			if(is_array($results))
			foreach($results as $id => $row) {
				if($id==$explore_from)
					$orig_pos = $pos;
				
				$model = new Model_ExplorerSet();
				$model->hash = $hash;
				$model->pos = $pos++;
				$model->params = array(
					'id' => $id,
					'url' => $url_writer->writeNoProxy(sprintf("c=profiles&type=domain&id=%s-%d", DevblocksPlatform::strToPermalink($row[SearchFields_Domain::NAME]), $id), true),
				);
				$models[] = $model; 
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}	
};

if (class_exists('DevblocksEventListenerExtension')):
class EventListener_DatacenterDomains extends DevblocksEventListenerExtension {
	/**
	 * @param Model_DevblocksEvent $event
	 */
	function handleEvent(Model_DevblocksEvent $event) {
		switch($event->id) {
			case 'cron.maint':
				DAO_Domain::maint();
				break;
		}
	}
};
endif;