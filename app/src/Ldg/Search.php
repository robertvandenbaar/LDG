<?php

namespace Ldg;

use Ldg\Model\Image;

class Search
{
    protected $index = null;
    protected $indexFile = null;

    public $filters = [
        'camera' => 'model',
        'lens' => 'lens',
        'year' => 'date_taken',
        'month' => 'date_taken',
        'day' => 'date_taken',
    ];

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

    function setEntry($key, $value, $type, $metadata = [])
    {
        $data = array_merge(['search_data' => $value, 'type' => $type, 'metadata' => $metadata]);

        $this->index[$key] = $data;
    }

    public function hasFilter()
    {
        $filters = array_keys($this->filters);
        $filters[] = 'limit_to_keyword_search';

        foreach ($filters as $filter) {
            if (isset($_REQUEST[$filter]) && strlen($_REQUEST[$filter]) > 0) {
                return true;
            }
        }
        return false;
    }

    function matchesMetadataFilter($value, $requestParameter, $metadataProperty)
    {
        if (!isset($_REQUEST[$requestParameter]) || strlen($_REQUEST[$requestParameter]) == 0) {
            return true;
        }

        if (!isset($value['metadata']) || !isset($value['metadata'][$metadataProperty])) {
            return false;
        }

        if (in_array($requestParameter, ['year', 'month', 'day'])) {

            switch ($requestParameter) {
                case 'year':
                    return date('Y', $value['metadata'][$metadataProperty]) == $_REQUEST[$requestParameter];
                    break;
                case 'month':
                    return date('m', $value['metadata'][$metadataProperty]) == $_REQUEST[$requestParameter];
                    break;
                case 'day':
                    return date('d', $value['metadata'][$metadataProperty]) == $_REQUEST[$requestParameter];
                    break;
                default:
                    return false;
                    break;
            }
        }

        if ($value['metadata'][$metadataProperty] == $_REQUEST[$requestParameter]) {
            return true;
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

            foreach ($this->filters as $requestParam => $filter) {
                if (!$this->matchesMetadataFilter($value, $requestParam, $filter)) {
                    continue 2;
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

    function getLatestImages($limit = 20)
    {
        $sortedIndex = $this->index;

        $sortedIndex = array_filter($sortedIndex, function($element) {
            if (isset($element['type'])) {
                return $element['type'] == Image::class;
            }
            return true;
        });

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
                $make = isset($item['metadata']['make']) ? $item['metadata']['make'] : '';
                $model = isset($item['metadata']['model']) ? $item['metadata']['model'] : '';

                if (strlen($make) > 0 && strlen($model) > 0) {

                    // if make not in model name, add it to the camName;
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

    public function getUniqueLenses()
    {
        $lenses = [];
        foreach ($this->index as $item) {
            if ($item['metadata']) {
                if (isset($item['metadata']['lens'])) {
                    $lens = $item['metadata']['lens'];
                    $lens = trim($lens);
                    $lens = trim($lens, '-');
                    if (strlen($lens) > 0) {
                        $lenses[$lens] = $lens;
                    }
                }
            }
        }

        asort($lenses);
        return $lenses;
    }
}