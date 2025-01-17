<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Web;

use yii\exceptions\InvalidArgumentException;
use yii\helpers\Json;

/**
 * Parses a raw HTTP request using [[\yii\helpers\Json::decode()]].
 *
 * To enable parsing for JSON requests you can configure [[Request::parsers]] using this class:
 *
 * ```php
 * 'request' => [
 *     'parsers' => [
 *         'application/json' => \Yiisoft\Web\JsonParser::class,
 *     ]
 * ]
 * ```
 *
 * @author Dan Schmidt <danschmidt5189@gmail.com>
 * @since 2.0
 */
class JsonParser implements RequestParserInterface
{
    /**
     * @var bool whether to return objects in terms of associative arrays.
     */
    public $asArray = true;
    /**
     * @var bool whether to throw a [[BadRequestHttpException]] if the body is invalid json
     */
    public $throwException = true;


    /**
     * {@inheritdoc}
     * @throws BadRequestHttpException if the body contains invalid json and [[throwException]] is `true`.
     */
    public function parse($request)
    {
        try {
            $parameters = Json::decode($request->getBody()->__toString(), $this->asArray);
            return $parameters === null ? [] : $parameters;
        } catch (InvalidArgumentException $e) {
            if ($this->throwException) {
                throw new BadRequestHttpException('Invalid JSON data in request body: ' . $e->getMessage());
            }

            return [];
        }
    }
}
