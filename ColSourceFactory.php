<?php

namespace Omnilab\Col;

use Omnilab\Config;
use Omnilab\Source\SourceFactory;
use Omnilab\Source\SourceInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * The Catalogue of Life (ChecklistBank): taxa and synonyms by COL id, GBIF
 * key or name, and a search of names - no key needed.
 *
 *   options:
 *     mailto: 'support@glitchr.io'     # in the User-Agent
 *     dataset: 3LXR                    # the latest eXtended Release (GBIF's base); 3LR the Base Release; 315557 the release GBIF runs; any release's number
 *     language: eng                    # the vernacular names put first (ISO 639-3); null: COL's order
 *     throttle: 0                      # seconds between two calls: ChecklistBank publishes no rate
 *     base_uri: 'https://api.checklistbank.org/'
 */
final class ColSourceFactory extends SourceFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnilab.factory_name' => 'col',
            'omnilab.required_options' => [],
            'dataset' => ColSource::LATEST_XR,
            'language' => 'eng',
            'throttle' => 0.0,
            'base_uri' => ColSource::BASE_URI,
        ]);
    }

    protected function build(Config $c): SourceInterface
    {
        $language = $c->get('language');

        return new ColSource(
            $this->http ?? HttpClient::create(),
            (string) $c->get('dataset'),
            \in_array($language, [null, '', 'all', false], true) ? null : (string) $language,
            (string) $c->get('base_uri', ColSource::BASE_URI),
            ['User-Agent' => self::userAgent($c)],
            (float) $c->get('throttle'),
        );
    }
}
