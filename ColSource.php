<?php

namespace Omnilab\Col;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Model\Domain;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Identifiers;
use Omnilab\Model\Property;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\HttpSource;
use Omnilab\Source\Page;
use Omnilab\Source\Query;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Catalogue of Life, through ChecklistBank's API (api.checklistbank.org):
 * a taxon or a synonym by its COL id, by the GBIF key that names it (GBIF's
 * taxonomy is the Catalogue of Life eXtended Release since June 2026: its
 * keys are COL's ids) or by its name - its authority, rank, status,
 * classification, group, vernacular names, distribution, place of
 * publication, the scrutinizer who checked it and the source it comes from;
 * its GBIF key, and the iNaturalist, WoRMS or ITIS records COL names for
 * it, as cross-references; its parent, accepted name and synonyms related.
 * A search of names, with its total.
 *
 * Which release: the latest eXtended Release by default (3LXR, ChecklistBank's
 * alias), the one GBIF builds on; 3LR for the Base Release, or a release's
 * number. No key; one call a taxon.
 */
final class ColSource extends HttpSource
{
    public const BASE_URI = 'https://api.checklistbank.org/';

    /** The latest eXtended Release (ChecklistBank's alias). */
    public const LATEST_XR = '3LXR';

    /** The release GBIF's taxonomy was built from (ChecklistBank 315557, COL26.6 XR of 2026-06-19, GBIF's dataset 7ddf754f-...: its endpoint on 2026-10-15). */
    public const GBIF_RELEASE = '315557';

    public const MAX_PER_PAGE = 1000;

    private const MAIN_RANKS = ['kingdom', 'phylum', 'class', 'order', 'family', 'genus'];

    /** COL's prefixes for the other bases' ids (usage.identifier) the core has a scheme for. */
    private const PREFIXES = ['inat' => Scheme::INATURALIST, 'worms' => Scheme::WORMS, 'itis' => Scheme::ITIS, 'tsn' => Scheme::ITIS, 'ncbi' => Scheme::NCBI_TAXON, 'gbif' => Scheme::GBIF, 'wikidata' => Scheme::WIKIDATA, 'pbdb' => Scheme::PBDB, 'iucn' => Scheme::IUCN];

    /**
     * @param string      $dataset  the ChecklistBank dataset: 3LXR (the latest XR), 3LR (the latest Base Release), or a release's number
     * @param string|null $language the vernacular names put first (ISO 639-3); null: COL's order
     */
    public function __construct(
        HttpClientInterface $http,
        private readonly string $dataset = self::LATEST_XR,
        private readonly ?string $language = 'eng',
        string $baseUri = self::BASE_URI,
        array $headers = [],
        float $throttle = 0.0,
    ) {
        parent::__construct($http, $baseUri, $headers, $throttle);
    }

    public function getName(): string
    {
        return 'col';
    }

    public function capabilities(): array
    {
        return [Capability::ENTITY, Capability::SEARCH, Capability::CROSS_REFERENCES];
    }

    /**
     * A taxon or a synonym by its COL id (4CGXP, col:4CGXP, its page on
     * catalogueoflife.org or checklistbank.org), by a GBIF key of the new
     * form (gbif:4CGXP: the same id; a legacy number is the Backbone's, not
     * COL's), or by its exact scientific name (the accepted usage first). One
     * call, two for a name. A synonym is itself, its accepted taxon related.
     */
    public function entity(Identifier|string $id): ?Entity
    {
        if ($id instanceof Identifier) {
            $identifier = $id;
        } else {
            $text = trim($id);
            if ('' === $text) {
                return null;
            }
            $identifier = Identifier::parse($text);
            if (null === $identifier) {
                // A bare id: letters upper case and digits (4CGXP, 322); a name has lower-case letters.
                return preg_match('~^[0-9A-Z]{1,12}$~', $text) ? $this->taxon($text) : $this->byName($text);
            }
        }
        $key = match (true) {
            Scheme::COL === $identifier->scheme => $identifier->value,
            Scheme::GBIF === $identifier->scheme && !ctype_digit($identifier->value) => $identifier->value,
            default => throw NotSupportedException::identifier('col', (string) $identifier),
        };

        return $this->taxon($key);
    }

    /**
     * ChecklistBank's search of the release's names (scientific, and the
     * vernacular names it indexes), its order, page by offset, its total. A
     * domain other than taxonomy answers nothing.
     */
    public function search(Query $query): Page
    {
        $text = trim($query->text);
        if ((null !== $query->domain && Domain::TAXONOMY !== $query->domain) || '' === $text) {
            return new Page();
        }
        $size = max(1, min(self::MAX_PER_PAGE, $query->limit));
        $offset = max(0, (int) $query->cursor);
        $data = $this->getJson('dataset/'.$this->dataset.'/nameusage/search', ['q' => $text, 'limit' => $size, 'offset' => $offset ?: null]) ?? [];
        $entities = [];
        foreach ($data['result'] ?? [] as $r) {
            if (\is_array($r) && \is_array($r['usage'] ?? null) && isset($r['usage']['id'])) {
                $entities[] = $this->toEntity(['usage' => $r['usage'], 'classification' => $r['classification'] ?? []]);
            }
        }
        $total = isset($data['total']) ? (int) $data['total'] : null;
        $more = null !== $total ? $offset + $size < $total : !($data['last'] ?? true);

        return new Page($entities, $more ? (string) ($offset + $size) : null, $total);
    }

    private function taxon(string $key): ?Entity
    {
        $info = $this->getJson('dataset/'.$this->dataset.'/taxon/'.rawurlencode($key).'/info');

        return \is_array($info) && isset($info['usage']['id']) ? $this->toEntity($info) : null;
    }

    private function byName(string $name): ?Entity
    {
        $data = $this->getJson('dataset/'.$this->dataset.'/nameusage/search', ['q' => $name, 'type' => 'EXACT', 'limit' => 20]) ?? [];
        $usages = [];
        foreach ($data['result'] ?? [] as $r) {
            if (\is_array($r['usage'] ?? null) && 0 === strcasecmp((string) ($r['usage']['name']['scientificName'] ?? ''), $name)) {
                $usages[] = $r['usage'];
            }
        }
        usort($usages, static fn (array $a, array $b) => ('accepted' === ($b['status'] ?? null)) <=> ('accepted' === ($a['status'] ?? null)));

        return isset($usages[0]['id']) ? $this->taxon((string) $usages[0]['id']) : null;
    }

    /** @param array<string, mixed> $info a taxon's info, or a search's usage and classification */
    private function toEntity(array $info): Entity
    {
        $u = $info['usage'];
        $id = (string) $u['id'];
        $n = \is_array($u['name'] ?? null) ? $u['name'] : [];
        $name = self::text($n['scientificName'] ?? null) ?? $id;
        $authority = self::text($n['authorship'] ?? null);
        $rank = self::text($n['rank'] ?? null);
        $status = self::text($u['status'] ?? null);
        $properties = [];
        $add = static function (string $key, string $label, string|int|bool|null $value) use (&$properties): void {
            if (null !== $value && '' !== $value) {
                $properties[] = new Property($key, $label, $value, 'col');
            }
        };
        $add('taxon_name', 'Taxon name', $name);
        $add('taxon_authority', 'Authority', $authority);
        $add('scientific_name', 'Scientific name', null === $authority ? $name : $name.' '.$authority);
        $add('taxon_rank', 'Taxon rank', $rank);
        $add('taxonomic_status', 'Taxonomic status', $status);
        $add('nomenclatural_code', 'Nomenclatural code', self::text($n['code'] ?? null));
        $add('nomenclatural_status', 'Nomenclatural status', self::text($n['nomStatus'] ?? null));
        $accepted = \is_array($u['accepted'] ?? null) ? $u['accepted'] : null;
        $add('accepted_name', 'Accepted name', null === $accepted ? null : self::text($accepted['label'] ?? null) ?? self::text($accepted['name']['scientificName'] ?? null));
        $lineage = [];
        $parent = null;
        foreach (\is_array($info['classification'] ?? null) ? $info['classification'] : [] as $c) {
            if (!\is_array($c) || (string) ($c['id'] ?? '') === $id || null === ($cn = self::text($c['name'] ?? null))) {
                continue;
            }
            $lineage[] = $cn;
            $parent = $c['id'] ?? $parent;
            if (\in_array($c['rank'] ?? null, self::MAIN_RANKS, true)) {
                $add((string) $c['rank'], ucfirst((string) $c['rank']), $cn);
            }
        }
        $add('lineage', 'Lineage', $lineage ? implode(' > ', $lineage) : null);
        $add('group', 'Group (COL)', self::text(\is_string($info['group'] ?? null) ? $info['group'] : null));
        $add('extinct', 'Extinct', isset($u['extinct']) ? (bool) $u['extinct'] : null);
        $add('environment', 'Environment', \is_array($u['environments'] ?? null) && $u['environments'] ? implode(', ', array_map('strval', $u['environments'])) : null);
        $add('published_in', 'Published in', self::text($info['publishedIn']['citation'] ?? null));
        $add('scrutinizer', 'Scrutinized by', self::text($u['scrutinizer'] ?? null));
        $add('scrutinized', 'Scrutinized on', self::text($u['scrutinizerDate'] ?? null));
        $add('source_record', 'Source record (where COL took it from)', self::text($u['link'] ?? null) ?? self::text($n['link'] ?? null));
        $names = [];
        foreach (\is_array($info['vernacularNames'] ?? null) ? $info['vernacularNames'] : [] as $v) {
            if (\is_array($v) && null !== ($vn = self::text($v['name'] ?? null))) {
                $names[] = [$vn, (string) ($v['language'] ?? '')];
            }
        }
        if (null !== $this->language) {
            usort($names, fn (array $a, array $b) => ($b[1] === $this->language) <=> ($a[1] === $this->language));
        }
        $add('common_name', 'Common name', $names[0][0] ?? null);
        $areas = [];
        foreach (\is_array($info['distributions'] ?? null) ? $info['distributions'] : [] as $d) {
            if (\is_array($d) && null !== ($area = self::text($d['area']['name'] ?? null))) {
                $areas[] = $area;
            }
        }
        $add('distribution', 'Distribution', $areas ? implode(', ', array_values(array_unique($areas))) : null);
        $synonymIds = [];
        $synonymNames = [];
        foreach (\is_array($info['synonyms'] ?? null) ? $info['synonyms'] : [] as $group) {
            foreach (\is_array($group) ? $group : [] as $s) {
                if (\is_array($s) && isset($s['id'])) {
                    $synonymIds[] = Identifier::tryOf(Scheme::COL, (string) $s['id']);
                    if (null !== ($sn = self::text($s['name']['scientificName'] ?? null))) {
                        $synonymNames[] = $sn;
                    }
                }
            }
        }

        return new Entity(
            id: Identifier::of(Scheme::COL, $id),
            label: $name,
            domain: Domain::TAXONOMY,
            kind: $rank,
            synonyms: array_values(array_unique([...array_column($names, 0), ...$synonymNames])),
            properties: $properties,
            identifiers: $this->crossReferences($id, $u),
            url: 'https://www.catalogueoflife.org/data/taxon/'.rawurlencode($id),
            source: 'col',
            related: array_filter([
                'parent' => Identifiers::of(null === $parent || null !== $accepted ? null : Identifier::tryOf(Scheme::COL, (string) $parent)),
                'accepted' => Identifiers::of(null === $accepted ? null : Identifier::tryOf(Scheme::COL, (string) ($accepted['id'] ?? ''))),
                'synonym' => new Identifiers($synonymIds),
            ], static fn (Identifiers $ids) => \count($ids) > 0),
        );
    }

    /**
     * The same taxon in other bases: its GBIF key (the same id, when it is
     * not all digits - a key of digits is the legacy Backbone's), the
     * records COL names for it (usage.identifier: inat:41964,
     * worms:urn:lsid:..., the first of each base) and the source record it
     * took it from (an ITIS or WoRMS page).
     *
     * @param array<string, mixed> $u
     */
    private function crossReferences(string $id, array $u): Identifiers
    {
        $ids = [ctype_digit($id) ? null : Identifier::tryOf(Scheme::GBIF, $id)];
        foreach (\is_array($u['identifier'] ?? null) ? $u['identifier'] : [] as $value) {
            if (\is_string($value) && preg_match('~^([a-z]+):\s*(.+)$~i', $value, $m) && isset(self::PREFIXES[strtolower($m[1])])) {
                $scheme = self::PREFIXES[strtolower($m[1])];
                $ids[] = Scheme::GBIF === $scheme && ctype_digit(trim($m[2])) ? null : Identifier::tryOf($scheme, trim($m[2]));
            }
        }
        foreach ([$u['link'] ?? null, $u['name']['link'] ?? null] as $link) {
            if (\is_string($link) && preg_match('~^https?://~', $link) && null !== ($parsed = Identifier::parse($link)) && \in_array($parsed->scheme, [Scheme::ITIS, Scheme::WORMS, Scheme::INATURALIST, Scheme::NCBI_TAXON], true)) {
                $ids[] = $parsed;
            }
        }

        // One of each base, the first: COL names the ids of the names it merged into the usage too (the dolphin's worms:220224 is WoRMS's misspelling Delphinus delphus).
        $first = [];
        foreach ($ids as $identifier) {
            if (null !== $identifier) {
                $first[$identifier->scheme->value] ??= $identifier;
            }
        }

        return new Identifiers(array_values($first));
    }
}
