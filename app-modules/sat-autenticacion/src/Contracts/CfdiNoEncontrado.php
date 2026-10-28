<?php

namespace DescargaSat\SatAutenticacion\Contracts;

use RuntimeException;

/**
 * El portal del SAT no tiene el CFDI entre los emitidos o recibidos de la Fiel.
 */
class CfdiNoEncontrado extends RuntimeException {}
