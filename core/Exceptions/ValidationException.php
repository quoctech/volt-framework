<?php

declare(strict_types=1);

namespace Volt\Core\Exceptions;

use InvalidArgumentException;

final class ValidationException extends InvalidArgumentException
{
    /** @var array<string, list<string>> */
    private array $fieldErrors = [];

    /**
     * @param array<string, list<string>> $fieldErrors
     */
    public function __construct(
        string $message = 'Validation failed.',
        int $code = 422,
        ?array $fieldErrors = null,
    ) {
        parent::__construct($message, $code);
        $this->fieldErrors = $fieldErrors ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function getFieldErrors(): array
    {
        return $this->fieldErrors;
    }

    /**
     * @return array{message: string, errors: array<string, list<string>>}
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'errors'  => $this->fieldErrors,
        ];
    }
}
