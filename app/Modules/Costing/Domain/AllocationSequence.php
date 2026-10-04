<?php

namespace App\Modules\Costing\Domain;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use LogicException;
use OutOfBoundsException;
use Traversable;

/** Complete trace values in bounded pure-data chunks; iteration never expands the whole sequence. */
final readonly class AllocationSequence implements ArrayAccess, Countable, IteratorAggregate
{
    public const CHUNK_SIZE = 256;

    public function __construct(private array $chunks, private int $size) {}

    public function count(): int
    {
        return $this->size;
    }

    public function getIterator(): Traversable
    {
        foreach ($this->chunks as $chunk) {
            foreach (json_decode(gzuncompress($chunk), true, flags: JSON_THROW_ON_ERROR) as $row) {
                yield $row;
            }
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_int($offset) && $offset >= 0 && $offset < $this->size;
    }

    public function offsetGet(mixed $offset): array
    {
        if (! $this->offsetExists($offset)) {
            throw new OutOfBoundsException('La asignación solicitada no existe.');
        }
        $rows = json_decode(gzuncompress($this->chunks[intdiv($offset, self::CHUNK_SIZE)]), true, flags: JSON_THROW_ON_ERROR);

        return $rows[$offset % self::CHUNK_SIZE];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Las asignaciones calculadas son inmutables.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Las asignaciones calculadas son inmutables.');
    }
}
