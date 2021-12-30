<?php

namespace blackjack200\proxyblock;

use blackjack200\proxyblock\cache\FilesystemCache;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use PrefixedLogger;
use Webmozart\PathUtil\Path;

class ProxyBlock extends PluginBase implements Listener {
	private CurlThread $thread;

	protected function onEnable() : void {
		$this->saveDefaultConfig();
		$this->thread = new CurlThread(
			new PrefixedLogger($this->getLogger(), "CurlThread"),
			new FilesystemCache(Path::join($this->getDataFolder(), "cache"), $this->getConfig()->get("cache-time")),
			$this->getConfig()->get("api-key"),
			$this->getConfig()->get("kick-message")
		);
		$this->thread->start();
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(fn() => $this->thread->mainThreadTick()), 20 * 5);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	protected function onDisable() : void {
		$this->thread->quit();
	}

	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		$player = $event->getPlayer();
		$this->thread->push($player->getNetworkSession()->getIp(), $player->getName());
	}
}