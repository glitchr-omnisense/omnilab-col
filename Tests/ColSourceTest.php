<?php

namespace Omnilab\Col\Tests;

use Omnilab\Col\ColSourceFactory;
use Omnilab\Exception\NotSupportedException;
use Omnilab\Model\Domain;
use Omnilab\Model\Identifier;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\Query;
use Omnilab\Source\SourceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Fixtures recorded from api.checklistbank.org on 2026-10-15
 * (Tests/Fixtures/index.json names each one's address): the lion (4CGXP)
 * and its synonym Felis leo (CFSCR) in the latest XR (COL26.9 XR) and the
 * lion in the release GBIF runs (315557); the exact-name searches for
 * Panthera leo and Delphinus delphis and the dolphin (34JWM); an unknown id
 * and name; a search for "Panthera leo" in two pages of five.
 */
final class ColSourceTest extends TestCase
{
    /** @var list<string> */
    private array $urls = [];

    private function source(array $options = []): SourceInterface
    {
        $fixtures = __DIR__.'/Fixtures';
        $index = json_decode((string) file_get_contents($fixtures.'/index.json'), true);
        $http = new MockHttpClient(function (string $method, string $url, array $o) use ($fixtures, $index): MockResponse {
            $this->urls[] = rawurldecode($url);
            $name = self::fixture($method, $url, $o['body'] ?? null);
            if (!isset($index[$name])) {
                self::fail('No recorded answer for '.rawurldecode($url));
            }

            return new MockResponse((string) file_get_contents($fixtures.'/'.$name), ['http_code' => $index[$name]['status']]);
        });

        return (new ColSourceFactory($http))->create($options + ['mailto' => 'support@glitchr.io']);
    }

    private static function fixture(string $method, string $url, ?string $body): string
    {
        $parts = parse_url($url);
        $readable = strtolower(trim((string) preg_replace('~[^A-Za-z0-9]+~', '-', rawurldecode(($parts['path'] ?? '').'-'.($parts['query'] ?? ''))), '-'));

        return substr($readable, 0, 70).'-'.substr(md5($method.' '.$url.' '.$body), 0, 8).'.json';
    }

    public function testCapabilities(): void
    {
        self::assertSame([Capability::ENTITY, Capability::SEARCH, Capability::CROSS_REFERENCES], $this->source()->capabilities());
    }

    public function testTheLionInOneCall(): void
    {
        $lion = $this->source()->entity('4CGXP');

        self::assertSame(['https://api.checklistbank.org/dataset/3LXR/taxon/4CGXP/info'], $this->urls);
        self::assertSame(['col:4CGXP', 'Panthera leo', Domain::TAXONOMY, 'species'], [(string) $lion->id, $lion->label, $lion->domain, $lion->kind]);
        self::assertSame(['(Linnaeus, 1758)', 'accepted', 'zoological', 'chordates'], [$lion->value('taxon_authority'), $lion->value('taxonomic_status'), $lion->value('nomenclatural_code'), $lion->value('group')]);
        self::assertSame(['Animalia', 'Chordata', 'Mammalia', 'Carnivora', 'Felidae', 'Panthera'], [$lion->value('kingdom'), $lion->value('phylum'), $lion->value('class'), $lion->value('order'), $lion->value('family'), $lion->value('genus')]);
        self::assertStringStartsWith('Eukaryota > Animalia > Chordata', $lion->value('lineage'));
        self::assertSame(['African Lion', 'Africa, Southern Asia', 'W. Christopher Wozencraft,Alfred L. Gardner', 'Syst. Nat., 10th ed. vol.1 p.41'], [$lion->value('common_name'), $lion->value('distribution'), $lion->value('scrutinizer'), $lion->value('published_in')]);
        self::assertSame(['col:4CGXP', 'gbif:4CGXP', 'inaturalist:41964', 'itis:183803'], $lion->identifiers()->keys(), 'GBIF\'s key is COL\'s id; iNaturalist named by COL; the ITIS record it took it from');
        self::assertSame(['col:6DBT'], $lion->related('parent')->keys(), 'Panthera');
        self::assertContains('col:CFSCR', $lion->related('synonym')->keys());
        self::assertContains('Felis leo', $lion->synonyms);
        self::assertSame('https://www.catalogueoflife.org/data/taxon/4CGXP', $lion->url);
    }

    public function testASynonymIsItselfItsAcceptedRelated(): void
    {
        $synonym = $this->source()->entity('col:CFSCR');

        self::assertSame(['Felis leo', 'synonym'], [$synonym->label, $synonym->value('taxonomic_status')]);
        self::assertSame(['col:4CGXP'], $synonym->related('accepted')->keys());
        self::assertSame([], $synonym->related('parent')->keys());
    }

    public function testByItsGbifKeyItsNameOrItsPage(): void
    {
        foreach ([Identifier::of(Scheme::GBIF, '4CGXP'), 'gbif:4CGXP', 'Panthera leo', 'https://www.catalogueoflife.org/data/taxon/4CGXP', 'https://www.checklistbank.org/dataset/3LXR/taxon/4CGXP'] as $id) {
            self::assertSame('col:4CGXP', (string) $this->source()->entity($id)?->id, (string) $id);
        }
        self::assertContains('https://api.checklistbank.org/dataset/3LXR/nameusage/search?q=Panthera leo&type=EXACT&limit=20', $this->urls);
    }

    public function testTheDolphinOneIdOfEachBase(): void
    {
        $dolphin = $this->source()->entity('Delphinus delphis');

        self::assertSame(['col:34JWM', 'gbif:34JWM', 'worms:137094', 'inaturalist:41526', 'itis:180438'], $dolphin->identifiers()->keys(), 'worms:220224 (the misspelling Delphinus delphus, merged) left out');
    }

    public function testTheReleaseGbifRuns(): void
    {
        self::assertSame('Panthera leo', $this->source(['dataset' => '315557'])->entity('4CGXP')?->label);
        self::assertSame(['https://api.checklistbank.org/dataset/315557/taxon/4CGXP/info'], $this->urls);
    }

    public function testUnknownIsNullAndWhatIsNotColsIsRefused(): void
    {
        self::assertNull($this->source()->entity('ZZZZZZ'), 'a 404');
        self::assertNull($this->source()->entity('Nothingus nowhereus'));
        $this->urls = [];
        foreach (['gbif:5219404', Identifier::of(Scheme::GBIF, '5219404'), 'worms:137094', 'Q140'] as $id) {
            try {
                $this->source()->entity($id);
                self::fail('not read: '.$id);
            } catch (NotSupportedException) {
            }
        }
        self::assertSame([], $this->urls, 'a legacy GBIF number is the Backbone\'s, not COL\'s');
    }

    public function testSearchByOffsetWithItsTotal(): void
    {
        $source = $this->source();
        $first = $source->search(new Query('Panthera leo', limit: 5));
        $second = $source->search(new Query('Panthera leo', limit: 5, cursor: $first->next));

        self::assertSame(['Panthera leo', 'Panthera leo leo', 'Panthera leo melanochaita'], array_map(static fn ($e) => $e->label, \array_slice($first->items, 0, 3)));
        self::assertSame([356, '5', '10'], [$first->total, $first->next, $second->next]);
        self::assertSame('Panthera', $second->first()->label);
        self::assertSame('Felidae', $first->first()->value('family'), 'a result\'s classification');
        self::assertSame([], $source->search(new Query('Panthera', Domain::CHEMISTRY))->items);
    }
}
