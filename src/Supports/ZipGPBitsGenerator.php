<?php

namespace ZipStore\Supports;

use Stringable;

class ZipGPBitsGenerator implements Stringable
{
    public function __construct(
        public readonly bool $utf8 = true
    ) {}

    public function __toString(): string
    {
        return $this->generate();
    }

    private function generate(): string
    {
        $flags = 0;

        /* enable UTF-8 support for filename and comments */
        if ($this->utf8) {
            $flags |= $this->getUTF8BitFlag();
        }

        return pack('v', $flags);
    }

    private function getUTF8BitFlag(): int
    {
        return 1 << 11;
    }
}
