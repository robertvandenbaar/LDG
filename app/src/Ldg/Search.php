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

    public function hasFilter()
    {
        $filters = ['camera', 'limit_to_keyword_search'];

        foreach ($filters as $filter) {
            if (isset($_REQUEST[$filter]) && strlen($_REQUEST[$filter]) > 0) {
                return true;
            }
        }
        return false;
    }

    function search($q)
    {
        $results = [];

        $words = explode(' ', trim($q));
        $words = array_filter($words,'strlen');

        $sortedIndex = $this->index;

        uasort($sortedIndex, [$this, 'sortByDateTaken']);

        foreach ($sortedIndex as $key => $value) {

            if (is_string($value)) {
                $searchValue = $value;
            } else {
                $searchValue = $value['search_data'];
            }

            if (!isset($_REQUEST['limit_to_keyword_search'])) {
                $searchValue .= $key;
            }

            if (isset($_REQUEST['camera']) && strlen($_REQUEST['camera']) > 0) {
                if (!isset($value['metadata']) || !isset($value['metadata']['model'])) {
                    continue;
                }

                if ($value['metadata']['model'] != $_REQUEST['camera']) {
                    continue;
                }
            }

            if (!empty($words)) {
                foreach ($words as $word) {
                    if (stripos($searchValue, $word) === false && stripos($this->replaceDiacritics($searchValue), $word) === false) {
                        continue 2;
                    }
                }
            }

            $results[$key] = $searchValue;

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

    public function getUniqueCameras()
    {
        $cams = [];

        foreach ($this->index as $item) {
            if ($item['metadata']) {
                $cam = '';
                $make = isset($item['metadata']['make']) ? $item['metadata']['make'] : '';
                $model = isset($item['metadata']['model']) ? $item['metadata']['model'] : '';

                if (strlen($make) > 0 && strlen($model) > 0) {

                    // if make not in model name, add it to the canName;
                    if (strpos($model, $make) === false) {
                        $camName = $make . ' ' . $model;
                    } else {
                        $camName = $model;
                    }

                    if (strlen($camName) > 0) {
                        $cams[$model] = $camName;
                    }
                }
            }
        }

        asort($cams);

        return $cams;

    }
}