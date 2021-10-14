<?php

namespace Navindex\SimpleConfig;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Serializable;
use Traversable;

/**
 * Config class.
 *
 * @implements \IteratorAggregate<int>
 * @implements \ArrayAccess<int,int>
 */
class Config implements ArrayAccess, IteratorAggregate, Serializable, Countable
{
    // Replace the original value (default)
    const MERGE_REPLACE = 1;
    
    // Keep the original value
    const MERGE_KEEP = 2;
    
    // Append the new value and convert to array if necessary
    const MERGE_APPEND = 3;

    /**
     * Configuration settings.
     *
     * @var mixed[]
     */
    protected $config = [];

    /**
     * Constructor.
     *
     * @param null|mixed[] $config Configuration array
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? [];
    }

    /**
     * Saves a key value.
     *
     * @param  string $key   Dot notation key
     * @param  mixed  $value Config item value
     * @return self
     */
    public function set(string $key, $value): self
    {
        $config = &$this->config;

        foreach (explode('.', $key) as $k) {
            $config = &$config[$k];
        }

        $config = $value;

        return $this;
    }

    /**
     * Completely removes a key.
     *
     * @param  string $key Dot notation key
     * @return self
     */
    public function unset(string $key): self
    {
        $config = &$this->config;

        foreach (explode('.', $key) as $k) {
            if (!isset($config[$k])) {
                return $this;
            }
            $config = &$config[$k];
        }

        $config = null;

        return $this;
    }

    /**
     * Retrieves a key value.
     *
     * @param  string     $key     Dot notation key
     * @param  mixed|null $default Default value
     * @return mixed      Value or default
     */
    public function get(string $key, $default = null)
    {
        $config = $this->config;

        foreach (explode('.', $key) as $k) {
            if (!isset($config[$k])) {
                return $default;
            }
            $config = $config[$k];
        }

        return $config;
    }

    /**
     * Checks if a key exists and not null.
     *
     * @param  string $key Dot notation key
     * @return bool   True if the key exists, false otherwise
     */
    public function has(string $key): bool
    {
        $config = $this->config;

        foreach (explode('.', $key) as $k) {
            if (!isset($config[$k])) {
                return false;
            }
            $config = $config[$k];
        }

        return true;
    }

    /**
     * Appends value(s) to an array.
     *
     * @param  string        $key   Dot notation key
     * @param  mixed|mixed[] $value Value or values to append
     * @return self
     */
    public function append(string $key, $value): self
    {
        $config = &$this->config;

        foreach (explode('.', $key) as $k) {
            $config = &$config[$k];
        }

        $config = array_merge(static::wrap($config), static::wrap($value));

        return $this;
    }

    /**
     * Substract value(s) from an array.
     *
     * Non-associative arrays will be re-indexed.
     *
     * @param  string        $key   Dot notation key
     * @param  mixed|mixed[] $value Value or values to remove
     * @return self
     */
    public function subtract(string $key, $value): self
    {
        $config = &$this->config;

        foreach (explode('.', $key) as $k) {
            if (!isset($config[$k])) {
                return $this;
            }
            $config = &$config[$k];
        }

        $value = static::wrap($value);

        if (is_array($config)) {
            $config = static::isAssoc($config)
                ? array_diff($config, $value)
                : array_values(array_diff($config, $value));
        } elseif (in_array($config, $value)) {
            $config = [];
        }

        return $this;
    }

    /**
     * Merges another config into this one.
     *
     * @param  null|mixed[]|\Navindex\SimpleConfig\Config $config Configuration array or class
     * @param  null|int                                   $method Merging method
     * @return self
     */
    public function merge($config, ?int $method = null): self
    {
        $config = ($config instanceof self)
            ? $config->toArray()
            : $config ?? [];

        $method = $method ?? self::MERGE_REPLACE;

        if (self::MERGE_KEEP === $method) {
            $base = $config;
            $replacement = $this->config;
        } else {
            $base = $this->config;
            $replacement = $config;
        }

        $this->config = $this->replace($base, $replacement, $method);

        return $this;
    }

    /**
     * Recursive value replacement.
     *
     * @param  null|mixed $base
     * @param  null|mixed $replacement
     * @param  int        $method
     * @return mixed
     */
    protected function replace($base, $replacement, int $method)
    {
        if (empty($replacement)) {
            return $base;
        }

        if (!is_array($base) || !is_array($replacement) || !static::isAssoc($base) || !static::isAssoc($replacement)) {
            return self::MERGE_APPEND === $method
                ? array_unique(array_merge(static::wrap($base), static::wrap($replacement)))
                : $replacement;
        }

        foreach (static::commonKeys($base, $replacement) as $key) {
            $base[$key] = $this->replace($base[$key], $replacement[$key], $method);
        }

        return $base + $replacement;
    }

    /**
     * Splits a sub-array of configuration options into a new config.
     *
     * @param  string                        $key Dot notation key
     * @return \Navindex\SimpleConfig\Config
     */
    public function split(string $key): self
    {
        return new self($this->get($key));
    }

    /**
     * Returns the entire configuration as an array.
     *
     * @return mixed[]
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return $this->has((string) $offset);
    }

    /**
     * @param  mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param  mixed $offset
     * @param  mixed $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->set((string) $offset, $value);
    }

    /**
     * @param  mixed $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        $this->unset((string) $offset);
    }

    /**
     * @return \Traversable <mixed, int>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->config);
    }

    /**
     * Generates a storable representation of the configuration.
     *
     * @return string|null
     */
    public function serialize(): ?string
    {
        return serialize($this->config);
    }

    /**
     * Sets the configuration from a stored representation.
     *
     * @param  mixed $data
     * @return void
     */
    public function unserialize($data): void
    {
        $config = unserialize($data, ['allowed_classes' => false]);
        $this->config = is_array($config) ? $config : [];
    }

    /**
     * Counts the config items.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->config, COUNT_RECURSIVE);
    }

    /**
     * If the given value is not an array, wraps it in one.
     *
     * @param  mixed   $value
     * @return mixed[]
     */
    public static function wrap($value): array
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * Tests if the array is associative.
     *
     * Note that this function will return false if an array is empty. Meaning
     * empty arrays will be treated as if they are not associative arrays.
     *
     * @param  mixed[] $array
     * @return bool
     */
    public static function isAssoc(array $array): bool
    {
        return 0 < count($array) && count(array_filter(array_keys($array), 'is_string')) == count($array);
    }

    /**
     * Returns the keys present in all arrays.
     *
     * @param  mixed[]  $array1
     * @param  mixed[]  $array2
     * @param  mixed[]  ...$_
     * @return string[]
     */
    public static function commonKeys(array $array1, array $array2, array ...$_): array
    {
        return array_keys(array_intersect_key($array1, $array2, ...$_));
    }
}
