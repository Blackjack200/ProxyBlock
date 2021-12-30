<?php

namespace blackjack200\proxyblock;

class QueryResult {
	private string $hostname;
	private string $ip;
	private string $countryCode;
	private string $countryName;
	private int $asn;
	private string $isp;
	private int $block;
	private ?string $error = null;

	public function blocked() : bool {
		return $this->block === 1;
	}

	public function ISP() : string {
		return $this->isp;
	}

	public function ASN() : int {
		return $this->asn;
	}

	public function countryCode() : string {
		return $this->countryCode;
	}

	public function countryName() : string {
		return $this->countryName;
	}

	public function error() : ?string {
		return $this->error;
	}

	public function hostname() : string {
		return $this->hostname;
	}
}