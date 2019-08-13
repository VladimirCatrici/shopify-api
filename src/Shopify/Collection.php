<?php
namespace VladimirCatrici\Shopify;

use ArrayAccess;
use Countable;
use GuzzleHttp\Exception\GuzzleException;
use Iterator;
use LogicException;

class Collection implements Iterator, Countable, ArrayAccess {

    private $endpoint;

    private $options;

    private $api;

    private $limit = 250;

    private $page = 1;

    private $numPages;

    private $items = [];

    private $fetched = false;

    private $partIndex = 0;

    private $count;

    private $currentIndex = 0;

    /**
     * Collection constructor.
     * @param API $shopify
     * @param $endpoint
     * @param array $options
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function __construct(API $shopify, $endpoint, $options = []) {
        $this->api = $shopify;
        $this->endpoint = $endpoint;
        if (array_key_exists('limit', $options)) {
            $this->limit = $options['limit'];
            unset($options['limit']);
        }
        $this->options = $options;

        $this->count = $this->api->get($this->endpoint . '/count', $this->options);
        $this->numPages = ceil($this->count / $this->limit);
    }

    /**
     * @return array
     */
    public function current() {
        return $this->items[$this->partIndex];
    }

    public function key() {
        return $this->currentIndex;
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function next() {
        $this->partIndex++;
        if ($this->partIndex == $this->limit && $this->page < $this->numPages) {
            $this->page++;
            $this->fetch();
        }
        $this->currentIndex++;
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function rewind() {
        $this->page = 1;
        $this->fetch();
        $this->currentIndex = 0;
    }

    public function valid() {
        return isset($this->items[$this->partIndex]);
    }

    /**
     * @return int
     */
    public function count() {
       return $this->count;
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function offsetGet($offset) {
        if (!is_int($offset) || $offset < 0 || $offset >= $this->count) {
            return null;
        }
        $offsetPage = $this->offset2page($offset);
        if ($offsetPage != $this->page || !$this->fetched) {
            $this->page = $offsetPage;
            $this->fetch();
        }
        $this->partIndex = $this->offset2partIndex($offset);
        return isset($this->items[$this->partIndex]) ? $this->items[$this->partIndex] : null;
    }

    public function offsetExists($offset) {
        return is_int($offset) && $offset >= 0 && $offset < $this->count;
    }

    public function offsetSet($offset, $value) {
        throw new LogicException('Shopify collection is read-only. You cannot add new items or change existing');
    }

    public function offsetUnset($offset) {
        throw new LogicException('Shopify collection is read-only. Items deletion prohibited');
    }

    /**
     * Fetches Shopify items based on current parameters like page, limit and options specified on object creation
     * @throws GuzzleException
     * @throws API\RequestException
     */
    private function fetch() {
        $this->items = $this->api->get($this->endpoint, [
            'limit' => $this->limit,
            'page' => $this->page
        ] + $this->options);
        $this->fetched = true;
        $this->partIndex = 0;
    }

    private function offset2page($offset) {
        return floor($offset / $this->limit) + 1;
    }

    private function offset2partIndex($offset) {
        return $offset < $this->limit ? $offset : $offset % $this->limit;
    }
}
