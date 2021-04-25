<?php

declare(strict_types=1);

namespace SOFe\RwLock;

use function count;

/**
 * A pass-by-reference array
 * @internal
 * @template T
 */
final class ListRef {
	/** @var T[] */
	private $init;

	/**
	 * @param T[] $init
	 */
	public function __construct(array $init) {
		$this->init = $init;
	}

	/**
	 * @param T $value
	 */
	public function push($value) : void {
		$this->init[] = $value;
	}

	/**
	 * Returns a copy-on-write array of this list
	 * @return T[]
	 */
	public function getCow() : array {
		return $this->init;
	}

	public function getCount() : int {
		return count($this->init);
	}
}
