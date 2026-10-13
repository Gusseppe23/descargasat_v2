<?php

namespace DescargaSat\SatAutenticacion\Services;

use DescargaSat\SatAutenticacion\Contracts\WebClientCancelaciones;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;

class GuzzleWebClientCancelaciones extends GuzzleWebClient implements WebClientCancelaciones {}
