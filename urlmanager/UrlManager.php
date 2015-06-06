<?php

/**
 * @copyright Copyright &copy; Pavels Radajevs, 2014
 * @package yii2-url-manager
 * @version 1.0.2
 */

namespace pavlinter\urlmanager;

use Yii;
use yii\helpers\ArrayHelper;

/**
 *
 * @author Pavels Radajevs <pavlinter@gmail.com>
 */
class UrlManager extends \yii\web\UrlManager
{
    public $enableLang = false;
    public $langParam  = 'lang';
    public $onlyFriendlyParams = false;
    public $gets = [];
    public $langBegin = []; //['en', 'fr']  if empty array request http://domain.com/en show Not Found
	/**
	 * Initializes UrlManager.
	 */
	public function init()
	{
        parent::init();
        if (Yii::$app->request->getIsConsoleRequest()) {
            return true;
        }
        $request  = Yii::$app->getRequest();
        $pathInfo = rtrim($request->getPathInfo(), '/');
        if ($this->enableLang) {

            if ($pathInfo != '') {
                if (strpos($pathInfo, '/') !== false) {

                    $segments = explode('/', $pathInfo);
                    $_GET[$this->langParam] = $segments['0'];
                    unset($segments['0']);
                    $pathInfo = join('/', $segments);
                } else if(in_array($pathInfo, $this->langBegin)) {
                    $_GET[$this->langParam] = $pathInfo;
                    $pathInfo = '';
                }

            }
		}
        if (isset($_GET)) {
            foreach ($_GET as $k => $v) {
                $this->gets[$k] = $v;
            }
        }
        $request->setPathInfo($pathInfo);
	}

    /**
     * Parses the user request.
     * @param Request $request the request component
     * @return array|boolean the route and the associated parameters. The latter is always empty
     * if [[enablePrettyUrl]] is false. False is returned if the current request cannot be successfully parsed.
     */
    public function parseRequest($request)
    {
        if ($this->enablePrettyUrl) {
            $pathInfo = $request->getPathInfo();
            /* @var $rule UrlRule */
            foreach ($this->rules as $rule) {
                if (($result = $rule->parseRequest($this, $request)) !== false) {
                    return $result;
                }
            }

            if ($this->enableStrictParsing) {
                return false;
            }

            Yii::trace('No matching URL rules. Using default URL parsing logic.', __METHOD__);

            $suffix = (string) $this->suffix;
            if ($suffix !== '' && $pathInfo !== '') {
                $n = strlen($this->suffix);
                if (substr_compare($pathInfo, $this->suffix, -$n, $n) === 0) {
                    $pathInfo = substr($pathInfo, 0, -$n);
                    if ($pathInfo === '') {
                        // suffix alone is not allowed
                        return false;
                    }
                } else {
                    // suffix doesn't match
                    return false;
                }
            }

            if ($pathInfo) {
                return $this->parseUrl($pathInfo);
            }
            return [$pathInfo, []];
        } else {
            Yii::trace('Pretty URL not enabled. Using default URL parsing logic.', __METHOD__);
            $route = $request->getQueryParam($this->routeParam, '');
            if (is_array($route)) {
                $route = '';
            }

            return [(string) $route, []];
        }
    }
    public function parseUrl($pathInfo)
    {
        $params = [];
        if (strpos($pathInfo, '/') !== false) {
            $segments = explode('/', $pathInfo);
            $n = count($segments);
            $modules = Yii::$app->getModules();

            $pathInfoStart = 2;
            foreach ($segments as $segment) {
                if (isset($modules[$segment])) {
                    $pathInfoStart++;
                    if (is_object($modules[$segment])) {
                        $modules = $modules[$segment]->getModules();
                    } else if (isset($modules[$segment]['modules'])) {
                        $modules = $modules[$segment]['modules'];
                    } else {
                        $modules = [];
                    }
                } else {
                    break;
                }
            }
            if ( $n > $pathInfoStart){
                $pathInfo = implode('/', array_slice($segments,0, $pathInfoStart));
                $segments = array_slice($segments,$pathInfoStart);
                $params = $this->createPrettyUrl($segments);
            }
        }
        return [$pathInfo, $params];
    }
    public function createUrl($params)
    {

        $params = (array) $params;

        $anchor = isset($params['#']) ? '#' . $params['#'] : '';
        unset($params['#'], $params[$this->routeParam]);

        $route = trim($params[0], '/');
        unset($params[0]);

        $standartParams = ArrayHelper::remove($params, '?', []);

        if ($this->onlyFriendlyParams && isset($params['_pjax'])) {
            unset($params['_pjax']);
        }

        $baseUrl = $this->showScriptName || !$this->enablePrettyUrl ? $this->getScriptUrl() : $this->getBaseUrl();

        if ($this->enablePrettyUrl) {
            /* @var $rule UrlRule */
            foreach ($this->rules as $rule) {
                if (($url = $rule->createUrl($this, $route, $params)) !== false) {
                    if (strpos($url, '://') !== false) {
                        if ($baseUrl !== '' && ($pos = strpos($url, '/', 8)) !== false) {
                            return substr($url, 0, $pos) . $baseUrl . substr($url, $pos);
                        } else {
                            return $url . $baseUrl . $anchor;
                        }
                    } else {
                        return "$baseUrl/{$url}{$anchor}";
                    }
                }
            }

            if ($this->enableLang) {
                if (isset($params[$this->langParam])) {
                    $route = $params[$this->langParam].'/'.$route;
                    unset($params[$this->langParam]);
                } else {
                    $route = Yii::$app->language.'/'.$route;
                }
            }

            $gets = [];
            if (!empty($params)) {
                if (!$this->onlyFriendlyParams) {
                    foreach ($this->gets as $k => $v) {
                        if (isset($params[$k])) {
                            $gets[$k] = ArrayHelper::remove($params, $k);
                        }
                    }
                }
                $route .= $this->paramsToUrl($params);
            }

            $gets = ArrayHelper::merge($gets, $standartParams);

            $query = '';
            if (!empty($gets) && ($q = http_build_query($gets)) !== '') {
                $query .= '?' . $q;
            }

            if ($this->suffix !== null) {
                $route .= $this->suffix;
                return "$baseUrl/{$route}{$this->suffix}{$query}{$anchor}";
            } else {
                return "$baseUrl/{$route}{$query}{$anchor}";
            }
        } else {
            $url = "$baseUrl?{$this->routeParam}=" . urlencode($route);

            if (!empty($params) && ($query = http_build_query($params)) !== '') {
                $url .= '&' . $query;
            }

            return $url . $anchor;
        }
    }

    public function createPrettyUrl($segments)
    {
        $params = [];
        $minkey = min(array_keys($segments));
        $count  = count($segments) + $minkey;
        for ($i = $minkey; $i < $count; $i++)
        {
            $key = $segments[$i];
            if ($key === '') {
                continue;
            }
            if (isset($segments[$i+1])) {
                $value = $segments[$i+1];
                $i++;
            } else {
                $value = '';
            }

            if(($pos = strpos($key, '[')) !== false && ($m=preg_match_all('/\[(.*?)\]/', $key, $matches)) > 0) {
                $name = substr($key, 0, $pos);
                for ($j = $m-1; $j >= 0; --$j)
                {
                    if($matches[1][$j] === '') {
                        $value = [$value];
                    } else {
                        $value = [$matches[1][$j] => $value];
                    }
                }
                if (isset($params[$name]) && is_array($params[$name])) {
                    $value = ArrayHelper::merge($params[$name], $value);
                }
                $params[$name] = $value;
            } else {
                $params[$key] = $value;
            }
        }
        return $params;
    }

    public function paramsToUrl($params)
    {
        $route = '';

        foreach ($params as $key => $value) {

            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $route .= $this->arrayToUrl($k, $v, $key);
                }
            } else {
                if ($value !== null) {
                    $route .= '/' . $key . '/' . $value;
                }
            }
        }

        return $route;
    }

    public function arrayToUrl($key, $value , $firstKey = null)
    {
        $route = '';
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if ($firstKey !== null) {
                    $route .= '/' . $firstKey;
                }
                $route .= '[' . $key . ']' . $this->arrayToUrl($k, $v);
            }
        } else {
            if ($firstKey !== null) {
                $route .= '/' . $firstKey;
            }
            $route .= '[' . $key . ']' . '/' . $value;
        }
        return $route;
    }
}
