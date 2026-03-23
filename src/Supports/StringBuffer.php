<?php

namespace ZipStore\Supports;

class StringBuffer implements \Stringable
{
    public string $content = '';

    /** @var int<0,max> */
    public int $size = 0;

    public function __construct(public readonly int $capacity)
    {
        if ($capacity < 0) {
            throw new \InvalidArgumentException('Negative size buffer not supported');
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
        return $this->capacity === $this->size;
    }

    public function leftSize(): int
    {
        return $this->capacity - $this->size;
    }

    public function write(string $content): int
    {
        $content = \substr($content, 0, $this->capacity - $this->size);
        $toWrite = \strlen($content);

        $this->size += $toWrite;
        $this->content .= $content;

        return $toWrite;
    }
}
