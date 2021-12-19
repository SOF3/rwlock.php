<?php

namespace SOFe\RwLock;

use PHPUnit\Framework\TestCase;
use SOFe\AwaitGenerator\Await;

final class MutexTest extends TestCase {
	public function testMutex() {
		$clock = new Clock;

		$mutex = new Mutex;

		$done = 0;

		$run1 = function() use($clock, &$done) {
			$this->assertSame(0, $clock->now());
			yield from $clock->sleep(2);
			$this->assertSame(2, $clock->now());
			echo "End 1: ", $clock->now(), "\n";
			$done++;
		};
		Await::g2c($mutex->run($run1()));

		$run2 = function() use($clock, &$done) {
			$this->assertSame(2, $clock->now());
			yield from $clock->sleep(1);
			$this->assertSame(3, $clock->now());
			$done++;
		};
		Await::g2c($mutex->run($run2()));

		for($i = 0; $i < 3; $i++) {
			$clock->tick();
		}

		$this->assertSame(2, $done);
	}
}
