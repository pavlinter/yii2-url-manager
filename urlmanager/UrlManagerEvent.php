<?php

/**
 * @copyright Copyright &copy; Pavels Radajevs, 2015
 * @package yii2-url-manager
 * @version 1.2.1
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
     * For UrlManager::EVENT_INIT event
     * @var string
     */
    public $pathInfo;

    /**
     * For UrlManager::EVENT_BEFORE_CONTROLLER event
     * @var string
     */
    public $router;

    /**
     * For UrlManager::EVENT_BEFORE_CONTROLLER event
     * @var array
     */
    public $params;
}
