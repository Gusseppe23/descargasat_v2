<?php

namespace DescargaSat\SatAutenticacion\Contracts;

use RuntimeException;

/**
 * La Fiel ya no está vigente, así que el SAT la rechazaría.
 */
class FielNoVigente extends RuntimeException {}
