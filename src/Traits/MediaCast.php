<?php
/**
 * HasMediaToUrl v1.0.0 (https://github.com/Anrail/NovaMediaLibraryTools)
 * Copyright 2021 Anrail (https://github.com/Anrail/NovaMediaLibraryTools/graphs/contributors)
 * Licensed under MIT (https://github.com/Anrail/NovaMediaLibraryTools/blob/main/LICENSE)
 */

namespace App\Casts;


use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

trait HasMediaToUrl
{
    /**
     * Set 'public $mediaToUrl = [];' in your model, specify the names of the variables for column where MediaLibrary field in your model, also the names of the keys of the nested attributes in json
     *
     * @param array
     *
     */

    /**
     * Get all attributes of your model
     *
     * @param array
     * @return mixed
     */

    public function getAllWithMediaUrl()
    {
        $result = [];
        foreach ($this->attributes as $key => $attribute)
        {
            $result[$key]= $this->getAttributeValue($key);
        }
        return $result;
    }

    /**
     * Override "getAttributeValue"
     *
     * @param string $key
     * @return mixed
     *
     */

    public function getAttributeValueWithMediaUrl($key)
    {
        // check, is HasTranslationsTrait
        if ($this->isHasTranslationsTrait())
        {
            if (! $this->isTranslatableAttribute($key)) {
                // if is not translatable attribute
                // check, is "media attribute to url"
                if (! $this->isMediaAttributeToUrl($key))
                {
                    // if is not "media attribute to url" return parent value
                    return parent::getAttributeValue($key);
                }
                // check, is json, return array
                $attributeValueArray = $this->isJsonToArray($key);
                // check, array is empty and if is a "Nova Flexible field"
                if ($attributeValueArray !== [] && $this->isFlexibleField($attributeValueArray))
                {
                    return $this->getMediaUrlFlexible($attributeValueArray);
                }
                return $this->getMediaUrl($key);
            }
            // if is "translatable attribute" get translation
            $translatedAttributeValue = $this->getTranslation($key, $this->getLocale());
            // if is not "media attribute to url" return attribute with translation
            if (! $this->isMediaAttributeToUrl($key))
            {
                return $translatedAttributeValue;
            }
            // if is media attribute to url check is json, return array
            $attributeValueArray = $this->isJsonToArray($key,$translatedAttributeValue);

            if ($attributeValueArray !== [] && $this->isFlexibleField($attributeValueArray))
            {

                return $this->getMediaUrlFlexible($attributeValueArray);

            }
            return $this->getMediaUrl($key);
        }

        if (! $this->isMediaAttributeToUrl($key))
        {
            // if is not "media attribute to url" return parent value
            return parent::getAttributeValue($key);
        }
        // check, is json, return array
        $attributeValueArray = $this->isJsonToArray($key);
        // check, array is empty and if is a "Nova Flexible field"
        if ($attributeValueArray !== [] && $this->isFlexibleField($attributeValueArray))
        {
            return $this->getMediaUrlFlexible($attributeValueArray);
        }
        return $this->getMediaUrl($key);

    }
    /**
     *
     * @return bool
     */
    private function isHasTranslationsTrait()
    {
        if (method_exists($this, 'isTranslatableAttribute') && method_exists($this, 'getTranslation') && method_exists($this, 'getLocale')) {
            return true;
        }
        return false;
    }


    /**
     * @param array $attributeValueArray
     * @return bool
     */
    public function isFlexibleField($attributeValueArray)
    {

        if(is_array($attributeValueArray) && isset($attributeValueArray[0]) && is_array($attributeValueArray[0]) && array_key_exists( 'key', $attributeValueArray[0]) && array_key_exists( 'layout', $attributeValueArray[0]) && array_key_exists( 'attributes', $attributeValueArray[0]) )
        {
            return true;
        }
        return false;
    }

    /**
     *  if ($key == Json) this return json_decode(AttributeValue) else return []
     *
     * @param string $key
     * @param mixed $value
     * @return array
     */
    private function isJsonToArray($key,$value = null)
    {
        if( $value && is_array($value) )
        {
            return $value;
        }
        elseif($value)
        {
            return $this->attributeValueArrayDecode($value);
        }
        if ($this->isHasTranslationsTrait() && $this->isTranslatableAttribute($key))
        {
            return $this->attributeValueArrayDecode($this->getTranslation($key, $this->getLocale()));
        }
        return $this->attributeValueArrayDecode(parent::getAttributeValue($key));
    }

    /**
     * @param mixed $value
     * @return array
     */
    private function attributeValueArrayDecode($value)
    {
        $attributeValueArray = json_decode($value,true);
        return json_last_error() == JSON_ERROR_NONE ? $attributeValueArray : [];
    }


    /**
     * For each Nova Flexible-field in array, search attribute key and check it isMediaAttributeToUrl(), if find, use getMedia() return $attributeValueArray with change attribute media "id" to "path/media-name"
     *
     * @param array $attributeValueArray
     * @return array
     *
     */
    public function getMediaUrlFlexible ($attributeValueArray)
    {
        foreach ( $attributeValueArray as $keyFlexible => $flexible )
        {
            if ( array_key_exists( 'attributes', $flexible) )
            {
                foreach ($attributeValueArray[$keyFlexible]['attributes'] as $keyAttributes => $attribute)
                {
                    if ($this->isFlexibleField($attribute)){
                        $attributeValueArray[$keyFlexible]['attributes'][$keyAttributes] = $this->getMediaUrlFlexible($attribute);
                    }elseif ($this->isMediaAttributeToUrl($keyAttributes)){
                        $attributeValueArray[$keyFlexible]['attributes'][$keyAttributes] = $this->getMedia($attribute);
                    }else{
                        $attributeValueArray[$keyFlexible]['attributes'][$keyAttributes] = $attribute;
                    }
                }
            }
            else
            {
                $attributeValueArray[$keyFlexible] = $flexible;
            }
        }
        return $attributeValueArray;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function isMediaAttributeToUrl( $key)
    {
        return in_array($key, $this->getMediaAttributesToUrl());
    }

    /**
     * @return array
     */
    public function getMediaAttributesToUrl()
    {
        return is_array($this->mediaToUrl)
            ? $this->mediaToUrl
            : [];
    }
    /**
     * @param string $key
     * @return array = [0 => url, 1 => url, 2 => url]
     * @return string = "url"
     */
    public function getMediaUrl($key)
    {
        return $this->getMedia(parent::getAttributeValue($key));
    }

    /**
     * Get array id, if error return '/storage/No_image_available.svg'.
     *
     *
     * @param  $id = [0,1,2]
     * @return array = [0 => url, 1 => url, 2 => url]
     *
     */
    protected function getManyMedia($id)
    {
        $result = [];
        if(!is_array($id) && substr($id, 0, 1) === '[')
        {
            $id = json_decode($id);
        }
        $media = DB::table('nova_media_library')->whereIn('id', $id)->pluck('name', 'id');
        if ($media !== null)
        {
            foreach ($media as $oneKey => $oneValue)
            {
                $resultTemp[$oneKey] = '/storage/' . $oneValue;
            }
            foreach ($id as $oneKey => $oneValue)
            {
                if (isset($media[$oneValue]) && $media[$oneValue] != null)
                {
                    $result[$oneKey] = '/storage/' . $media[$oneValue];
                } else {
                    $result[$oneKey] = '/storage/No_image_available.svg';
                }
            }
        }

        return $result;
    }
    /**
     * Get one id, if error return '/storage/No_image_available.svg'.
     *
     * @param  $id
     * @return string
     *
     */
    protected function getOneMedia($id)
    {
        $media = DB::table('nova_media_library')->where('id', $id)->value('name');
        if ($media === null)
        {
            return '/storage/No_image_available.svg';
        }
        return '/storage/' . $media;
    }

    /**
     * Get one id, if error return '/storage/No_image_available.svg'.
     *
     * @param  $id
     * @return string & array
     *
     */

    public function getMedia($id)
    {

        if($id != null)
        {
            if(is_array($id) || substr($id, 0, 1) === '[')
            {
                return $this->getManyMedia($id);
            }
            return $this->getOneMedia($id);
        }
        return null;
    }

    /**
     * Translate model by current Locale
     *
     * @param object $model
     * @param array $without = ['created_at','updated_at']
     * @return array
     */
    protected function translateModel($model,$without = [])
    {
        if(!$this->isHasTranslationsTrait())
        {
            return $model->getAttributes();
        }
        foreach ($model->getAttributes() as $key => $field)
        {
            if(!$model->isTranslatableAttribute($key) && in_array($key,$without))
            {
                $attributes[$key] = $field;
            }
        }
        foreach ($model->getTranslatableAttributes() as $field)
        {
            $attributes[$field] = $model->getTranslation($field, App::currentLocale());
        }
        return $attributes;
    }

    /**
     *
     * Translate model by current Locale without created_at, updated_at.
     *
     * @param  $model
     * @return array
     *
     */
    protected function translateModelWithoutTime($model)
    {
        return $this->translateModel($model,['created_at','updated_at']);
    }

    /**
     *
     * Translate model by current Locale without id, created_at, updated_at.
     *
     * @param  $model
     * @return array
     *
     */
    protected function translateModelWithoutIdAndTime($model)
    {
        return $this->translateModel($model,['created_at','updated_at','id']);
    }

}

