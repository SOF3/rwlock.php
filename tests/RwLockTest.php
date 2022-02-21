<?php

namespace SOFe\RwLock;

use PHPUnit\Framework\TestCase;
use SOFe\AwaitGenerator\Await;

final class RwLockTest extends TestCase {
	public function testLock() {
		$clock = new Clock;

		$lock = new RwLock;

		$done = 0;

		$_1w = function() use($clock, &$done) {
			$this->assertSame(0, $clock->now());
			yield from $clock->sleep(2);
			$this->assertSame(2, $clock->now());
			$done++;
		};
		Await::g2c($lock->write($_1w()));

		$_2w = function() use($clock, &$done) {
			$this->assertSame(2, $clock->now());
			yield from $clock->sleep(1);
			$this->assertSame(3, $clock->now());
			$done++;
		};

        // This write waits for the first write to finish.
        Await::g2c($lock->write($_2w()));

		$_3w = function() use($clock, &$done) {
			$this->assertSame(3, $clock->now());
			yield from $clock->sleep(1);
			$this->assertSame(4, $clock->now());
			$done++;
		};

        // This write waits for the first write to finish.
        Await::g2c($lock->write($_3w()));

        $_4r = function() use($clock, &$done) {
			$this->assertSame(4, $clock->now());
			yield from $clock->sleep(1);
			$this->assertSame(5, $clock->now());
			$done++;
		};

		$_5w = function() use($clock, &$done) {
			$this->assertSame(5, $clock->now());
			yield from $clock->sleep(1);
			$this->assertSame(6, $clock->now());
			$done++;
		};

        // Reads will happen will take priority in this case
        // because read() is called before write().
		Await::g2c($lock->read($_4r()));
        // This write will wait until all reads finish.
		Await::g2c($lock->write($_5w()));
		Await::g2c($lock->read($_4r()));
		Await::g2c($lock->read($_4r()));

		for($i = 0; $i < 6; $i++) {
			$clock->tick();
		}

		$this->assertSame(7, $done);
	}
}
