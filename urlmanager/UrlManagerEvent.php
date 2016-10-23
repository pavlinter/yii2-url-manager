<?php

/**
 * @copyright Copyright &copy; Pavels Radajevs, 2015
 * @package yii2-url-manager
 * @version 1.2.0
 */

namespace pavlinter\urlmanager;
use yii\base\Event;

/**
 *
 * @author Pavels Radajevs <pavlinter@gmail.com>
 */
class UrlManagerEvent extends Event
{
    /**
     * @var string
     */
    public $pathInfo;
}
