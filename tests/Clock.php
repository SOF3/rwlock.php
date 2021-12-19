<?php

namespace SOFe\RwLock;

use Generator;
use SOFe\AwaitGenerator\Await;
use SplPriorityQueue;

final class Clock {
	private int $time = 0;
	private SplPriorityQueue $queue;

	public function __construct() {
		$this->queue = new class extends SplPriorityQueue {
			public function compare($priority1, $priority2) {
				return $priority2 <=> $priority1;
			}
		};
		$this->queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
	}

	public function now() : int {
		return $this->time;
	}

	public function tick() : void {
		while($this->hasRunnableNow()) {
			$this->queue->extract()["data"](null);
		}

		$this->time++;

		while($this->hasRunnableNow()) {
			$this->queue->extract()["data"](null);
		}
	}

	private function hasRunnableNow() : bool {
		$top = $this->queue->current();
		return $top !== null && $top["priority"] <= $this->time;
	}

	public function sleep(int $ticks) : Generator {
		$resolve = yield Await::RESOLVE;
		$this->queue->insert($resolve, $this->time + $ticks);
		yield Await::ONCE;
	}
}
