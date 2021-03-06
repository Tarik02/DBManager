<?php
namespace GoldWill\DBManager\query;

use GoldWill\DBManager\ConnectionConfig;


class Query
{
	/** @var string[] */
	private $queries;
	
	/** @var string[] */
	private $parameters;
	
	
	/**
	 * Query constructor.
	 *
	 * @param string[] $queries
	 * @param array|null $parameters
	 */
	public function __construct(array $queries, array $parameters = null)
	{
		$this->queries = $queries;
		$this->parameters = $parameters ?: [  ];
	}
	
	/**
	 * @param ConnectionConfig $connectionConfig
	 *
	 * @return QueryResult|QueryResult[]
	 */
	public function query(ConnectionConfig $connectionConfig)
	{
		$mysqli = $connectionConfig->getConnection();
		
		$queries = [  ];
		
		foreach ($this->parameters as $key => $value)
		{
			$queries []= 'SET @' . $key . ' = ' . $this->parameterToQuery($value, $connectionConfig);
		}
		
		foreach ($this->queries as $query)
		{
			$queries []= $query;
		}
		
		$query = implode('; ', $queries);
		array_splice($queries, 0, count($this->parameters));
		
		if ($mysqli->multi_query($query))
		{
			$results = [];
			
			do
			{
				$result = $mysqli->store_result();
				$query = array_shift($queries) ?: '';
				
				if ($result instanceof \mysqli_result)
				{
					$results [] = new QueryResult(array_map(function(array $row)
					{
						return new QueryResultRow($row);
					}, $result->fetch_all(MYSQLI_ASSOC)), null, $query, null);
					
					$result->free();
				}
				elseif (($result === true) || ($mysqli->insert_id !== 0))
				{
					$results [] = new QueryResult([  ], $mysqli->insert_id, $query, null);
				}
				elseif ($result === false)
				{
					if ($mysqli->insert_id !== 0)
					{
						$results [] = new QueryResult([  ], $mysqli->insert_id, $query, null);
					}
					else if (!empty($mysqli->error))
					{
						$results [] = new QueryResult([  ], null, $query, $mysqli->error);
					}
				}
			}
			while (($mysqli->more_results()) && ($mysqli->next_result()));
			
			/*if ($result = $mysqli->query('SHOW WARNINGS'))
			{
				while ($row = $result->fetch_row())
				{
					$results [] = new QueryResult([], null, $query, sprintf("%s (%d): %s", $row[0], $row[1], $row[2]));
				}
				
				$result->free();
			}*/
			
			return (count($results) === 1) ? ($results[0]) : ($results);
		}
		else
		{
			throw new \RuntimeException($mysqli->error);
		}
	}
	
	/**
	 * @param ConnectionConfig $connectionConfig
	 *
	 * @return string
	 */
	public function toString(ConnectionConfig $connectionConfig) : string
	{
		$queries = [  ];
		
		foreach ($this->parameters as $key => $value)
		{
			$queries []= 'SET @' . $key . ' = ' . $this->parameterToQuery($value, $connectionConfig);
		}
		
		foreach ($this->queries as $query)
		{
			$queries []= $query;
		}
		
		return implode(';' . PHP_EOL, $queries);
	}
	
	/**
	 * @param mixed $value
	 * @param ConnectionConfig $connectionConfig
	 *
	 * @return string
	 */
	private function parameterToQuery($value, ConnectionConfig $connectionConfig)
	{
		if (is_numeric($value))
		{
			return $value;
		}
		elseif (is_string($value))
		{
			return '\'' . $connectionConfig->getConnection()->escape_string($value) . '\'';
		}
		elseif (is_null($value))
		{
			return 'NULL';
		}
		elseif ((is_object($value)) || (is_array($value)))
		{
			return '\'' . $connectionConfig->getConnection()->escape_string(json_encode($value)) . '\'';
		}
		
		return '\'' . $connectionConfig->getConnection()->escape_string($value) . '\'';
	}
}