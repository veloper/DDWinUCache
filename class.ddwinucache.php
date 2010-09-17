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

class DDWinUCacheException extends Exception {}

/**
 * Class for interacting with WinCache's User Cache functionality.
 *
 * @copyright  Copyright (c) 2010 Daniel Doezema. (http://dan.doezema.com)
 * @license    http://dan.doezema.com/licenses/new-bsd     New BSD License
 */
class DDWinUCache implements ArrayAccess {
    
    /**
     * The TTL in seconds used when $ttl is omitted in $this->set();
     * @var int
     */
    protected $default_ttl = 0; // 0 = Never Expires = Wincache Default

    /**
     * Results from wincache_ucache_info();
     *
     * @var array
     */
    protected $info_array = array();

    /**
     * Results from wincache_ucache_meminfo();
     *
     * @var array
     */
    protected $mem_info_array = array();

    /**
     * Success result from the last $this->dec();
     *
     * @var bool
     */
    protected $last_dec_result = false;

    /**
     * Success result from the last $this->inc();
     *
     * @var bool
     */
    protected $last_inc_result = false;

    /**
     * Success result from the last $this->get();
     *
     * @var bool
     */
    protected $last_get_result = false;

    /**
     * Used to determine if a search string is a regular expression.
     *
     * @var string
     */
    protected $reg_exp_delimiter = '/';

    /**
     * Singleton :: The DDWinUCache instance.
     *
     * @var DDWinUCache
     */
    protected static $instance;

    /**
     * Returns a single instance of DDWinUCache within the current request's global scope.
     *
     * @return DDWinUCache
     */
    public static function getInstance() {
        if((self::$instance instanceof DDWinUCache) == false) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * Creates a WinUCache instance.
     *
     * @throws Exception When the WinCache version >= 1.1.0 is not installed.
     */
    protected function __construct() {
        if(!$this->isWinUCacheInstalled()) {
            throw new DDWinUCacheException('WinCache version >= 1.1.0 is not installed.');
        }
    }

    /**
     * Get the default TTL used when $ttl is omitted in $this->set();
     *
     * @return int
     */
    public function getDefaultTTL() {
        return $this->default_ttl;
    }

    /**
     * A shortcut to the wincache_ucahce_info()['ucache_entries'] array.
     *
     * @see wincache_ucache_meminfo();
     * @return array
     */
    public function getEntriesArray() {
        $info_array = $this->getInfoArray();
        return isset($info_array['ucache_entries']) ? $info_array['ucache_entries'] : array();
    }

    /**
     * Get the success result of the last $this->dec() call.
     *
     * @return bool
     */
    public function getLastDecResult() {
        return $this->last_dec_result;
    }

    /**
     * Get the success result of the last $this->get() call.
     *
     * @return bool
     */
    public function getLastGetResult() {
        return $this->last_get_result;
    }

    /**
     * Get the success result of the last $this->inc() call.
     *
     * @return bool
     */
    public function getLastIncResult() {
        return $this->last_inc_result;
    }

    /**
     * Get the amount of free memory in bytes available for the user cache.
     *
     * @see wincache_ucache_meminfo();
     * @return int
     */
    public function getMemoryFree() {
        $mem_info_array = $this->getMemInfoArray();
        return $mem_info_array['memory_free'];
    }

    /**
     * Get the number of free memory blocks available for the user cache.
     *
     * @see wincache_ucache_meminfo();
     * @return int
     */
    public function getMemoryFreeBlocks() {
        $mem_info_array = $this->getMemInfoArray();
        return $mem_info_array['num_free_blks'];
    }

    /**
     * Get the amount of memory in bytes used for the user cache internal structures.
     *
     * @see wincache_ucache_meminfo();
     * @return int
     */
    public function getMemoryOverhead() {
        $mem_info_array = $this->getMemInfoArray();
        return $mem_info_array['memory_overhead'];
    }

    /**
     * Get the amount of memory in bytes allocated for the user cache.
     *
     * @see wincache_ucache_meminfo();
     * @return int
     */
    public function getMemoryTotal() {
        $mem_info_array = $this->getMemInfoArray();
        return $mem_info_array['memory_total'];
    }

    /**
     * Get the percentage of memory used for the user cache.
     *
     * @param int
     * @return float
     */
    public function getMemoryUsedPercent($precision = 2) {
        return round((($this->getMemoryUsed() / $this->getMemoryTotal()) * 100), (int) $precision);
    }

    /**
     * Get the amount of memory in bytes used by the user cache.
     *
     * @return int
     */
    public function getMemoryUsed() {
        return ($this->getMemoryTotal() - $this->getMemoryFree());
    }

    /**
     * Get the number of memory blocks used by the user cache.
     *
     * @see wincache_ucache_meminfo();
     * @return int
     */
    public function getMemoryUsedBlocks() {
        $mem_info_array = $this->getMemInfoArray();
        return $mem_info_array['num_used_blks'];
    }

    /**
     * Get the number of times the data has been served from the the user cache.
     *
     * @see wincache_ucache_info();
     * @return int
     */
    public function getTotalHitCount() {
        $info_array = $this->getInfoArray();
        return $info_array['total_hit_count'];
    }

    /**
     * Get the total number of elements that are currently in the user cache.
     *
     * @see wincache_ucache_info();
     * @return int
     */
    public function getTotalItemCount() {
        $info_array = $this->getInfoArray();
        return $info_array['total_item_count'];
    }

    /**
     * Get the number of times the data has not been found in the user cache.
     *
     * @see wincache_ucache_info();
     * @return int
     */
    public function getTotalMissCount() {
        $info_array = $this->getInfoArray();
        return $info_array['total_miss_count'];
    }

    /**
     * Get the total time in seconds that the user cache has been active.
     *
     * @see wincache_ucache_info();
     * @return int
     */
    public function getTotalUptime() {
        $info_array = $this->getInfoArray();
        return $info_array['total_cache_uptime'];
    }

    /**
     * Checks if cache is local.
     *
     * true if the cache metadata is for a local cache instance,
     * false if the metadata is for the global cache.
     *
     * @see wincache_ucache_info();
     * @return bool
     */
    public function isLocalCache() {
        $info_array = $this->getInfoArray();
        return $info_array['is_local_cache'];
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
     * Decrements the value associated with the key.
     *
     * The third parameter has been replaced by $this->getLastDecResult();
     *
     * @see wincache_ucache_dec();
     */
    public function dec($key, $dec_by = 1) {
        $result = wincache_ucache_dec($key, $dec_by, $this->lastDecResult);
        $this->clearInfoArrays();
        return $result;
    }

    /**
     * Delete a cache entry via three possible methods...
     *   + Pass a Regular Expression (PCRE) string.
     *      + Requirement: $this->reg_exp_delimiter must be the delimiter.
     *   + Pass a search string with *unix based wild cards (* and ?).
     *   + Pass the key name of the cache entry.
     *
     * @see wincache_ucache_del();
     * @param string;
     * @return array; The number of cache entries deleted.
     */
    public function delete($search) {
        $reg_exp = false;
        if($this->isSearchRegExp($search)) {
            $reg_exp = $search;
        } else if($this->isSearchWildCard($search)) {
            $reg_exp = $this->getWildCardRegExp($search);
        }
        $key = $search;
        if(is_string($reg_exp) && (count($cache_ids_array = $this->getCacheIdsByRegExp($reg_exp)) > 0)) {
            $key = $cache_ids_array;
        }
        if($result = wincache_ucache_delete($key)) {
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
     * Gets a variable stored in the user cache.
     *
     * The second parameter has been replaced by $this->getLastGetResult();
     *
     * @see wincache_ucache_get();
     */
    public function get($key) {
        return wincache_ucache_get($key, $this->last_get_result);
    }

    /**
     * Increments the value associated with the key
     *
     * The third parameter has been replaced by $this->getLastIncResult();
     *
     * @see wincache_ucache_inc();
     */
    public function inc($key, $inc_by = 1) {
        $result = wincache_ucache_inc($key, $inc_by, $this->last_inc_result);
        $this->clearInfoArrays();
        return $result;
    }

    /**
     * @see wincache_ucache_info();
     */
    public function info($summary_only = false, $key = null) {
        if(is_string($key)) {
            return wincache_ucache_info($summary_only, $key);
        }
        return wincache_ucache_info($summary_only);
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
     * Set the default TTL used when $ttl is omitted in $this->set();
     *
     * @param int; time in seconds
     * @return void
     */
    public function setDefaultTTL($ttl) {
        $this->default_ttl = $ttl;
    }

    /**
     * Internal method that returns $this->info_array.
     *
     * If the array is not populated it will be lazy loaded.
     *
     * @return array
     */
    protected function getInfoArray() {
        if(!$this->hasInfo()) {
            $this->populateInfoArray();
        }
        return $this->info_array;
    }

    /**
     * Internal method that checks if $this->info_array has been populated.
     *
     * @return bool
     */
    protected function hasInfo() {
        return isset($this->info_array['ucache_entries']);
    }

    /**
     * Internal method that clears $this->info_array.
     *
     * @return void
     */
    protected function clearInfo() {
        $this->info_array = array();
    }

    /**
     * Internal method that populates $this->info_array.
     *
     * This internal array is used to avoid extra calls to wincache_ucahce_info();
     *
     * @return bool
     */
    protected function populateInfoArray() {
        $this->info_array = $this->info();
        return $this->hasInfo();
    }

    /**
     * Internal method that returns $this->mem_info_array.
     *
     * If the array is not populated it will be lazy loaded.
     *
     * @return array
     */
    protected function getMemInfoArray() {
        if(!$this->hasMemInfo()) {
            $this->populateMemInfoArray();
        }
        return $this->mem_info_array;
    }

    /**
     * Internal method that checks if $this->mem_info_array has been populated.
     *
     * @return bool
     */
    protected function hasMemInfo() {
        return isset($this->mem_info_array['memory_total']);
    }

    /**
     * Internal method that clears $this->mem_info_array.
     *
     * @return void
     */
    protected function clearMemInfo() {
        $this->mem_info_array = array();
    }

    /**
     * Internal method that populates $this->mem_info_array.
     *
     * This internal array is used to avoid extra calls to wincache_ucahce_meminfo();
     *
     * @return bool
     */
    protected function populateMemInfoArray() {
        $this->mem_info_array = $this->memInfo();
        return $this->hasMemInfo();
    }

    /**
     * Internal shortcut method that clears both $this->info_array and $this->mem_info_array.
     *
     * @return void
     */
    protected function clearInfoArrays() {
        $this->clearInfo();
        $this->clearMemInfo();
    }

    /**
     * Internal method to test if the proper version of WinCache is installed.
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
     * @param string
     * @return array
     */
    protected function getCacheIdsByRegExp($reg_exp) {
        $results = array();
        foreach($this->getEntriesArray() as $key => $entry) {
            if(preg_match($reg_exp, $entry['key_name'])) {
                $results[$key] = $entry['key_name'];
            }
        }
        return $results;
    }

    /**
     * Internal method to create the regular expression
     * string from a *unix wild card based search string.
     *
     * @param string
     * @return string
     */
    protected function getWildCardRegExp($string) {
        $reg_exp = '/';
        $reg_exp .= (substr($string,0,1) != '*') ? '^' : '';
        $reg_exp .= str_replace(array('*','?'), array('.*?','.{1}'), $string);
        $reg_exp .= (substr($string,-1) != '*') ? '$' : '';
        $reg_exp .= '/';
        return $reg_exp;
    }

    /**
     * Internal method to check if a passed search string is a regular
     * expression based on the presence of $this->reg_exp_delimiter
     * at the beginning of the string.
     *
     * @param string
     * @return bool
     */
    protected function isSearchRegExp($string) {
        return (substr($string, 0, 1) == $this->reg_exp_delimiter);
    }

    /**
     * Internal method to check if a passed search string contains
     * any *unix based wild card (* or ?).
     *
     * @param string
     * @return bool
     */
    protected function isSearchWildCard($string) {
        return (strpos($string, '*') !== false) || (strpos($string, '?') !== false);
    }

    /**
     * ArrayAccess Methods
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            throw new DDWinUCacheException('A key_name must be given.');
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