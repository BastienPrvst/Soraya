<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use SoapClient;
use SoapFault;
use Symfony\Component\Console\Logger;

class MondialRelayService
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * @throws \SoapFault
     */
    public function getRelayAddress(string $relayId): array
    {
        $relayArray = explode('-', $relayId);
        $client = new SoapClient('https://api.mondialrelay.com/Web_Services.asmx?WSDL');
        $privateKey = 'PrivateK';
        $id = ltrim($relayArray[1], '0');

        $params = [
            'BDTEST13',
            $id,
            $relayArray[0],
        ];

        $security = $this->generateSecurity($params, $privateKey);

        try {
            $result = $client->WSI2_DetailPointRelais([
                'Enseigne' => 'BDTEST13',
                'Num' => $id,
                'Pays' => $relayArray[0],
                'Security' => $security
            ]);
        } catch (SoapFault $e) {
            $this->logger->error($e->getMessage());
            return [];
        }

        dd($result);
    }

    private function generateSecurity(array $params, string $privateKey): string
    {
        return strtoupper(md5(implode('', $params) . $privateKey));
    }
}
