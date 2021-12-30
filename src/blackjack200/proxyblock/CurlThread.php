<?php

namespace blackjack200\proxyblock;

use blackjack200\proxyblock\cache\CacheInterface;
use GlobalLogger;
use JsonMapper;
use Logger;
use pocketmine\Server;
use pocketmine\thread\Thread;
use Threaded;
use Throwable;
use Volatile;

class CurlThread extends Thread {
	private Threaded $queue;
	private Threaded $result;
	private CacheInterface $cache;
	private string $token;
	private Logger $logger;
	private bool $running = true;
	private string $kickMessage;

	public function __construct(Logger $logger, CacheInterface $cache, string $token, string $kickMessage) {
		$this->logger = $logger;
		$this->token = $token;
		$this->cache = $cache;
		$this->queue = new Volatile();
		$this->result = new Volatile();
		$this->kickMessage = $kickMessage;
	}

	protected function onRun() : void {
		GlobalLogger::set($this->logger);
		while ($this->running) {
			[$addr, $playerName] = $this->queue->shift();
			if ($addr === null) {
				continue;
			}
			$data = $this->getRequestData($addr);
			if ($data === null) {
				$this->logger->error("Failed to query $addr");
				continue;
			}
			$result = $this->mapData($data);
			if ($result === null) {
				$this->logger->error("Failed to map data for $addr");
			}
			$this->result->synchronized(fn() => $this->result[] = [$playerName, $result]);

			$this->wait();
		}
	}

	public function quit() : void {
		$this->running = false;
		parent::quit();
	}

	public function push(string $addr, string $playerName) : void {
		$this->queue->synchronized(fn() => $this->queue[] = [$addr, $playerName]);
		$this->notify();
	}

	public function mainThreadTick() : void {
		$this->result->synchronized(function () : void {
			while ($this->result->count() > 0) {
				[$playerName, $data] = $this->result->shift();
				if (!($data instanceof QueryResult)) {
					continue;
				}
				$player = Server::getInstance()->getPlayerExact($playerName);
				if ($player === null) {
					continue;
				}
				if ($data->blocked()) {
					$msg = $this->kickMessage;
					$msg = str_replace("[ASN]", $data->ASN(), $msg);
					$msg = str_replace("[ISP]", $data->ISP(), $msg);
					$msg = str_replace("[COUNTRY_CODE]", $data->countryCode(), $msg);
					$msg = str_replace("[COUNTRY_NAME]", $data->countryName(), $msg);
					$msg = str_replace("[HOSTNAME]", $data->hostname(), $msg);
					$player->kick($msg);
				}
			}
		});
	}

	private function mapData(string $data) : ?QueryResult {
		try {
			$mapper = new JsonMapper();
			$mapper->bExceptionOnUndefinedProperty = true;
			$mapper->bExceptionOnMissingData = true;
			$mapper->bIgnoreVisibility = true;
			$result = $mapper->map(json_decode($data, false), new QueryResult());
			if ($result instanceof QueryResult) {
				if ($result->error() === null) {
					return $result;
				} else {
					$this->logger->error("Query error: " . $result->error());
				}
			}
		} catch (Throwable $e) {
			$this->logger->logException($e);
		}
		return null;
	}

	private function query(string $token, string $addr) : ?string {
		$retry = 10;
		$this->logger->debug("Querying $addr");
		while ($retry-- > 0) {
			$ch = curl_init("https://v2.api.iphub.info/ip/$addr");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "X-Key: $token"]);
			$buf = curl_exec($ch);
			curl_close($ch);
			if ($buf !== false) {
				$this->logger->debug("Query $addr succeeded");
				return $buf;
			} else {
				$this->logger->debug("query $addr failed retry: $retry");
			}
		}
		return null;
	}

	private function getRequestData(mixed $addr) : string|null {
		$data = $this->cache->get($addr);
		if ($data === null) {
			$query = $this->query($this->token, $addr);
			if ($query !== null) {
				$this->cache->set($addr, $query);
			}
			$data = $query;
		}
		return $data;
	}
}