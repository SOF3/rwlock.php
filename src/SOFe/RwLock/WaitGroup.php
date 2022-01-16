<?php

declare(strict_types=1);

namespace SOFe\RwLock;

use Generator;
use SOFe\AwaitGenerator\Await;

final class WaitGroup {
	private int $count = 0;
	private array $waiting = [];

	public function start() : void {
		++$this->count;
	}

	public function done() : void {
		if(--$this->count === 0) {
			$this->wake();
		}
	}

	private function wake() : void {
		$waiting = $this->waiting;
		$this->waiting = [];
		foreach($waiting as $run) {
			$run();
		}
	}

	public function wait() : Generator {
		$resolve = yield Await::RESOLVE;

		if($this->count === 0) {
			$resolve(null);
		} else {
			$this->waiting[] = $resolve;
		}

		yield Await::ONCE;
	}
}
