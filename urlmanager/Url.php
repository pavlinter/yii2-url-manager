<?php

/**
 * @copyright Copyright &copy; Pavels Radajevs, 2015
 * @package yii2-url-manager
 * @version 1.0.2
 */

namespace pavlinter\urlmanager;

use Yii;
use yii\helpers\ArrayHelper;

/**
 * Url provides a set of static methods for managing URLs.
 */
class Url extends \yii\helpers\BaseUrl
{
    /**
     * @param array $params
     * @param bool $scheme
     * @param bool $strict only through "?"
     * @return string
     */
    public static function current(array $params = [], $scheme = false, $strict = false)
    {
        $currentParams = Yii::$app->getRequest()->getQueryParams();
        if (isset($params[0])) {
            $currentParams[0] = static::normalizeRoute($params[0]);
            unset($params[0]);
        } else {
            $currentParams[0] = '/' . Yii::$app->controller->getRoute();
        }

        if($strict){
            $params = ArrayHelper::merge($currentParams, $params);
            $route['0'] = ArrayHelper::remove($params, '0');
            if(Yii::$app->urlManager instanceof UrlManager){
                ArrayHelper::remove($params, Yii::$app->urlManager->langParam);
            }

            $route['?'] = $params;
        } else {
            $route = ArrayHelper::merge($currentParams, $params);
        }

        return static::toRoute($route, $scheme);
    }
}
