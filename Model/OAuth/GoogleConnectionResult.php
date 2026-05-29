<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpGoogleAnalyticsTools\Model\OAuth;

/**
 * Immutable outcome of a successful authorization-code exchange.
 */
class GoogleConnectionResult
{
    /**
     * @param string $refreshToken
     * @param ?string $email
     */
    public function __construct(
        public readonly string $refreshToken,
        public readonly ?string $email
    ) {
    }
}
