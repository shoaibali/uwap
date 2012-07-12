<?php

class LogStore {

	protected $store;

	public function __construct() {
		$this->store = new UWAPStore;
	}

	public function getLogs($after, $filters = array(), $max = 100) {

		$query = array();


		foreach($filters AS $filter) {

			foreach($filter AS $attr => $def) {

				foreach($def AS $value => $include) {

					error_log("Processing [" . $attr . ", " . var_export($value, true) . ", " . $include) ;

					if (!array_key_exists($attr, $query)) $query[$attr] = array();

					if ($include == 1) {
						if (!array_key_exists('$in', $query[$attr])) {
							$query[$attr]['$in'] = array();
						}
						$query[$attr]['$in'][] = $value;
					} else if ($include == 0) {
						if (!array_key_exists('$nin', $query[$attr])) {
							$query[$attr]['$nin'] = array();
						}
						$query[$attr]['$nin'][] = $value;
					} else {
						error_log("Invalid value : " . var_export($value, true));
					}

				}

			}

		}


		$query['time'] = array(
			'$gt' => $after,
		);


		error_log(">>>>> Incomming filter: " . json_encode($filters));
		error_log(">>>>> Genereated query  " . json_encode($query));

		$options = array(
			'limit' => $max,
			'sort' => array('time' => -1),
		);

		$res = $this->store->queryList('log', $query, array(), $options);
		$res2 = array();
		$result = array(
			'data' => null,
		);
		if (count($res) > 0) {

			$lastTimestamp = number_format($res[count($res)-1]['time'], 6, '.', '');

			foreach($res AS $r) {
				if (number_format($r['time'], 4, '.', '') !== $lastTimestamp) {
					$res2[] = $r;
				}
			}
 
			error_log('From time ' . number_format($res[0]['time'], 6, '.', ''));
			error_log('To   time ' . number_format($res[count($res)-1]['time'], 6, '.', ''));
			error_log('To2  time ' . number_format($res2[count($res2)-1]['time'], 6, '.', ''));

			$result['to'] = number_format($res2[0]['time'], 6, '.', '');
			$result['from'] = number_format($res2[count($res2)-1]['time'], 6, '.', '');
			$result['data'] = $res2;
		}
		return $result;
	}

}
