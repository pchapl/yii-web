<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Web\Tests\Formatters;

use Yiisoft\Web\Formatters\JsonResponseFormatter;
use Yiisoft\Web\Tests\Stubs\ModelStub;
use Yiisoft\Web\Tests\Stubs\Post;

/**
 * @author Alexander Makarov <sam@rmcreative.ru>
 * @since 2.0.3
 *
 * @group web
 */
class JsonResponseFormatterTest extends FormatterTest
{
    /**
     * @return JsonResponseFormatter
     */
    protected function getFormatterInstance($configuration = [])
    {
        $configuration['__class'] = JsonResponseFormatter::class;

        return $this->factory->create($configuration);
    }

    public function formatScalarDataProvider()
    {
        return [
            [1, 1],
            ['abc', '"abc"'],
            [true, 'true'],
            ['<>', '"<>"'],
        ];
    }

    public function formatArrayDataProvider()
    {
        return [
            // input, json, pretty json
            [[], '[]', '[]'],
            [[1, 'abc'], '[1,"abc"]', "[\n    1,\n    \"abc\"\n]"],
            [
                [
                    'a' => 1,
                    'b' => 'abc',
                ],
                '{"a":1,"b":"abc"}',
                "{\n    \"a\": 1,\n    \"b\": \"abc\"\n}",
            ],
            [
                [
                    1,
                    'abc',
                    [2, 'def'],
                    true,
                ],
                '[1,"abc",[2,"def"],true]',
                "[\n    1,\n    \"abc\",\n    [\n        2,\n        \"def\"\n    ],\n    true\n]",
            ],
            [
                [
                    'a' => 1,
                    'b' => 'abc',
                    'c' => [2, '<>'],
                    true,
                ],
                '{"a":1,"b":"abc","c":[2,"<>"],"0":true}',
                "{\n    \"a\": 1,\n    \"b\": \"abc\",\n    \"c\": [\n        2,\n        \"<>\"\n    ],\n    \"0\": true\n}",
            ],
        ];
    }

    public function formatObjectDataProvider()
    {
        return [
            [new Post(123, 'abc'), '{"id":123,"title":"abc","city":null}'],
            [[
                new Post(123, 'abc'),
                new Post(456, 'def'),
            ], '[{"id":123,"title":"abc","city":null},{"id":456,"title":"def","city":null}]'],
            [[
                new Post(123, '<>'),
                'a' => new Post(456, 'def'),
            ], '{"0":{"id":123,"title":"<>","city":null},"a":{"id":456,"title":"def","city":null}}'],
        ];
    }

    public function formatTraversableObjectDataProvider()
    {
        $postsStack = new \SplStack();
        $postsStack->push(new Post(915, 'record1'));
        $postsStack->push(new Post(456, 'record2'));

        return [
            [$postsStack, '{"1":{"id":456,"title":"record2","city":null},"0":{"id":915,"title":"record1","city":null}}'],
        ];
    }

    public function formatModelDataProvider()
    {
        return [
            [new ModelStub(['id' => 123, 'title' => 'abc', 'hidden' => 'hidden']), '{"id":123,"title":"abc"}'],
        ];
    }

    public function contentTypeGenerationDataProvider()
    {
        return [
            [
                [
                ],
                'application/json; charset=UTF-8',
            ],
            [
                [
                    'useJsonp' => false,
                ],
                'application/json; charset=UTF-8',
            ],
            [
                [
                    'useJsonp' => true,
                ],
                'application/javascript; charset=UTF-8',
            ],
            [
                [
                    'contentType' => 'application/javascript; charset=UTF-8',
                    'useJsonp' => false,
                ],
                'application/javascript; charset=UTF-8',
            ],
            [
                [
                    'contentType' => 'application/json; charset=UTF-8',
                    'useJsonp' => true,
                ],
                'application/json; charset=UTF-8',
            ],
            [
                [
                    'contentType' => 'application/hal+json; charset=UTF-8',
                    'useJsonp' => false,
                ],
                'application/hal+json; charset=UTF-8',
            ],
            [
                [
                    'contentType' => 'application/hal+json; charset=UTF-8',
                    'useJsonp' => true,
                ],
                'application/hal+json; charset=UTF-8',
            ],
        ];
    }

    /**
     * @param mixed  $data the data to be formatted
     * @param string $json the expected JSON body
     * @param string $prettyJson the expected pretty JSON body
     * @dataProvider formatArrayDataProvider
     */
    public function testFormatArraysPretty($data, $json, $prettyJson)
    {
        $this->response->data = $data;
        $this->formatter->prettyPrint = true;
        $this->formatter->format($this->response);
        $this->assertEquals($prettyJson, $this->response->content);
    }

    /**
     * @param array $configuration JSON formatter configuration array.
     * @param string $contentTypeExpected Expected value of the response `Content-Type` header.
     * @dataProvider contentTypeGenerationDataProvider
     */
    public function testContentTypeGeneration($configuration, $contentTypeExpected)
    {
        $formatter = $this->getFormatterInstance($configuration);
        $formatter->format($this->response);
        $contentTypeActual = $this->response->getHeader('Content-Type')[0];

        $this->assertEquals($contentTypeExpected, $contentTypeActual);
    }
}
