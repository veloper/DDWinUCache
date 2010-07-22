<?php
/**
 * Copyright (c) 2010, Daniel Doezema
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * The names of the contributors and/or copyright holder may not be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL DANIEL DOEZEMA BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * Class for interacting with WinCache's User Cache
 *
 * @copyright  Copyright (c) 2010 Daniel Doezema. (http://dan.doezema.com)
 * @license    http://dan.doezema.com/licenses/new-bsd     New BSD License
 */
class DDWinUCache implements ArrayAccess {

	// Used to determine if a string is a regular expression.
	const REGEXP_DELIMITER = '/';

	/**
	* The TTL in seconds used when $ttl is 
	* omitted in $this->set();
	*
	* @var int
	*/
	protected $defaultTTLInt = '0';

	/**
	* Results from wincache_ucache_info();
	* @var array
	*/
	protected $infoArr = array();

	/**
	* Results from wincache_ucache_meminfo();
	* @var array
	*/
	protected $memInfoArr = array();

	/**
	* Success result from the last $this->dec();
	* @var bool
	*/
	protected $lastDecResult = false;

	/**
	* Success result from the last $this->inc();
	* @var bool
	*/
	protected $lastIncResult = false;

	/**
	* Success result from the last $this->get();
	* @var bool
	*/
	protected $lastGetResult = false;

	/**
	* Singleton :: The DDWinUCache instance.
	* @var DDWinUCache
	*/
	protected static $instanceObj;


	/**
     * Returns a single instance of DDWinUCache within 
     * the current request's global scope.
     * 
     * @return DDWinUCache
     */
	public static function getInstance() {
		if((self::$instanceObj instanceof DDWinUCache) == false) {
			self::$instanceObj = new self;
		}
		return self::$instanceObj;
	}
	
	/**
     * Creates a WinUCache instance.
     * 
     * @throws Exception When the WinCache version >= 1.1.0 is not installed.
     */
	protected function __construct() {
		if(!$this->isWinUCacheInstalled()) {
			throw new Exception('WinCache version >= 1.1.0 is not installed.');
		}
	}
	
	/**
	 * Get the default TTL used when $ttl is 
     * omitted in $this->set();
	 *
     * @return int
     */
	public function getDefaultTTL() {
		return $this->defaultTTLInt;
	}

	/**
     * A shortcut to the wincache_ucahce_info()['ucache_entries'] array.
     * 
     * @see wincache_ucache_meminfo();
     * @return array
     */
	public function getEntriesArr(){
		$infoArr = $this->getInfoArr();
		return isset($infoArr['ucache_entries']) ? $infoArr['ucache_entries'] : array();
	}

	/**
	 * Get the success result of the last $this->dec() call
	 *
     * @return bool
     */
	public function getLastDecResult() {
		return $this->lastDecResult;
	}
	
	/**
	 * Get the success result of the last $this->get() call
	 *
     * @return bool
     */
	public function getLastGetResult() {
		return $this->lastGetResult;
	}
	
	/**
	 * Get the success result of the last $this->inc() call
	 *
     * @return bool
     */
	public function getLastIncResult() {
		return $this->lastIncResult;
	}
	
	/**
	 * Get the amount of free memory in bytes available for 
	 * the user cache
     *
     * @see wincache_ucache_meminfo();
     * @return int
     */
	public function getMemoryFree() {
		$memInfoArr = $this->getMemInfoArr();
		return $memInfoArr['memory_free'];
	}
	
	/**
	 * Get the number of free memory blocks available for 
	 * the user cache
	 *
     * @see wincache_ucache_meminfo();
     * @return int
     */
	public function getMemoryFreeBlocks() {
		$memInfoArr = $this->getMemInfoArr();
		return $memInfoArr['num_free_blks'];
	}
	
	/**
	 * Get the amount of memory in bytes used for the 
	 * user cache internal structures 
     *
     * @see wincache_ucache_meminfo();
     * @return int
     */
	public function getMemoryOverhead() {
		$memInfoArr = $this->getMemInfoArr();
		return $memInfoArr['memory_overhead'];
	}
	
	/**
	 * Get the amount of memory in bytes allocated for
	 * the user cache
     *
     * @see wincache_ucache_meminfo();
     * @return int
     */
	public function getMemoryTotal() {
		$memInfoArr = $this->getMemInfoArr();
		return $memInfoArr['memory_total'];
	}
	
	/**
     * Get the percentage of memory used for the user cache
     *
     * @param int $precisionInt
     * @return float
     */
	public function getMemoryUsedPercent($precisionInt = 2) {
		return round((($this->getMemoryUsed() / $this->getMemoryTotal()) * 100), (int) $precisionInt);
	}
	
	/**
     * Get the amount of memory in bytes used by the user cache
     *
     * @return int
     */
	public function getMemoryUsed() {
		return $this->getMemoryTotal() - $this->getMemoryFree();
	}
	
	/**
	 * Get the number of memory blocks used by the user cache
     *
     * @see wincache_ucache_meminfo();
     * @return int
     */
	public function getMemoryUsedBlocks() {
		$memInfoArr = $this->getMemInfoArr();
		return $memInfoArr['num_used_blks'];
	}
	
	/**
	 * Get the number of times the data has been served from the
	 * the user cache
     *
     * @see wincache_ucache_info();
     * @return int
     */
	public function getTotalHitCount() {
		$memInfoArr = $this->getInfoArr();
		return $memInfoArr['total_hit_count'];
	}
	
	/**
	 * Get the total number of elements that are currently in
	 * the user cache
     *
     * @see wincache_ucache_info();
     * @return int
     */
	public function getTotalItemCount() {
		$memInfoArr = $this->getInfoArr();
		return $memInfoArr['total_item_count'];
	}
	
	/**
	 * Get the number of times the data has not been found in
	 * the user cache
     *
     * @see wincache_ucache_info();
     * @return int
     */
	public function getTotalMissCount() {
		$memInfoArr = $this->getInfoArr();
		return $memInfoArr['total_miss_count'];
	}
	
	/**
	 * Get the total time in seconds that the user cache
	 * has been active
     *
     * @see wincache_ucache_info();
     * @return int
     */
	public function getTotalUptime() {
		$memInfoArr = $this->getInfoArr();
		return $memInfoArr['total_cache_uptime'];
	}
	
	/**
	 * Check if cache is local
	 *
	 * true if the cache metadata is for a local cache instance,
	 * false if the metadata is for the global cache 
     *
     * @see wincache_ucache_info();
     * @return bool
     */
	public function isLocalCache() {
		$memInfoArr = $this->getInfoArr();
		return $memInfoArr['is_local_cache'];
	}
	
	/**
     * @see wincache_ucache_add();
     */
	public function add($key, $value, $ttl = 0) {
		$result = wincache_ucache_add($key, $value, $ttl);
		$this->clearInfoArrays();
		return $result;
	}
	
	/**
     * @see wincache_ucache_clear();
     */
	public function clear() {
		$result = wincache_ucache_clear();
		$this->clearInfoArrays();
		return $result;
	}
	
	/**
	 * Decrements the value associated with the key 
	 *
	 * The third parameter has been replaced by
	 * $this->getLastDecResult();
	 *
     * @see wincache_ucache_dec();
     */
	public function dec($key, $decBy = 1) {
		$result = wincache_ucache_dec($key, $decBy, $this->lastDecResult);
		$this->clearInfoArrays();
		return $result;
	}

	/**
	 * Delete a cache entry via three possible methods...
	 *   + Pass a Regular Expression (PCRE) string.
	 *      + Requirement: self::REGEXP_DELIMITER must be the delimiter.
	 *   + Pass a search string with *unix based wild cards (* and ?).
	 *   + Pass the key name of the cache entry.
	 * @see wincache_ucache_del();
	 * @param string;
	 * @return array; The number of cache entries deleted.
	 */
	public function delete($searchStr) {
		$regExpStr = false;
		if(substr($searchStr, 0, 1) == self::REGEXP_DELIMITER) {
			$regExpStr = $searchStr;
		} else if((strpos($searchStr, '*') !== false) || (strpos($searchStr, '?') !== false)) {
			$regExpStr = $this->getWildCardRegExp($searchStr);
		}
		$keyMix = $searchStr;
		if($regExpStr && count($cacheIdsArr = $this->getCacheIdsByRegExp($regExpStr))) {
			$keyMix = $cacheIdsArr;
		}
		if($result = wincache_ucache_delete($keyMix)) {
			$this->clearInfoArrays();
		}
		return $result;
	}
	
	/**
     * @see wincache_ucache_exists();
     */
	public function exists($key) {
		return wincache_ucache_exists($key);
	}
	
	/**
	 * Gets a variable stored in the user cache
	 *
	 * The second parameter has been replaced by
	 * $this->getLastGetResult();
	 *
     * @see wincache_ucache_get();
     */
	public function get($key) {
		return wincache_ucache_get($key, $this->lastGetResult);
	}
	
	/**
	 * Increments the value associated with the key
	 *
	 * The third parameter has been replaced by
	 * $this->getLastIncResult();
	 *
     * @see wincache_ucache_inc();
     */
	public function inc($key, $incBy = 1) {
		$result = wincache_ucache_inc($key, $incBy, $this->lastIncResult);
		$this->clearInfoArrays();
		return $result;
	}
	
	/**
     * @see wincache_ucache_info();
     */
	public function info($summaryOnly = false, $key = null) {
		if(is_string($key)) {
			return wincache_ucache_info($summaryOnly, $key);
		}
		return wincache_ucache_info($summaryOnly);
	}
	
	/**
     * @see wincache_ucache_meminfo();
     */
	public function memInfo() {
		return wincache_ucache_meminfo();
	}
	
	/**
     * @see wincache_ucache_set();
     */
	public function set($key, $value, $ttl = null) {
		$result = wincache_ucache_set($key, $value, (is_null($ttl) ? $this->getDefaultTTL() : $ttl));
		$this->clearInfoArrays();
		return $result;
	}
	
	/**
	 * Set the default TTL used when $ttl is 
     * omitted in $this->set();
	 *
     * @return void
     */
	public function setDefaultTTL($ttlInt) {
		$this->defaultTTLInt = $ttlInt;
	}
	
		
	/**
     * Internal method that returns the internal 
     * info array.
     *
     * If the array is not populated a call to
     * set the data will be made before the return
     *
     * @return array
     */
	protected function getInfoArr() {
		if(!$this->hasInfo()) {
			$this->setInfoArr();
		}
		return $this->infoArr;
	}
	/**
     * Internal method that checks if the internal 
     * info array has been populated.
     *
     * @return bool
     */
	protected function hasInfo() {
		return isset($this->infoArr['ucache_entries']);
	}
	
	/**
     * Internal method that clears the internal info 
     * array.
     *
     * @return void
     */
	protected function clearInfo() {
		$this->infoArr = array();
	}
	
	/**
     * Internal method that sets the internal info 
     * array data.
     *
     * This internal array is used to avoid extra
     * calls to wincache_ucahce_info();
     *
     * @return bool
     */
	protected function setInfoArr() {
		$this->infoArr = $this->info();
		return $this->hasInfo();
	}
	
	/**
     * Internal method that returns the internal 
     * meminfo array.
     *
     * If the array is not populated a call to
     * set the data will be made before the return
     *
     * @return array
     */
	protected function getMemInfoArr() {
		if(!$this->hasMemInfo()) {
			$this->setMemInfoArr();
		}
		return $this->memInfoArr;
	}
	
	/**
     * Internal method that checks if the internal 
     * meminfo array has been populated.
     *
     * @return bool
     */
	protected function hasMemInfo() {
		return isset($this->memInfoArr['memory_total']);
	}
	
	/**
     * Internal method that clears the internal meminfo 
     * array.
     *
     * @return void
     */
	protected function clearMemInfo() {
		$this->memInfoArr = array();
	}
	
	/**
     * Internal method that sets the internal meminfo 
     * array data.
     *
     * This internal array is used to avoid extra
     * calls to wincache_ucahce_meminfo();
     *
     * @return bool
     */
	protected function setMemInfoArr() {
		$this->memInfoArr = $this->memInfo();
		return $this->hasMemInfo();
	}
	
	/**
     * Internal shortcut method that clears both the 
     * info and meminfo property arrays.
     *
     * @return void
     */
	protected function clearInfoArrays() {
		$this->clearInfo();
		$this->clearMemInfo();
	}
	/**
     * Internal method to test if the proper version 
     * of WinCache is installed.
     *
     * @return bool
     */
	protected function isWinUCacheInstalled() {
		return function_exists('wincache_ucache_info');
	}
	
	/**
     * Internal method to get an array of cache entry
     * key_names that match a passed regular expression.
     *
     * @param  string $regExpStr
     * @return array
     */
	protected function getCacheIdsByRegExp($regExpStr) {
		$array = array();
		foreach($this->getEntriesArr() as $entryKeyInt => $entryArr) {
			if(preg_match($regExpStr, $entryArr['key_name'])) {
				$array[$entryKeyInt] = $entryArr['key_name'];
			}
		}
		return $array;
	}
	
	/**
     * Internal method to create the regular expression
     * that represents the WildCard based search string
     *
     * @param  string $searchStr
     * @return string
     */
	protected function getWildCardRegExp($searchStr) {
		$regExpStr = '/';
		$regExpStr .= (substr($searchStr,0,1) != '*') ? '^' : '';
		$regExpStr .= str_replace(array('*','?'), array('.*?','.{1}'), $searchStr);
		$regExpStr .= (substr($searchStr,-1) != '*') ? '$' : '';
		$regExpStr .= '/';
		return $regExpStr;
	}
	
	/**
	 * ArrayAccess Methods
	 */
	public function offsetSet($offset, $value) {
		if (is_null($offset)) {
			throw new Exception('A key_name must be given.');
		} else {
			$this->set($offset, $value);
		}
	}
	public function offsetExists($offset) {
		return $this->exists($offset) ? true : false;
	}
    public function offsetUnset($offset) {
        $this->delete($offset);
    }
    public function offsetGet($offset) {
		$valueMix = $this->get($offset);
        return $this->getLastGetResult() ? $valueMix : null;
    }
	
}