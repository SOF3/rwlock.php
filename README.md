# rwlock.php

Exclusive and Read-write locks for PHP.

This is a quick port of [my javascript library `rwlock-promise`](https://github.com/SOF3/rwlock-promise) to PHP.
Some parts may happen to be less idiomatic because of this.

This library uses the [`await-generator`](https://github.com/SOF3/await-generator) framework
to expose asynchronous function call API.

## Usage
```php
$mutex = new Mutex;

$epoch = time();

yield $mutex->run(function() use($epoch) {
	echo "Start 1: ", time() - $epoch, "\n";
	yield $this->waitSeconds(2);
	echo "End 1: ", time() - $epoch, "\n";
});

yield $mutex->run(function() use($epoch) {
	echo "Start 2: ", time() - $epoch, "\n";
	yield $this->waitSeconds(1);
	echo "End 2: ", time() - $epoch, "\n";
});
```

The above should output

```
Start 1: 0
End 1: 2
Start 2: 2
End 2: 3
```

The second generator is only run after the first generator returns.

```php
$mutex = new RwLock;

$epoch = time();

Await::g2c($mutex->readClosure(function() use($epoch) {
	echo "Start 1: ", time() - $epoch, "\n";
	yield $this->waitSeconds(2);
	echo "End 1: ", time() - $epoch, "\n";
}));

Await::g2c($mutex->readClosure(function() use($epoch, $mutex) {
	echo "Start 2: ", time() - $epoch, "\n";
	yield $this->waitSeconds(1);
	echo "End 2: ", time() - $epoch, "\n";

	Await::g2c($mutex->readClosure(function() use($epoch) {
		echo "Start 3: ", time() - $epoch, "\n";
		yield $this->waitSeconds(2);
		echo "End 3: ", time() - $epoch, "\n";
	}));

	Await::f2c($mutex->writeClosure(function() use($epoch) {
		echo "Start 4: ", time() - $epoch, "\n";
		yield $this->waitSeconds(2);
		echo "End 4: ", time() - $epoch, "\n";
	}));

	Await::f2c($mutex->readClosure(function() use($epoch) {
		echo "Start 5: ", time() - $epoch, "\n";
		yield $this->waitSeconds(2);
		echo "End 5: ", time() - $epoch, "\n";
	}));
}));
```

The above should output

```
Start 1: 0
Start 2: 0
End 2: 1
Start 3: 1
End 1: 2
End 3: 3
Start 4: 3
End 4: 5
Start 5: 5
End 5: 7
```

It does not make sense to `yield` (await) a read/write inside a read/write block;
in particular, yielding another write inside a read/write block would lead to a deadlock.
Use `Await::g2c`, as shown above, to schedule a read/write without blocking on it.
However, you must always either `yield` or `f2c`/`g2c` a read/write,
because it only returns a generator,
which does nothing if you don't call anything on it.
