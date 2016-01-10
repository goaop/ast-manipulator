<?php
/**
 * AST Manipulator API
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\AstManipulator\SourceTransformer;

use Go\AstManipulator\Engine;
use php_user_filter as PhpStreamFilter;

/**
 * Php class loader filter for processing php code
 */
class AstSourceFilter extends PhpStreamFilter
{

    /**
     * Default PHP filter name for registration
     */
    const FILTER_IDENTIFIER = 'go.ast.filter';

    /**
     * String buffer
     *
     * @var string
     */
    protected $sourceCode = '';

    /**
     * Identifier of filter
     *
     * @var string
     */
    protected static $filterId;

    /**
     * Registers current class as an active stream filter in PHP
     *
     * @param string $filterId Identifier for the filter
     *
     * @throws \RuntimeException If registration was failed
     */
    public static function register($filterId = self::FILTER_IDENTIFIER)
    {
        if (!empty(self::$filterId)) {
            throw new \RuntimeException('AST stream filter already registered');
        }

        $result = stream_filter_register($filterId, static::class);
        if (!$result) {
            throw new \RuntimeException('AST stream filter was not registered');
        }
        self::$filterId = $filterId;
    }

    /**
     * Returns the name of source path, prefixed with the name of filter
     *
     * @param string $path Path to the original file
     *
     * @return string Path to the file with enabled filter
     */
    public static function getTransformedSourcePath($path)
    {
        if (empty(self::$filterId)) {
            throw new \RuntimeException('Stream filter was not registered');
        }

        $newPath = 'php://filter/read=' . self::$filterId . '/resource=' . $path;

        return $newPath;
    }

    /**
     * {@inheritdoc}
     */
    public function filter($in, $out, &$consumed, $closing)
    {
        while ($streamBucket = stream_bucket_make_writeable($in)) {
            $this->sourceCode .= $streamBucket->data;
        }

        if ($closing || feof($this->stream)) {
            $consumed      = strlen($this->sourceCode);
            $processedCode = Engine::parse($this->sourceCode);
            $streamBucket  = stream_bucket_new($this->stream, $processedCode);
            stream_bucket_append($out, $streamBucket);

            return PSFS_PASS_ON;
        }

        return PSFS_FEED_ME;
    }
}
