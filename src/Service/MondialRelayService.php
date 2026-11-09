<?php

namespace App\Service;

use App\Entity\Order;
use Psr\Log\LoggerInterface;
use SoapClient;
use SoapFault;

class MondialRelayService
{
    private const string API_URL = 'https://api.mondialrelay.com/Web_Services.asmx?WSDL';
    private const string SHIPPING_REQUEST_URL = '
    https://www.mondialrelay.fr/media/51911/Mondial-Relay-Shipment-API-.Request.1.0.xsd';

    public function __construct(
        private LoggerInterface $logger,
        private readonly string $mrEnseigne,
        private readonly string $mrPrivateKey,
        private readonly string $mrBrandCode,
        private readonly string $mrApiId,
        private readonly string $mrApiPassword,
        private readonly string $senderName,
        private readonly string $senderStreet,
        private readonly string $senderZipcode,
        private readonly string $senderCity,
        private readonly string $senderCountry,
        private readonly string $senderPhone,
        private readonly string $senderEmail,
        private readonly HttpClientInterface $httpClient,
        private readonly string $projectDir,
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

        $security = strtoupper(md5($this->mrEnseigne.$num.$country.$this->mrPrivateKey));

        try {
            $result = $client->WSI4_PointRelais_Recherche([
                'Enseigne' => $this->mrEnseigne,
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
//
//    public function shippingCreation(Order $order): array
//    {
//    }

    private function getTotalWeight(Order $order): int
    {
        $weight = 0;

        foreach ($order->getOrderItems() as $item) {
            $weight += $item->getProduct()?->getWeight() * $item->getQuantity();
        }

        if ($weight < 10) {
            throw new \RuntimeException('Le poids du colis doit être supérieur ou égal à 10 grammes.');
        }

        return $weight;
    }
}
