<?php

namespace DescargaSat\SatAutenticacion\Contracts;

use RuntimeException;

/**
 * El portal del SAT encontró el CFDI pero no entregó ni el XML ni el PDF.
 */
class ArchivoNoDisponible extends RuntimeException {}
