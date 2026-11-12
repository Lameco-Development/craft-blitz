<?php

namespace lameco\blitz\models;

use craft\base\Model;

class Settings extends Model
{
    /**
     * Comma-separated query string parameters that make any page uncacheable when present.
     */
    public string $globalQueryStringParams = '';

    /**
     * @var array<array-key, array{section?: string, queryStringParams?: string}> Rows of a
     * "sectionHandle:entryTypeHandle" and its comma-separated query string parameters.
     */
    public array $sectionQueryStringParams = [];
}
