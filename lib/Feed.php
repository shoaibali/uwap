<?php


class Feed {
	protected $store, $userid, $groups;


	public function __construct($userid, $groups) {

		$this->userid = $userid;
		$this->groups = $groups;
		$this->store = new UWAPStore();

	}

	public function read() {
		$query = array(
		);
		// echo 'groups'; print_r($this->groups); exit;
		$auth = new AuthBase();
		$list = $this->store->queryListUser("feed", $this->userid, $this->groups, $query);
		if (empty($list)) return array();
		foreach($list AS $k => $v) {
			if (!empty($v['uwap-acl-read'])) {
				$list[$k]['groups'] = $v['uwap-acl-read'];
			}
			if (!empty($v['uwap-userid'])) {
				$list[$k]['user'] = $auth->getUserBasic($v['uwap-userid']);
			}
		}
		return $list;
	}


	public function post($msg, $groups = array()) {
		if (!is_array($groups)) throw new Exception("Provided groups must be an array");
		$msg['uwap-acl-read'] = $groups;
		// unset($groups);
		$msg['ts'] = time();
		return $this->store->store("feed", $this->userid, $msg);
		// store($collection, $userid = null, $obj, $expiresin = null) {
	}



/*

	case 'remove':
		if (empty($parameters['object'])) throw new Exception("Missing required parameter [object] object to save");
		$store->remove("appdata-" . $targetapp, $userid, $parameters['object']);
		break;

	case 'save':
		if (empty($parameters['object'])) throw new Exception("Missing required parameter [object] object to save");
		$store->store("appdata-" . $targetapp, $userid, $parameters['object']);
		break;

		// TODO: Clean output before returning. In example remove uwap- namespace attributes...
	case 'queryOne':
		if (empty($parameters['query'])) throw new Exception("Missing required parameter [query] query");
		$response['data'] = $store->queryOneUser("appdata-" . $targetapp, $userid, $groups, $parameters['query']);
		break;

	case 'queryList':
		if (empty($parameters['query'])) throw new Exception("Missing required parameter [query] query");
		$response['data'] = $store->queryListUser("appdata-" . $targetapp, $userid, $groups, $parameters['query']);
		break;

 */

}

