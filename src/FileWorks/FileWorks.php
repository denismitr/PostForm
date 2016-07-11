<?php

namespace Denismitr\PostForm\FileWorks;

use Denismitr\PostForm\FileWorks\FileWorksException;
use Denismitr\PostForm\FileWorks\FileWorksInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileWorks
{
    protected $model;
    protected $filesInfo = [];


    public function __construct(FileWorksInterface $model)
    {
        $this->model = $model;
        $this->filesInfo = $this->model->getFilesInfo();
    }


    public function __get($fieldName)
    {
        return $this->getFullFilePath($fieldName);
    }


    //Get the uploadDir for the corresponding FieldName
    protected function getUploadDir($fieldName)
    {
        if (array_key_exists($fieldName, $this->filesInfo)) {
            return $this->filesInfo[$fieldName];
        }

        throw new FileWorksException("No upload path for fieldName: " . $fieldName);
    }


    //Combine uploadDir and the filename and return them
    protected function getFullFilePath($fieldName) {
        return $this->getUploadDir($fieldName) . '/' . $this->model->{$fieldName};
    }


    /**
     * @param $fieldName
     * @param UploadedFile $file
     * @return string
     */
    public function create($fieldName, UploadedFile $file)
    {
        //Assign the newly generated filename to the class instance
        $this->model->{$fieldName} = $newFileName = $this->saveAs($fieldName, $file);

        return $newFileName;
    }


    protected function saveAs($fieldName, UploadedFile $file)
    {
        // Get the original extension of the file
        $ext = $file->getClientOriginalExtension();

        if (empty($ext)) {
            throw new FileWorksException("File extension cannot be empty!");
        }

        //Get upload dir for corresponding FieldName
        $uploadDir = $this->getUploadDir($fieldName);

        if (empty($uploadDir)) {
            throw new FileWorksException("Upload dir cannot be empty!");
        }

        //Generate a new name
        $newFileName = rand(100, 999) . '-' . uniqid() . '.' . strtolower($ext);

        //Move file with the new name to the upload directory
        if ( ! $file->move($uploadDir, $newFileName) ) {
            throw new FileWorksException("Something went wrong during the move of the file " . $file->path());
        }

        //We check if the new file is readable
        if (!is_readable($uploadDir . '/' . $newFileName)) {
            throw new FileWorksException($file->path() . " has not been successfully saved to server.");
        }

        return $newFileName;
    }



    public function update($fieldName, UploadedFile $file)
    {
        //Delete the existing file
        $this->delete($fieldName);

        return $this->create($fieldName, $file);
    }


    //Delete file from disk
    public function delete($fieldName)
    {
        $fullFilePath = $this->getFullFilePath($fieldName);

        if (file_exists($fullFilePath) && is_writable($fullFilePath)) {
            @unlink($fullFilePath);

            //Check if the file has been actually deleted
            if (is_readable($fullFilePath)) {
                throw new FileWorksException($fullFilePath . " was not really deleted, despite an attempt.");
            }

            return;
        }

        throw new FileWorksException($fullFilePath . " was not detected and hence not deleted.");
    }


    //Delete all files
    public function deleteAll()
    {
        foreach($this->filesInfo as $fileFieldName => $uploadDir) {
            $this->delete($fileFieldName);
        }
    }
}
