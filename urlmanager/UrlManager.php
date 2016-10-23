<?php

/**
 * @copyright Copyright &copy; Pavels Radajevs, 2014
 * @package yii2-url-manager
 * @version 1.1.1
 */

namespace pavlinter\urlmanager;

use Yii;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;

/**
 *
 * @author Pavels Radajevs <pavlinter@gmail.com>
 */
class UrlManager extends \yii\web\UrlManager
{
    const EVENT_INIT = 'init';

    public $enableLang = false;
    public $langParam  = 'lang';
    public $onlyFriendlyParams = false;
    public $gets = [];

    public $langBegin; //['en', 'fr'] if null langBegin load from db

    public $db = 'db';
    public $enableCaching = true;
    public $durationCaching;
    public $codeField = 'code';
    public $updatedAtField = 'updated_at';
    public $table = '{{%language}}';
    public $condition = ['active' => 1];
    public $orderBy = ['weight' => SORT_ASC];

    /**
     * @var array the default configuration of URL rules. Individual rule configurations
     * specified via [[rules]] will take precedence when the same property of the rule is configured.
     */
    public $ruleConfig = ['class' => 'pavlinter\urlmanager\UrlRule'];

    public $normalized = false;

    /**
	 * Initializes UrlManager.
	 */
	public function init()
	{
        parent::init();
        if (Yii::$app->request->getIsConsoleRequest()) {
            return true;
        }

        //set $this->langBegin is empty
        $this->setLangBegin();

        $request  = Yii::$app->getRequest();

        if ($this->normalizer !== false) {
            $pathInfo = $this->normalizer->normalizePathInfo($request->getPathInfo(), (string) $this->suffix, $this->normalized);
        } else {
            $pathInfo = rtrim($request->getPathInfo(), '/');
        }

        $suffix = (string) $this->suffix;
        if ($suffix !== '' && $pathInfo !== '') {
            $n = strlen($this->suffix);
            if (substr_compare($pathInfo, $this->suffix, -$n, $n) === 0) {
                $pathInfo = substr($pathInfo, 0, -$n);
                if ($pathInfo === '') {
                    // suffix alone is not allowed
                    throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
                }
            } else {
                // suffix doesn't match
                throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
            }
        }


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

            } else {
                $_GET[$this->langParam] = reset($this->langBegin);
            }
		}

        $event = new UrlManagerEvent([
            'pathInfo' => $pathInfo,
        ]);

        $this->trigger(self::EVENT_INIT, $event);

        if (isset($_GET)) {
            foreach ($_GET as $k => $v) {
                $this->gets[$k] = $v;
            }
        }

        $request->setPathInfo($event->pathInfo);
	}

    /**
     * Parses the user request.
     * @param \yii\web\Request $request the request component
     * @return array|boolean the route and the associated parameters. The latter is always empty
     * if [[enablePrettyUrl]] is false. False is returned if the current request cannot be successfully parsed.
     */
    public function parseRequest($request)
    {
        if ($this->enablePrettyUrl) {
            /* @var $rule UrlRule */
            $pathInfo = $request->getPathInfo();
            foreach ($this->rules as $rule) {
                if (($result = $rule->parseRequest($this, $request)) !== false) {
                    return $result;
                }
            }

            if ($this->enableStrictParsing) {
                return false;
            }

            Yii::trace('No matching URL rules. Using default URL parsing logic.', __METHOD__);

            if ($pathInfo) {
                $result = $this->parseUrl($pathInfo);
            } else {
                $result = [$pathInfo, []];
            }

            if ($this->normalized) {
                return $this->normalizer->normalizeRoute($result);
            } else {
                return $result;
            }
        } else {
            Yii::trace('Pretty URL not enabled. Using default URL parsing logic.', __METHOD__);
            $route = $request->getQueryParam($this->routeParam, '');
            if (is_array($route)) {
                $route = '';
            }

            return [(string) $route, []];
        }
    }

    /**
     * @param $pathInfo
     * @return array
     */
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

    /**
     * @param array|string $params
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
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
            $cacheKey = $route . '?';
            foreach ($params as $key => $value) {
                if ($value !== null) {
                    $cacheKey .= $key . '&';
                }
            }

            $url = $this->getUrlFromCache($cacheKey, $route, $params);
            if ($url === false) {
                $cacheable = true;
                foreach ($this->rules as $rule) {
                    /* @var $rule UrlRule */
                    if (!empty($rule->defaults) && $rule->mode !== UrlRule::PARSING_ONLY) {
                        // if there is a rule with default values involved, the matching result may not be cached
                        $cacheable = false;
                    }

                    if (($url = $rule->createUrl($this, $route, $params)) !== false) {
                        if ($cacheable) {
                            $this->setRuleToCache($cacheKey, $rule);
                        }
                        break;
                    }
                }
            }

            if ($url !== false) {
                if (strpos($url, '://') !== false) {
                    if ($baseUrl !== '' && ($pos = strpos($url, '/', 8)) !== false) {
                        return substr($url, 0, $pos) . $baseUrl . substr($url, $pos) . $anchor;
                    } else {
                        return $url . $baseUrl . $anchor;
                    }
                } else {
                    return "$baseUrl/{$url}{$anchor}";
                }
            }

            if ($this->enableLang) {
                if (isset($params[$this->langParam])) {
                    $route = $params[$this->langParam] . '/' . $route;
                    unset($params[$this->langParam]);
                } else {
                    $route = Yii::$app->language . '/' . $route;
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

    /**
     * @param $segments
     * @return array
     */
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

    /**
     * @param $params
     * @return string
     */
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

    /**
     * @param $key
     * @param $value
     * @param null $firstKey
     * @return string
     */
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

    public function setLangBegin()
    {
        if(!$this->langBegin){
            $key = static::className() . 'langBegin';
            $this->langBegin = $this->cache->get($key);
            if($this->langBegin === false){
                $query = new Query();
                $query->select(['c' => $this->codeField])->from($this->table);

                if ($this->condition) {
                    $query->where($this->condition);
                }
                if ($this->orderBy) {
                    $query->orderBy($this->orderBy);
                }
                $this->langBegin = ArrayHelper::getColumn($query->all($this->getDb()), 'c');
            }

            if ($this->enableCaching) {
                if ($this->durationCaching !== null) {
                    $this->cache->set($key, $this->langBegin, $this->durationCaching);
                } else {
                    $query = new Query();
                    $sql = $query->select('COUNT(*),MAX(' . $this->updatedAtField . ')')
                        ->from($this->table)
                        ->createCommand($this->getDb())
                        ->getRawSql();
                    $this->cache->set($key, $this->langBegin, $this->durationCaching, new \yii\caching\DbDependency([
                        'sql' => $sql,
                    ]));
                }
            }
        }
    }

    /**
     * @return \yii\db\Connection
     */
    public function getDb()
    {
        return Yii::$app->get($this->db);
    }
}
