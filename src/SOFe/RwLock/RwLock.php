<?php

declare(strict_types=1);

namespace SOFe\RwLock;

use function assert;
use function count;
use Generator;
use Throwable;
use SOFe\AwaitGenerator\Await;

/**
 * @phpstan-import-type AsyncRunnable from RwLock
 * @phpstan-import-type Runnable from RwLock
 */
final class RwLock {
	/** @var Mutex */
	private $mutex;
	/** @var int */
	private $remaining = 0;
	/** @var ListRef<AsyncRunnable>|null */
	private $tailFxList = null;
	/** @var Runnable|null */
	private $batchDone = null;

	/**
	 * Creates a RwLock
	 */
	public function __construct() {
		$this->mutex = new Mutex();
	}

	/**
	 * Schedules a write (exclusive) operation.
	 */
	public function write(Generator $promise) : Generator {
		$this->tailFxList = null;
		yield $this->mutex->run($promise);
	}

	/**
	 * Schedules a read (shared) operation.
	 */
	public function read(Generator $promise) : Generator {
		$resolve = yield;
		$reject = yield Await::REJECT;

		$handler = (static function() use($promise, $resolve, $reject) : Generator {
			try {
				$resolve(yield $promise);
			} catch(Throwable $e) {
				$reject($e);
			}
		})();

		if($this->tailFxList === null) {
			// need to schedule new batch
			$fxList = new ListRef([$handler]);
			$this->tailFxList = $fxList;
			$this->mutex->runClosure(function() use($fxList) : Generator {
				$this->batchDone = yield;
				$this->remaining = $fxList->getCount();

				foreach($fxList->getCow() as $fx) {
					Await::f2c(function() use($fx, $fxList) {
						yield $fx;

						$this->remaining--;
						if($this->remaining === 0) {
							if($this->tailFxList === $fxList) {
								$this->tailFxList = null;
							}

							assert($this->batchDone !== null, "batchDone() should be set during mutex lock by read batch");
							($this->batchDone)();
						}
					});
				}
			});
		} else {
			if($this->mutex->getQueueLength() > 0) {
				$this->tailFxList->push($handler);
			} else {
				$this->remaining++;
				$fxList = $this->tailFxList;

				Await::f2c(function() use($handler, $fxList) : Generator {
					yield $handler;
					$this->remaining--;
					if($this->remaining === 0) {
						if($this->tailFxList === $fxList) {
							$this->tailFxList = null;
						}

						assert($this->batchDone !== null, "batchDone() should be set during mutex lock by read batch");
						($this->batchDone)();
					}
				});
			}
		}

		return yield Await::ONCE;
	}
}
