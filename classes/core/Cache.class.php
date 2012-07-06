<?php
/**
 * Cache is a low level service offering volatile key-value pair
 * storage. Chain-of-command allows multiple providers to be used.
 * 
 * Placing all cache providers in this Cache file to avoid costs
 * of including individual providers. For the perf concerned be 
 * encouraged to remove unused cache providers from this file.
 * 
 * @author Kris Jordan <krisjordan@gmail.com>
 * @package Recess PHP Framework
 * @license MIT
 * @link http://www.recessframework.org/
 **/
abstract class Cache {
	protected static $reportsTo;
	
	protected static $inMemory = array();
	
	protected static $distributed = false;

	/**
	 * Push a cache provider onto the providers stack. Most expensive providers
	 * should be pushed first.
	 *
	 * @param ICacheProvider $cache
	 */
	static function reportsTo(ICacheProvider $cache) {
		if(!$cache instanceof ICacheProvider) {
			$cache = new NoOpCacheProvider();
		}
		
		if(isset(self::$reportsTo)) {
			$temp = self::$reportsTo;
			self::$reportsTo = $cache;
			self::$reportsTo->reportsTo($temp);
		} else {
			self::$reportsTo = $cache;
		}		
	}
	
	/**
	 * Set a cache value.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param int $duration
	 */
	static function set($key, $value, $duration = 0, $use_distributed = false) {		
		if($use_distributed == self::$distributed)
			self::$inMemory[$key] = $value;
		self::$reportsTo->set($key, $value, $duration, $use_distributed);
	}
	
	/**
	 * Fetch a value from cache.
	 *
	 * @param string $key
	 * @return mixed
	 */
	static function get($key, $use_distributed = false) {
		if(($use_distributed == self::$distributed) && isset(self::$inMemory[$key])) {
			return self::$inMemory[$key];
		} else {
			return self::$reportsTo->get($key, $use_distributed);
		}
	}
	
	/**
	 * Remove a key value pair from cache.
	 *
	 * @param string $key
	 */
	static function delete($key, $use_distributed = false) {
		if($use_distributed == self::$distributed)
			unset(self::$inMemory[$key]);
		self::$reportsTo->delete($key, $use_distributed);
	}
	
	/**
	 * Clear all values from the cache.
	 */
	static function clear($use_distributed = false) {
		if($use_distributed == self::$distributed)
			self::$inMemory = array();
		self::$reportsTo->clear($use_distributed);
	}
}

/**
 * Common interface for caching subsystems.
 * @author Kris Jordan <krisjordan@gmail.com>
 */
interface ICacheProvider {
	/**
	 * Enter description here...
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param unknown_type $duration
	 */
	function set($key, $value, $duration = 0, $use_distributed = false);
	function get($key, $use_distributed = false);
	function delete($key, $use_distributed = false);
	function clear($use_distributed = false);
}

class NoOpCacheProvider implements ICacheProvider {
	function set($key, $value, $duration = 0, $use_distributed = false) {}
	function get($key, $use_distributed = false) { return false; }
	function delete($key, $use_distributed = false) {}
	function clear($use_distributed = false) {}
}

Cache::reportsTo(new NoOpCacheProvider());

/**
 * Alternative PHP Cache provider. 
 * @see http://us3.php.net/apc
 */
class ApcCacheProvider implements ICacheProvider {
	protected $reportsTo;
	protected $distributed = false;

	function reportsTo(ICacheProvider $cache) {
		if(!$cache instanceof ICacheProvider) {
			$cache = new NoOpCacheProvider();
		}
		
		if(isset($this->reportsTo)) {
			$temp = $this->reportsTo;
			$this->reportsTo = $cache;
			$this->reportsTo->reportsTo($temp);
		} else {
			$this->reportsTo = $cache;
		}
	}
	
	function set($key, $value, $duration = 0, $use_distributed = false) {
		
		if($use_distributed == $this->distributed)
			apc_store($key, $value, $duration);
		$this->reportsTo->set($key, $value, $duration, $use_distributed);		
	}
	
	function get($key, $use_distributed = false) {
		if($use_distributed == $this->distributed)
		{			
			$result = apc_fetch($key);
			if($result === false) {
				$result = $this->reportsTo->get($key, $use_distributed);
				if($result !== false) {
					$this->set($key, $result, 0, $use_distributed);	
				}
			}
		}
		else
			$result = $this->reportsTo->get($key, $use_distributed);
		return $result;
	}
	
	function delete($key, $use_distributed = false) {
		if($use_distributed == $this->distributed)
			apc_delete($key);
		$this->reportsTo->delete($key, $use_distributed);
	}
	
	function clear($use_distributed = false) {
		if($use_distributed == $this->distributed)
			apc_clear_cache('user');
		$this->reportsTo->clear($use_distributed);
	}
}

/**
 * Memcache Provider
 * @see http://us2.php.net/memcache
 */
class MemcacheCacheProvider implements ICacheProvider {
	protected $reportsTo;
	protected $memcache;
	protected $distributed = true;
	
	function __construct($host = 'localhost', $port = 11211) {
		$this->memcache = new Memcache;
		$this->memcache->pconnect($host, $port);
	}

	function reportsTo(ICacheProvider $cache) {
		if(!$cache instanceof ICacheProvider) {
			$cache = new NoOpCacheProvider();
		}
		
		if(isset($this->reportsTo)) {
			$temp = $this->reportsTo;
			$this->reportsTo = $cache;
			$this->reportsTo->reportsTo($temp);
		} else {
			$this->reportsTo = $cache;
		}
	}
	
	function set($key, $value, $duration = 0, $use_distributed = false) {		
		if($use_distributed == $this->distributed)
			$this->memcache->set($key, $value, null, $duration);
		$this->reportsTo->set($key, $value, $duration, $use_distributed);
	}
	
	function get($key, $use_distributed = false) {
		if($use_distributed == $this->distributed)
		{			
			$result = $this->memcache->get($key);
			if($result === false) {
				$result = $this->reportsTo->get($key, $use_distributed);
				if($result !== false) {
					$this->set($key, $result, 0, $use_distributed);	
				}
			}
		}
		else
			$result = $this->reportsTo->get($key, $use_distributed);
		return $result;
	}
	
	function delete($key, $use_distributed = false) {
		if($use_distributed == $this->distributed)
			$this->memcache->delete($key);
		$this->reportsTo->delete($key, $use_distributed);
	}
	
	function clear($use_distributed = false) {
		if($use_distributed == $this->distributed)
			$this->memcache->flush();
		$this->reportsTo->clear($use_distributed, $use_distributed);
	}
}

/**
 * Memcached Provider
 * @see http://us2.php.net/memcached
 */
class MemcachedCacheProvider implements ICacheProvider {
	protected $reportsTo;
	protected $memcached;
	protected $distributed = true;

	function __construct($host = 'localhost', $port = 11211) {
		$this->memcached = new Memcached();
		$this->memcached->addServer($host, $port);
	}

	function reportsTo(ICacheProvider $cache) {
		if(!$cache instanceof ICacheProvider) {
			$cache = new NoOpCacheProvider();
		}

		if(isset($this->reportsTo)) {
			$temp = $this->reportsTo;
			$this->reportsTo = $cache;
			$this->reportsTo->reportsTo($temp);
		} else {
			$this->reportsTo = $cache;
		}
	}

	function set($key, $value, $duration = 0, $use_distributed = false) {		

		if($use_distributed == $this->distributed)
			$this->memcached->set($key, $value, $duration);		
		$this->reportsTo->set($key, $value, $duration, $use_distributed);
	}

	function get($key, $use_distributed = false) {
		if($use_distributed == $this->distributed)
		{
			$result = $this->memcached->get($key);
			if($result === false) {
				$result = $this->reportsTo->get($key, $use_distributed);
				if($result !== false) {
					$this->set($key, $result, 0, $use_distributed);
				}
			}
		}
		else
			$result = $this->reportsTo->get($key, $use_distributed);
		return $result;
	}

	function delete($key, $use_distributed = false) {
		if($use_distributed == $this->distributed)
			$this->memcached->delete($key);
		$this->reportsTo->delete($key,$use_distributed);
	}

	function clear($use_distributed = false) {
		if($use_distributed == $this->distributed)
			$this->memcached->flush();
		$this->reportsTo->clear($use_distributed);
	}
}

/**
 * Provider implemented with Sqlite backend. Less preferable than 
 * APC/Memcache but works well for shared hosts.
 */
class SqliteCacheProvider implements ICacheProvider {
	protected $reportsTo;
	protected $pdo;
	protected $setStatement;
	protected $getStatement;
	protected $getManyStatement;
	protected $deleteStatement;
	protected $time;
	protected $distributed = false;
	
	
	protected $entries = array();

	const VALUE = 0;
	const EXPIRE = 1;
	const KEY = 2;
	
	function __construct() {
		$this->pdo = new Pdo('sqlite:' . $_ENV['dir.temp'] . 'sqlite-cache.db');
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		
		$tries = 0;
		while($tries < 2) {
			try {
				$tries++;
				$this->setStatement = $this->pdo->prepare('INSERT OR REPLACE INTO cache (key,value,expire) values (:key,:value,:expire)');
				$this->getStatement = $this->pdo->prepare('SELECT value,expire FROM cache WHERE key = :key');
				$this->getManyStatement = $this->pdo->prepare('SELECT value,expire,key FROM cache WHERE key LIKE :key');
				break;
			} catch(PDOException $e) {
			
				try {
					$this->pdo->exec('CREATE TABLE "cache" ("key" TEXT PRIMARY KEY  NOT NULL , "value" TEXT NOT NULL , "expire" INTEGER NOT NULL)');
					$this->pdo->exec('CREATE INDEX "expiration" ON "cache" ("expire" ASC)');
				} catch(PDOException $e) {
					if($tries == 2) {
						die('Could not create cache table.');
					}
					sleep(1);
					continue;
				}
			}
		}
		
		$this->time = time();
	}
	
	function reportsTo(ICacheProvider $cache) {
		if(!$cache instanceof ICacheProvider) {
			$cache = new NoOpCacheProvider();
		}
		
		if(isset($this->reportsTo)) {
			$temp = $this->reportsTo;
			$this->reportsTo = $cache;
			$this->reportsTo->reportsTo($temp);
		} else {
			$this->reportsTo = $cache;
		}
	}
	
	function set($key, $value, $duration = 0, $use_distributed = false) {
		if($use_distributed == $this->distributed)
		{
			$this->setStatement->execute(array(':key' => $key, ':value' => serialize($value), ':expire' => $duration == 0 ? 0 : time() + $duration));		
			$this->entries[$key] = $value;
		}
		$this->reportsTo->set($key, $value, $duration, $use_distributed);
	}
	
	function clearStaleEntries() {
		$this->pdo->exec('DELETE FROM cache WHERE expire != 0 AND expire < ' . $this->time);
	}
	
	function get($key, $use_distributed = false) {
		if(($use_distributed == $this->distributed) && isset($this->entries[$key])) {
			return $this->entries[$key];
		}

		if(($starPos = strpos($key,'*')) === false) {
			// Fetch Single
			$this->getStatement->execute(array(':key' => $key));
			$entries = $this->getStatement->fetchAll(PDO::FETCH_NUM);
		} else {
			// Prefetch With Wildcard
			$this->getManyStatement->execute(array(':key' => substr($key,0,$starPos+1) . '%'));
			$entries = $this->getManyStatement->fetchAll(PDO::FETCH_NUM);
		}
		
		$clearStaleEntries = false;
		foreach($entries as $entry) {
			if($entry[self::EXPIRE] == 0 || $entry[self::EXPIRE] <= $this->time) {
				if(isset($entry[self::KEY])) {
					$this->entries[$entry[self::KEY]] = unserialize($entry[self::VALUE]);
				} else {
					$this->entries[$key] = unserialize($entry[self::VALUE]);
				}
			} else {
				$clearStaleEntries = true;
			}
		}
		
		if($clearStaleEntries) {
			$this->clearStaleEntries();
		}
		
		if(($use_distributed == $this->distributed) && isset($this->entries[$key])) {
			return $this->entries[$key];
		} else{
			return $this->reportsTo->get($key, $use_distributed);
		}
	}
	
	function delete($key, $use_distributed = false) {
		if($use_distributed == $this->distributed)
		{
			if($this->deleteStatement == null) {
				$this->deleteStatement = $this->pdo->prepare('DELETE FROM cache WHERE key = :key OR (expire != 0 AND expire < ' . $this->time . ')');
			}
			$this->deleteStatement->execute(array(':key' => $key));
		}
		$this->reportsTo->delete($key, $use_distributed);
	}
	
	function clear($use_distributed = false) {
		if($use_distributed == $this->distributed)
			$this->pdo->exec('DELETE FROM cache');		
		$this->reportsTo->clear($use_distributed);
	}
}
?>