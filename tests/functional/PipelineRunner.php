<?php

declare(strict_types=1);

namespace functional\Kiboko\Component\Flow\SQL;

use Kiboko\Contract\Bucket\AcceptanceResultBucketInterface;
use Kiboko\Contract\Bucket\RejectionResultBucketInterface;
use Kiboko\Contract\Pipeline\PipelineRunnerInterface;
use Kiboko\Contract\Pipeline\StepRejectionInterface;
use Kiboko\Contract\Pipeline\StepStateInterface;

final class PipelineRunner implements PipelineRunnerInterface
{
    public function run(
        \Iterator $source,
        \Generator $async,
        StepRejectionInterface $rejection,
        StepStateInterface $state,
    ): \Iterator
    {
        $source->rewind();
        $async->rewind();

        while ($source->valid() && $async->valid()) {
            $bucket = $async->send($source->current());

            if ($bucket instanceof RejectionResultBucketInterface) {
                foreach ($bucket->walkRejection() as $line) {
                    $rejection->reject($line);
                    $state->reject();
                }
            }
            if ($bucket instanceof AcceptanceResultBucketInterface) {
                yield from $bucket->walkAcceptance();
                $state->accept();
            }

            $source->next();
        }
    }
}
