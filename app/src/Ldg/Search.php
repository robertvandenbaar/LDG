<?php

namespace Ldg;

use Ldg\Model\Image;

class Search
{
	protected $index = null;
	protected $indexFile = null;

	function __construct($indexFile = null)
	{
		if ($indexFile === null)
		{
			$indexFile = BASE_DIR . '/data/search.json';
		}

		$this->indexFile = $indexFile;

		if (!file_exists($this->indexFile))
		{
			if (!file_put_contents($this->indexFile, json_encode([],JSON_PRETTY_PRINT)))
			{
				throw new \Exception('Could not create index file: ' . $this->indexFile);
			}
		}

		if (!$this->loadIndex())
		{
			throw new \Exception('Could not read index file: ' . $this->indexFile);
		}
	}

	function resetIndex()
	{
		$this->index = [];
	}

	function loadIndex()
	{
		$index = json_decode(file_get_contents($this->indexFile), true);

		if ($index === false)
		{
			return false;
		}

		$this->index = $index;

		return true;

	}

	function setEntry($key, $value, $metadata = [])
	{
	    $data = array_merge(['search_data' => $value, 'metadata' => $metadata]);

        $this->index[$key] = $data;
	}

	function search($q)
	{
		$results = [];

		$words = explode(' ', trim($q));

		foreach ($this->index as $key => $value)
		{
			$match = true;

			if (is_string($value)) {
                $searchValue = $value;
            } else {
                $searchValue = $value['search_data'];
            }

			if (isset($_REQUEST['include_file_path']))
            {
                $searchValue .= $key;
            }

			foreach($words as $word)
			{
				if (stripos($searchValue, $word) === false && stripos($this->replaceDiacritics($searchValue), $word) === false)
				{
					$match = false;
					continue;
				}
			}

			if ($match)
			{
				$results[$key] = $searchValue;
			}

		}

		return $results;
	}

	function replaceDiacritics($string)
	{
		if (function_exists('iconv'))
		{
			return iconv('UTF-8','ASCII//TRANSLIT', $string);
		}

		return $string;

	}

	function getIndexCount()
	{
		return count($this->index);
	}

	function save()
	{
		file_put_contents($this->indexFile, json_encode($this->index, JSON_PRETTY_PRINT));
	}

	function getLatestFiles($limit = 20)
    {
        $sort = $this->index;

        $sort = array_filter($sort, function($el){
            return isset($el['metadata']) && isset($el['metadata']['date_taken']);
        });

        uasort($sort, function($a, $b){
           $dateA = $a['metadata']['date_taken'];
           $dateB = $b['metadata']['date_taken'];

           if ($dateA == $dateB) {
               return 0;
           }

           return $dateA > $dateB ? -1 : 1;

        });

        $sliced = array_slice($sort, 0, $limit);

        $return = [];

        foreach ($sliced as $key => $image) {
            $return[] = new Image($key);
        }

        return $return;

    }
}