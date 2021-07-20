<?php

namespace Kiboko\Component\Flow\SQL;

use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Contract\Mapping\CompiledMapperInterface;
use Kiboko\Contract\Pipeline\TransformerInterface;

class Lookup implements TransformerInterface
{
    public function __construct(
        private CompiledMapperInterface $mapper
    )
    {
    }

    public function transform(): \Generator
    {
        $line = yield;
        do {
            $line = ($this->mapper)($line);
        } while ($line = (yield new AcceptanceResultBucket($line)));
    }
}
