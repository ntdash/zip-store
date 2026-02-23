<?php

namespace ZipStore\Supports;

use Exception;
use Stringable;

class StringBuffer implements Stringable
{
    public string $content = '';

    /** @var int<0,max> */
    public int $size = 0;

    public function __construct(public readonly int $limit)
    {
        if ($limit < 0) {
            throw new Exception('Negative size buffer not supported');
        }
    }

    public function __toString(): string
    {
        return $this->content;
    }

    public function isEmpty(): bool
    {
        return 0 === $this->size;
    }

    public function isFull(): bool
    {
        return $this->limit === $this->size;
    }

    public function leftSize(): int
    {
        return $this->limit - $this->size;
    }

    public function write(string $content): int
    {
        $content = \substr($content, 0, $this->limit - $this->size);
        $toWrite = \strlen($content);

        $this->size += $toWrite;
        $this->content .= $content;

        return $toWrite;
    }
}
