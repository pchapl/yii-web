<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Web\Tests\Filters;

use yii\helpers\Yii;
use Yiisoft\Web\Filters\HttpCache;

/**
 * @group filters
 */
class HttpCacheTest extends \yii\tests\TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $_SERVER['SCRIPT_FILENAME'] = '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $this->mockWebApplication();
    }

    public function testDisabled()
    {
        $httpCache = new HttpCache();
        $this->assertTrue($httpCache->beforeAction(null));
        $httpCache->enabled = false;
        $this->assertTrue($httpCache->beforeAction(null));
    }

    public function testEmptyPragma()
    {
        $httpCache = new HttpCache();
        $httpCache->etagSeed = function ($action, $params) {
            return '';
        };
        $httpCache->beforeAction(null);
        $response = $this->app->getResponse();
        $this->assertFalse($response->hasHeader('Pragma'));
    }

    /**
     * @covers \Yiisoft\Web\Filters\HttpCache::validateCache
     */
    public function testValidateCache()
    {
        $request = $this->app->request;
        $httpCache = new HttpCache();
        $method = new \ReflectionMethod($httpCache, 'validateCache');
        $method->setAccessible(true);

        $request->setHeaders([]);
        $this->assertFalse($method->invoke($httpCache, null, null));
        $this->assertFalse($method->invoke($httpCache, 0, null));
        $this->assertFalse($method->invoke($httpCache, 0, '"foo"'));

        $request->setHeaders([
            'if-modified-since' => ['Thu, 01 Jan 1970 00:00:00 GMT']
        ]);
        $this->assertTrue($method->invoke($httpCache, 0, null));
        $this->assertFalse($method->invoke($httpCache, 1, null));

        $request->setHeaders([
            'if-none-match' => ['"foo"']
        ]);
        $this->assertTrue($method->invoke($httpCache, 0, '"foo"'));
        $this->assertFalse($method->invoke($httpCache, 0, '"foos"'));
        $this->assertTrue($method->invoke($httpCache, 1, '"foo"'));
        $this->assertFalse($method->invoke($httpCache, 1, '"foos"'));
        $this->assertFalse($method->invoke($httpCache, null, null));

        $request->setHeaders([
            'if-none-match' => ['*']
        ]);
        $this->assertFalse($method->invoke($httpCache, 0, '"foo"'));
        $this->assertFalse($method->invoke($httpCache, 0, null));
    }

    /**
     * @covers \Yiisoft\Web\Filters\HttpCache::generateEtag
     */
    public function testGenerateEtag()
    {
        $httpCache = new HttpCache();
        $httpCache->weakEtag = false;

        $httpCache->etagSeed = function ($action, $params) {
            return null;
        };
        $httpCache->beforeAction(null);
        $response = $this->app->getResponse();
        $this->assertFalse($response->hasHeader('ETag'));

        $httpCache->etagSeed = function ($action, $params) {
            return '';
        };
        $httpCache->beforeAction(null);
        $response = $this->app->getResponse();

        $this->assertTrue($response->hasHeader('ETag'));

        $etag = $response->getHeaderLine('ETag');
        $this->assertStringStartsWith('"', $etag);
        $this->assertStringEndsWith('"', $etag);


        $httpCache->weakEtag = true;
        $httpCache->beforeAction(null);
        $response = $this->app->getResponse();

        $this->assertTrue($response->hasHeader('ETag'));

        $etag = $response->getHeaderLine('ETag');
        $this->assertStringStartsWith('W/"', $etag);
        $this->assertStringEndsWith('"', $etag);
    }
}
