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
 */
class Config implements ArrayAccess, IteratorAggregate, Serializable, Countable
{
    const
        MERGE_REPLACE = 1,  // Replace the original value (default)
        MERGE_KEEP    = 2,  // Keep the original value
        MERGE_APPEND  = 3;  // Append the new value and convert to array if necessary

    /**
     * Configuration settings.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Constructor.
     *
     * @param array|null $config Configuration array
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? [];
    }

    /**
     * Stores a key value.
     *
     * @param string $key   Dot notation key
     * @param mixed  $value Config item value
     *
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
     * @param string $key Dot notation key
     *
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
     * @param string     $key     Dot notation key
     * @param mixed|null $default Default value
     *
     * @return mixed Value or default
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
     * Probes a configuration key.
     *
     * @param string $key Dot notation key
     *
     * @return boolean True if the key exists, false otherwise
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
     * @param string        $key   Dot notation key
     * @param mixed|mixed[] $value Value or values to append
     *
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
     * @param string        $key   Dot notation key
     * @param mixed|mixed[] $value Value or values to remove
     *
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
     * Merge a config array into this one.
     *
     * @param array        $config Configuration array
     * @param null|integer $type   Merge type
     *
     * @return self
     */
    public function merge(?array $config, ?int $type = self::MERGE_REPLACE): self
    {
        $config = $config ?? [];

        if (self::MERGE_KEEP === $type) {
            $base = $config;
            $replacement = $this->config;
        } else {
            $base = $this->config;
            $replacement = $config;
        }

        $this->config = $this->replace($base, $replacement, $type);

        return $this;
    }

    /**
     * Recursive value replacement.
     *
     * @param null|mixed $base
     * @param null|mixed $replacement
     * @param integer    $type
     *
     * @return mixed
     */
    protected function replace($base, $replacement, int $type)
    {
        if (!is_array($base) || !is_array($replacement) || !static::isAssoc($base) || !static::isAssoc($replacement)) {
            return self::MERGE_APPEND === $type
                ? array_unique(array_merge(static::wrap($base), static::wrap($replacement)))
                : $replacement;
        }

        foreach (static::commonKeys($base, $replacement) as $key) {
            $base[$key] = $this->replace($base[$key], $replacement[$key], $type);
        }

        return $base + $replacement;
    }

    /**
     * Split a sub-array of configuration options into a new config.
     *
     * @param string $key Dot notation key
     *
     * @return \Navindex\SimpleConfig\Config
     */
    public function split(string $key): Config
    {
        return new self($this->get($key));
    }

    /**
     * Returns the entire configuration as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * @param mixed $offset
     *
     * @return boolean
     */
    public function offsetExists($offset): bool
    {
        return $this->has((string)$offset);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     *
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->set((string)$offset, $value);
    }

    /**
     * @param mixed $offset
     *
     * @return void
     */
    public function offsetUnset($offset): void
    {
        $this->unset((string)$offset);
    }

    /**
     * @return \Traversable
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->config);
    }

    /**
     * @return string|null
     */
    public function serialize(): ?string
    {
        return serialize($this->config);
    }

    /**
     * @param mixed $data
     *
     * @return void
     */
    public function unserialize($data): void
    {
        $config = unserialize($data, ['allowed_classes' => false]);
        $this->config = is_array($config) ? $config : [];
    }

    /**
     *
     * @return integer
     */
    public function count(): int
    {
        return count($this->config);
    }

    /**
     * If the given value is not an array, wraps it in one.
     *
     * @param mixed $value
     *
     * @return array
     */
    public static function wrap($value)
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * Tests if array is an associative array
     *
     * Note that this function will return false if an array is empty. Meaning
     * empty arrays will be treated as if they are not associative arrays.
     *
     * @param mixed[] $array
     *
     * @return boolean
     */
    public static function isAssoc(array $array): bool
    {
        return 0 < count($array) && count(array_filter(array_keys($array), 'is_string')) == count($array);
    }

    /**
     * Returns the keys present in all arrays
     *
     * @param array $array1
     * @param array $array2
     * @param array ...$_
     *
     * @return array
     */
    public static function commonKeys(array $array1, array $array2, array ...$_): array
    {
        return array_keys(array_intersect_key($array1, $array2, ...$_));
    }
}
