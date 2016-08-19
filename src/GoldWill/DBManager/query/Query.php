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
			if (is_numeric($value))
			{
				$queries []= 'SET @' . $key . ' = ' . $value;
			}
			else
			{
				$queries []= 'SET @' . $key . ' = \'' . $mysqli->escape_string($value) . '\'';
			}
		}
		
		foreach ($this->queries as $query)
		{
			$queries []= $query;
		}
		
		if ($mysqli->multi_query(implode('; ', $queries)))
		{
			$results = [];
			
			do
			{
				$result = $mysqli->store_result();
				$query = array_shift($queries);
				
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
					if ($mysqli->error)
					{
						$results [] = new QueryResult([  ], null, $query, $mysqli->error);
					}
					elseif ($mysqli->warning_count > 0)
					{
						$warnings = $mysqli->get_warnings();
						$warningArray = [  ];
						
						do
						{
							$warningArray []= $warnings->message;
						}
						while ($warnings->next());
						
						$results [] = new QueryResult([  ], null, $query, implode(PHP_EOL, $warningArray));
					}
				}
			}
			while (($mysqli->more_results()) && ($mysqli->next_result()));
			
			return (count($results) === 1) ? ($results[0]) : ($results);
		}
		else
		{
			throw new \RuntimeException($mysqli->error);
		}
	}
	
	/**
	 * @return string
	 */
	public function __toString() : string
	{
		return 'Query(Parameters: ' . PHP_EOL . print_r($this->parameters, true) . PHP_EOL . 'Queries: ' . PHP_EOL . print_r($this->queries, true) . PHP_EOL . ')';
	}
}