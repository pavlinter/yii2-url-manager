<?php

/**
 * @copyright Copyright &copy; Pavels Radajevs, 2014
 * @package yii2-url-manager
 * @version 1.0.0
 */

namespace pavlinter\urlmanager;

use Yii;
use yii\helpers\ArrayHelper;

/**
 *
 * @author Pavels Radajevs <pavlinter@gmail.com>
 * @since 1.0
 * @last-commit 3a1e0f3a5c6f75ed08bb8cea321c2391045bc804#diff-5ee57ea516b11a9064564ca7291d6bde
 */
class UrlManager extends \yii\web\UrlManager
{
    public $enableLang = false;
    public $langParam  = 'lang';
	/**
	 * Initializes UrlManager.
	 */
	public function init()
	{
        parent::init();
        if ($this->enableLang) {
            $request  = Yii::$app->getRequest();
            $pathInfo = $request->getPathInfo();
            if ($pathInfo !== "") {
                if (strpos($pathInfo, '/') !== false) {
                    $segments = explode('/', $pathInfo);
                    $_GET[$this->langParam] = $segments['0'];
                    unset($segments['0']);
                    $request->setPathInfo(join('/', $segments));
                }
            }
		}
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
                if (substr_compare($pathInfo, $this->suffix, -$n) === 0) {
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
            $segments = explode('/', trim($pathInfo, '/'));
            $n = count($segments);
            $modules = Yii::$app->getModules();
            $pathInfoStart = 2;
            if (isset($modules[$segments['0']])) {
                $pathInfoStart = 3;
            }
            if ( $n > $pathInfoStart){
                if($pathInfoStart === 3){
                    $pathInfo = $segments['0'] . '/' . $segments['1'] . '/' . $segments['2'];
                    unset($segments['0'], $segments['1'], $segments['2']);
                }else{
                    $pathInfo = $segments['0'] . '/' . $segments['1'];
                    unset($segments['0'], $segments['1']);
                }

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

            if (!empty($params)) {
                foreach ($params as $key => $value) {
                    $route .= '/' . $key . '/' . $value;
                }
            }

            if ($this->suffix !== null) {
                $route .= $this->suffix;
                return "$baseUrl/{$route}{$this->suffix}{$anchor}";
            } else {
                return "$baseUrl/{$route}{$anchor}";
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
}
