<?php
class bdApi_Model_Client extends XenForo_Model
{	
	public function verifySecret(array $client, $secret)
	{
		return $client['client_secret'] == $this->hashSecret($secret);
	}
	
	public function hashSecret($secret)
	{
		return md5($secret);
	}

	public function getList(array $conditions = array(), array $fetchOptions = array())
	{
		$clients = $this->getClients($conditions, $fetchOptions);
		$list = array();
		
		foreach ($clients as $clientId => $client)
		{
			$list[$clientId] = $client['name'];
		}
		
		return $list;
	}

	public function getClientById($clientId, array $fetchOptions = array())
	{
		$data = $this->getClients(array ('client_id' => $clientId), $fetchOptions);
		
		return reset($data);
	}
	
	public function getClients(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareClientConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareClientOrderOptions($fetchOptions);
		$joinOptions = $this->prepareClientFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		$all = $this->fetchAllKeyed($this->limitQueryResults("
				SELECT client.*
					$joinOptions[selectFields]
				FROM `xf_bdapi_client` AS client
					$joinOptions[joinTables]
				WHERE $whereConditions
					$orderClause
			", $limitOptions['limit'], $limitOptions['offset']
		), 'client_id');

		return $all;
	}
		
	public function countClients(array $conditions = array(), array $fetchOptions = array())
	{
		$whereConditions = $this->prepareClientConditions($conditions, $fetchOptions);

		$orderClause = $this->prepareClientOrderOptions($fetchOptions);
		$joinOptions = $this->prepareClientFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

		return $this->_getDb()->fetchOne("
			SELECT COUNT(*)
			FROM `xf_bdapi_client` AS client
				$joinOptions[joinTables]
			WHERE $whereConditions
		");
	}
	
	public function prepareClientConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();
		
		foreach (array('client_id', 'user_id') as $columnName)
		{
			if (!isset($conditions[$columnName])) continue;
			
			if (is_array($conditions[$columnName]))
			{
				if (!empty($conditions[$columnName]))
				{
					// only use IN condition if the array is not empty (nasty!)
					$sqlConditions[] = "client.$columnName IN (" . $db->quote($conditions[$columnName]) . ")";
				}
			}
			else
			{
				$sqlConditions[] = "client.$columnName = " . $db->quote($conditions[$columnName]);
			}
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
	
	public function prepareClientFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinTables = '';
		
		return array(
			'selectFields' => $selectFields,
			'joinTables'   => $joinTables
		);
	}
	
	public function prepareClientOrderOptions(array &$fetchOptions, $defaultOrderSql = '') {
		$choices = array(
			
		);
		
		return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
	}
}