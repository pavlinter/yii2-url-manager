<?php

/**
 * @copyright Copyright &copy; Pavels Radajevs, 2015
 * @package yii2-url-manager
 * @version 1.2.1
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
        $route = static::currentArray($params, $strict);
        return static::toRoute($route, $scheme);
    }

    /**
     * @param array $params
     * @param bool $strict only through "?"
     * @return array
     */
    public static function currentArray(array $params = [], $strict = false)
    {
        $currentParams = Yii::$app->getRequest()->getQueryParams();
        if (isset($params[0])) {
            $currentParams[0] = ArrayHelper::remove($params, 0);
        } else {
            $currentParams[0] = '/' . Yii::$app->controller->getRoute();
        }

        if($strict){
            $params = ArrayHelper::merge($currentParams, $params);
            $route[0] = ArrayHelper::remove($params, 0);
            if(Yii::$app->urlManager instanceof UrlManager){
                ArrayHelper::remove($params, Yii::$app->urlManager->langParam);
            }
            $route['?'] = $params;
        } else {
            $route = ArrayHelper::merge($currentParams, $params);
        }

        return $route;
    }

    /**
     * @param string $params
     * @param bool $scheme
     * @return string
     */
    public static function getLangUrl($params = '', $scheme = false)
    {
        $langBegin = Yii::$app->getUrlManager()->langBegin;
        if (isset($langBegin['0']) && $langBegin['0'] === Yii::$app->language) {
            return Url::to('@web/' . $params, $scheme);
        } else {
            return Url::to('@web/' . Yii::$app->language . '/' . $params, $scheme);
        }
    }
}
