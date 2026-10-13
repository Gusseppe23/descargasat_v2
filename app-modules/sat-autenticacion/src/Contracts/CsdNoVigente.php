<?php

namespace DescargaSat\SatAutenticacion\Contracts;

/**
 * El CSD ya no está vigente (o todavía no entra en vigor), así que el SAT lo rechazaría; no se contacta al SAT.
 */
class CsdNoVigente extends ProblemaConElSat {}
