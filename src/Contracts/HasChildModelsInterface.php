<?php


namespace Denismitr\PostForm\Contracts;

use Illuminate\Database\Eloquent\Model;

interface HasChildModelsInterface
{
    public function addChildModel(Model $model);
}