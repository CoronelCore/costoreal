<?php

namespace App\Modules\Costing\Domain;

/** Calculation-local builder; completed sequences own immutable compressed value chunks. */
final class AllocationBuffer
{
    private array $rows = [];

    private array $chunks = [];

    private int $count = 0;

    private bool $finished = false;

    public function add(array $allocation): void
    {
        if ($this->finished) {
            throw new \LogicException('El resultado del reparto ya está completo.');
        }
        $this->rows[] = $allocation;
        $this->count++;
        if (count($this->rows) === AllocationSequence::CHUNK_SIZE) {
            $this->flush();
        }
    }

    public function finish(): AllocationSequence
    {
        $this->flush();
        $this->finished = true;

        return new AllocationSequence($this->chunks, $this->count);
    }

    private function flush(): void
    {
        if ($this->rows) {
            $chunk = gzcompress(json_encode($this->rows, JSON_THROW_ON_ERROR), 1);
            if ($chunk === false) {
                throw new \RuntimeException('No se pudo completar la representación del reparto.');
            }
            $this->chunks[] = $chunk;
            $this->rows = [];
        }
    }
}
