<?php

namespace Denismitr\PostForm\FileWorks;

interface FileWorksInterface
{
    //Get the array of fileInfo on the model
    //that contains fieldName as keys
    //and uploadPath as value
    public function getFilesInfo();

    //Get the FileWorks instance
    //If does not exist
    //Create and assign it to $this->files
    public function files();
}
