# DDWinUCache

## Purpose

A PHP class wrapper for the wincache_ucache* functions.

## Basic Usage

    /**
     * Get Cache Object
     */
    $cacheObj = DDWinUCache::getInstance();
    
    /**
     * Cache Data
     */
    $cacheObj->set('foo', 'bar');
    $cacheObj->set('bar', 'candy');
    $cacheObj->set('hello_world', 'we did it!');
    $cacheObj->set('apple_red_core', 'simple tagging');
    
    /**
     * Delete Cache Entries
     */
    $cacheObj->delete('*or*'); // array('apple_red_core', 'hello_world');
    
    /**
     * Get Cached Data
     */
    $cacheObj->get('foo'); // "bar"

## Website

http://dan.doezema.com/2010/07/wincache-php-class

## License

DDWinUCache is released under the New BSD license.
http://dan.doezema.com/licenses/new-bsd/