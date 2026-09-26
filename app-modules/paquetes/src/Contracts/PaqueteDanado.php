<?php

namespace DescargaSat\Paquetes\Contracts;

use RuntimeException;

/**
 * El ZIP de un Paquete no se puede extraer de forma segura.
 */
class PaqueteDanado extends RuntimeException {}
