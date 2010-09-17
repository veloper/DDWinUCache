# DDWinUCache

## Purpose

A PHP class wrapper for the wincache_ucache* functions.

## Basic Usage

    /**
     * Get Cache Object
     */
    $cache = DDWinUCache::getInstance();
    
    /**
     * Cache Data
     */
    $cache->set('foo', 'bar');
    $cache->set('bar', 'candy');
    $cache->set('hello_world', 'we did it!');
    $cache->set('apple_red_core', 'simple tagging');
    // - or -
    $cache['foo'] = 'bar';
    $cache['bar'] = 'candy';
    $cache['hello_world'] = 'we did it!';
    $cache['apple_red_core'] = 'simple tagging';
    
    /**
     * Delete Cache Entries
     */
    $cache->delete('*or*'); // -> array('apple_red_core', 'hello_world');
    // - or -
    unset($cache['*or*']); // -> array('apple_red_core', 'hello_world');
    
    /**
     * Get Cached Data
     */
    $cache->get('foo'); // -> "bar"
    // - or -
    $cache['*or*']; // -> "bar"

## Website

http://dan.doezema.com/2010/07/wincache-php-class

## License

DDWinUCache is released under the New BSD license.
http://dan.doezema.com/licenses/new-bsd/