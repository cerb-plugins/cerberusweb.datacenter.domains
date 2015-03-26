<?php
class Context_Domain extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek, IDevblocksContextImport {
	function getRandom() {
		return DAO_Domain::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=domain&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$domain = DAO_Domain::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($domain->name);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $domain->id,
			'name' => $domain->name,
			'permalink' => $url,
		);
	}
	
	function getPropertyLabels(DevblocksDictionaryDelegate $dict) {
		$labels = $dict->_labels;
		$prefix = $labels['_label'];
		
		if(!empty($prefix)) {
			array_walk($labels, function(&$label, $key) use ($prefix) {
				$label = preg_replace(sprintf("#^%s #", preg_quote($prefix)), '', $label);
				
				// [TODO] Use translations
				switch($key) {
				}
				
				$label = mb_convert_case($label, MB_CASE_LOWER);
				$label[0] = mb_convert_case($label[0], MB_CASE_UPPER);
			});
		}
		
		asort($labels);
		
		return $labels;
	}
	
	// [TODO] Interface
	function getDefaultProperties() {
		return array(
			'server__label',
			'created',
			'updated',
		);
	}
	
	function getContext($id_map, &$token_labels, &$token_values, $prefix=null) {
		$domain = null;

		// Polymorph
		if(is_numeric($id_map)) {
			$domain = DAO_Domain::get($id_map);
		} elseif(is_array($id_map) && isset($id_map['name'])) {
			$domain = Cerb_ORMHelper::recastArrayToModel($id_map, 'Model_Domain');
		} elseif(is_array($id_map) && isset($id_map['id'])) {
			$domain = DAO_Domain::get($id_map['id']);
		} elseif($id_map instanceof Model_Domain) {
			$domain = $id_map;
		} else {
			$domain = null;
		}
		
		if(is_null($prefix))
			$prefix = 'Domain:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_DOMAIN);
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'created' => $prefix.$translate->_('common.created'),
			'name' => $prefix.$translate->_('common.name'),
			'record_url' => $prefix.$translate->_('common.url.record'),
			'updated' => $prefix.$translate->_('common.updated'),
			'contacts_list' => $prefix.'Contacts List',
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'created' => Model_CustomField::TYPE_DATE,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
			'record_url' => Model_CustomField::TYPE_URL,
			'updated' => Model_CustomField::TYPE_DATE,
			'contacts_list' => null,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = CerberusContexts::CONTEXT_DOMAIN;
		$token_values['_types'] = $token_types;
		
		// Domain token values
		if(null != $domain) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $domain->name;
			$token_values['id'] = $domain->id;
			$token_values['created'] = $domain->created;
			$token_values['name'] = $domain->name;
			$token_values['updated'] = $domain->updated;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($domain, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=domain&id=%s-%d",DevblocksPlatform::strToPermalink($domain->name),$domain->id), true);
			
			// Server
			$server_id = (null != $domain && !empty($domain->server_id)) ? $domain->server_id : null;
			$token_values['server_id'] = $server_id;
		}

		// Addy
		$address_id = (is_array($id_map) && isset($id_map['address_id'])) ? $id_map['address_id'] : null;
		$merge_token_labels = array();
		$merge_token_values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_ADDRESS, $address_id, $merge_token_labels, $merge_token_values, null, true);

		CerberusContexts::merge(
			'contact_',
			$prefix.'Contact:',
			$merge_token_labels,
			$merge_token_values,
			$token_labels,
			$token_values
		);
		
		// Server
		$merge_token_labels = array();
		$merge_token_values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_SERVER, null, $merge_token_labels, $merge_token_values, null, true);

		CerberusContexts::merge(
			'server_',
			$prefix,
			$merge_token_labels,
			$merge_token_values,
			$token_labels,
			$token_values
		);
		
		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_DOMAIN;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			CerberusContexts::getContext($context, $context_id, $labels, $values);
		}
		
		switch($token) {
			case 'contacts':
				$contacts = array();
				$address_links = DAO_ContextLink::getContextLinks($context, $context_id, CerberusContexts::CONTEXT_ADDRESS);

				if(!is_array($address_links))
					break;
				
				// The results are keyed by source ID
				$address_links = array_shift($address_links);
				
				if(is_array($address_links))
				foreach($address_links as $address_link) { /* @var $address_link Model_ContextLink */
					$token_labels = array();
					$token_values = array();
					CerberusContexts::getContext($address_link->context, $address_link->context_id, $token_labels, $token_values, null, true);
					
					if(!empty($token_values))
						$contacts[$address_link->context_id] = $token_values;
				}
				
				$values[$token] = $contacts;
				break;
				
			case 'contacts_list':
				$result = $this->lazyLoadContextValues('contacts', $dictionary);
				$contacts = array();
				
				if(isset($result['contacts']))
				foreach($result['contacts'] as $contact) {
					$contacts[] = $contact['address'];
				}
				
				$values[$token] = implode(', ', $contacts);
				break;
			
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(substr($token,0,7) == 'custom_') {
					$fields = $this->_lazyLoadCustomFields($token, $context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}
		
		return $values;
	}
	
	function getChooserView($view_id=null) {
		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
	
		// View
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;
		$defaults->class_name = $this->getViewClass();
		
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		
		$view->renderSortBy = SearchFields_Domain::NAME;
		$view->renderSortAsc = true;
		$view->renderLimit = 10;
		$view->renderFilters = false;
		$view->renderTemplate = 'contextlinks_chooser';
		
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = str_replace('.','_', $this->id);
		
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->class_name = $this->getViewClass();
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_Domain::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_Domain::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='') {
		$id = $context_id; // [TODO] Cleanup
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		// Model
		$model = null;
		if(empty($id) || null == ($model = DAO_Domain::get($id)))
			$model = new Model_Domain();
		
		$tpl->assign('model', $model);
		
		// Servers
		$servers = DAO_Server::getAll();
		$tpl->assign('servers', $servers);
		
		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_DOMAIN, false);
		$tpl->assign('custom_fields', $custom_fields);

		$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_DOMAIN, $id);
		if(isset($custom_field_values[$id]))
			$tpl->assign('custom_field_values', $custom_field_values[$id]);
		
		$types = Model_CustomField::getTypes();
		$tpl->assign('types', $types);
		
		// Context: Addresses
		$context_addresses = Context_Address::searchInboundLinks(CerberusContexts::CONTEXT_DOMAIN, $id);
		$tpl->assignByRef('context_addresses', $context_addresses);
		
		// Comments
		$comments = DAO_Comment::getByContext(CerberusContexts::CONTEXT_DOMAIN, $id);
		$comments = array_reverse($comments, true);
		$tpl->assign('comments', $comments);
		
		// Render
		$tpl->display('devblocks:cerberusweb.datacenter.domains::domain/peek.tpl');
	}
	
	function importGetKeys() {
		// [TODO] Translate
	
		$keys = array(
			'created' => array(
				'label' => 'Created Date',
				'type' => Model_CustomField::TYPE_DATE,
				'param' => SearchFields_Domain::CREATED,
			),
			'name' => array(
				'label' => 'Name',
				'type' => Model_CustomField::TYPE_SINGLE_LINE,
				'param' => SearchFields_Domain::NAME,
				'required' => true,
				'force_match' => true,
			),
			'updated' => array(
				'label' => 'Updated Date',
				'type' => Model_CustomField::TYPE_DATE,
				'param' => SearchFields_Domain::UPDATED,
			),
// 			'primary_email_id' => array(
// 				'label' => 'Email',
// 				'type' => 'ctx_' . CerberusContexts::CONTEXT_ADDRESS,
// 				'param' => SearchFields_CrmOpportunity::PRIMARY_EMAIL_ID,
// 			),
		);
	
		$fields = SearchFields_Domain::getFields();
		self::_getImportCustomFields($fields, $keys);
		
		DevblocksPlatform::sortObjects($keys, '[label]', true);
	
		return $keys;
	}
	
	function importKeyValue($key, $value) {
		switch($key) {
		}
	
		return $value;
	}
	
	function importSaveObject(array $fields, array $custom_fields, array $meta) {
		// If new...
		if(!isset($meta['object_id']) || empty($meta['object_id'])) {
			// Make sure we have a name
			if(!isset($fields[DAO_Domain::NAME])) {
				$fields[DAO_Domain::NAME] = 'New ' . $this->manifest->name;
			}
				
			// Default the created date to now
			if(!isset($fields[DAO_Domain::CREATED]))
				$fields[DAO_Domain::CREATED] = time();
			
			if(!isset($fields[DAO_Domain::UPDATED]))
				$fields[DAO_Domain::UPDATED] = time();
				
			// Create
			$meta['object_id'] = DAO_Domain::create($fields);
				
		} else {
			// Update
			DAO_Domain::update($meta['object_id'], $fields);
		}
	
		// Custom fields
		if(!empty($custom_fields) && !empty($meta['object_id'])) {
			DAO_CustomFieldValue::formatAndSetFieldValues($this->manifest->id, $meta['object_id'], $custom_fields, false, true, true); //$is_blank_unset (4th)
		}
	}
};

class DAO_Domain extends Cerb_ORMHelper {
	const ID = 'id';
	const NAME = 'name';
	const SERVER_ID = 'server_id';
	const CREATED = 'created';
	const UPDATED = 'updated';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO datacenter_domain () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
		
		if(!isset($fields[self::UPDATED]))
			$fields[self::UPDATED] = time();
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
			
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_DOMAIN, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'datacenter_domain', $fields);
			
			// Send events
			if($check_deltas) {
				
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.domain.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_DOMAIN, $batch_ids);
			}
		}
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('datacenter_domain', $fields, $where);
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_Domain[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, name, server_id, created, updated ".
			"FROM datacenter_domain ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->ExecuteSlave($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_Domain	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_Domain[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_Domain();
			$object->id = intval($row['id']);
			$object->name = $row['name'];
			$object->server_id = intval($row['server_id']);
			$object->created = intval($row['created']);
			$object->updated = intval($row['updated']);
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM datacenter_domain WHERE id IN (%s)", $ids_list));
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_DOMAIN,
					'context_ids' => $ids
				)
			)
		);
		
		return true;
	}
	
	public static function maint() {
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.maint',
				array(
					'context' => CerberusContexts::CONTEXT_DOMAIN,
					'context_table' => 'datacenter_domain',
					'context_key' => 'id',
				)
			)
		);
	}
	
	public static function random() {
		return self::_getRandom('datacenter_domain');
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_Domain::getFields();
		
		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
			$sortBy=null;

		list($tables, $wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"datacenter_domain.id as %s, ".
			"datacenter_domain.name as %s, ".
			"datacenter_domain.server_id as %s, ".
			"datacenter_domain.created as %s, ".
			"datacenter_domain.updated as %s ",
				SearchFields_Domain::ID,
				SearchFields_Domain::NAME,
				SearchFields_Domain::SERVER_ID,
				SearchFields_Domain::CREATED,
				SearchFields_Domain::UPDATED
			);
			
		$join_sql = "FROM datacenter_domain ".
			// [JAS]: Dynamic table joins
			(isset($tables['context_link']) ? "INNER JOIN context_link ON (context_link.to_context = 'cerberusweb.contexts.datacenter.domain' AND context_link.to_context_id = datacenter_domain.id) " : " ")
		;
		
		// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'datacenter_domain.id',
			$select_sql,
			$join_sql
		);
				
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";

		// Translate virtual fields
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
			'has_multiple_values' => &$has_multiple_values
		);
		
		array_walk_recursive(
			$params,
			array('DAO_Domain', '_translateVirtualParameters'),
			$args
		);
		
		$result = array(
			'primary_table' => 'datacenter_domain',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
		
		return $result;
	}
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
	
		$from_context = CerberusContexts::CONTEXT_DOMAIN;
		$from_index = 'datacenter_domain.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			case SearchFields_Domain::FULLTEXT_COMMENT_CONTENT:
				$search = Extension_DevblocksSearchSchema::get(Search_CommentContent::ID);
				$query = $search->getQueryFromParam($param);
				
				if(false === ($ids = $search->query($query, array('context_crc32' => sprintf("%u", crc32($from_context)))))) {
					$args['where_sql'] .= 'AND 0 ';

				} elseif(is_array($ids)) {
					$from_ids = DAO_Comment::getContextIdsByContextAndIds($from_context, $ids);
					
					$args['where_sql'] .= sprintf('AND %s IN (%s) ',
						$from_index,
						implode(', ', (!empty($from_ids) ? $from_ids : array(-1)))
					);
					
				} elseif(is_string($ids)) {
					$db = DevblocksPlatform::getDatabaseService();
					$temp_table = sprintf("_tmp_%s", uniqid());
					
					$db->ExecuteSlave(sprintf("CREATE TEMPORARY TABLE %s (PRIMARY KEY (id)) SELECT DISTINCT context_id AS id FROM comment INNER JOIN %s ON (%s.id=comment.id)",
						$temp_table,
						$ids,
						$ids
					));
					
					$args['join_sql'] .= sprintf("INNER JOIN %s ON (%s.id=datacenter_domain.id) ",
						$temp_table,
						$temp_table
					);
				}
				
				break;
			
			case SearchFields_Domain::VIRTUAL_CONTEXT_LINK:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualContextLinks($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
				
			case SearchFields_Domain::VIRTUAL_HAS_FIELDSET:
				self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
			
			case SearchFields_Domain::VIRTUAL_WATCHERS:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualWatchers($param, $from_context, $from_index, $args['join_sql'], $args['where_sql'], $args['tables']);
				break;
		}
	}
	
	/**
	 * Enter description here...
	 *
	 * @param array $columns
	 * @param DevblocksSearchCriteria[] $params
	 * @param integer $limit
	 * @param integer $page
	 * @param string $sortBy
	 * @param boolean $sortAsc
	 * @param boolean $withCounts
	 * @return array
	 */
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();

		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			($has_multiple_values ? 'GROUP BY datacenter_domain.id ' : '').
			$sort_sql;
		
		// [TODO] Could push the select logic down a level too
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
		} else {
			$rs = $db->ExecuteSlave($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs mysqli_result */
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_Domain::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT datacenter_domain.id) " : "SELECT COUNT(datacenter_domain.id) ").
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

};

class SearchFields_Domain implements IDevblocksSearchFields {
	const ID = 'w_id';
	const NAME = 'w_name';
	const SERVER_ID = 'w_server_id';
	const CREATED = 'w_created';
	const UPDATED = 'w_updated';
	
	// Virtuals
	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	const VIRTUAL_WATCHERS = '*_workers';

	// Context Links
	const CONTEXT_LINK = 'cl_context_from';
	const CONTEXT_LINK_ID = 'cl_context_from_id';
	
	// Comment Content
	const FULLTEXT_COMMENT_CONTENT = 'ftcc_content';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'datacenter_domain', 'id', $translate->_('common.id')),
			self::NAME => new DevblocksSearchField(self::NAME, 'datacenter_domain', 'name', $translate->_('common.name'), Model_CustomField::TYPE_SINGLE_LINE),
			self::SERVER_ID => new DevblocksSearchField(self::SERVER_ID, 'datacenter_domain', 'server_id', $translate->_('dao.datacenter_domain.server_id')),
			self::CREATED => new DevblocksSearchField(self::CREATED, 'datacenter_domain', 'created', $translate->_('common.created'), Model_CustomField::TYPE_DATE),
			self::UPDATED => new DevblocksSearchField(self::UPDATED, 'datacenter_domain', 'updated', $translate->_('common.updated'), Model_CustomField::TYPE_DATE),
			
			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS'),
			
			self::CONTEXT_LINK => new DevblocksSearchField(self::CONTEXT_LINK, 'context_link', 'from_context', null),
			self::CONTEXT_LINK_ID => new DevblocksSearchField(self::CONTEXT_LINK_ID, 'context_link', 'from_context_id', null),
				
			self::FULLTEXT_COMMENT_CONTENT => new DevblocksSearchField(self::FULLTEXT_COMMENT_CONTENT, 'ftcc', 'content', $translate->_('comment.filters.content'), 'FT'),
		);
		
		// Fulltext indexes
		
		$columns[self::FULLTEXT_COMMENT_CONTENT]->ft_schema = Search_CommentContent::ID;
		
		// Custom fields with fieldsets
		
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array(
			CerberusContexts::CONTEXT_DOMAIN,
		));
		
		if(is_array($custom_columns))
			$columns = array_merge($columns, $custom_columns);
		
		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_Domain {
	public $id;
	public $name;
	public $server_id;
	public $created;
	public $updated;
};

class View_Domain extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'datacenter_domain';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = $translate->_('cerberusweb.datacenter.domains');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_Domain::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_Domain::SERVER_ID,
			SearchFields_Domain::UPDATED,
		);
		
		// Filter columns
		$this->addColumnsHidden(array(
			SearchFields_Domain::FULLTEXT_COMMENT_CONTENT,
			SearchFields_Domain::VIRTUAL_CONTEXT_LINK,
			SearchFields_Domain::VIRTUAL_HAS_FIELDSET,
			SearchFields_Domain::VIRTUAL_WATCHERS,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_Domain::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		return $objects;
	}

	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_Domain', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_Domain', $size);
	}
	
	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Booleans
				case SearchFields_Domain::SERVER_ID:
					$pass = true;
					break;
					
				// Booleans
				case SearchFields_Domain::VIRTUAL_CONTEXT_LINK:
				case SearchFields_Domain::VIRTUAL_HAS_FIELDSET:
				case SearchFields_Domain::VIRTUAL_WATCHERS:
					$pass = true;
					break;
					
				// Valid custom fields
				default:
					if('cf_' == substr($field_key,0,3))
						$pass = $this->_canSubtotalCustomField($field_key);
					break;
			}
			
			if($pass)
				$fields[$field_key] = $field_model;
		}
		
		return $fields;
	}
	
	function getSubtotalCounts($column) {
		$counts = array();
		$fields = $this->getFields();

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_Domain::SERVER_ID:
				$servers = DAO_Server::getAll();
				$label_map = array(
					'0' => '(none)',
				);
				foreach($servers as $server_id => $server)
					$label_map[$server_id] = $server->name;
				$counts = $this->_getSubtotalCountForStringColumn('DAO_Domain', $column, $label_map, 'in', 'options[]');
				break;

			case SearchFields_Domain::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn('DAO_Domain', CerberusContexts::CONTEXT_DOMAIN, $column);
				break;
				
			case SearchFields_Domain::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn('DAO_Domain', CerberusContexts::CONTEXT_DOMAIN, $column);
				break;
				
			case SearchFields_Domain::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn('DAO_Domain', $column);
				break;
				
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_Domain', $column, 'datacenter_domain.id');
				}
				
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$fields = array(
			'_fulltext' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_Domain::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'comments' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_FULLTEXT,
					'options' => array('param_key' => SearchFields_Domain::FULLTEXT_COMMENT_CONTENT),
				),
			'created' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_Domain::CREATED),
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_Domain::ID),
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_Domain::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'server' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_Domain::SERVER_ID),
					'examples' => array(
						'host1',
						'web,database,mail',
					)
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_Domain::UPDATED),
				),
			'watchers' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_WORKER,
					'options' => array('param_key' => SearchFields_Domain::VIRTUAL_WATCHERS),
				),
		);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_DOMAIN, $fields, null);
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_SERVER, $fields, 'server');
		
		// Engine/schema examples: Comments
		
		$ft_examples = array();
		
		if(false != ($schema = Extension_DevblocksSearchSchema::get(Search_CommentContent::ID))) {
			if(false != ($engine = $schema->getEngine())) {
				$ft_examples = $engine->getQuickSearchExamples($schema);
			}
		}
		
		if(!empty($ft_examples))
			$fields['comments']['examples'] = $ft_examples;
		
		// Sort by keys
		
		ksort($fields);
		
		return $fields;
	}	
	
	function getParamsFromQuickSearchFields($fields) {
		$search_fields = $this->getQuickSearchFields();
		$params = DevblocksSearchCriteria::getParamsFromQueryFields($fields, $search_fields);

		// Handle virtual fields and overrides
		if(is_array($fields))
		foreach($fields as $k => $v) {
			switch($k) {
				case 'server':
					$field_keys = array(
						'server' => SearchFields_Domain::SERVER_ID,
					);
					
					@$field_key = $field_keys[$k];
					
					$oper = DevblocksSearchCriteria::OPER_IN;
					
					$servers = DAO_Server::getAll();
					$patterns = DevblocksPlatform::parseCsvString($v);
					$values = array();
					
					if(is_array($patterns))
					foreach($patterns as $pattern) {
						foreach($servers as $server_id => $server) {
							if(false !== stripos($server->name, $pattern))
								$values[$server_id] = true;
						}
					}
					
					$param = new DevblocksSearchCriteria(
						$field_key,
						$oper,
						array_keys($values)
					);
					$params[$field_key] = $param;
					break;
			}
		}
		
		$this->renderPage = 0;
		$this->addParams($params, true);
		
		return $params;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_DOMAIN);
		$tpl->assign('custom_fields', $custom_fields);
		
		switch($this->renderTemplate) {
			case 'contextlinks_chooser':
			default:
				$tpl->assign('view_template', 'devblocks:cerberusweb.datacenter.domains::domain/view.tpl');
				$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
				break;
		}
		
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_Domain::NAME:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_Domain::ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_Domain::CREATED:
			case SearchFields_Domain::UPDATED:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_Domain::SERVER_ID:
				$options = array();
				$servers = DAO_Server::getAll();
				
				if(is_array($servers))
				foreach($servers as $server_id => $server) { /* @var $server Model_Server */
					$options[$server_id] = $server->name;
				}
				
				$tpl->assign('options', $options);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__list.tpl');
				break;
				
			case SearchFields_Domain::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			case SearchFields_Domain::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, CerberusContexts::CONTEXT_DOMAIN);
				break;
				
			case SearchFields_Domain::VIRTUAL_WATCHERS:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_worker.tpl');
				break;
				
			case SearchFields_Domain::FULLTEXT_COMMENT_CONTENT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__fulltext.tpl');
				break;
				
			default:
				// Custom Fields
				if('cf_' == substr($field,0,3)) {
					$this->_renderCriteriaCustomField($tpl, substr($field,3));
				} else {
					echo ' ';
				}
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		switch($key) {
			case SearchFields_Domain::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_Domain::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
				
			case SearchFields_Domain::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}
	
	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_Domain::SERVER_ID:
				$servers = DAO_Server::getAll();
				$strings = array();

				if(empty($values)) {
					echo "(blank)";
					break;
				}
				
				foreach($values as $val) {
					if(empty($val))
						$strings[] = "(none)";
					elseif(!isset($servers[$val]))
						continue;
					else
						$strings[] = $servers[$val]->name;
				}
				echo implode(" or ", $strings);
				break;
				
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_Domain::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_Domain::NAME:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
			case SearchFields_Domain::ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_Domain::CREATED:
			case SearchFields_Domain::UPDATED:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_Domain::SERVER_ID:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$options);
				break;
				
			case SearchFields_Domain::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_Domain::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_Domain::VIRTUAL_WATCHERS:
				@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$worker_ids);
				break;
				
			case SearchFields_Domain::FULLTEXT_COMMENT_CONTENT:
				@$scope = DevblocksPlatform::importGPC($_REQUEST['scope'],'string','expert');
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_FULLTEXT,array($value,$scope));
				break;
				
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}
		
	function doBulkUpdate($filter, $do, $ids=array()) {
		@set_time_limit(600); // 10m
		
		$change_fields = array();
		$custom_fields = array();
		$deleted = false;

		// Make sure we have actions
		if(empty($do))
			return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
			return;
			
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
				case 'delete':
					$deleted = true;
					break;
				case 'server_id':
					$change_fields[DAO_Domain::SERVER_ID] = $v;
					break;
				default:
					// Custom fields
					if(substr($k,0,3)=="cf_") {
						$custom_fields[substr($k,3)] = $v;
					}
					break;
			}
		}

		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_Domain::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_Domain::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		// Broadcast?
		if(isset($do['broadcast'])) {
			$tpl_builder = DevblocksPlatform::getTemplateBuilder();
			
			$params = $do['broadcast'];
			if(
				!isset($params['worker_id'])
				|| empty($params['worker_id'])
				|| !isset($params['subject'])
				|| empty($params['subject'])
				|| !isset($params['message'])
				|| empty($params['message'])
				)
				break;

			$is_queued = (isset($params['is_queued']) && $params['is_queued']) ? true : false;
			$next_is_closed = (isset($params['next_is_closed'])) ? intval($params['next_is_closed']) : 0;
			
			if(is_array($ids))
			foreach($ids as $domain_id) {
				$addresses = Context_Address::searchInboundLinks(CerberusContexts::CONTEXT_DOMAIN, $domain_id);
				
				foreach($addresses as $address_id => $address) {
					try {
						if($address[SearchFields_Address::IS_DEFUNCT])
							continue;
						
						CerberusContexts::getContext(CerberusContexts::CONTEXT_DOMAIN, array('id'=>$domain_id,'address_id'=>$address_id), $tpl_labels, $tpl_tokens);
						
						$tpl_dict = new DevblocksDictionaryDelegate($tpl_tokens);
						
						$subject = $tpl_builder->build($params['subject'], $tpl_dict);
						$body = $tpl_builder->build($params['message'], $tpl_dict);
						
						$json_params = array(
							'to' => $tpl_dict->contact_address,
							'group_id' => $params['group_id'],
							'next_is_closed' => $next_is_closed,
							'is_broadcast' => 1,
							'context_links' => array(
								array(CerberusContexts::CONTEXT_DOMAIN, $domain_id),
							),
						);
						
						if(isset($params['format']))
							$json_params['format'] = $params['format'];
						
						if(isset($params['html_template_id']))
							$json_params['html_template_id'] = intval($params['html_template_id']);
						
						$fields = array(
							DAO_MailQueue::TYPE => Model_MailQueue::TYPE_COMPOSE,
							DAO_MailQueue::TICKET_ID => 0,
							DAO_MailQueue::WORKER_ID => $params['worker_id'],
							DAO_MailQueue::UPDATED => time(),
							DAO_MailQueue::HINT_TO => $tpl_dict->contact_address,
							DAO_MailQueue::SUBJECT => $subject,
							DAO_MailQueue::BODY => $body,
							DAO_MailQueue::PARAMS_JSON => json_encode($json_params),
						);
						
						if($is_queued) {
							$fields[DAO_MailQueue::IS_QUEUED] = 1;
						}
						
						$draft_id = DAO_MailQueue::create($fields);
						
					} catch (Exception $e) {
						// [TODO] ...
					}
				}
			}
		}
		
		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			if(!$deleted) {
				DAO_Domain::update($batch_ids, $change_fields);
	
				// Custom Fields
				self::_doBulkSetCustomFields(CerberusContexts::CONTEXT_DOMAIN, $custom_fields, $batch_ids);
				
				// Scheduled behavior
				if(isset($do['behavior']) && is_array($do['behavior'])) {
					$behavior_id = $do['behavior']['id'];
					@$behavior_when = strtotime($do['behavior']['when']) or time();
					@$behavior_params = isset($do['behavior']['params']) ? $do['behavior']['params'] : array();
					
					if(!empty($batch_ids) && !empty($behavior_id))
					foreach($batch_ids as $batch_id) {
						DAO_ContextScheduledBehavior::create(array(
							DAO_ContextScheduledBehavior::BEHAVIOR_ID => $behavior_id,
							DAO_ContextScheduledBehavior::CONTEXT => CerberusContexts::CONTEXT_DOMAIN,
							DAO_ContextScheduledBehavior::CONTEXT_ID => $batch_id,
							DAO_ContextScheduledBehavior::RUN_DATE => $behavior_when,
							DAO_ContextScheduledBehavior::VARIABLES_JSON => json_encode($behavior_params),
						));
					}
				}
			} else {
				DAO_Domain::delete($batch_ids);
			}
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

