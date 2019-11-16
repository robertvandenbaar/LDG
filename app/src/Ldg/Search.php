<?php

namespace Ldg;

use Ldg\Model\Image;

class Search
{
    protected $index = null;
    protected $indexFile = null;

    function __construct($indexFile = null)
    {
        if ($indexFile === null) {
            $indexFile = BASE_DIR . '/data/search.json';
        }

        $this->indexFile = $indexFile;

        if (!file_exists($this->indexFile)) {
            if (!file_put_contents($this->indexFile, json_encode([], JSON_PRETTY_PRINT))) {
                throw new \Exception('Could not create index file: ' . $this->indexFile);
            }
        }

        if (!$this->loadIndex()) {
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

        if ($index === false) {
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

        $sortedIndex = $this->index;

        uasort($sortedIndex, [$this, 'sortByDateTaken']);

        foreach ($sortedIndex as $key => $value) {
            $match = true;

            if (is_string($value)) {
                $searchValue = $value;
            } else {
                $searchValue = $value['search_data'];
            }

            if (isset($_REQUEST['include_file_path'])) {
                $searchValue .= $key;
            }

            foreach ($words as $word) {
                if (stripos($searchValue, $word) === false && stripos($this->replaceDiacritics($searchValue),
                        $word) === false
                ) {
                    $match = false;
                    continue;
                }
            }

            if ($match) {
                $results[$key] = $searchValue;
            }

        }

        return $results;
    }

    function replaceDiacritics($string)
    {
        if (function_exists('iconv')) {
            return iconv('UTF-8', 'ASCII//TRANSLIT', $string);
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

    function sortByDateTaken($a, $b)
    {
        if (!isset($a['metadata']) || !isset($a['metadata']['date_taken'])) {
            return 0;
        }

        if (!isset($b['metadata']) || !isset($b['metadata']['date_taken'])) {
            return 0;
        }

        $dateA = $a['metadata']['date_taken'];
        $dateB = $b['metadata']['date_taken'];

        if ($dateA == $dateB) {
            return 0;
        }

        return $dateA > $dateB ? -1 : 1;
    }

    function getLatestFiles($limit = 20)
    {
        $sortedIndex = $this->index;

        uasort($sortedIndex, [$this, 'sortByDateTaken']);

        $sliced = array_slice($sortedIndex, 0, $limit);

        $return = [];

        foreach ($sliced as $key => $image) {
            $return[] = new Image($key);
        }

        return $return;

    }
}