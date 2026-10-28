<?php

namespace DescargaSat\DescargaCfdi\Exceptions;

use RuntimeException;

/**
 * No hay una Fiel activa con la cual iniciar sesión en el portal del SAT.
 */
class SinFielActiva extends RuntimeException {}
