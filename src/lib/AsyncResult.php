<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\AgentPlatform;

/**
 * A deferred result of an already started HTTP request.
 *
 * @template T
 */
final class AsyncResult
{
    private bool $resolved = false;

    private mixed $value = null;

    private ?\Throwable $error = null;

    /** @param \Closure(): T $resolver */
    public function __construct(private \Closure $resolver) {}

    /** @return T */
    public function wait(): mixed
    {
        if (!$this->resolved) {
            try {
                $this->value = ($this->resolver)();
            } catch (\Throwable $error) {
                $this->error = $error;
            }
            $this->resolved = true;
        }

        if ($this->error !== null) {
            throw $this->error;
        }

        return $this->value;
    }

    /**
     * @template U
     *
     * @param callable(T): U $mapper
     *
     * @return self<U>
     */
    public function map(callable $mapper): self
    {
        return new self(fn () => $mapper($this->wait()));
    }
}
