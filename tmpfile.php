<?php

namespace Denisyuk;

final class tmpfile
{
    public $filename;

    public function __construct()
    {
        $this->filename = $this->create();

        register_shutdown_function([$this, 'delete']);
    }

    private function create()
    {
        $filename = tempnam(sys_get_temp_dir(), 'php');

        if (!$filename) {
            throw new \Error('The function tempnam() could not create a file in temporary directory.');
        }

        return $filename;
    }

    public function delete()
    {
        return @unlink($this->filename);
    }

    public function __toString()
    {
        return $this->filename;
    }
}