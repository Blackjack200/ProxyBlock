<?php

namespace blackjack200\proxyblock\cache;

/** @template T */
interface CacheInterface {
	/**
	 * @return T
	 */
	public function get(string $key) : ?string;

	/**
	 * @param T $value
	 */
	public function set(string $key, mixed $value) : bool;
}