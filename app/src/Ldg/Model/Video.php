<?php

namespace Ldg\Model;


class Video extends File
{
    public $playable = true;

    public function getPlayUrl()
    {
        return BASE_URL_LDG . '/video_stream' . $this->getRelativeLocation();
    }
}