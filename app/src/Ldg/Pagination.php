<?php

namespace Ldg;

class Pagination
{
    public $currentPage;
    public $totalItems;
    public $totalPages;
    public $lastPage;
    public $baseUrl;
    public $itemsPerPage;

    public function getNumberOfPages()
    {
        if ($this->itemsPerPage > 0)
        {
            return ceil($this->totalItems / $this->itemsPerPage);
        }

        return 0;
    }

}