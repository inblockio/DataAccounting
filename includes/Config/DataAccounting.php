<?php

namespace DataAccounting\Config;

use Config;
use Exception;
use MultiConfig;
use MutableConfig;

class DataAccounting extends MultiConfig implements MutableConfig {
	/**
	 * @param Config[] $configs
	 * @param Handler $handler
	 */
	public function __construct( array $configs, Handler $handler ) {
		$this->handler = $handler;
		parent::__construct( $configs );
	}

	/**
	 * @see MutableConfig::set
	 * @param string $name
	 * @param mixed $value
	 */
	public function set( $name, $value ) {
		$status = $this->handler->set( $name, $value );
		if ( !$status->isOK() ) {
			throw new Exception( $status->getMessage() );
		}
	}
}
