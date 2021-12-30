<?php

namespace blackjack200\proxyblock\cache;

use pocketmine\utils\BinaryStream;
use Webmozart\PathUtil\Path;

class FilesystemCache implements CacheInterface {
	private string $path;
	private int $validTime;

	public function __construct(string $path, int $validTime) {
		$this->path = $path;
		$this->validTime = $validTime;
	}

	private function writeCache(string $key, mixed $data) : bool {
		$file = Path::join($this->path, $this->formatKey($key));
		if (!file_exists($file)) {
			touch($file);
		}
		$stream = new BinaryStream();
		$stream->putUnsignedVarLong(time());
		$hash = hash("sha256", $data);
		$stream->putUnsignedVarLong(strlen($hash));
		$stream->put($hash);
		$stream->putUnsignedVarLong(strlen($data));
		$stream->put($data);
		return file_put_contents($file, $stream->getBuffer()) !== false;
	}

	private function readCache(string $key) : ?string {
		$file = Path::join($this->path, $this->formatKey($key));
		if (!file_exists($file)) {
			return null;
		}
		$stream = new BinaryStream(file_get_contents($file));
		$time = $stream->getUnsignedVarLong();
		$hash = $stream->get($stream->getUnsignedVarLong());
		$length = $stream->getUnsignedVarLong();
		$data = $stream->get($length);
		if (hash("sha256", $data) !== $hash) {
			@unlink($file);
			return null;
		}
		if ($time + $this->validTime < time()) {
			@unlink($file);
			return null;
		}
		return $data;
	}

	public function get(string $key) : ?string {
		return $this->readCache($key);
	}

	public function set(string $key, mixed $value) : bool {
		return $this->writeCache($key, $value);
	}

	private function formatKey(string $key) : string {
		return "$key.cache";
	}
}