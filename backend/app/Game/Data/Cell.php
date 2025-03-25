<?php

declare(strict_types=1);

namespace App\Game\Data;

use Illuminate\Contracts\Support\Arrayable;

readonly class Cell implements Arrayable
{
    public function __construct(
        public CellType $type,
        public bool $revealed = false,
        public ?int $direction = null,
    ) {}

    public static function fromArray(array $cell): self
    {
        return new self(CellType::from($cell['type']), $cell['revealed'] ?? false, $cell['direction'] ?? null);
    }

    public function toArray(): array
    {
        $data = [
            'type' => $this->type->value,
        ];

        if ($this->direction !== null) {
            $data['direction'] = $this->direction;
        }

        if ($this->revealed) {
            $data['revealed'] = true;
        }

        return $data;
    }

    public function reveal(): self
    {
        return new self($this->type, true, $this->direction);
    }
}
