<?php

require dirname(__DIR__) . '/vendor/autoload.php';

// Yii objects (events, models) configure themselves through the Yii class, which Composer does not autoload.
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';

// Craft's global Craft class (Craft::warning() and friends) is loaded by Craft's own bootstrap, not by Composer.
require dirname(__DIR__) . '/vendor/craftcms/cms/src/Craft.php';
