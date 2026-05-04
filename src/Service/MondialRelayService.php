<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use SoapClient;
use SoapFault;
use Symfony\Component\Console\Logger;
use function PHPUnit\Framework\throwException;

class MondialRelayService
{

    private const string API_URL = 'https://api.mondialrelay.com/Web_Services.asmx?WSDL';
    private const string ENSEIGNE = 'TTNTWSDB';
    private const string PRIVATE_KEY = 'PrivateK';

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * @throws \SoapFault
     * @throws \Exception
     */
    public function getRelayAddress(string $relayId): array
    {
        [$country, $num] = explode('-', $relayId);

        $client = new SoapClient(
            self::API_URL,
            ['trace' => true]
        );

        $security = strtoupper(md5(self::ENSEIGNE.$num.$country.self::PRIVATE_KEY));

        try {
            $result = $client->WSI4_PointRelais_Recherche([
                'Enseigne' => self::ENSEIGNE,
                'Pays' => $country,
                'NumPointRelais' => $num,
                'Ville' => null,
                'CP' => null,
                'Latitude' => null,
                'Longitude' => null,
                'Taille' => null,
                'Poids' => null,
                'Action' => null,
                'DelaiEnvoi' => null,
                'RayonRecherche' => null,
                'TypeActivite' => null,
                'NACE' => null,
                'NombreResultats' => 1,
                'Security' => $security
            ]);

            $errorCode = $result->WSI4_PointRelais_RechercheResult->STAT;
            if ($errorCode !== '0') {
                $this->logger->critical('Erreur API Mondial Relay : '.$errorCode);
                throw new \RuntimeException('Erreur API Mondial Relay : '.$errorCode);
            }

            $result = $result->WSI4_PointRelais_RechercheResult->PointsRelais->PointRelais_Details;

            $lines = array_filter([
                trim($result->LgAdr1),
                trim($result->LgAdr2),
                trim($result->LgAdr3),
                trim($result->LgAdr4),
            ]);

            $address = implode("\n", $lines);

            return [
                'NumPointRelais' => $result->Num,
                'Country' => trim($country),
                'Street' => $address,
                'ZipCode' => trim($result->CP),
                'City' => trim($result->Ville),
            ];
        } catch (SoapFault $e) {
            $this->logger->error($e->getMessage());
            return [];
        }
    }
}
