<?php

namespace App;

use Denismitr\PostForm\PostForm;

class ExamplePostForm extends PostForm
{

    protected function setModelClassName()
    {
        $this->modelClassName = SomeEloquentModel::class;
    }

    protected function getMessages()
    {
        return [
            'path.required' => "Необходимо загрузить картинку",
            'path.mimes' => "Картинка должна быть в формате jpg или png",
            'path.max' => "Размер картинки не должен превышать 300Кб",
            'name.required' => "Название рубрики обязательно",
            'name_en.required' => "Название рубрики обязательно",
            'name_en.unique' => "Название работы должно быть уникальным",
            'rubric_id.required' => "Выбрать рубрику нужно обязательно",
            'rubric_id.numeric' => "Рубрика должна быть выбрана из списка",
            'rubric_id.exists' => "Рубрика должна быть выбрана из списка"
        ];
    }

    //Rules for updating
    protected function getUpdateRules()
    {
        return [
            'rubric_id' => 'required|numeric|exists:rubrics,id',
            'path' => 'sometimes|image|mimes:jpg,jpeg,png,gif|max:400',
            'name' => 'required',
            'name_en' => 'required',
        ];
    }

    //Rules for creating
    protected function getCreateRules()
    {
        return [
            'rubric_id' => 'required|numeric|exists:rubrics,id',
            'path' => 'required|image|mimes:jpg,jpeg,png,gif|max:400',
            'name' => 'required|unique:paintings,name',
            'name_en' => 'required',
        ];
    }
}
