<?php

namespace Denismitr\PostForm\FileWorks;


trait FileWorkable
{
    protected $files = null;

    public function files()
    {
        if (is_null($this->files)) {
            $this->files = new FileWorks($this);
        }

        return $this->files;
    }


    public function getFilesInfo()
    {
        if ( ! property_exists($this, 'filesInfo')) {
            throw new FileWorksException("Required property of filesInfo does not exist on the model " .
                get_class($this));
        }

        if (! is_array($this->filesInfo)) {
            throw new FileWorksException("Property of filesInfo has to be an array!");
        }

        foreach($this->filesInfo as $fieldName => $uploadPath) {
            if (in_array($fieldName, $this->fillable)) {
                throw new FileWorksException(
                    "Fields for file upload must never be present in Fillable array of a model"
                );
            }
        }

        return $this->filesInfo;
    }
}