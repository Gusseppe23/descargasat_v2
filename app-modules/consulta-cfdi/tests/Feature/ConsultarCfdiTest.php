<?php

use DescargaSat\ConsultaCfdi\Actions\ConsultarCfdi;
use DescargaSat\SatAutenticacion\Contracts\CodigoConsultaCfdi;
use DescargaSat\SatAutenticacion\Contracts\WebClientConsultaCfdi;
use Tests\Support\WebClientFalso;

test('asks the sat with the expresion impresa built from the data of the cfdi', function () {
    $webClient = (new WebClientFalso)->responder('consulta-cfdi-vigente.xml');
    app()->instance(WebClientConsultaCfdi::class, $webClient);

    $acuse = app(ConsultarCfdi::class)(' eku9003173c9 ', 'xaxx010101000', '1234.50', 'ae4b0ca8-8116-4699-8e20-77b6e9c507a2');

    $sobre = new DOMDocument;
    $sobre->loadXML($webClient->peticiones[0]->getBody());

    expect($sobre->getElementsByTagNameNS('http://tempuri.org/', 'expresionImpresa')->item(0)->textContent)
        ->toBe('?re=EKU9003173C9&rr=XAXX010101000&tt=1234.50&id=AE4B0CA8-8116-4699-8E20-77B6E9C507A2')
        ->and($acuse->codigo())->toBe(CodigoConsultaCfdi::ComprobanteObtenido)
        ->and($acuse->estado)->toBe('Vigente');
});
