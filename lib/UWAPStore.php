<?php

class UWAPStore {
	
	protected $db;


	public function __construct() {

		$mongoconfig = GlobalConfig::getValue('mongodb');
		if ($mongoconfig === null) {
			$dbc = new Mongo();
		} else {
			$credential = '';
			if (isset($mongoconfig['user']) && isset($mongoconfig['password'])) {
				$credential = $mongoconfig['user'] . ':' . $mongoconfig['password'] . '@';	
			}
			$connstr = 'mongodb://' . $credential . '' . $mongoconfig['host'] . '/uwap';
			$dbc = new Mongo($connstr);
		}
		
		$this->db = $dbc->uwap;
	}

	public function getStats($collection) {
		$result = $this->db->execute('db["appdata-' . $collection . '"].stats()');
		// echo '<pre>'; print_r($result); exit; echo '</pre>';
		if (!$result['ok']) return null;
		if (!$result['retval']['ok']) return null;
		return $result['retval'];
	}


	// Use the $set operator to set a particular value. The $set operator requires the following syntax:
	// 
	// 		db.collection.update( { field: value1 }, { $set: { field1: value2 } } );
	// 
	// This statement updates in the document in collection where field matches value1 by replacing the value of 
	// the field field1 with value2. This operator will add the specified field or fields if they do not exist in 
	// this document or replace the existing value of the specified field(s) if they already exist.
	public function update($collection, $userid, $criteria, $updates) {

		UWAPLogger::debug('store', 'Updating an object in [' . $collection . ']', array(
			'collection' => $collection,
			'userid' => $userid,
			'criteria' => $criteria,
			'updates' => $updates,
		));

		if (isset($userid)) {
			$criteria["uwap-userid"] = $userid;	
		}

		$updatestmnt = array('$set' => $updates);

		$return = $this->db->{$collection}->update($criteria, $updatestmnt, array("safe" => true));
		// error_log("Return on update() : " . var_export($return, true));
		return $return;
	}

	public function store($collection, $userid = null, $obj, $expiresin = null) {
		
		if ($collection !== 'log') {
			UWAPLogger::debug('store', 'Storing a object in [' . $collection . ']', $obj);
		}

		if (isset($userid)) {
			$obj["uwap-userid"] = $userid;	
		}

		if (isset($obj["_id"]) && !is_object($obj["_id"]) && isset($obj["_id"]['$id'])) {
			$obj["_id"] = new MongoId($obj["_id"]['$id']);
		}
		if ($expiresin !== null) {
			$obj["expires"] = floor(microtime(true)*1000.0) + (1000*$expiresin);
		}

		// echo 'store'; print_r($obj);
		// error_log("store() " . var_export($obj, true));

		// try {
		$this->db->{$collection}->save($obj, array("safe" => true));	


		// error_log("STORING Object: ". var_export($obj, true));
		// } catch (Exception $e) {
		// 	print_r($e);
		// }
		

		// save() returns an array of info about success of the save. TODO: translate to exception if needed.

	}




	public function remove($collection, $userid, $obj) {


		UWAPLogger::debug('store', 'Removing an object in [' . $collection . ']', array(
			'collection' => $collection,
			'userid' => $userid,
			'object' => $obj,
		));

		if (isset($obj["_id"]) && !is_object($obj["_id"]) && isset($obj["_id"]['$id'])) {
			$obj["_id"] = new MongoId($obj["_id"]['$id']);
		}

		if (isset($userid)) {
			$obj["uwap-userid"] = $userid;
		}

		// echo "Query for removal is ";
		// print_r($obj); 

		$result = $this->db->{$collection}->remove($obj, array("safe" => true));
		if (is_array($result)) {
			foreach($result AS $r) {
				if ($r === false) throw new Exception('Error removing object from MongoDB storage');
			}
		} else if (is_bool($result)) {
			if (!$result) throw new Exception('Error removing object from MongoDB storage');
		}
		return true;
	}

	public function getACLclient($clientid, $groups = array()) {
		if (empty($clientid)) throw new Exception('Clientid is missing');
		$grps = $groups;
		$grps[] = '!public';
		$criteria = array();
		$criteria[] = array("uwap-clientid" => $clientid);
		$criteria[] = array(
			"uwap-acl-read" => array(
				'$in' => $grps,
			),
		);
		return $criteria;
	}

	public function getACL($userid, $groups = array()) {
		if (empty($userid)) throw new Exception('Userid is missing');
		$grps = array_keys($groups);
		$grps[] = '!public';
		$criteria = array();
		$criteria[] = array("uwap-userid" => $userid);
		$criteria[] = array(
			"uwap-acl-read" => array(
				'$in' => $grps,
			),
		);
		return $criteria;
	}


	public function getACLwithSubscriptions($userid, $groups = array(), $subs = array()) {

		// echo 'getACLwithSubscriptions()';
		// print_r($userid); print_r($groups); print_r($subs);

		if (empty($userid)) throw new Exception('Userid is missing');
		$grps = array_keys($groups);
		// $grps[] = '!public';
		// 
		$sc = array(
			'$and' => array(
				array("uwap-acl-read" => array(
					'$in' => array('!public')
				)),
				array("uwap-acl-read" => array(
					'$in' => $subs
				))
			)
		);

		$criteria = array();
		// $criteria[] = array("uwap-userid" => $userid);
		$criteria[] = array(
			"uwap-acl-read" => array(
				'$in' => $grps,
			),
		);
		$criteria[] = $sc;

		// print_r($criteria);

		return $criteria;
	}

	public function queryOneUser($collection, $userid, $groups, $criteria = array(), $fields = array()) {
		// $criteria["uwap-userid"] = $userid;
		if ($userid !== null) {
			$criteria['$or'] = $this->getACL($userid, $groups);
		}
		
		if ($collection !== 'log') {
			UWAPLogger::debug('store', 'Query one userobject in [' . $collection . ']', array(
				'collection' => $collection,
				'userid' => $userid,
				'criteria' => $criteria,
			));
		}

		return $this->queryOne($collection, $criteria, $fields);
	}

	public function queryListClient($collection, $clientid, $groups, $criteria = array(), $fields = array(), $options = array() ) {

		if (empty($groups)) {
			$criteria["uwap-userid"] = $userid;
		} else {
			if (isset($criteria['$or'])) {
				
				$criteria['$and'] = array(
					array('$or' => $this->getACL($userid, $groups)),
					array('$or' => $criteria['$or']),
				) ;
				unset($criteria['$or']);
			} else {
				$criteria['$or'] = $this->getACL($userid, $groups);
			}
		}
		// echo 'query'; print_r($criteria); exit;
		if ($collection !== 'log') {
			UWAPLogger::debug('store', 'Query list client object in [' . $collection . ']', array(
				'collection' => $collection,
				'clientid' => $clientid,
				'criteria' => $criteria,
			));
		}

		$ret = $this->queryList($collection, $criteria, $fields, $options);
		// echo 'Result'; print_r($ret); exit;
		return $ret;
	}


	public function queryListUserAdvanced($collection, $userid, $groups, $subscriptions, $criteria = array(), $fields = array(), $options = array() ) {

		// $criteria = array(
		// 	'$or' => $this->getACLwithSubscriptions($userid, $groups, $subscriptions)
		// );

		if (isset($criteria['$or'])) {

			$criteria['$and'] = array(
				array('$or' => $this->getACLwithSubscriptions($userid, $groups, $subscriptions)),
				array('$or' => $criteria['$or']),
			) ;
			unset($criteria['$or']);
		} else {
			$criteria['$or'] = $this->getACLwithSubscriptions($userid, $groups, $subscriptions);
		}

		// echo "prepared query:\n"; print_r($criteria);
		// echo "\n\ndb.feed.find("; echo json_encode($criteria); echo ");\n\n";


		// if (empty($groups)) {
		// 	$criteria["uwap-userid"] = $userid;
		// } else {
		// 	if (isset($criteria['$or'])) {

		// 		$criteria['$and'] = array(
		// 			array('$or' => $this->getACLwithSubscriptions($userid, $groups, $subscriptions)),
		// 			array('$or' => $criteria['$or']),
		// 		) ;
		// 		unset($criteria['$or']);
		// 	} else {
		// 		$criteria['$or'] = $this->getACLwithSubscriptions($userid, $groups, $subscriptions);
		// 	}
		// }
		// echo 'query'; print_r($criteria); exit;
		
		if ($collection !== 'log') {
			UWAPLogger::debug('store', 'Query list userobject in [' . $collection . ']', array(
				'collection' => $collection,
				'userid' => $userid,
				'criteria' => $criteria,
			));
		}

		// print_r($criteria); exit;
		// 
		// echo "\nBEFORE\n";

		$ret = $this->queryList($collection, $criteria, $fields, $options);

		// echo "raw result\n"; print_r($ret); echo "\n\n";

		return $ret;
	}

	public function queryListUser($collection, $userid, $groups, $criteria = array(), $fields = array(), $options = array() ) {

		if (empty($groups)) {
			$criteria["uwap-userid"] = $userid;
		} else {
			if (isset($criteria['$or'])) {

				$criteria['$and'] = array(
					array('$or' => $this->getACL($userid, $groups)),
					array('$or' => $criteria['$or']),
				) ;
				unset($criteria['$or']);
			} else {
				$criteria['$or'] = $this->getACL($userid, $groups);
			}
		}
		// echo 'query'; print_r($criteria); exit;
		if ($collection !== 'log') {
			UWAPLogger::debug('store', 'Query list userobject in [' . $collection . ']', array(
				'collection' => $collection,
				'userid' => $userid,
				'criteria' => $criteria,
			));
		}

		// print_r($criteria);

		$ret = $this->queryList($collection, $criteria, $fields, $options);
		// echo 'Result'; print_r($ret); exit;
		return $ret;
	}

	public function count($collection, $criteria = array()) {
		return $this->db->{$collection}->count($criteria);
	}

	public function queryOne($collection, $criteria = array(), $fields = array()) {
		// error_log("queryOne: (" . $collection . ") " . var_export($criteria, true));

		if ($collection !== 'log') {
			UWAPLogger::debug('store', 'Query one in [' . $collection . ']', array(
				'collection' => $collection,
				'criteria' => $criteria,
			));
		}

		if (isset($criteria["_id"])) {
			$criteria["_id"] = new MongoId($criteria["_id"]);
		}

		$cursor = $this->db->{$collection}->find($criteria, $fields);
		if ($cursor->count() < 1) return null;
		return $cursor->getNext();
	}

	public static function idify($q) {
		foreach($q AS $k => $v) {
			if ($k === '_id') {
				$q[$k] = new MongoId($q["_id"]);
			}
			if (is_array($v)) {
				$q[$k] = self::idify($v);
			}
		}
		return $q;
	}

	public function queryList($collection, $criteria, $fields = array(), $options = array()) {

		if ($collection !== 'log') {
			UWAPLogger::debug('store', 'Query list in [' . $collection . ']', array(
				'collection' => $collection,
				'criteria' => $criteria,
				'options'  => $options,
			));
		}

		$criteria = self::idify($criteria);

		// echo "IDIFIED \n"; print_r($criteria2); echo "\n\n";


		// if ($collection === 'feed') {
		// echo 'QUERY:';
		// print_r($criteria);	
		// }




		$cursor = $this->db->{$collection}->find($criteria, $fields);
		// if ($cursor->count() < 1) return null;

		if (isset($options['limit'])) {
			$cursor->limit($options['limit']);
		}
		if (isset($options['sort'])) {
			$cursor->sort($options['sort']);
		} else {

		}
		
		$result = array();

		if (!$cursor->hasNext()) return $result;

		// try {
		foreach($cursor AS $element) $result[] = $element;	
		// } catch(Exception $e) {
		// 	echo "error in performing query: "; print_r($criteria); exit;
		// }

		// echo "Criteria: \n";
		// // print_r($criteria);
		// var_export($criteria);

		// echo "\ndb.feed.find("; echo json_encode($criteria); echo ");\n";
		// echo "\n\n";
		// echo "RESULT\n"; print_r(count($result)); echo "\n\n\n\n-------------------\n";

		return $result;
	}

}