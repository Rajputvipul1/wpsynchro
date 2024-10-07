<?php

namespace WPSynchro\Files;

/**
 * Handle path data for FileSystem API
 * @since 1.2.0
 */
class PathData
{
    public $pathkey = "";
    public $absolutepath = "";
    public $basename = "";
    public $is_file = false;
    public $dirname = "";
    public $dir_has_content = false;
    public $children = [];
    public $locked = false;
    public $is_expanded = false;

    public function __construct()
    {
        $this->pathkey = uniqid();
    }
}
