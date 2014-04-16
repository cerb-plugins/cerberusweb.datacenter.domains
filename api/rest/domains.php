<?php
class ChRest_Domains extends Extension_RestController implements IExtensionRestController {
	function getAction($stack) {
		@$action = array_shift($stack);
		
		// Looking up a single ID?
		if(is_numeric($action)) {
			$this->getId(intval($action));
			
		} else { // actions
			switch($action) {
			}
		}
		
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function putAction($stack) {
		@$action = array_shift($stack);
		
		// Looking up a single ID?
		if(is_numeric($action)) {
			$this->putId(intval($action));
			
		} else { // actions
			switch($action) {
			}
		}
		
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function postAction($stack) {
		@$action = array_shift($stack);
		
		if(is_numeric($action) && !empty($stack)) {
			$id = intval($action);
			$action = array_shift($stack);
			
			switch($action) {
				case 'contacts':
					$this->postContacts($id);
					break;
			}
			
		} else {
			switch($action) {
				case 'create':
					$this->postCreate();
					break;
				case 'search':
					$this->postSearch();
					break;
			}
		}
		
		$this->error(self::ERRNO_NOT_IMPLEMENTED);
	}
	
	function deleteAction($stack) {
		$id = array_shift($stack);

		if(null == ($domain = DAO_Domain::get($id)))
			$this->error(self::ERRNO_CUSTOM, sprintf("Invalid domain ID %d", $id));

		DAO_Domain::delete($id);

		$result = array('id' => $id);
		$this->success($result);
	}

	function translateToken($token, $type='dao') {
		$tokens = array();
		
		if('dao'==$type) {
			$tokens = array(
				'created' => DAO_Domain::CREATED,
				'name' => DAO_Domain::NAME,
				'server_id' => DAO_Domain::SERVER_ID,
			);
		} else {
			$tokens = array(
				'created' => SearchFields_Domain::CREATED,
				'id' => SearchFields_Domain::ID,
				'name' => SearchFields_Domain::NAME,
				'server_id' => SearchFields_Domain::SERVER_ID,
			);
		}
		
		if(isset($tokens[$token]))
			return $tokens[$token];
		
		return NULL;
	}

	function getContext($id) {
		$labels = array();
		$values = array();
		$context = CerberusContexts::getContext(CerberusContexts::CONTEXT_DOMAIN, $id, $labels, $values, null, true);

		return $values;
	}
	
	function getId($id) {
		$worker = CerberusApplication::getActiveWorker();
		
		$container = $this->search(array(
			array('id', '=', $id),
		));
		
		if(is_array($container) && isset($container['results']) && isset($container['results'][$id]))
			$this->success($container['results'][$id]);

		// Error
		$this->error(self::ERRNO_CUSTOM, sprintf("Invalid domain id '%d'", $id));
	}
	
	function search($filters=array(), $sortToken='id', $sortAsc=1, $page=1, $limit=10) {
		$worker = CerberusApplication::getActiveWorker();

		$custom_field_params = $this->_handleSearchBuildParamsCustomFields($filters, CerberusContexts::CONTEXT_DOMAIN);
		$params = $this->_handleSearchBuildParams($filters);
		$params = array_merge($params, $custom_field_params);
				
		// Sort
		$sortBy = $this->translateToken($sortToken, 'search');
		$sortAsc = !empty($sortAsc) ? true : false;
		
		// Search
		list($results, $total) = DAO_Domain::search(
			!empty($sortBy) ? array($sortBy) : array(),
			$params,
			$limit,
			max(0,$page-1),
			$sortBy,
			$sortAsc,
			true
		);
		
		$objects = array();
		
		foreach($results as $id => $result) {
			$values = $this->getContext($id);
			$objects[$id] = $values;
		}
		
		$container = array(
			'total' => $total,
			'count' => count($objects),
			'page' => $page,
			'results' => $objects,
		);
		
		return $container;
	}
	
	function postSearch() {
		$container = $this->_handlePostSearch();
		$this->success($container);
	}
	
	function putId($id) {
		$worker = CerberusApplication::getActiveWorker();
		
		// Validate the ID
		if(null == ($domain = DAO_Domain::get($id)))
			$this->error(self::ERRNO_CUSTOM, sprintf("Invalid domain ID '%d'", $id));
			
		$putfields = array(
			'created' => 'timestamp',
			'name' => 'string',
			'server_id' => 'integer',
		);

		$fields = array();

		foreach($putfields as $putfield => $type) {
			if(!isset($_POST[$putfield]))
				continue;
			
			@$value = DevblocksPlatform::importGPC($_POST[$putfield], 'string', '');
			
			if(null == ($field = self::translateToken($putfield, 'dao'))) {
				$this->error(self::ERRNO_CUSTOM, sprintf("'%s' is not a valid field.", $putfield));
			}
			
			// Sanitize
			$value = DevblocksPlatform::importVar($value, $type);

			$fields[$field] = $value;
		}
		
		// [TODO] All records should have an updated timestamp for syncing
		//if(!isset($fields[DAO_Domain::UPDATED]))
		//	$fields[DAO_Domain::UPDATED] = time();
		
		// Handle custom fields
		$customfields = $this->_handleCustomFields($_POST);
		if(is_array($customfields))
			DAO_CustomFieldValue::formatAndSetFieldValues(CerberusContexts::CONTEXT_DOMAIN, $id, $customfields, true, true, true);
		
		// Update
		DAO_Domain::update($id, $fields);
		$this->getId($id);
	}
	
	function postCreate() {
		$worker = CerberusApplication::getActiveWorker();
		
		$postfields = array(
			'created' => 'timestamp',
			'name' => 'string',
			'server_id' => 'integer',
		);

		$fields = array();
		
		foreach($postfields as $postfield => $type) {
			if(!isset($_POST[$postfield]))
				continue;
				
			@$value = DevblocksPlatform::importGPC($_POST[$postfield], 'string', '');
				
			if(null == ($field = self::translateToken($postfield, 'dao'))) {
				$this->error(self::ERRNO_CUSTOM, sprintf("'%s' is not a valid field.", $postfield));
			}

			// Sanitize
			$value = DevblocksPlatform::importVar($value, $type);
			
			switch($field) {
				default:
					$fields[$field] = $value;
					break;
			}
		}

		// Defaults
		if(!isset($fields[DAO_Domain::CREATED]))
			$fields[DAO_Domain::CREATED] = time();
		
		// Check required fields
		$reqfields = array(
			DAO_Domain::NAME,
		);
		$this->_handleRequiredFields($reqfields, $fields);
		
		// Create
		if(false != ($id = DAO_Domain::create($fields))) {
			// Handle custom fields
			$customfields = $this->_handleCustomFields($_POST);
			if(is_array($customfields))
				DAO_CustomFieldValue::formatAndSetFieldValues(CerberusContexts::CONTEXT_DOMAIN, $id, $customfields, true, true, true);
			
			$this->getId($id);
		}
	}

	function postContacts($domain_id) {
		// Validate the ID
		if(false == ($domain = DAO_Domain::get($domain_id)))
			$this->error(self::ERRNO_CUSTOM, sprintf("The domain id #%d does not exist.", $domain_id));
		
		$postfields = array(
			'mode' => 'string', // add | remove
			'emails' => 'string', // CSV['jeff@example.com']
			'ids' => 'string', // CSV [1,2,3]
		);

		$mode = null;
		$ids = array();
		
		foreach($postfields as $postfield => $type) {
			if(!isset($_POST[$postfield]))
				continue;
				
			@$value = DevblocksPlatform::importGPC($_POST[$postfield], 'string', '');
			
			switch($postfield) {
				case 'mode':
					if(!in_array($value, array('add', 'remove')))
						$this->error(self::ERRNO_CUSTOM, sprintf("The valid options for '%s' are 'add' or 'remove'.", $postfield));
					
					$mode = $value;
					break;
					
				case 'ids':
					$value = DevblocksPlatform::parseCsvString($value);

					foreach($value as $id) {
						if(false == ($address = DAO_Address::get($id)))
							$this->error(self::ERRNO_CUSTOM, sprintf("The address id #%d in '%s' does not exist.", $id, $postfield));
							
						$ids[] = $address->id;
					}
					break;
					
				case 'emails':
					$value = DevblocksPlatform::parseCsvString($value);

					foreach($value as $email) {
						if(false == ($address = DAO_Address::lookupAddress($email, ($mode=='add'))))
							$this->error(self::ERRNO_CUSTOM, sprintf("The address '%s' in '%s' is invalid.", $email, $postfield));
							
						$ids[] = $address->id;
					}
					break;
			}
		}

		if(!empty($ids))
		switch($mode) {
			case 'add':
				foreach($ids as $address_id)
					DAO_ContextLink::setLink(CerberusContexts::CONTEXT_DOMAIN, $domain_id, CerberusContexts::CONTEXT_ADDRESS, $address_id);
				break;
				
			case 'remove':
				foreach($ids as $address_id)
					DAO_ContextLink::deleteLink(CerberusContexts::CONTEXT_DOMAIN, $domain_id, CerberusContexts::CONTEXT_ADDRESS, $address_id);
				break;
		}
		
		
		$container = array();
		$this->success($container);
	}
};