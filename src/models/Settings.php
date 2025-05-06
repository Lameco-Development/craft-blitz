<?php

namespace lameco\blitz\models;

use craft\base\Model;

class Settings extends Model
{
    public string $globalQueryStringParams = '';

    public array $sectionQueryStringParams = [];
}
