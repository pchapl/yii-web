<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Web\Tests\Filters;

use yii\base\Action;
use yii\base\ActionEvent;
use Yiisoft\Web\Filters\VerbFilter;
use Yiisoft\Web\Controller;
use Yiisoft\Web\MethodNotAllowedHttpException;
use Yiisoft\Web\Request;
use yii\tests\TestCase;

/**
 * @group filters
 */
class VerbFilterTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $_SERVER['SCRIPT_FILENAME'] = '/index.php';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
    }

    public function testFilter()
    {
        $this->mockWebApplication();
        $request = new Request($this->app);
        $this->container->set('request', $request);
        $controller = new Controller('id', $this->app);
        $action = new Action('test', $controller);
        $filter = $this->factory->create([
            '__class' => VerbFilter::class,
            'actions' => [
                '*' => ['GET', 'POST', 'Custom'],
            ]
        ]);

        $event = ActionEvent::before($action);

        $request->setMethod('GET');
        $this->assertTrue($filter->beforeAction($event));

        $request->setMethod('CUSTOM');

        try {
            $filter->beforeAction($event);
        } catch (MethodNotAllowedHttpException $exception) {
        }

        $this->assertTrue(isset($exception));
        $this->assertEquals(['GET, POST, Custom'], $this->app->response->getHeader('Allow'));
    }
}
