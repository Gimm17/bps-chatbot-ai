<?php

namespace App\Bps;

use RuntimeException;

/**
 * Dilempar BpsApiClient saat timeout/network error.
 * Tool menangkap ini dan return teks aman ke LLM (bukan crash agent).
 */
final class BpsApiException extends RuntimeException {}
