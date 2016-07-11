<?php

namespace Denismitr\PostForm;


use Http\Requests\Request;
use Illuminate\Database\Eloquent\Model;
use Denismitr\PostForm\PostFormException;
use Denismitr\FileWorks\FileWorksInterface;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Denismitr\PostForm\Contracts\HasChildModelsInterface;

abstract class PostForm
{
    use ValidatesRequests;

    protected $request;
    protected $modelClassName;
    protected $modelInstance = null;
    protected $fileFieldNames = [];

    protected $parentModelInstance = null;
    protected $parentModelName;
    protected $parentModelFieldName;

    protected $rules = [];
    protected $messages = [];

    //Uploaded file objects
    protected $files = [];


    public function __construct()
    {
        //$this->request = $request ?: request(); //TODO

        $this->setModelClassName();

        $this->request = request();
        //dd($this->request);
    }


    /**
     * Get all fields in the request
     *
     * @return array
     */
    public function fields()
    {
        return $this->request->all();
    }


    /**
     * Set a new file field name
     *
     * @param $fieldName
     * @return $this
     */
    public function withFile($fieldName)
    {
        $this->fileFieldNames[] = $fieldName;

        return $this;
    }


    /**
     * Set fileFields Names
     *
     * @param array $fieldNames
     * @return $this
     */
    public function withFiles(array $fieldNames)
    {
        $this->fileFieldNames = $fieldNames;

        return $this;
    }


    /**
     * Set a model object (required for updates only)
     *
     * @param $model
     * @return $this
     */
    public function model(Model $model)
    {
        $this->modelInstance = $model;

        //If model exists that means that we do the update
        //And therefore we set rules to update rules
        $this->rules = $this->getUpdateRules();

        return $this;
    }


    /**
     * Explicitly set a belongsTo relationship (optional)
     *
     * @param $modelName
     * @param null $fieldName
     * @return $this
     * @throws PostFormException
     */
    public function parentModel($modelName, $fieldName = null)
    {
        //Check if model is given as a first parameter
        //Assign it to parentModel prop and return
        if (is_object($modelName) && is_null($fieldName))
        {
            if ( ! $modelName instanceof HasChildModelsInterface)
            {
                throw new PostFormException("Parent class must implement HasChildModelsInterface");
            }

            $this->parentModelInstance = $modelName;

            return $this;
        }

        $this->parentModelName = $modelName;
        $this->parentModelFieldName = $fieldName;

        return $this;
    }


    protected abstract function getUpdateRules();
    protected abstract function getCreateRules();
    protected abstract function getMessages();
    protected abstract function setModelClassName();


    //Update or create the data in the DB and/or file uploads
    public function persist()
    {
        //If validation passes
        if ($this->isValid())
        {
            $this->checkForFileUploads();
            $this->checkParentModel();

            if (is_null($this->modelInstance))
            {
                //If Model instance does not yet exists
                return $this->createFromRequest();
            }

            //If Model instance already exists
            return $this->updateExistingModel();
        }

        return false;
    }


    /**
     * Create a new model from the modelClassName field
     *
     * @throws PostFormException
     */
    protected function createModelInstance()
    {
        if ( ! class_exists($this->modelClassName)) {
            throw new PostFormException("No such class: " . $this->modelClassName);
        }

        $this->modelInstance = new $this->modelClassName();
    }


    // Validation
    protected function isValid()
    {
        foreach ($this->fileFieldNames as $fileFieldName) {
            //Check if the file size exceeds that configured in php.ini
            if ($_FILES[$fileFieldName]["error"] === 1) {
                throw new PostFormException("Размер файла превышает максимально разрешенной СЕРВЕРОМ!");
            }
        }

        //Check if rules are already set to updateRules
        //by the model function
        //If not set them to createRules
        $this->rules = $this->rules ?: $this->getCreateRules();

        //set the messages (optional array)
        $this->messages = $this->getMessages();

        //Do the validate
        $this->validate($this->request, $this->rules, $this->messages);

        return true;
    }


    /**
     * Check if the parent model is already set
     * Otherwise check if it needs to be created
     * From a name and a field name passed to the parentModel method
     *
     * @throws PostFormException
     */
    protected function checkParentModel()
    {
        //Check if Parent Model is already defined
        //And if so exit the function doing nothing
        if (is_object($this->parentModelInstance))
        {
            return;
        }

        //Check if Parent Model name is a valid class name
        //Check if Request data has a key and a value
        //For a given 'parentModelFieldName' e.g. (user_id or post_id)
        if (class_exists($this->parentModelName)
            && $this->request->has($this->parentModelFieldName))
        {
            //Check if the parent model implements that interface
            if ( ! (in_array('App\HasChildModelsInterface', class_implements($this->parentModelName))) )
            {
                throw new PostFormException("Parent model does not implement HasChildModelsInterface");
            }

            $model = $this->parentModelName;

            $this->parentModelInstance = $model::findOrFail($this->request->input($this->parentModelFieldName));
        }
    }

    /**
     * Check if there are files in the request object
     *
     * @return bool
     * @throws PostFormException
     */
    protected function checkForFileUploads()
    {
        foreach($this->fileFieldNames as $fileFieldName) {
            if ($this->request->hasFile($fileFieldName)) {

                //Check if the file ha been uploaded correctly
                if (!$this->request->file($fileFieldName)->isValid()) {
                    throw new PostFormException($fileFieldName . " file has been uploaded with some errors");
                }

                //Add uploaded file object into an array
                $this->files[$fileFieldName] = $this->request->file($fileFieldName);
            }
        }

        //If some files have been uploaded
        if (count($this->files) > 0) {
            return true;
        }

        return false;
    }


    protected function processNewFilesToABrandNewModel()
    {
        foreach ($this->files as $fileName => $fileObject) {
            if (! $this->modelInstance instanceof FileWorksInterface) {
                throw new PostFormException("Model Instance must implement FileWorksInterface");
            }
            $this->modelInstance->files()->create($fileName, $fileObject);
        }
    }


    //Create a whole new model instance and persist it
    protected function createFromRequest()
    {
        //Create a new model
        $this->createModelInstance();

        //Check for file uploads
        if ($this->checkForFileUploads()) {
            $this->processNewFilesToABrandNewModel();
        }

        //Fill a new model with data and persist it
        $this->saveNewModel();

        return true;
    }


    //Proceed with saving a new model
    protected function saveNewModel()
    {
        //Fill the model with new data
        $this->modelInstance->fill($this->fields());

        //Check if a model belong to another model
        if ($this->parentModelInstance) {
            //Persist the model through it's owner model
            $result = $this->parentModelInstance->addChildModel($this->modelInstance);
        } else {
            //Simple persist
            $result = $this->modelInstance->save();
        }

        if ( ! $result)
        {
            throw new PostFormException("Could not save the record to DB!");
        }
    }



    /**
     * Update an existing model
     *
     * @throws PostFormException
     * return boolean
     */
    protected function updateExistingModel()
    {
        //Fill the model with new data
        $this->modelInstance->fill($this->fields());

        //Check if there are file to update
        if (count($this->files) > 0) {

            foreach($this->files as $fileName => $fileObject) {
                if (! $this->modelInstance instanceof FileWorksInterface) {
                    throw new PostFormException("Model Instance must implement FileWorksInterface");
                }
                $this->modelInstance->files()->update($fileName, $fileObject);
            }

        }

        if ( ! $this->modelInstance->save())
        {
            throw new PostFormException("Could not update the model in the DB!");
        }

        return true;
    }
}
