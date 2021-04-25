<?php

declare(strict_types=1);

namespace SOFe\RwLock;

use function array_shift;
use function count;
use Closure;
use Generator;
use RuntimeException;
use Throwable;
use SOFe\AwaitGenerator\Await;

/**
 * @phpstan-type AsyncRunnable Closure() : Generator
 * @phpstan-type Runnable Closure() : void
 */
final class Mutex {
	/** @var bool */
	private $running = false;
	/** @var Runnable[] */
	private $queue = [];

	/**
	 * @var AsyncRunnable $closure
	 */
	public function runClosure(Closure $closure) : Generator {
		return $this->run($closure());
	}

	public function run(Generator $promise) : Generator {
		$resolve = yield;
		$reject = yield Await::REJECT;
		$this->queue[] = function() use($promise, $resolve, $reject) : void {
			try {
				$ret = $promise();
				$resolve($ret);
			} catch(Throwable $e) {
				$reject($e);
			}
			$this->running = false;
			$this->next();
		};

		if(!$this->running) {
			$this->next();
		}

		return yield Await::ONCE;
	}

	private function next() : void {
		if($this->running) {
			throw new RuntimeException("Call to next() while still running");
		}

		$runnable = array_shift($this->queue);
		if($runnable !== null) {
			$this->running = true;
			$runnable();
		}
	}

	/**
	 * Returns true if the mutex is currently unused, false if it is currently unlocked.
	 */
	public function isIdle() : bool {
		return !$this->running;
	}

	/**
	 * Returns the number of async functions that the mutex is scheduled to run,
	 * excluding the currently executing one.
	 */
	public function getQueueLength() : int {
		return count($this->queue);
	}
}
