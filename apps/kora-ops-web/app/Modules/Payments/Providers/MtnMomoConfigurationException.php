<?php

namespace App\Modules\Payments\Providers;

use RuntimeException;

/**
 * MTN refused the selected target environment.
 *
 * This is a configuration error (for example `mtnbenin` while the adapter runs
 * against the sandbox), not a transient provider outage. Callers must fail the
 * payment instead of leaving it pending forever.
 */
final class MtnMomoConfigurationException extends RuntimeException {}
