<?php

namespace DescargaSat\SatAutenticacion\Contracts;

/**
 * No hay una Fiel con la que autenticar el servicio de cancelaciones, así que el SAT lo rechazaría; no se contacta al SAT.
 */
class FielNoUtilizable extends ProblemaConElSat {}
