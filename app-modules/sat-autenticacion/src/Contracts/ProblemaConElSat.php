<?php

namespace DescargaSat\SatAutenticacion\Contracts;

use RuntimeException;

/**
 * El SAT no dio una respuesta útil, o ni siquiera se le pudo preguntar.
 */
class ProblemaConElSat extends RuntimeException {}
