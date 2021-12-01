<?php

namespace DataAccounting\Config;

use Config;
use FormatJson;
use GlobalVarConfig;
use HashConfig;
use MediaWiki\MediaWikiServices;
use Status;
use Wikimedia\Rdbms\ILoadBalancer;

class Handler {
	/**
	 * @var ILoadBalancer
	 */
	private $loadBalancer = null;

	/**
	 * @var Config
	 */
	private $mainConfig = null;

	/**
	 * @var Config
	 */
	private $config = null;

	/**
	 * @var HashConfig
	 */
	private $databaseConfig = null;

	/**
	 * @param Config $mainConfig
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct( Config $mainConfig, ILoadBalancer $loadBalancer ) {
		$this->mainConfig = $mainConfig;
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * @return Config
	 */
	public static function ConfigFactoryCallback(): Config {
		return MediaWikiServices::getInstance()->get( 'DataAccountingConfigHandler' )
			->getConfig();
	}

	/**
	 * @return Config
	 */
	public function getConfig(): Config {
		if ( $this->config ) {
			return $this->config;
		}
		$this->config = $this->makeConfig();
		return $this->config;
	}

	/**
	 * @return Config
	 */
	private function makeConfig(): Config {
		if ( !$this->databaseConfig ) {
			$this->databaseConfig = $this->makeDatabaseConfig();
		}
		return new DataAccounting( [
			&$this->databaseConfig,
			new GlobalVarConfig( 'da' ),
			$this->mainConfig
		], $this );
	}

	/**
	 * @return HashConfig
	 */
	private function makeDatabaseConfig(): HashConfig {
		$conn = $this->loadBalancer->getConnection( DB_REPLICA );
		$hash = [];

		// workaround for the upgrade process. The new settings cannot be
		// accessed before teh table is created
		if ( !$conn->tableExists( 'da_settings', __METHOD__ ) ) {
			return new HashConfig( $hash );
		}

		$res = $conn->select( 'da_settings', '*', '', __METHOD__ );
		foreach ( $res as $row ) {
			$hash[$row->das_name] = FormatJson::decode( $row->das_value, true );
		}

		return new HashConfig( $hash );
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @return Status
	 */
	public function set( $name, $value ): Status {
		$status = Status::newGood();
		$dsConfig = new GlobalVarConfig( 'da' );
		if ( !$dsConfig->has( $name ) ) {
			$status->fatal(
				"The config '$name' does not exist within the da config prefix"
			);
			return $status;
		}
		$status->merge( $this->setDatabaseConfig( $name, $value ) );
		if ( $status->isOK() ) {
			$this->databaseConfig = $this->makeDatabaseConfig();
		}
		return $status;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @return Status
	 */
	private function setDatabaseConfig( $name, $value ): Status {
		$status = Status::newGood();
		$value = FormatJson::encode( $value );
		try {
			$exists = $this->loadBalancer->getConnection( DB_REPLICA )->selectRow(
				'da_settings',
				'das_name',
				['das_name' => $name],
				__METHOD__
			);
			$res = $exists ? $this->loadBalancer->getConnection( DB_MASTER )->update(
				'da_settings',
				['das_value' => $value],
				['das_name' => $name],
				__METHOD__
			) : $this->loadBalancer->getConnection( DB_MASTER )->insert(
				'da_settings',
				['das_value' => $value, 'das_name' => $name],
				__METHOD__
			);
			if ( !$res ) {
				$status->fatal( 'Unknown Database error' );
			}
		} catch( Exception $e ) {
			$status->fatal( $e->getMessage() );
		}
		return $status;
	}

}
