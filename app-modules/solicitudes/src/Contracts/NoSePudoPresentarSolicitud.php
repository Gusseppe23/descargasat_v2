<?php

namespace DescargaSat\Solicitudes\Contracts;

use RuntimeException;

/**
 * No se pudo presentar la Solicitud: no hay Fiel activa, la Fiel no está vigente o el SAT no respondió.
 */
final class NoSePudoPresentarSolicitud extends RuntimeException {}
