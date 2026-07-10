<?php

namespace App\Support;

use RuntimeException;

/**
 * Thrown by {@see SafeHttp} when an outbound fetch (or one of its redirect hops)
 * targets a URL that fails the SSRF guard — a non-http(s) scheme, an unresolvable
 * host, or one resolving to a private/reserved address.
 */
class SsrfException extends RuntimeException {}
