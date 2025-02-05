<?php

declare(strict_types=1);

namespace App\Service\Scraper;

use App\Entity\Datum;
use App\Entity\Scraper;
use App\Enum\DatumTypeEnum;
use App\Service\ArrayTraverser;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;

readonly class JsonScraper
{
    public function __construct(
        private HttpClientInterface $client,
        private Environment $twig,
        private ArrayTraverser $arrayTraverser
    ) {
    }

    public function scrap(Scraper $scraper, string $url): array
    {
        $response = $this->client->request(
            'GET',
            $url,
            ['timeout' => 2.5, 'verify_peer' => false, 'verify_host' => false]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \Exception('Api error: ' . $response->getStatusCode() . ' - ' . $response->getContent());
        }

        $content = json_decode($response->getContent(), true);

        $data = [];
        foreach ($scraper->getDataPaths() as $key => $dataPath) {
            $value = $this->extract($dataPath->getPath(), $content);
            $datum = (new Datum())
                ->setValue($value)
                ->setLabel($dataPath->getName())
                ->setType(DatumTypeEnum::TYPE_TEXT)
                ->setPosition($key)
            ;

            $data[] = [
                DatumTypeEnum::TYPE_TEXT,
                $dataPath->getName(),
                $this->twig->render('App/Datum/_datum.html.twig', [
                    'entity' => 'item',
                    'iteration' => '__placeholder__',
                    'type' => str_contains($value, PHP_EOL) ? DatumTypeEnum::TYPE_TEXTAREA : DatumTypeEnum::TYPE_TEXT,
                    'datum' => $datum,
                    'label' => $datum->getLabel(),
                    'choiceList' => $datum->getChoiceList(),
                ])
            ];
        }

        return [
            'name' => $this->extract($scraper->getNamePath(), $content),
            'image' => $this->extract($scraper->getImagePath(), $content),
            'data' => $data
        ];
    }

    private function extract(?string $namePath, $content): string
    {
        if (!$namePath) {
            return '';
        }

        $result = $namePath;
        $results = [];

        preg_match_all('/#(.*?)#/', $namePath, $matches);

        foreach ($matches[1] as $path) {
            $value = $this->arrayTraverser->get($content, $path);

            if (is_array($value)) {
                if ($results === []) {
                    foreach ($value as $element) {
                        $results[] = str_replace("#$path#", $element, $namePath);
                    }
                } else {
                    foreach ($value as $key => $element) {
                        $results[$key] = str_replace("#$path#", $element,  $results[$key]);
                    }
                }
            } else {
                $result = str_replace("#$path#", $value, $result);
            }
        }

        return $results === [] ? $result : implode(PHP_EOL, $results);
    }
}
