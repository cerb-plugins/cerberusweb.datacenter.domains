<?php
abstract class AbstractEvent_Domain extends Extension_DevblocksEvent {
	protected $_event_id = null; // override

	/**
	 *
	 * @param integer $domain_id
	 * @return Model_DevblocksEvent
	 */
	function generateSampleEventModel($domain_id=null) {
		
		if(empty($domain_id)) {
			// Pull the latest record
			list($results) = DAO_Domain::search(
				array(),
				array(
					//new DevblocksSearchCriteria(SearchFields_Domain::IS_CLOSED,'=',0),
				),
				10,
				0,
				SearchFields_Domain::ID,
				false,
				false
			);
			
			shuffle($results);
			
			$result = array_shift($results);
			
			$domain_id = $result[SearchFields_Domain::ID];
		}
		
		return new Model_DevblocksEvent(
			$this->_event_id,
			array(
				'domain_id' => $domain_id,
			)
		);
	}
	
	function setEvent(Model_DevblocksEvent $event_model=null) {
		$labels = array();
		$values = array();

		/**
		 * Domain
		 */
		
		@$domain_id = $event_model->params['domain_id'];
		$merge_labels = array();
		$merge_values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_DOMAIN, $domain_id, $merge_labels, $merge_values, null, true);

			// Merge
			CerberusContexts::merge(
				'domain_',
				'',
				$merge_labels,
				$merge_values,
				$labels,
				$values
			);

		/**
		 * Return
		 */

		$this->setLabels($labels);
		$this->setValues($values);
	}
	
	function renderSimulatorTarget($trigger, $event_model) {
		$context = CerberusContexts::CONTEXT_DOMAIN;
		$context_id = $event_model->params['domain_id'];
		DevblocksEventHelper::renderSimulatorTarget($context, $context_id, $trigger, $event_model);
	}
	
	function getValuesContexts($trigger) {
		$vals = array(
			'domain_id' => array(
				'label' => 'Domain',
				'context' => CerberusContexts::CONTEXT_DOMAIN,
			),
			'domain_contacts' => array(
				'label' => 'Domain contacts',
				'context' => CerberusContexts::CONTEXT_ADDRESS,
			),
			'domain_watchers' => array(
				'label' => 'Domain watchers',
				'context' => CerberusContexts::CONTEXT_WORKER,
			),
			'domain_server_id' => array(
				'label' => 'Server',
				'context' => CerberusContexts::CONTEXT_SERVER,
			),
			'domain_server_watchers' => array(
				'label' => 'Server watchers',
				'context' => CerberusContexts::CONTEXT_WORKER,
			),
		);
		
		$vars = parent::getValuesContexts($trigger);
		
		$vals_to_ctx = array_merge($vals, $vars);
		asort($vals_to_ctx);
		
		return $vals_to_ctx;
	}
	
	function getConditionExtensions() {
		$labels = $this->getLabels();
		
		$labels['domain_link'] = 'Domain is linked';
		$labels['domain_server_link'] = 'Server is linked';
		
		$labels['domain_contacts_count'] = 'Domain contacts count';
		$labels['domain_server_watcher_count'] = 'Server watcher count';
		$labels['domain_watcher_count'] = 'Domain watcher count';
		
		$types = array(
			'domain_name' => Model_CustomField::TYPE_SINGLE_LINE,
			
			'domain_server_name' => Model_CustomField::TYPE_SINGLE_LINE,

			'domain_contact_address' => Model_CustomField::TYPE_SINGLE_LINE,
			'domain_contact_is_banned' => Model_CustomField::TYPE_CHECKBOX,
			'domain_contact_first_name' => Model_CustomField::TYPE_SINGLE_LINE,
			'domain_contact_full_name' => Model_CustomField::TYPE_SINGLE_LINE,
			'domain_contact_last_name' => Model_CustomField::TYPE_SINGLE_LINE,
			'domain_contact_num_nonspam' => Model_CustomField::TYPE_NUMBER,
			'domain_contact_num_spam' => Model_CustomField::TYPE_NUMBER,
			
			'domain_contact_org_city' => Model_CustomField::TYPE_SINGLE_LINE,
			'domain_contact_org_country' => Model_CustomField::TYPE_SINGLE_LINE,
			'domain_contact_org_created|date' => Model_CustomField::TYPE_DATE,
			'domain_contact_org_name' => Model_CustomField::TYPE_SINGLE_LINE,
			'domain_contact_org_phone' => Model_CustomField::TYPE_SINGLE_LINE,
			'domain_contact_org_postal' => Model_CustomField::TYPE_SINGLE_LINE,
			'domain_contact_org_province' => Model_CustomField::TYPE_SINGLE_LINE,
			'domain_contact_org_street' => Model_CustomField::TYPE_SINGLE_LINE,
			'domain_contact_org_website' => Model_CustomField::TYPE_URL,
			
			'domain_link' => null,
			'domain_server_link' => null,
		
			'domain_contacts_count' => null,
			'domain_server_watcher_count' => null,
			'domain_watcher_count' => null,
		);

		$conditions = $this->_importLabelsTypesAsConditions($labels, $types);
		
		return $conditions;
	}
	
	function renderConditionExtension($token, $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);

		if(!is_null($seq))
			$tpl->assign('namePrefix','condition'.$seq);
		
		switch($token) {
			case 'domain_link':
			case 'domain_server_link':
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::events/condition_link.tpl');
				break;
				
			case 'domain_contacts_count':
			case 'domain_server_watcher_count':
			case 'domain_watcher_count':
				$tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_number.tpl');
				break;
		}

		$tpl->clearAssign('namePrefix');
		$tpl->clearAssign('params');
	}
	
	function runConditionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$pass = true;
		
		switch($token) {
			case 'domain_link':
			case 'domain_server_link':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');
				
				$from_context = null;
				$from_context_id = null;
				
				switch($token) {
					case 'domain_link':
						$from_context = CerberusContexts::CONTEXT_DOMAIN;
						@$from_context_id = $dict->domain_id;
						break;
					case 'domain_server_link':
						$from_context = CerberusContexts::CONTEXT_SERVER;
						@$from_context_id = $dict->domain_server_id;
						break;
					default:
						$pass = false;
				}
				
				// Get links by context+id

				if(!empty($from_context) && !empty($from_context_id)) {
					@$context_strings = $params['context_objects'];
					$links = DAO_ContextLink::intersect($from_context, $from_context_id, $context_strings);
					
					// OPER: any, !any, all
	
					switch($oper) {
						case 'in':
							$pass = (is_array($links) && !empty($links));
							break;
						case 'all':
							$pass = (is_array($links) && count($links) == count($context_strings));
							break;
						default:
							$pass = false;
							break;
					}
					
					$pass = ($not) ? !$pass : $pass;
					
				} else {
					$pass = false;
				}
				
				break;
				
			case 'domain_contacts_count':
			case 'domain_server_watcher_count':
			case 'domain_watcher_count':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');
				
				switch($token) {
					case 'domain_contact_org_watcher_count':
						$value = count($dict->domain_contact_org_watchers);
						break;
					case 'domain_contact_watcher_count':
						$value = count($dict->domain_contact_watchers);
						break;
					case 'domain_contacts_count':
						$value = count($dict->domain_contacts);
						break;
					case 'domain_server_watcher_count':
						$value = count($dict->domain_server_watchers);
						break;
					case 'domain_watcher_count':
					default:
						$value = count($dict->domain_watchers);
						break;
				}
				
				switch($oper) {
					case 'is':
						$pass = intval($value)==intval($params['value']);
						break;
					case 'gt':
						$pass = intval($value) > intval($params['value']);
						break;
					case 'lt':
						$pass = intval($value) < intval($params['value']);
						break;
				}
				
				$pass = ($not) ? !$pass : $pass;
				break;
				
			default:
				$pass = false;
				break;
		}
		
		return $pass;
	}
	
	function getActionExtensions() {
		$actions =
			array(
				'add_watchers' => array('label' =>'Add watchers'),
				'create_comment' => array('label' =>'Create a comment'),
				'create_notification' => array('label' =>'Create a notification'),
				'create_task' => array('label' =>'Create a task'),
				'create_ticket' => array('label' =>'Create a ticket'),
				'schedule_behavior' => array('label' => 'Schedule behavior'),
				'send_email' => array('label' => 'Send email'),
				'set_links' => array('label' => 'Set links'),
				'unschedule_behavior' => array('label' => 'Unschedule behavior'),
			)
			+ DevblocksEventHelper::getActionCustomFieldsFromLabels($this->getLabels())
			;
			
		return $actions;
	}
	
	function renderActionExtension($token, $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);

		if(!is_null($seq))
			$tpl->assign('namePrefix','action'.$seq);

		$labels = $this->getLabels();
		$tpl->assign('token_labels', $labels);
			
		switch($token) {
			case 'add_watchers':
				DevblocksEventHelper::renderActionAddWatchers($trigger);
				break;
			
			case 'create_comment':
				DevblocksEventHelper::renderActionCreateComment($trigger);
				break;
				
			case 'create_notification':
				DevblocksEventHelper::renderActionCreateNotification($trigger);
				break;
				
			case 'create_task':
				DevblocksEventHelper::renderActionCreateTask($trigger);
				break;
				
			case 'create_ticket':
				DevblocksEventHelper::renderActionCreateTicket($trigger);
				break;
				
			case 'schedule_behavior':
				$dates = array();
				$conditions = $this->getConditions($trigger);
				foreach($conditions as $key => $data) {
					if(isset($data['type']) && $data['type'] == Model_CustomField::TYPE_DATE)
						$dates[$key] = $data['label'];
				}
				$tpl->assign('dates', $dates);
			
				DevblocksEventHelper::renderActionScheduleBehavior($trigger);
				break;
				
			case 'unschedule_behavior':
				DevblocksEventHelper::renderActionUnscheduleBehavior($trigger);
				break;
				
			case 'send_email':
				DevblocksEventHelper::renderActionSendEmail($trigger);
				break;
				
			case 'set_links':
				DevblocksEventHelper::renderActionSetLinks($trigger);
				break;
				
			default:
				if(preg_match('#set_cf_(.*?)_custom_([0-9]+)#', $token, $matches)) {
					$field_id = $matches[2];
					$custom_field = DAO_CustomField::get($field_id);
					DevblocksEventHelper::renderActionSetCustomField($custom_field);
				}
				break;
		}
		
		$tpl->clearAssign('params');
		$tpl->clearAssign('namePrefix');
		$tpl->clearAssign('token_labels');
	}
	
	function simulateActionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		@$domain_id = $dict->domain_id;

		if(empty($domain_id))
			return;
		
		switch($token) {
			case 'add_watchers':
				return DevblocksEventHelper::simulateActionAddWatchers($params, $dict, 'domain_id');
				break;
			
			case 'create_comment':
				return DevblocksEventHelper::simulateActionCreateComment($params, $dict, 'domain_id');
				break;
				
			case 'create_notification':
				return DevblocksEventHelper::simulateActionCreateNotification($params, $dict, 'domain_id');
				break;
				
			case 'create_task':
				return DevblocksEventHelper::simulateActionCreateTask($params, $dict, 'domain_id');
				break;

			case 'create_ticket':
				return DevblocksEventHelper::simulateActionCreateTicket($params, $dict, 'domain_id');
				break;
				
			case 'schedule_behavior':
				return DevblocksEventHelper::simulateActionScheduleBehavior($params, $dict);
				break;
				
			case 'unschedule_behavior':
				return DevblocksEventHelper::simulateActionUnscheduleBehavior($params, $dict);
				break;
				
			case 'send_email':
				return DevblocksEventHelper::simulateActionSendEmail($params, $dict);
				break;
				
			case 'set_links':
				return DevblocksEventHelper::simulateActionSetLinks($trigger, $params, $dict);
				break;
				
			default:
				if(preg_match('#set_cf_(.*?)_custom_([0-9]+)#', $token))
					return DevblocksEventHelper::simulateActionSetCustomField($token, $params, $dict);
				break;
		}
	}
	
	function runActionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		@$domain_id = $dict->domain_id;

		if(empty($domain_id))
			return;
		
		switch($token) {
			case 'add_watchers':
				DevblocksEventHelper::runActionAddWatchers($params, $dict, 'domain_id');
				break;
			
			case 'create_comment':
				DevblocksEventHelper::runActionCreateComment($params, $dict, 'domain_id');
				break;
				
			case 'create_notification':
				DevblocksEventHelper::runActionCreateNotification($params, $dict, 'domain_id');
				break;
				
			case 'create_task':
				DevblocksEventHelper::runActionCreateTask($params, $dict, 'domain_id');
				break;

			case 'create_ticket':
				DevblocksEventHelper::runActionCreateTicket($params, $dict, 'domain_id');
				break;
				
			case 'schedule_behavior':
				DevblocksEventHelper::runActionScheduleBehavior($params, $dict);
				break;
				
			case 'unschedule_behavior':
				DevblocksEventHelper::runActionUnscheduleBehavior($params, $dict);
				break;
				
			case 'send_email':
				DevblocksEventHelper::runActionSendEmail($params, $dict);
				break;
				
			case 'set_links':
				DevblocksEventHelper::runActionSetLinks($trigger, $params, $dict);
				break;
				
			default:
				if(preg_match('#set_cf_(.*?)_custom_([0-9]+)#', $token))
					return DevblocksEventHelper::runActionSetCustomField($token, $params, $dict);
				break;
		}
	}
	
};