<?php

declare(strict_types=1);

namespace Sample;

/**
 * Thrown by `ClientFactory::make()` when neither the session nor `.env`
 * has a usable LogDB API key. The front controller catches it and
 * redirects to /auth instead of bubbling a 500.
 */
final class MissingApiKey extends \RuntimeException
{
}
