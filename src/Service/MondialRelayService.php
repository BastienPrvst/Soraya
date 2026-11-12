<?php

namespace App\Service;

use App\Entity\Order;
use Psr\Log\LoggerInterface;
use SoapClient;
use SoapFault;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MondialRelayService
{
    private const string API_V1_URL = 'https://api.mondialrelay.com/Web_Services.asmx?WSDL';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string          $mrEnseigne,
        private readonly string          $mrPrivateKey,
        private readonly string          $mrBrandCode,
        private readonly string          $mrApiUrl,
        private readonly string          $mrApiId,
        private readonly string          $mrApiPassword,
        private readonly string          $senderName,
        private readonly string          $senderStreet,
        private readonly string          $senderZipcode,
        private readonly string          $senderCity,
        private readonly string          $senderCountry,
        private readonly string          $senderPhone,
        private readonly string          $senderEmail,
        private readonly HttpClientInterface $httpClient,
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
            self::API_V1_URL,
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
    public function shippingCreation(Order $order): array
    {
        $xml = $this->buildShipmentXml($order);

        try {
            $response = $this->httpClient->request('POST', $this->mrApiUrl, [
            'headers' => [
                'Content-Type' => 'text/xml',
                'Accept' => 'application/xml',
            ],
            'body' => $xml,
            'timeout' => 20,
            ]);
            $content = $response->getContent();
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('Mondial Relay v2 : erreur HTTP', ['message' => $e->getMessage()]);
            throw new \RuntimeException('Mondial Relay est injoignable.', 0, $e);
        } catch (TransportExceptionInterface $e) {
        }

        $result = simplexml_load_string($content);
        if (false === $result) {
            $this->logger->error('Mondial Relay v2 : réponse XML invalide', ['body' => $content]);
            throw new \RuntimeException('Réponse Mondial Relay illisible.');
        }

        // Une erreur est signalée par la présence de <Status> dans <StatusList>
        if (isset($result->StatusList->Status) && count($result->StatusList->Status) > 0) {
            $messages = [];
            foreach ($result->StatusList->Status as $status) {
                $messages[] = sprintf(
                    '[%s] %s',
                    (string) $status['Code'],
                    (string) $status['Message']
                );
            }
            $this->logger->error('Mondial Relay v2 : création refusée', [
            'order' => $order->getId(),
            'errors' => $messages,
            ]);
            throw new \RuntimeException('Mondial Relay : '.implode(' | ', $messages));
        }

        $shipment = $result->ShipmentsList->Shipment[0] ?? null;
        $label = $shipment?->LabelList->Label[0] ?? null;
        if (null === $label) {
            throw new \RuntimeException('Mondial Relay : aucune étiquette dans la réponse.');
        }

        $shipmentNumber = '';
        foreach ($label->RawContent->LabelValues as $value) {
            if ('MR.Expedition.NumeroExpedition' === (string) $value['Key']) {
                $shipmentNumber = (string) $value['Value'];
            }
        }

        $barcodes = [];
        foreach ($shipment->LabelList->Barcodes as $cab) {
            $barcodes[] = (string) $cab->Barcodes->Barcode['Value'];
        }

        return [
        'shipmentNumber' => $shipmentNumber,
        'labelUrl' => (string) $label->Output,
        'barcodes' => $barcodes,
        ];
    }

    private function buildShipmentXml(Order $order): string
    {
        $doc = new \DOMDocument('1.0', 'utf-8');

        $root = $doc->createElement('ShipmentCreationRequest');
        $root->setAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttribute('xmlns', 'http://www.example.org/Request');
        $doc->appendChild($root);

        $context = $this->el($doc, 'Context');
        $context->appendChild($this->el($doc, 'Login', $this->mrApiId . '@business-api.mondialrelay.com'));
        $context->appendChild($this->el($doc, 'Password', $this->mrApiPassword));
        $context->appendChild($this->el($doc, 'CustomerId', $this->mrApiId));
        $context->appendChild($this->el($doc, 'Culture', 'fr-FR'));
        $context->appendChild($this->el($doc, 'VersionAPI', '1.0'));
        $root->appendChild($context);

        $output = $this->el($doc, 'OutputOptions');
        $output->appendChild($this->el($doc, 'OutputFormat', '10x15'));
        $output->appendChild($this->el($doc, 'OutputType', 'PdfUrl'));
        $root->appendChild($output);

        $list = $this->el($doc, 'ShipmentsList');
        $shipment = $this->el($doc, 'Shipment');

        $shipment->appendChild($this->el($doc, 'OrderNo', (string) $order->getId()));
        $shipment->appendChild($this->el($doc, 'ParcelCount', '1'));
        $shipment->appendChild($this->el($doc, 'DeliveryInstruction', ''));

        // Livraison en point relais (24R) ; dépôt/collecte chez l'expéditeur (CCC)
        $delivery = $this->el($doc, 'DeliveryMode');
        $delivery->setAttribute('Mode', '24R');
        $delivery->setAttribute('Location', $order->getRelayId());
        $shipment->appendChild($delivery);

        $collection = $this->el($doc, 'CollectionMode');
        $collection->setAttribute('Mode', 'CCC');
        $shipment->appendChild($collection);

        $lang = $this->el($doc, 'Option');
        $lang->setAttribute('Key', 'LNG');
        $lang->setAttribute('Value', 'FR');
        $shipment->appendChild($lang);

        $parcels = $this->el($doc, 'Parcels');
        $parcel = $this->el($doc, 'Parcel');
        $parcel->appendChild($this->el($doc, 'Content', 'Commande '.$order->getId()));
        $weight = $this->el($doc, 'Weight');
        $weight->setAttribute('Value', (string) $this->getTotalWeight($order));
        $weight->setAttribute('Unit', 'gr');
        $parcel->appendChild($weight);
        $parcels->appendChild($parcel);
        $shipment->appendChild($parcels);

        $shipment->appendChild($this->addressNode($doc, 'Sender', [
        'firstname' => $this->senderName,
        'street' => $this->senderStreet,
        'zip' => $this->senderZipcode,
        'city' => $this->senderCity,
        'country' => $this->senderCountry,
        'phone' => $this->senderPhone,
        'email' => $this->senderEmail,
        ]));

        $shipment->appendChild($this->addressNode($doc, 'Recipient', [
        'firstname' => $order->getFirstname(),
        'lastname' => $order->getLastname(),
        'street' => $order->getRelayAddress()?->getStreet1(),
        'zip' => $order->getRelayAddress()?->getZipcode(),
        'city' => $order->getRelayAddress()?->getCity(),
        'country' => $order->getRelayAddress()?->getCountry(),
        'phone' => $order->getPhoneNumber(),
        'email' => $order->getEmail(),
        ]));

        $list->appendChild($shipment);
        $root->appendChild($list);

        return $doc->saveXML();
    }

    private function addressNode(\DOMDocument $doc, string $type, array $a): \DOMElement
    {
        $node = $this->el($doc, $type);
        $address = $this->el($doc, 'Address');

        $address->appendChild($this->el($doc, 'Title'));
        $address->appendChild($this->el($doc, 'Firstname', $a['firstname'] ?? ''));
        $address->appendChild($this->el($doc, 'Lastname', $a['lastname'] ?? ''));
        $address->appendChild($this->el($doc, 'Streetname', $a['street'] ?? ''));
        $address->appendChild($this->el($doc, 'HouseNo'));
        $address->appendChild($this->el($doc, 'CountryCode', $a['country'] ?? 'FR'));
        $address->appendChild($this->el($doc, 'PostCode', $a['zip'] ?? ''));
        $address->appendChild($this->el($doc, 'City', $a['city'] ?? ''));
        $address->appendChild($this->el($doc, 'AddressAdd1'));
        $address->appendChild($this->el($doc, 'AddressAdd2'));
        $address->appendChild($this->el($doc, 'AddressAdd3'));
        $address->appendChild($this->el($doc, 'PhoneNo', $a['phone'] ?? ''));
        $address->appendChild($this->el($doc, 'MobileNo'));
        $address->appendChild($this->el($doc, 'Email', $a['email'] ?? ''));

        $node->appendChild($address);

        return $node;
    }

    private function el(\DOMDocument $doc, string $name, string $value = ''): \DOMElement
    {
        $el = $doc->createElement($name);
        if ('' !== $value) {
            $el->appendChild($doc->createTextNode($value));
        }

        return $el;
    }

    private function getTotalWeight(Order $order): int
    {
        $weight = 0;

        foreach ($order->getOrderItems() as $item) {
            $weight += $item->getProduct()?->getWeight() * $item->getQuantity() * 1000;
        }

        if ($weight < 10) {
            throw new \RuntimeException('Le poids du colis doit être supérieur ou égal à 10 grammes.');
        }

        return $weight;
    }

    private function buildTrackingLink(string $shipmentNumber): string
    {
        // Selon leur classe : code enseigne + BrandId (2 chiffres). Si votre
        // mrEnseigne contient déjà les 2 chiffres, supprimez la concaténation.
        $ens = trim($this->mrEnseigne).$this->mrBrandCode;
        $crc = strtoupper(md5('<'.$ens.'>'.$shipmentNumber.'<'.$this->mrPrivateKey.'>'));

        return sprintf(
            'https://www.mondialrelay.fr/public/permanent/tracking.aspx?ens=%s&exp=%s&pays=fr&language=fr&crc=%s',
            $ens,
            $shipmentNumber,
            $crc
        );
    }
}
