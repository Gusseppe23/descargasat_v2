<?php

namespace DescargaSat\SatAutenticacion\Services;

use DescargaSat\SatAutenticacion\Contracts\WebClientConsultaCfdi;
use PhpCfdi\SatWsDescargaMasiva\WebClient\GuzzleWebClient;

class GuzzleWebClientConsultaCfdi extends GuzzleWebClient implements WebClientConsultaCfdi {}
