<?php

declare(strict_types=1);

namespace SOFe\RwLock;

use Closure;
use Generator;
use SOFe\AwaitGenerator\Await;

/**
 * A lock that allows shared or exclusive acquisition.
 */
final class RwLock {
    private int $readerCount = 0;
    private bool $isWriting = false;

    /**
     * @var array<int, Closure(): void> Called when RwLock may switch ReadWrite mode.
     */
    private array $releasePromises = [];
    /**
     * @var array<int, Closure(): void> Scheduled to be moved to $releasePromises.
     */
    private array $newPromises = [];

    private function release() : void {
        $this->releasePromises = array_merge($this->releasePromises, $this->newPromises);
        while (($p = array_shift($this->releasePromises)) !== null) {
            $p();
        }
        $this->releasePromises = array_merge($this->releasePromises, $this->newPromises);
    }

    public function readClosure(Closure $closure) : Generator {
        return $this->read($closure());
    }

    public function read(Generator $generator) : Generator {
        while ($this->isWriting) {
            $this->newPromises[] = yield Await::RESOLVE;
            yield Await::ONCE;
        }
        $this->readerCount += 1;
        yield from $generator;
        $this->readerCount -= 1;
        if ($this->readerCount === 0) {
            $this->release();
        }
    }

    public function writeClosure(Closure $closure) : Generator {
        return $this->write($closure());
    }

    public function write(Generator $generator) : Generator {
        while ($this->isWriting || $this->readerCount !== 0) {
            $this->newPromises[] = yield Await::RESOLVE;
            yield Await::ONCE;
        }
        $this->isWriting = true;
        yield from $generator;
        $this->isWriting = false;
        $this->release();
    }
}
