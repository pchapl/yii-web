<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Web\Filters;

use yii\base\Action;
use yii\base\ActionFilter;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Cache\Dependencies\Dependency;
use yii\helpers\Yii;
use yii\view\DynamicContentAwareInterface;
use yii\view\DynamicContentAwareTrait;
use yii\view\View;
use Yiisoft\Web\Response;

/**
 * PageCache implements server-side caching of whole pages.
 *
 * It is an action filter that can be added to a controller and handles the `beforeAction` event.
 *
 * To use PageCache, declare it in the `behaviors()` method of your controller class.
 * In the following example the filter will be applied to the `index` action and
 * cache the whole page for maximum 60 seconds or until the count of entries in the post table changes.
 * It also stores different versions of the page depending on the application language.
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         'pageCache' => [
 *             '__class' => \Yiisoft\Web\filters\PageCache::class,
 *             'only' => ['index'],
 *             'duration' => 60,
 *             'dependency' => [
 *                 '__class' => \Yiisoft\Cache\Dependencies\DbDependency::class,
 *                 'sql' => 'SELECT COUNT(*) FROM post',
 *             ],
 *             'variations' => [
 *                 \Yii::getApp()->language,
 *             ]
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Sergey Makinen <sergey@makinen.ru>
 * @since 2.0
 */
class PageCache extends ActionFilter implements DynamicContentAwareInterface
{
    use DynamicContentAwareTrait;

    /**
     * Page cache version, to detect incompatibilities in cached values when the
     * data format of the cache changes.
     */
    const PAGE_CACHE_VERSION = 2;

    /**
     * @var bool whether the content being cached should be differentiated according to the route.
     * A route consists of the requested controller ID and action ID. Defaults to `true`.
     */
    public $varyByRoute = true;
    /**
     * @var CacheInterface the cache object.
     */
    private $cache;
    /**
     * @var int number of seconds that the data can remain valid in cache.
     * Use `0` to indicate that the cached data will never expire.
     */
    public $duration = 60;
    /**
     * @var array|Dependency the dependency that the cached content depends on.
     * This can be either a [[Dependency]] object or a configuration array for creating the dependency object.
     * For example,
     *
     * ```php
     * [
     *     '__class' => \Yiisoft\Cache\Dependencies\DbDependency::class,
     *     'sql' => 'SELECT MAX(updated_at) FROM post',
     * ]
     * ```
     *
     * would make the output cache depend on the last modified time of all posts.
     * If any post has its modification time changed, the cached content would be invalidated.
     *
     * If [[cacheCookies]] or [[cacheHeaders]] is enabled, then [[Dependency::reusable]] should be enabled as well to save performance.
     * This is because the cookies and headers are currently stored separately from the actual page content, causing the dependency to be evaluated twice.
     */
    public $dependency;
    /**
     * @var string[]|string list of factors that would cause the variation of the content being cached.
     * Each factor is a string representing a variation (e.g. the language, a GET parameter).
     * The following variation setting will cause the content to be cached in different versions
     * according to the current application language:
     *
     * ```php
     * [
     *     Yii::getApp()->language,
     * ]
     * ```
     */
    public $variations;
    /**
     * @var bool whether to enable the page cache. You may use this property to turn on and off
     * the page cache according to specific setting (e.g. enable page cache only for GET requests).
     */
    public $enabled = true;
    /**
     * @var bool|array a boolean value indicating whether to cache all cookies, or an array of
     * cookie names indicating which cookies can be cached. Be very careful with caching cookies, because
     * it may leak sensitive or private data stored in cookies to unwanted users.
     * @since 2.0.4
     */
    public $cacheCookies = false;
    /**
     * @var bool|array a boolean value indicating whether to cache all HTTP headers, or an array of
     * HTTP header names (case-insensitive) indicating which HTTP headers can be cached.
     * Note if your HTTP headers contain sensitive information, you should white-list which headers can be cached.
     * @since 2.0.4
     */
    public $cacheHeaders = true;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * This method is invoked right before an action is to be executed (after all possible filters.)
     * You may override this method to do last-minute preparation for the action.
     * @param Action $action the action to be executed.
     * @return bool whether the action should continue to be executed.
     */
    public function beforeAction($action)
    {
        if (!$this->enabled) {
            return true;
        }

        if (is_array($this->dependency)) {
            $this->dependency = Yii::createObject($this->dependency);
        }

        $response = Yii::getApp()->getResponse();
        $data = $this->cache->get($this->calculateCacheKey());
        if (!is_array($data) || !isset($data['cacheVersion']) || $data['cacheVersion'] !== static::PAGE_CACHE_VERSION) {
            $this->getView()->pushDynamicContent($this);
            ob_start();
            ob_implicit_flush(false);
            $response->on(Response::EVENT_AFTER_SEND, [$this, 'cacheResponse']);
            Yii::debug('Valid page content is not found in the cache.', __METHOD__);
            return true;
        }

        $this->restoreResponse($response, $data);
        Yii::debug('Valid page content is found in the cache.', __METHOD__);
        return false;
    }

    /**
     * This method is invoked right before the response caching is to be started.
     * You may override this method to cancel caching by returning `false` or store an additional data
     * in a cache entry by returning an array instead of `true`.
     * @return bool|array whether to cache or not, return an array instead of `true` to store an additional data.
     * @since 2.0.11
     */
    public function beforeCacheResponse()
    {
        return true;
    }

    /**
     * This method is invoked right after the response restoring is finished (but before the response is sent).
     * You may override this method to do last-minute preparation before the response is sent.
     * @param array|null $data an array of an additional data stored in a cache entry or `null`.
     * @since 2.0.11
     */
    public function afterRestoreResponse($data)
    {
    }

    /**
     * Restores response properties from the given data.
     * @param Response $response the response to be restored.
     * @param array $data the response property data.
     * @since 2.0.3
     */
    protected function restoreResponse($response, $data)
    {
        foreach (['format', 'protocolVersion', 'statusCode', 'reasonPhrase', 'content'] as $name) {
            $response->{$name} = $data[$name];
        }

        if (isset($data['headers'])) {
            $response->setHeaders($data['headers']);
        }

        if (isset($data['cookies']) && is_array($data['cookies'])) {
            $response->getCookies()->fromArray(array_merge($data['cookies'], $response->getCookies()->toArray()));
        }

        if (!empty($data['dynamicPlaceholders']) && is_array($data['dynamicPlaceholders'])) {
            $response->content = $this->updateDynamicContent($response->content, $data['dynamicPlaceholders'], true);
        }
        $this->afterRestoreResponse($data['cacheData'] ?? null);
    }

    /**
     * Caches response properties.
     * @since 2.0.3
     */
    public function cacheResponse()
    {
        $this->getView()->popDynamicContent();
        $beforeCacheResponseResult = $this->beforeCacheResponse();
        if ($beforeCacheResponseResult === false) {
            echo $this->updateDynamicContent(ob_get_clean(), $this->getDynamicPlaceholders());
            return;
        }

        $response = Yii::getApp()->getResponse();
        $data = [
            'cacheVersion' => static::PAGE_CACHE_VERSION,
            'cacheData' => is_array($beforeCacheResponseResult) ? $beforeCacheResponseResult : null,
            'content' => ob_get_clean(),
        ];
        if ($data['content'] === false || $data['content'] === '') {
            return;
        }

        $data['dynamicPlaceholders'] = $this->getDynamicPlaceholders();
        foreach (['format', 'protocolVersion', 'statusCode', 'reasonPhrase'] as $name) {
            $data[$name] = $response->{$name};
        }
        $this->insertResponseCollectionIntoData($response, 'headers', $data);
        $this->insertResponseCollectionIntoData($response, 'cookies', $data);
        $this->cache->set($this->calculateCacheKey(), $data, $this->duration, $this->dependency);
        $data['content'] = $this->updateDynamicContent($data['content'], $this->getDynamicPlaceholders());
        echo $data['content'];
    }

    /**
     * Inserts (or filters/ignores according to config) response headers/cookies into a cache data array.
     * @param Response $response the response.
     * @param string $collectionName currently it's `headers` or `cookies`.
     * @param array $data the cache data.
     */
    private function insertResponseCollectionIntoData(Response $response, $collectionName, array &$data)
    {
        $property = 'cache' . ucfirst($collectionName);
        if ($this->{$property} === false) {
            return;
        }

        $collection = $response->{$collectionName};
        $all = is_array($collection) ? $collection : $collection->toArray();
        if (is_array($this->{$property})) {
            $filtered = [];
            foreach ($this->{$property} as $name) {
                if ($collectionName === 'headers') {
                    $name = strtolower($name);
                }
                if (isset($all[$name])) {
                    $filtered[$name] = $all[$name];
                }
            }
            $all = $filtered;
        }
        $data[$collectionName] = $all;
    }

    /**
     * @return array the key used to cache response properties.
     * @since 2.0.3
     */
    protected function calculateCacheKey()
    {
        $key = [__CLASS__];
        if ($this->varyByRoute) {
            $key[] = Yii::getApp()->requestedRoute;
        }

        return array_merge($key, (array)$this->variations);
    }

    protected $view;

    public function setView(View $view): self
    {
        $this->view = $view;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getView()
    {
        if ($this->view === null) {
            $this->view = Yii::getApp()->getView();
        }

        return $this->view;
    }
}
