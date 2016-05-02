<?php
if(class_exists('Extension_ContextProfileTab', true)):
class ChDomainsServerTab extends Extension_ContextProfileTab {
	function showTab($context, $context_id) {
		if(0 != strcasecmp($context, CerberusContexts::CONTEXT_SERVER))
			return;
		
		$tpl = DevblocksPlatform::getTemplateService();

		// View
		$view_id = 'server_domains';
		
		$defaults = C4_AbstractViewModel::loadFromClass('View_Domain');
		$defaults->id = $view_id;
		
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
		
		// Template
		$tpl->display('devblocks:cerberusweb.datacenter.domains::server_tab/index.tpl');
	}
};
endif;

// Controller
// [TODO] Move this to profiles
class Page_Domains extends CerberusPageExtension {
	function isVisible() {
		// The current session must be a logged-in worker to use this page.
		if(null == ($worker = CerberusApplication::getActiveWorker()))
			return false;
		return true;
	}
	
	function render() {
	}
	
	function savePeekJsonAction() {
		@$id = DevblocksPlatform::importGPC($_REQUEST['id'],'integer',0);
		@$delete = DevblocksPlatform::importGPC($_REQUEST['do_delete'],'integer',0);
		@$view_id = DevblocksPlatform::importGPC($_REQUEST['view_id'],'string','');

		$active_worker = CerberusApplication::getActiveWorker();
		
		header('Content-Type: application/json; charset=' . LANG_CHARSET_CODE);
		
		try {
			if(!empty($id) && !empty($delete)) { // delete
				if(!$active_worker->hasPriv('datacenter.domains.actions.delete'))
					throw new Exception_DevblocksAjaxValidationError("You don't have permission to delete this record.");
				
				DAO_Domain::delete($id);
				
				echo json_encode(array(
					'status' => true,
					'id' => $id,
					'view_id' => $view_id,
				));
				return;
				
			} else { // create/edit
				@$name = DevblocksPlatform::importGPC($_REQUEST['name'],'string','');
				@$server_id = DevblocksPlatform::importGPC($_REQUEST['server_id'],'integer',0);
				@$created = DevblocksPlatform::importGPC($_REQUEST['created'],'string','');
				@$contact_address_ids = DevblocksPlatform::importGPC($_REQUEST['contact_address_id'],'array',array());
				@$comment = DevblocksPlatform::importGPC($_REQUEST['comment'], 'string', '');
				
				// Require fields
				if(empty($name))
					throw new Exception_DevblocksAjaxValidationError("A 'Name' is required.", 'name');
				
				// Verify the server_id
				if($server_id && false == ($server = DAO_Server::get($server_id)))
					throw new Exception_DevblocksAjaxValidationError("The specified 'Server' is invalid.", 'server_id');
				
				if(false == (@$created = strtotime($created)))
					$created = time();
				
				$fields = array(
					DAO_Domain::NAME => $name,
					DAO_Domain::SERVER_ID => $server_id,
					DAO_Domain::CREATED => $created,
				);
				
				// Create/Update
				if(empty($id)) {
					// Check for dupes
					if(false != DAO_Domain::getByName($name))
						throw new Exception_DevblocksAjaxValidationError(sprintf("A domain record already exists with the name '%s'.", $name), 'name');
					
					if(false == ($id = DAO_Domain::create($fields)))
						throw new Exception_DevblocksAjaxValidationError("There was an error creating the record.");
					
					// View marquee
					if(!empty($id) && !empty($view_id)) {
						C4_AbstractView::setMarqueeContextCreated($view_id, CerberusContexts::CONTEXT_DOMAIN, $id);
					}
					
				} else {
					DAO_Domain::update($id, $fields);
				}
				
				// If we're adding a comment
				if(!empty($comment)) {
					$also_notify_worker_ids = array_keys(CerberusApplication::getWorkersByAtMentionsText($comment));
					
					$fields = array(
						DAO_Comment::CREATED => time(),
						DAO_Comment::CONTEXT => CerberusContexts::CONTEXT_DOMAIN,
						DAO_Comment::CONTEXT_ID => $id,
						DAO_Comment::COMMENT => $comment,
						DAO_Comment::OWNER_CONTEXT => CerberusContexts::CONTEXT_WORKER,
						DAO_Comment::OWNER_CONTEXT_ID => $active_worker->id,
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
				DAO_CustomFieldValue::handleFormPost(CerberusContexts::CONTEXT_DOMAIN, $id, $field_ids);
				
				// Address context links
				DAO_ContextLink::setContextOutboundLinks(CerberusContexts::CONTEXT_DOMAIN, $id, CerberusContexts::CONTEXT_ADDRESS, $contact_address_ids);
			
				echo json_encode(array(
					'status' => true,
					'id' => $id,
					'label' => $name,
					'view_id' => $view_id,
				));
				return;
			}
			
		} catch (Exception_DevblocksAjaxValidationError $e) {
				echo json_encode(array(
					'status' => false,
					'id' => $id,
					'error' => $e->getMessage(),
					'field' => $e->getFieldName(),
				));
				return;
			
		} catch (Exception $e) {
				echo json_encode(array(
					'status' => false,
					'id' => $id,
					'error' => 'An error occurred.',
				));
				return;
			
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
		
		// Servers
		if(DevblocksPlatform::isPluginEnabled('cerberusweb.datacenter.servers')) {
			$servers = DAO_Server::getAll();
			$tpl->assign('servers', $servers);
		}
		
		// Custom Fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_DOMAIN, false);
		$tpl->assign('custom_fields', $custom_fields);
		
		// Broadcast
		CerberusContexts::getContext(CerberusContexts::CONTEXT_DOMAIN, null, $token_labels, $token_values);
		$tpl->assign('token_labels', $token_labels);
		
		// HTML templates
		$html_templates = DAO_MailHtmlTemplate::getAll();
		$tpl->assign('html_templates', $html_templates);
		
		// Macros
		
		$macros = DAO_TriggerEvent::getReadableByActor(
			$active_worker,
			'event.macro.domain'
		);
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
		$view->setAutoPersist(false);
		
		// Scheduled behavior
		@$behavior_id = DevblocksPlatform::importGPC($_POST['behavior_id'],'string','');
		@$behavior_when = DevblocksPlatform::importGPC($_POST['behavior_when'],'string','');
		@$behavior_params = DevblocksPlatform::importGPC($_POST['behavior_params'],'array',array());
		
		$do = array();
		
		$status = DevblocksPlatform::importGPC($_POST['status'],'string','');
		$server_id = DevblocksPlatform::importGPC($_POST['server_id'],'string','');
		
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
		
		if(strlen($server_id)) {
			$do['server_id'] = intval($server_id);
		}
		
		// Broadcast: Mass Reply
		if(1 || $active_worker->hasPriv('fill.in.the.acl.string')) {
			@$do_broadcast = DevblocksPlatform::importGPC($_REQUEST['do_broadcast'],'string',null);
			@$broadcast_group_id = DevblocksPlatform::importGPC($_REQUEST['broadcast_group_id'],'integer',0);
			@$broadcast_subject = DevblocksPlatform::importGPC($_REQUEST['broadcast_subject'],'string',null);
			@$broadcast_message = DevblocksPlatform::importGPC($_REQUEST['broadcast_message'],'string',null);
			@$broadcast_format = DevblocksPlatform::importGPC($_REQUEST['broadcast_format'],'string',null);
			@$broadcast_html_template_id = DevblocksPlatform::importGPC($_REQUEST['broadcast_html_template_id'],'integer',0);
			@$broadcast_is_queued = DevblocksPlatform::importGPC($_REQUEST['broadcast_is_queued'],'integer',0);
			@$broadcast_status_id = DevblocksPlatform::importGPC($_REQUEST['broadcast_status_id'],'integer',0);
			
			if(0 != strlen($do_broadcast) && !empty($broadcast_subject) && !empty($broadcast_message)) {
				$do['broadcast'] = array(
					'subject' => $broadcast_subject,
					'message' => $broadcast_message,
					'format' => $broadcast_format,
					'html_template_id' => $broadcast_html_template_id,
					'is_queued' => $broadcast_is_queued,
					'status_id' => $broadcast_status_id,
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
		$view->setAutoPersist(false);

		$tpl = DevblocksPlatform::getTemplateService();
		
		// [TODO]
		if(1 || $active_worker->hasPriv('core.addybook.addy.view.actions.broadcast')) {
			@$broadcast_subject = DevblocksPlatform::importGPC($_REQUEST['broadcast_subject'],'string',null);
			@$broadcast_message = DevblocksPlatform::importGPC($_REQUEST['broadcast_message'],'string',null);
			@$broadcast_format = DevblocksPlatform::importGPC($_REQUEST['broadcast_format'],'string',null);
			@$broadcast_html_template_id = DevblocksPlatform::importGPC($_REQUEST['broadcast_html_template_id'],'integer',0);
			@$broadcast_group_id = DevblocksPlatform::importGPC($_REQUEST['broadcast_group_id'],'integer',0);

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
					$addresses = Context_Address::searchInboundLinks(CerberusContexts::CONTEXT_DOMAIN, current($results));
					
					if(empty($addresses)) {
						$success = false;
						$output = "This row has no associated addresses. Try again.";
						throw new Exception();
					}
	
					// Randomize the address
					@$addy = DAO_Address::get(array_rand($addresses, 1));
	
					// Try to build the template
					CerberusContexts::getContext(CerberusContexts::CONTEXT_DOMAIN, array('id'=>current($results),'address_id'=>$addy->id), $token_labels, $token_values);
	
					if(empty($broadcast_subject)) {
						$success = false;
						$output = "Subject is blank.";
					
					} else {
						$template = "Subject: $broadcast_subject\n\n$broadcast_message";
						
						if(false === ($out = $tpl_builder->build($template, $token_values))) {
							// If we failed, show the compile errors
							$errors = $tpl_builder->getErrors();
							$success = false;
							$output = @array_shift($errors);
							
						} else {
							// If successful, return the parsed template
							$success = true;
							$output = $out;
							
							switch($broadcast_format) {
								case 'parsedown':
									// Markdown
									$output = DevblocksPlatform::parseMarkdown($output);
									
									// HTML Template
									
									$html_template = null;
									
									if($broadcast_html_template_id)
										$html_template = DAO_MailHtmlTemplate::get($broadcast_html_template_id);
									
									if(!$html_template && false != ($group = DAO_Group::get($broadcast_group_id)))
										$html_template = $group->getReplyHtmlTemplate(0);
									
									if(!$html_template && false != ($replyto = DAO_AddressOutgoing::getDefault()))
										$html_template = $replyto->getReplyHtmlTemplate();
									
									if($html_template)
										$output = $tpl_builder->build($html_template->content, array('message_body' => $output));
									
									// HTML Purify
									$output = DevblocksPlatform::purifyHTML($output, true);
									break;
									
								default:
									$output = nl2br(DevblocksPlatform::strEscapeHtml($output));
									break;
							}
						}
					}
					
				} catch(Exception $e) {
					// nothing
				}
			}
			
			if($success) {
				header("Content-Type: text/html; charset=" . LANG_CHARSET_CODE);
				echo sprintf('<html><head><meta http-equiv="content-type" content="text/html; charset=%s"></head><body>',
					LANG_CHARSET_CODE
				);
				echo $output;
				echo '</body></html>';
				
			} else {
				echo $output;
			}
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
		$view->setAutoPersist(false);

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
					'url' => $url_writer->writeNoProxy(sprintf("c=profiles&type=domain&id=%d-%s", $id, DevblocksPlatform::strToPermalink($row[SearchFields_Domain::NAME])), true),
				);
				$models[] = $model;
			}
			
			DAO_ExplorerSet::createFromModels($models);
			
			$view->renderPage++;
			
		} while(!empty($results));
		
		DevblocksPlatform::redirect(new DevblocksHttpResponse(array('explore',$hash,$orig_pos)));
	}
};

if(class_exists('Extension_DevblocksEventAction')):
class VaAction_CreateDomain extends Extension_DevblocksEventAction {
	function render(Extension_DevblocksEvent $event, Model_TriggerEvent $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);
		
		if(!is_null($seq))
			$tpl->assign('namePrefix', 'action'.$seq);
		
		$event = $trigger->getEvent();
		$values_to_contexts = $event->getValuesContexts($trigger);
		$tpl->assign('values_to_contexts', $values_to_contexts);
		
		// Custom fields
		DevblocksEventHelper::renderActionCreateRecordSetCustomFields(CerberusContexts::CONTEXT_DOMAIN, $tpl);
		
		// Template
		$tpl->display('devblocks:cerberusweb.datacenter.domains::events/action_create_domain.tpl');
	}
	
	function simulate($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$tpl_builder = DevblocksPlatform::getTemplateBuilder();

		$out = null;
		
		@$name = $tpl_builder->build($params['name'], $dict);
		
		@$server_id = DevblocksPlatform::importVar($params['server_id'],'string','');
		@$email_ids = DevblocksPlatform::importVar($params['email_ids'],'array',array());
		
		@$notify_worker_ids = DevblocksPlatform::importVar($params['notify_worker_id'],'array',array());
		$notify_worker_ids = DevblocksEventHelper::mergeWorkerVars($notify_worker_ids, $dict);
		
		if(empty($name))
			return "[ERROR] Name is required.";
		
		// Check dupes
		if(false != ($domain = DAO_Domain::getByName($name))) {
			return sprintf("[ERROR] Name must be unique. A domain named '%s' already exists.", $name);
		}
		
		$comment = $tpl_builder->build($params['comment'], $dict);
		
		$out = sprintf(">>> Creating domain: %s\n", $name);
		
		// Server
		
		if(!is_numeric($server_id) && isset($dict->$server_id)) {
			if(is_array($dict->$server_id)) {
				$server_id = key($dict->$server_id);
			} else {
				$server_id = $dict->$server_id;
			}
		}
		
		$server_id = intval($server_id);
		
		if($server = DAO_Server::get($server_id)) {
			$out .= sprintf("Server: %s\n", $server->name);
		}
		
		// Contacts
		
		if(is_array($email_ids))
		foreach($email_ids as $idx => $email_id) {
			if(!is_numeric($email_id) && isset($dict->$email_id)) {
				if(is_array($dict->$email_id)) {
					$email_ids = array_merge($email_ids, array_keys($dict->$email_id));
				} else {
					$email_ids[] = $dict->$email_id;
				}
				unset($email_ids[$idx]);
			}
		}
		
		$email_ids = DevblocksPlatform::sanitizeArray($email_ids, 'int');
		
		if(!empty($email_ids)) {
			$out .= "Contacts:\n";
			
			$models = DAO_Address::getIds($email_ids);
			
			if(is_array($models))
			foreach($models as $model) {
				$out .= " * " . $model->email . "\n";
			}
		}
		
		// Custom fields

		$out .= DevblocksEventHelper::simulateActionCreateRecordSetCustomFields($params, $dict);
		
		$out .= "\n";
		
		// Comment content
		if(!empty($comment)) {
			$out .= sprintf(">>> Writing comment on domain\n\n".
				"%s\n\n",
				$comment
			);
			
			if(!empty($notify_worker_ids) && is_array($notify_worker_ids)) {
				$out .= ">>> Notifying\n";
				foreach($notify_worker_ids as $worker_id) {
					if(null != ($worker = DAO_Worker::get($worker_id))) {
						$out .= ' * ' . $worker->getName() . "\n";
					}
				}
				$out .= "\n";
			}
		}
		
		// Set object variable
		$out .= DevblocksEventHelper::simulateActionCreateRecordSetVariable($params, $dict);

		// Run in simulator
		@$run_in_simulator = !empty($params['run_in_simulator']);
		if($run_in_simulator) {
			$this->run($token, $trigger, $params, $dict);
		}
		
		return $out;
	}
	
	function run($token, Model_TriggerEvent $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$tpl_builder = DevblocksPlatform::getTemplateBuilder();
		
		@$name = $tpl_builder->build($params['name'], $dict);
		
		@$server_id = DevblocksPlatform::importVar($params['server_id'],'string','');
		@$email_ids = DevblocksPlatform::importVar($params['email_ids'],'array',array());
		
		@$notify_worker_ids = DevblocksPlatform::importVar($params['notify_worker_id'],'array',array());
		$notify_worker_ids = DevblocksEventHelper::mergeWorkerVars($notify_worker_ids, $dict);
		
		$comment = $tpl_builder->build($params['comment'], $dict);
		
		if(empty($name))
			return;
		
		// Dupe check
		if(false != ($domain = DAO_Domain::getByName($name))) {
			return;
		}
		
		// Server
		
		if(!is_numeric($server_id) && isset($dict->$server_id)) {
			if(is_array($dict->$server_id)) {
				$server_id = key($dict->$server_id);
			} else {
				$server_id = $dict->$server_id;
			}
		}
		
		$server_id = intval($server_id);
		
		// Contacts
		
		if(is_array($email_ids))
		foreach($email_ids as $idx => $email_id) {
			if(!is_numeric($email_id) && isset($dict->$email_id)) {
				if(is_array($dict->$email_id)) {
					$email_ids = array_merge($email_ids, array_keys($dict->$email_id));
				} else {
					$email_ids[] = $dict->$email_id;
				}
				unset($email_ids[$idx]);
			}
		}
		
		$email_ids = DevblocksPlatform::sanitizeArray($email_ids, 'int');
		
		$fields = array(
			DAO_Domain::NAME => $name,
			DAO_Domain::SERVER_ID => $server_id,
		);
			
		if(false == ($domain_id = DAO_Domain::create($fields)))
			return;
		
		// Contact links
		
		if(is_array($email_ids))
		foreach($email_ids as $email_id)
			DAO_ContextLink::setLink(CerberusContexts::CONTEXT_DOMAIN, $domain_id, CerberusContexts::CONTEXT_ADDRESS, $email_id);
		
		// Custom fields
		DevblocksEventHelper::runActionCreateRecordSetCustomFields(CerberusContexts::CONTEXT_DOMAIN, $domain_id, $params, $dict);
		
		// Comment content
		if(!empty($comment)) {
			$fields = array(
				DAO_Comment::OWNER_CONTEXT => CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT,
				DAO_Comment::OWNER_CONTEXT_ID => $trigger->virtual_attendant_id,
				DAO_Comment::COMMENT => $comment,
				DAO_Comment::CONTEXT => CerberusContexts::CONTEXT_DOMAIN,
				DAO_Comment::CONTEXT_ID => $domain_id,
				DAO_Comment::CREATED => time(),
			);
			DAO_Comment::create($fields, $notify_worker_ids);
		}
		
		// Set object variable
		DevblocksEventHelper::runActionCreateRecordSetVariable(CerberusContexts::CONTEXT_DOMAIN, $domain_id, $params, $dict);
	}
	
};
endif;

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