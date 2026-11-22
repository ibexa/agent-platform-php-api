<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\AgentPlatform;

final class ResponseStatus
{
    public static function withinRange(
        string $rangeCode,
        int $statusCode
    ): bool {
        $left = (int) ($rangeCode[0] . '00');
        $right = (int) ($rangeCode[0] . '99');

        return $statusCode >= $left && $statusCode <= $right;
    }
}
